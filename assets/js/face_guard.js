/**
 * face_guard.js â€” the same face check used by the org on-site scanner, packaged
 * for the student pages (online attendance + anti-spoofing / presence check).
 *
 *  â€¢ NO live-verification challenge: the student just looks at the camera.
 *  â€¢ Anti-spoofing: a face shown on a PHONE / TABLET / MONITOR is blocked
 *    (ScreenSpoof, COCO-SSD running in a Web Worker).
 *  â€¢ Identity: the live face must match the student's registered face
 *    (Euclidean distance, face-api's metric) on 2 frames in a row.
 *  â€¢ Live tracking: boxes around faces, phones/screens and QR codes, drawn on a
 *    canvas over the video, plus a counts line shown BELOW the camera.
 *  â€¢ Loading screen over the camera while the models load.
 *
 * Requires: face-api.min.js, liveness.js (NaapLiveness.loadModels /
 * fetchOwnDescriptor), screen_spoof.js.
 *
 *   FaceGuard.showLoading(container, title, sub) / FaceGuard.hideLoading(container)
 *   const s = FaceGuard.createSession({ faceapi, video, container, referenceDescriptor,
 *                                        onStatus(text, type), onPass(), onFail(), onSpoof(reason) });
 *   s.start(); s.stop(); s.reset();
 */
(function (global) {
  'use strict';

  const CFG = {
    MATCH_THRESHOLD: 0.5,    // Euclidean distance; lower = stricter
    MATCH_FRAMES: 2,         // same identity on N consecutive frames
    MISMATCH_FRAMES: 4,      // wrong person on N consecutive frames -> onFail
    DEVICE_CHECK_MS: 600,    // phone/screen detector cadence
    STALE_MS: 1200,          // tracking box disappears after this
    LOOP_MS: 160
  };

  // â”€â”€ Loading screen â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  function ensureStyle() {
    if (document.getElementById('fg-style')) return;
    const st = document.createElement('style');
    st.id = 'fg-style';
    st.textContent =
      '@keyframes fgSpin{to{transform:rotate(360deg)}}' +
      '.fg-loading{position:absolute;inset:0;z-index:6;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;' +
      'background:#0f172a;color:#e2e8f0;text-align:center;padding:20px;font-family:Inter,sans-serif}' +
      '.fg-loading .fg-spin{width:44px;height:44px;border:4px solid rgba(255,255,255,.15);border-top-color:#38bdf8;border-radius:50%;animation:fgSpin .9s linear infinite}' +
      '.fg-loading strong{font-size:15px}.fg-loading span{font-size:12.5px;color:#94a3b8;max-width:320px}' +
      '.fg-counts{display:flex;justify-content:center;gap:16px;flex-wrap:wrap;margin:8px auto 4px;font:700 13px Inter,sans-serif;color:#cbd5e1}' +
      '.fg-overlay{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:5}';
    document.head.appendChild(st);
  }
  function showLoading(container, title, sub) {
    if (!container) return;
    ensureStyle();
    if (getComputedStyle(container).position === 'static') container.style.position = 'relative';
    let el = container.querySelector('.fg-loading');
    if (!el) {
      el = document.createElement('div');
      el.className = 'fg-loading';
      el.innerHTML = '<div class="fg-spin"></div><strong></strong><span></span>';
      container.appendChild(el);
    }
    el.querySelector('strong').textContent = title || 'Loading face recognition modelâ€¦';
    el.querySelector('span').textContent = sub || 'This only takes a moment the first time.';
    el.style.display = 'flex';
  }
  function hideLoading(container) {
    const el = container && container.querySelector('.fg-loading');
    if (el) el.style.display = 'none';
  }

  function dist(a, b) {
    let s = 0;
    for (let i = 0; i < a.length; i++) { const d = a[i] - b[i]; s += d * d; }
    return Math.sqrt(s);
  }

  function createSession(opts) {
    const faceapi = opts.faceapi;
    const video = opts.video;
    const container = opts.container || video.parentElement;
    const ref = opts.referenceDescriptor ? new Float32Array(opts.referenceDescriptor) : null;
    const onStatus = opts.onStatus || (() => {});
    const onPass = opts.onPass || (() => {});
    const onFail = opts.onFail || (() => {});
    const onSpoof = opts.onSpoof || ((r) => onStatus('ðŸš« ' + r, 'error'));

    ensureStyle();
    if (getComputedStyle(container).position === 'static') container.style.position = 'relative';
    // Reuse the overlay / counts line if a previous session on this camera made them
    let overlay = container.querySelector('canvas.fg-overlay');
    if (!overlay) {
      overlay = document.createElement('canvas');
      overlay.className = 'fg-overlay';
      container.appendChild(overlay);
    }
    let counts = container.nextElementSibling && container.nextElementSibling.classList.contains('fg-counts') ? container.nextElementSibling : null;
    if (!counts) {
      counts = document.createElement('div');
      counts.className = 'fg-counts';
      counts.innerHTML = '<span><span style="color:#22c55e">&#9632;</span> Faces: <b class="fg-f">0</b></span>' +
                         '<span><span style="color:#3b82f6">&#9632;</span> QR codes: <b class="fg-q">0</b></span>' +
                         '<span><span style="color:#ef4444">&#9632;</span> Phones / Screens: <b class="fg-d">0</b></span>';
      container.insertAdjacentElement('afterend', counts);
    }

    const st = { running: false, timer: null, raf: null, faces: [], devices: [], qrs: [],
                 lastDevice: 0, cleanChecks: 0, hits: 0, misses: 0, done: false, paused: false };
    let qrDetector = null;
    try { if ('BarcodeDetector' in window) qrDetector = new window.BarcodeDetector({ formats: ['qr_code'] }); } catch (e) {}

    const mirrored = () => /matrix\(-1/.test(getComputedStyle(video).transform || '');
    const opt = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.35 });
    const work = document.createElement('canvas');
    const workCtx = work.getContext('2d', { willReadFrequently: true });

    function draw() {
      st.raf = null;
      const cw = overlay.clientWidth, ch = overlay.clientHeight, dpr = window.devicePixelRatio || 1;
      if (overlay.width !== Math.round(cw * dpr) || overlay.height !== Math.round(ch * dpr)) {
        overlay.width = Math.round(cw * dpr); overlay.height = Math.round(ch * dpr);
      }
      const g = overlay.getContext('2d');
      g.setTransform(dpr, 0, 0, dpr, 0, 0);
      g.clearRect(0, 0, cw, ch);
      const vw = video.videoWidth, vh = video.videoHeight, now = Date.now();
      const live = (a) => a.filter(o => now - o.ts < CFG.STALE_MS);
      const faces = live(st.faces), devices = live(st.devices), qrs = live(st.qrs);
      if (vw && vh) {
        const s = Math.max(cw / vw, ch / vh), ox = (cw - vw * s) / 2, oy = (ch - vh * s) / 2, mir = mirrored();
        const box = (b, color, text, dashed) => {
          let x = ox + b.x * s; const y = oy + b.y * s, w = b.width * s, h = b.height * s;
          if (mir) x = cw - x - w;
          g.lineWidth = 3; g.strokeStyle = color; g.setLineDash(dashed ? [8, 6] : []);
          g.strokeRect(x, y, w, h); g.setLineDash([]);
          if (text) {
            g.font = "700 12px Inter, sans-serif";
            const tw = g.measureText(text).width + 12, ty = y > 22 ? y - 22 : y + h + 4;
            g.fillStyle = color; g.fillRect(x, ty, tw, 20); g.fillStyle = '#fff'; g.fillText(text, x + 6, ty + 14);
          }
        };
        devices.forEach(d => box(d, '#ef4444', d.kind.toUpperCase() + ' ' + Math.round(d.score * 100) + '%', true));
        qrs.forEach(q => box(q, '#3b82f6', 'QR CODE', false));
        const col = { ok: '#22c55e', checking: '#f59e0b', unknown: '#64748b', spoof: '#ef4444' };
        faces.forEach(f => box(f, col[f.state] || col.checking, f.label, false));
      }
      counts.querySelector('.fg-f').textContent = faces.length;
      counts.querySelector('.fg-q').textContent = qrs.length;
      counts.querySelector('.fg-d').textContent = devices.length;
      if (st.running) st.raf = requestAnimationFrame(draw);
    }

    function setFaces(list, state, label) {
      const now = Date.now();
      st.faces = list.map(b => ({ x: b.x, y: b.y, width: b.width, height: b.height, state, label, ts: now }));
    }

    async function tick() {
      st.timer = null;
      if (!st.running) return;
      try {
        if (!st.paused && !st.done && video.readyState >= 2 && video.videoWidth) {
          const now = Date.now();

          if (qrDetector) {
            try {
              // Some browsers expose BarcodeDetector but never resolve: time-box it
              // and switch it off for good after a failure.
              const codes = await Promise.race([
                qrDetector.detect(video),
                new Promise((_, rej) => setTimeout(() => rej(new Error('qr timeout')), 400))
              ]);
              st.qrs = codes.map(c => ({ x: c.boundingBox.x, y: c.boundingBox.y, width: c.boundingBox.width, height: c.boundingBox.height, ts: now }));
            } catch (e) { qrDetector = null; }
          }

          // Detect on a downscaled canvas copy of the frame (same as the on-site
          // scanner): faster, and reliable across browsers; boxes scaled back.
          const vw = video.videoWidth, vh = video.videoHeight;
          const dw = Math.min(vw, 320), dh = Math.max(1, Math.round(vh * dw / vw)), kk = vw / dw;
          if (work.width !== dw || work.height !== dh) { work.width = dw; work.height = dh; }
          workCtx.drawImage(video, 0, 0, dw, dh);
          // Descriptors (identity) are only computed when there is a registered face to compare with
          const dets = ref
            ? await faceapi.detectAllFaces(work, opt).withFaceLandmarks().withFaceDescriptors()
            : (await faceapi.detectAllFaces(work, opt)).map(d => ({ detection: d }));
          const boxes = dets.map(d => { const b = d.detection.box; return { x: b.x * kk, y: b.y * kk, width: b.width * kk, height: b.height * kk }; });

          let deviceSpoof = null;
          if (window.ScreenSpoof && now - st.lastDevice > CFG.DEVICE_CHECK_MS) {
            st.lastDevice = now;
            const r = await ScreenSpoof.check(video, boxes.length === 1 ? boxes[0] : null);
            if (r.ready && !r.busy && !r.error) {
              st.devices = (r.devices || []).map(d => Object.assign({ ts: Date.now() }, d));
              if (r.spoof) deviceSpoof = r;
              else if (boxes.length === 1) st.cleanChecks++;
            }
          }

          if (dets.length === 0) {
            setFaces([], 'checking', '');
            st.cleanChecks = 0; st.hits = 0; st.misses = 0;
            onStatus('Position your face in the camera.', 'pending');
          } else if (dets.length > 1) {
            setFaces(boxes, 'spoof', 'Multiple faces');
            st.hits = 0;
            onStatus('Multiple faces detected â€” only the student should be in view.', 'error');
          } else if (deviceSpoof) {
            setFaces(boxes, 'spoof', 'Face on ' + deviceSpoof.device);
            st.cleanChecks = 0; st.hits = 0;
            st.paused = true;
            onSpoof('A ' + deviceSpoof.device + ' showing a face was detected (' + Math.round(deviceSpoof.score * 100) + '%). Only your real face is accepted.');
            setTimeout(() => { st.paused = false; }, 3000);
          } else if (!window.ScreenSpoof || st.cleanChecks < 1) {
            setFaces(boxes, 'checking', 'Checkingâ€¦');
            onStatus('Checking for phone or screen spoofingâ€¦', 'pending');
          } else {
            const d = dets[0];
            const ok = !ref || dist(d.descriptor, ref) < CFG.MATCH_THRESHOLD;
            if (ok) {
              st.hits++; st.misses = 0;
              setFaces(boxes, 'ok', 'Verified âœ“');
              if (st.hits >= CFG.MATCH_FRAMES) {
                st.done = true;
                onStatus('âœ“ Face verified.', 'success');
                onPass();
              } else {
                onStatus('Verifying your faceâ€¦', 'pending');
              }
            } else {
              st.hits = 0; st.misses++;
              setFaces(boxes, 'unknown', 'Not the registered student');
              if (st.misses >= CFG.MISMATCH_FRAMES) {
                st.misses = 0; st.paused = true;
                onFail();
                setTimeout(() => { st.paused = false; }, 3000);
              }
            }
          }
        }
      } catch (e) {
        console.warn('[FaceGuard] frame error:', e && e.message ? e.message : e);
      }
      if (st.running) st.timer = setTimeout(tick, CFG.LOOP_MS);
    }

    return {
      start() {
        if (st.running) return;
        st.running = true; st.done = false;
        st.raf = requestAnimationFrame(draw);
        tick();
      },
      stop() {
        st.running = false;
        if (st.timer) clearTimeout(st.timer);
        if (st.raf) cancelAnimationFrame(st.raf);
        overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height);
      },
      reset() {
        st.done = false; st.paused = false; st.hits = 0; st.misses = 0; st.cleanChecks = 0;
        if (!st.running) this.start();
      },
      /** Mark the tracked face as verified and keep showing the box (after success). */
      freeze(label) { setFaces(st.faces, 'ok', label || 'Verified âœ“'); }
    };
  }

  global.FaceGuard = { createSession, showLoading, hideLoading, CFG };
})(window);
