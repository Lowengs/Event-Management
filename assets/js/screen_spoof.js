/**
 * screen_spoof.js — detects a face shown on a PHONE / TABLET / MONITOR screen.
 *
 * Uses COCO-SSD (TensorFlow.js object detector, bundled locally in
 * assets/models/coco-ssd) to find "cell phone", "tv" and "laptop" objects in the
 * camera frame. If the detected face sits inside such a device, the face is a
 * picture or video on a screen, not a live person.
 *
 * The detector runs in a Web Worker (screen_spoof_worker.js) because tf.js 4
 * and face-api.js cannot share one page scope. Do NOT add tf.min.js to the page.
 *
 * API:
 *   ScreenSpoof.load()                  -> Promise<boolean>  (true = model ready)
 *   ScreenSpoof.check(source, faceBox)  -> Promise<{ ready, spoof, device, score, busy?, error? }>
 *       source  : <video>/<canvas>/<img>  (raw camera frame)
 *       faceBox : {x, y, width, height} in the source's native pixel space
 *
 * Runs in the browser, so it raises the bar against phone/tablet replays but is
 * not tamper-proof against someone who edits the page's JavaScript.
 */
(function (global) {
  'use strict';

  const DEVICE_CLASSES = { 'cell phone': 'phone', 'tv': 'screen / tablet', 'laptop': 'laptop screen' };
  const CFG = {
    MIN_SCORE: 0.40,      // detector confidence for a device
    FACE_INSIDE: 0.60,    // fraction of the face box that must lie inside the device box
    PAD: 0.12,            // device box is padded by this fraction (bezels are often cropped)
    MAX_SIDE: 640,        // frame is downscaled to this before detection
    TIMEOUT_MS: 15000
  };

  const scriptSrc = (document.currentScript && document.currentScript.src) || location.href;
  const url = (rel) => new URL(rel, scriptSrc).href;

  let worker = null;
  let loading = null;
  let ready = false;
  let busy = false;
  let seq = 0;
  const pending = new Map();
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });

  function load() {
    if (ready) return Promise.resolve(true);
    if (loading) return loading;
    loading = new Promise((resolve) => {
      try {
        worker = new Worker(url('screen_spoof_worker.js'));
      } catch (e) {
        console.warn('[ScreenSpoof] worker failed to start:', e);
        loading = null;
        return resolve(false);
      }
      const t = setTimeout(() => { console.warn('[ScreenSpoof] model load timed out'); loading = null; resolve(false); }, 60000);
      worker.onmessage = (ev) => {
        const m = ev.data || {};
        if (m.type === 'loaded') {
          clearTimeout(t);
          ready = !!m.ok;
          if (!m.ok) { console.warn('[ScreenSpoof] model failed:', m.error); loading = null; }
          resolve(ready);
        } else if (m.type === 'result' && pending.has(m.id)) {
          pending.get(m.id)(m);
          pending.delete(m.id);
        }
      };
      worker.onerror = (e) => { console.warn('[ScreenSpoof] worker error:', e.message); };
      worker.postMessage({
        type: 'load',
        tfUrl: url('lib/tf.min.js'),
        cocoUrl: url('lib/coco-ssd.min.js'),
        modelUrl: url('../models/coco-ssd/model.json')
      });
    });
    return loading;
  }

  function overlapFraction(face, dev) {
    const padX = dev.width * CFG.PAD, padY = dev.height * CFG.PAD;
    const dx1 = dev.x - padX, dy1 = dev.y - padY;
    const dx2 = dev.x + dev.width + padX, dy2 = dev.y + dev.height + padY;
    const ix = Math.max(0, Math.min(face.x + face.width, dx2) - Math.max(face.x, dx1));
    const iy = Math.max(0, Math.min(face.y + face.height, dy2) - Math.max(face.y, dy1));
    const area = face.width * face.height;
    return area > 0 ? (ix * iy) / area : 0;
  }

  async function check(source, faceBox) {
    if (!ready) return { ready: false, spoof: false };
    if (busy) return { ready: true, busy: true, spoof: false };
    busy = true;
    try {
      const sw = source.videoWidth || source.naturalWidth || source.width;
      const sh = source.videoHeight || source.naturalHeight || source.height;
      if (!sw || !sh) return { ready: true, spoof: false, error: true };
      const k = Math.min(1, CFG.MAX_SIDE / Math.max(sw, sh));
      const w = Math.max(1, Math.round(sw * k)), h = Math.max(1, Math.round(sh * k));
      if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
      ctx.drawImage(source, 0, 0, w, h);
      const img = ctx.getImageData(0, 0, w, h);

      const id = ++seq;
      const res = await new Promise((resolve) => {
        const t = setTimeout(() => { pending.delete(id); resolve({ preds: [], error: 'timeout' }); }, CFG.TIMEOUT_MS);
        pending.set(id, (m) => { clearTimeout(t); resolve(m); });
        worker.postMessage({ type: 'detect', id, width: w, height: h, data: img.data }, [img.data.buffer]);
      });
      if (res.error) return { ready: true, spoof: false, error: true };

      const face = faceBox ? { x: faceBox.x * k, y: faceBox.y * k, width: faceBox.width * k, height: faceBox.height * k } : null;
      let best = null;
      const devices = [];   // every phone/screen found, in the SOURCE's pixel space (for tracking overlays)
      for (const p of res.preds) {
        const kind = DEVICE_CLASSES[p.class];
        if (!kind || p.score < CFG.MIN_SCORE) continue;
        const [x, y, bw, bh] = p.bbox;
        devices.push({ kind, score: p.score, x: x / k, y: y / k, width: bw / k, height: bh / k });
        if (!face) continue;
        const inside = overlapFraction(face, { x, y, width: bw, height: bh });
        if (inside >= CFG.FACE_INSIDE && (!best || p.score > best.score)) best = { device: kind, score: p.score };
      }
      return best ? { ready: true, spoof: true, device: best.device, score: best.score, devices }
                  : { ready: true, spoof: false, devices };
    } catch (e) {
      console.warn('[ScreenSpoof] check error:', e && e.message ? e.message : e);
      return { ready: true, spoof: false, error: true };
    } finally {
      busy = false;
    }
  }

  global.ScreenSpoof = { load, check, CFG };
})(window);
