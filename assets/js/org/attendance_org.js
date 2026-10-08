/**
 * Organization Portal: Attendance Management & Scanner Script
 * Unified QR & Face Scanner with Anti-Spoofing / Liveness Verification
 */

let stream = null;
let scanInterval = null;
let scanMode = '';
let faceScanTimeout = null;
let faceScanBusy = false;
let isFaceApiLoaded = false;
let isFaceScanning = false;
let faceMatcher = null;
let currentLogType = 'Log In';
let pendingAttendance = null;

// Liveness tracking buffer
let faceHistory = [];
let consecutiveSpoofFrames = 0;
// Per-student scan cooldown (studentId -> last scan time), see promptAttendance
const recentScans = {};
const SCAN_COOLDOWN_MS = 15000;
let lastSpoofAlertTime = 0;

function setLogType(type) {
    currentLogType = type;
    const btnIn = document.getElementById('btnLogTypeIn');
    const btnOut = document.getElementById('btnLogTypeOut');
    if (type === 'Log In') {
        if (btnIn) { btnIn.style.background = '#2563eb'; btnIn.style.color = '#fff'; btnIn.classList.add('active'); }
        if (btnOut) { btnOut.style.background = 'transparent'; btnOut.style.color = '#64748b'; btnOut.classList.remove('active'); }
    } else {
        if (btnOut) { btnOut.style.background = '#dc2626'; btnOut.style.color = '#fff'; btnOut.classList.add('active'); }
        if (btnIn) { btnIn.style.background = 'transparent'; btnIn.style.color = '#64748b'; btnIn.classList.remove('active'); }
    }
}

let faceDetectionOptions = null;
function getFaceDetectionOptions() {
    if (!faceDetectionOptions && typeof faceapi !== 'undefined' && faceapi.TinyFaceDetectorOptions) {
        faceDetectionOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.35 });
    }
    return faceDetectionOptions;
}

const faceDetectionCanvas = document.createElement('canvas');
const faceDetectionCtx = faceDetectionCanvas.getContext('2d', { willReadFrequently: true });
const qrScanCanvas = document.createElement('canvas');
const qrScanCtx = qrScanCanvas.getContext('2d', { willReadFrequently: true });
let cameraHealthInterval = null;
let scanCycleCount = 0;

// ── Improved QR detection ────────────────────────────────────────────────
// 1) Native BarcodeDetector (Chrome/Edge/Android) — fast, handles blur/angles.
// 2) jsQR fallback: alternates a full-frame pass with a CENTER CROP at native
//    resolution (small / far-away QR codes get ~2x more pixels), and only tries
//    color inversion every 3rd pass (inversion doubles the cost per frame).
let nativeQrDetector = null;
let nativeQrChecked = false;
let qrPass = 0;

async function getNativeQrDetector() {
    if (nativeQrChecked) return nativeQrDetector;
    nativeQrChecked = true;
    try {
        if ('BarcodeDetector' in window) {
            const formats = await window.BarcodeDetector.getSupportedFormats();
            if (formats.includes('qr_code')) nativeQrDetector = new window.BarcodeDetector({ formats: ['qr_code'] });
        }
    } catch (e) { nativeQrDetector = null; }
    return nativeQrDetector;
}

// Where the last decoded QR code(s) were, in video pixels (for the tracking overlay)
let lastQrBoxes = [];

async function detectQrFromVideo(video, vw, vh) {
    if (!vw || !vh) return null;
    qrPass++;

    const native = await getNativeQrDetector();
    if (native) {
        try {
            const codes = await native.detect(video);
            if (codes && codes.length) {
                lastQrBoxes = codes.map(c => ({ x: c.boundingBox.x, y: c.boundingBox.y, width: c.boundingBox.width, height: c.boundingBox.height }));
                trackQrs(lastQrBoxes);
                const hit = codes.find(c => c.rawValue);
                if (hit) return hit.rawValue;
            } else {
                lastQrBoxes = [];
            }
        } catch (e) { /* fall through to jsQR */ }
    }
    if (typeof jsQR === 'undefined') return null;

    let sx = 0, sy = 0, sw = vw, sh = vh;
    if (qrPass % 2 === 0) {            // center crop, native resolution
        sw = Math.round(vw * 0.6);
        sh = Math.round(vh * 0.6);
        sx = Math.round((vw - sw) / 2);
        sy = Math.round((vh - sh) / 2);
    }
    const scale = Math.min(1, 800 / sw);
    const tw = Math.max(1, Math.round(sw * scale));
    const th = Math.max(1, Math.round(sh * scale));
    if (qrScanCanvas.width !== tw || qrScanCanvas.height !== th) {
        qrScanCanvas.width = tw;
        qrScanCanvas.height = th;
    }
    qrScanCtx.imageSmoothingEnabled = false;   // keep module edges sharp
    qrScanCtx.drawImage(video, sx, sy, sw, sh, 0, 0, tw, th);
    const img = qrScanCtx.getImageData(0, 0, tw, th);
    const code = jsQR(img.data, tw, th, {
        inversionAttempts: (qrPass % 3 === 0) ? 'attemptBoth' : 'dontInvert'
    });
    lastQrBoxes = [];
    if (code && code.location) {
        const L = code.location;
        const xs = [L.topLeftCorner.x, L.topRightCorner.x, L.bottomLeftCorner.x, L.bottomRightCorner.x];
        const ys = [L.topLeftCorner.y, L.topRightCorner.y, L.bottomLeftCorner.y, L.bottomRightCorner.y];
        const minX = Math.min(...xs), minY = Math.min(...ys);
        lastQrBoxes = [{ x: sx + minX / scale, y: sy + minY / scale, width: (Math.max(...xs) - minX) / scale, height: (Math.max(...ys) - minY) / scale }];
    }
    if (lastQrBoxes.length) trackQrs(lastQrBoxes);
    return code && code.data ? code.data : null;
}

function showStatus(msg, ok = true) {
    const el = document.getElementById('attStatus');
    if (!el) return;
    el.textContent = msg;
    el.style.display = 'block';
    el.style.background = ok ? '#f0fdf4' : '#fef2f2';
    el.style.color = ok ? '#15803d' : '#dc2626';
    el.style.borderColor = ok ? '#bbf7d0' : '#fecaca';
    setTimeout(() => { if (el) el.style.display = 'none'; }, 4000);
}

function getEventId() {
    const select = document.getElementById('eventSelect');
    if (!select || !select.value) {
        showStatus('Please select an event first.', false);
        return null;
    }
    return select.value;
}

function parseStudentQrPayload(rawData) {
    if (!rawData) return null;
    const trimmed = String(rawData).trim();
    try {
        const parsed = JSON.parse(trimmed);
        if (parsed && typeof parsed === 'object') {
            if (parsed.type && parsed.type !== 'student_qr') return null;
            const studentId = String(parsed.student_id || parsed.studentId || parsed.user_id || '').trim();
            return studentId ? studentId : null;
        }
    } catch (e) {}
    const legacy = trimmed.replace(/^ID:\s*/i, '').trim();
    return legacy || null;
}

async function loadFaceAPI() {
    if (isFaceApiLoaded) return true;
    showStatus('Loading AI Face recognition models…', true);
    
    const candidatePaths = [
        '../../assets/models',
        '../assets/models',
        '/Project/assets/models',
        'assets/models',
        'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/',
        'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/'
    ];

    for (const p of candidatePaths) {
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(p),
                faceapi.nets.faceLandmark68Net.loadFromUri(p),
                faceapi.nets.faceRecognitionNet.loadFromUri(p)
            ]);
            await initFaceMatcher();
            isFaceApiLoaded = true;
            showStatus('AI Models loaded successfully!', true);
            // Phone / screen spoof detector (local COCO-SSD). Loads in the background;
            // until it is ready, faces are not accepted (see scanUnified).
            if (window.ScreenSpoof) {
                ScreenSpoof.load().then(ok => {
                    screenSpoofReady = ok;
                    if (!ok) showStatus('Phone-screen detector failed to load — face check-in is paused; QR check-in still works.', false);
                });
            }
            return true;
        } catch (e) {
            console.warn(`Candidate model path failed (${p}):`, e.message || e);
        }
    }
    showStatus('Face model loading failed. Please check internet connection.', false);
    return false;
}

async function initFaceMatcher() {
    try {
        const res = await fetch('../../config/API/endpoints/index.php?action=get_face_descriptors');
        const data = await res.json();
        if (data.success && data.faces && data.faces.length > 0) {
            const labeledDescriptors = data.faces.map(f => {
                const descFloat = new Float32Array(f.descriptor);
                return new faceapi.LabeledFaceDescriptors(f.student_id, [descFloat]);
            });
            faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.45);
        }
    } catch(e) {
        console.warn("Face descriptors load warning:", e);
    }
}

async function startCamera(mode) {
    const ev = getEventId();
    if (!ev) return;
    scanMode = mode || 'unified';
    if (stream) {
        try { stream.getTracks().forEach(t => t.stop()); } catch(e){}
        stream = null;
    }
    const cameraBox = document.getElementById('cameraBox');
    const video = document.getElementById('cameraFeed');
    const btnStop = document.getElementById('btnStop');
    if (cameraBox) cameraBox.style.display = 'block';
    if (btnStop) btnStop.style.display = 'inline-flex';

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 1280 }, height: { ideal: 720 }, facingMode: 'user' }
        });
        try {
            // Continuous autofocus helps QR cards held at varying distances (ignored if unsupported)
            const track = stream.getVideoTracks()[0];
            const caps = track && track.getCapabilities ? track.getCapabilities() : {};
            if (caps.focusMode && caps.focusMode.includes('continuous')) {
                await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
            }
        } catch (_) {}
        if (video) {
            video.srcObject = stream;
            video.setAttribute('playsinline', true);
            video.play().catch(e => console.warn('Video play error:', e));
        }
        showStatus('Camera Feed Active! Position QR code or Face in camera frame.', true);
        isFaceScanning = true;
        faceScanBusy = false;
        scanCycleCount = 0;
        faceHistory = [];
        scheduleUnifiedScan(ev, 0);
        startTrackingOverlay();

        if (cameraHealthInterval) clearInterval(cameraHealthInterval);
        cameraHealthInterval = setInterval(() => checkCameraHealth(ev), 3000);

        if (!isFaceApiLoaded || !screenSpoofReady) {
            setCameraLoading(true, 'Loading face recognition model…', 'This only takes a moment the first time. QR code scanning is already active.');
        }
        loadFaceAPI().then(async loaded => {
            if (loaded && !faceMatcher) initFaceMatcher();
            if (!loaded) {
                setCameraLoading(false);
                return;
            }
            if (window.ScreenSpoof && !screenSpoofReady) {
                setCameraLoading(true, 'Loading phone & screen detector…', 'Almost ready. QR code scanning is already active.');
                screenSpoofReady = await ScreenSpoof.load();
            }
            setCameraLoading(false);
        }).catch(err => { setCameraLoading(false); console.warn('Face API background load warning:', err); });
    } catch(e) {
        showStatus('Camera access error: ' + e.message + '. Please check browser camera permissions.', false);
    }
}

function checkCameraHealth(eventId) {
    if (!stream) return;
    const tracks = stream.getVideoTracks();
    if (!tracks.length || tracks[0].readyState === 'ended' || tracks[0].muted) {
        stopCamera();
        setTimeout(() => startCamera(scanMode || 'unified'), 500);
        return;
    }
    if (faceScanBusy) {
        faceScanBusy = false;
    }
    const modalVisible = document.getElementById('attModal')?.style.display === 'flex' || document.getElementById('antiSpoofAlertModal')?.style.display === 'flex';
    if (!isFaceScanning && !modalVisible) {
        isFaceScanning = true;
        faceScanBusy = false;
        scheduleUnifiedScan(eventId, 200);
    }
}

function stopCamera() {
    if (stream) stream.getTracks().forEach(t => t.stop());
    if (scanInterval) clearInterval(scanInterval);
    if (faceScanTimeout) clearTimeout(faceScanTimeout);
    if (cameraHealthInterval) { clearInterval(cameraHealthInterval); cameraHealthInterval = null; }
    stream = null; scanInterval = null; scanMode = ''; isFaceScanning = false;
    faceScanBusy = false;
    scanCycleCount = 0;
    faceHistory = [];
    stopTrackingOverlay();
    const cameraBox = document.getElementById('cameraBox');
    const btnStop = document.getElementById('btnStop');
    if (cameraBox) cameraBox.style.display = 'none';
    if (btnStop) btnStop.style.display = 'none';
}

function resumeFaceScan(eventId, delay = 0) {
    if (!stream) return;
    faceScanBusy = false;
    isFaceScanning = true;
    faceHistory = [];
    scheduleUnifiedScan(eventId, delay);
}

function scheduleUnifiedScan(eventId, delay = 150) {
    if (!isFaceScanning) return;
    if (faceScanTimeout) clearTimeout(faceScanTimeout);
    faceScanTimeout = setTimeout(() => scanUnified(eventId), Math.max(delay, 60));
}

// ── On-site anti-spoofing (no live-verification challenge) ──────────────
// Students are NOT asked to blink or turn. A face is accepted once the
// phone/screen detector (screen_spoof.js, COCO-SSD) has checked the frame and
// found NO phone, tablet or monitor around the face. The face must also be
// recognised as the same student on 2 frames in a row (see scanUnified).
const LIVE_CFG = {
    MAX_BOX_JUMP: 0.5,      // face-width fraction allowed between frames
    DEVICE_CHECK_MS: 600    // how often the phone/screen detector runs per track
};
let liveTrack = null;
let screenSpoofReady = false;

function resetLiveTrack() {
    liveTrack = null;
    faceHistory = [];
}

// ── Live tracking overlay (faces + phones/screens) ─────────────────────────
// Boxes are kept in the VIDEO's native pixel space and mapped onto the
// on-screen video (object-fit: cover) each animation frame.
const tracker = {
    faces: [],        // [{ x, y, width, height, label, state: 'checking'|'ok'|'unknown'|'spoof', ts }]
    devices: [],      // [{ x, y, width, height, kind, score, ts }]
    qrs: [],          // [{ x, y, width, height, ts }]
    lastDevicePoll: 0,
    raf: null
};
const TRACK_STALE_MS = 1200;

function trackQrs(list) {
    const now = Date.now();
    tracker.qrs = (list || []).map(q => Object.assign({ ts: now }, q));
}

function trackFaces(list) {
    const now = Date.now();
    tracker.faces = list.map(f => Object.assign({ ts: now }, f));
}
function trackDevices(list) {
    const now = Date.now();
    tracker.devices = (list || []).map(d => Object.assign({ ts: now }, d));
}
function setTrackedFaceState(state, label) {
    tracker.faces.forEach(f => { f.state = state; if (label !== undefined) f.label = label; });
}

function drawTracking() {
    tracker.raf = null;
    const video = document.getElementById('cameraFeed');
    const cvs = document.getElementById('trackOverlay');
    if (!video || !cvs || !stream) { if (cvs) cvs.getContext('2d').clearRect(0, 0, cvs.width, cvs.height); return; }

    const cw = cvs.clientWidth, ch = cvs.clientHeight;
    const dpr = window.devicePixelRatio || 1;
    if (cvs.width !== Math.round(cw * dpr) || cvs.height !== Math.round(ch * dpr)) {
        cvs.width = Math.round(cw * dpr); cvs.height = Math.round(ch * dpr);
    }
    const g = cvs.getContext('2d');
    g.setTransform(dpr, 0, 0, dpr, 0, 0);
    g.clearRect(0, 0, cw, ch);

    const vw = video.videoWidth, vh = video.videoHeight;
    if (vw && vh) {
        const s = Math.max(cw / vw, ch / vh);                 // object-fit: cover
        const ox = (cw - vw * s) / 2, oy = (ch - vh * s) / 2;
        const now = Date.now();
        const box = (b, color, text, dashed) => {
            const x = ox + b.x * s, y = oy + b.y * s, w = b.width * s, h = b.height * s;
            g.lineWidth = 3; g.strokeStyle = color; g.setLineDash(dashed ? [8, 6] : []);
            g.strokeRect(x, y, w, h); g.setLineDash([]);
            // corner accents
            const c = Math.min(18, w / 4, h / 4); g.lineWidth = 5;
            [[x, y, 1, 1], [x + w, y, -1, 1], [x, y + h, 1, -1], [x + w, y + h, -1, -1]].forEach(([px, py, sx, sy]) => {
                g.beginPath(); g.moveTo(px, py + sy * c); g.lineTo(px, py); g.lineTo(px + sx * c, py); g.stroke();
            });
            if (text) {
                g.font = "700 12px 'Inter', sans-serif";
                const tw = g.measureText(text).width + 12, ty = y > 22 ? y - 22 : y + h + 4;
                g.fillStyle = color; g.fillRect(x, ty, tw, 20);
                g.fillStyle = '#fff'; g.fillText(text, x + 6, ty + 14);
            }
        };
        const devices = tracker.devices.filter(d => now - d.ts < TRACK_STALE_MS);
        const faces = tracker.faces.filter(f => now - f.ts < TRACK_STALE_MS);
        const qrs = tracker.qrs.filter(q => now - q.ts < TRACK_STALE_MS);
        devices.forEach(d => box(d, '#ef4444', `${d.kind.toUpperCase()} ${Math.round(d.score * 100)}%`, true));
        qrs.forEach(q => box(q, '#2563eb', 'QR CODE', false));
        const colors = { ok: '#22c55e', checking: '#f59e0b', unknown: '#64748b', spoof: '#ef4444' };
        faces.forEach(f => box(f, colors[f.state] || colors.checking, f.label || 'Checking…', false));

        const fc = document.getElementById('trackFaceCount'), dc = document.getElementById('trackDeviceCount'), qc = document.getElementById('trackQrCount');
        if (fc) fc.textContent = faces.length;
        if (dc) dc.textContent = devices.length;
        if (qc) qc.textContent = qrs.length;
    }
    tracker.raf = requestAnimationFrame(drawTracking);
}
function startTrackingOverlay() {
    const counts = document.getElementById('trackCounts');
    if (counts) counts.style.display = 'flex';
    if (!tracker.raf) tracker.raf = requestAnimationFrame(drawTracking);
}
function stopTrackingOverlay() {
    if (tracker.raf) cancelAnimationFrame(tracker.raf);
    tracker.raf = null; tracker.faces = []; tracker.devices = []; tracker.qrs = [];
    const counts = document.getElementById('trackCounts');
    if (counts) counts.style.display = 'none';
    setCameraLoading(false);
    const cvs = document.getElementById('trackOverlay');
    if (cvs) cvs.getContext('2d').clearRect(0, 0, cvs.width, cvs.height);
}

// Loading screen over the camera while the face / phone models load.
function setCameraLoading(on, title, sub) {
    const el = document.getElementById('camLoading');
    if (!el) return;
    el.style.display = on ? 'flex' : 'none';
    if (title) { const t = document.getElementById('camLoadingTitle'); if (t) t.textContent = title; }
    if (sub)   { const s = document.getElementById('camLoadingSub');   if (s) s.textContent = sub; }
}

/**
 * Keeps one track per continuously visible face (resets if the face
 * "teleports", e.g. a phone swapped in) so the phone/screen checks and the
 * 2-frame identity match apply to the same face. Always { live: true }.
 */
function evaluateLiveness(detection) {
    const box = detection && ((detection.detection && detection.detection.box) || detection.box || detection.alignedRect?.box);
    if (!box) return { live: false, waiting: true };
    const now = Date.now();
    if (liveTrack) {
        const jump = Math.hypot(box.x - liveTrack.lastBox.x, box.y - liveTrack.lastBox.y) / box.width;
        if (jump > LIVE_CFG.MAX_BOX_JUMP || now - liveTrack.lastSeen > 1500) resetLiveTrack();
    }
    if (!liveTrack) liveTrack = { start: now, lastSeen: now, lastBox: box, deviceChecks: 0, lastDeviceCheck: 0 };
    liveTrack.lastSeen = now;
    liveTrack.lastBox = box;
    return { live: true };
}
function showAntiSpoofAlertModal(reason, spoofType) {
    const modal = document.getElementById('antiSpoofAlertModal');
    const reasonEl = document.getElementById('asAlertReason');
    if (reasonEl && reason) reasonEl.textContent = reason;
    if (modal) modal.style.display = 'flex';

    isFaceScanning = false;
    faceScanBusy = false;
    if (faceScanTimeout) clearTimeout(faceScanTimeout);

    // Record spoof attempt to backend audit trail
    const evId = getEventId();
    try {
        const fd = new FormData();
        fd.append('event_id', evId || 0);
        fd.append('spoof_type', spoofType || 'Static Photo / Phone Screen');
        fd.append('details', reason || 'Blocked static face photo on camera feed.');
        fetch('../../config/API/endpoints/index.php?action=record_spoof_attempt', {
            method: 'POST',
            body: fd
        }).catch(() => {});
    } catch(e) {}
}

function closeAntiSpoofAlertModal() {
    const modal = document.getElementById('antiSpoofAlertModal');
    if (modal) modal.style.display = 'none';
    faceHistory = [];
    consecutiveSpoofFrames = 0;
    const ev = getEventId();
    if (stream && ev) {
        resumeFaceScan(ev, 600);
    }
}
window.closeAntiSpoofAlertModal = closeAntiSpoofAlertModal;

async function scanUnified(eventId) {
    if (!isFaceScanning || faceScanBusy) return;
    const video = document.getElementById('cameraFeed');
    if (!video || video.readyState < 2 || !video.videoWidth) {
        if (isFaceScanning) scheduleUnifiedScan(eventId, 200);
        return;
    }
    faceScanBusy = true;
    scanCycleCount++;

    try {
        const vw = video.videoWidth;
        const vh = video.videoHeight;

        // 1. QR Code Scan (Always enabled - accepts physical QR cards & phone screens displaying QR)
        const qrText = await detectQrFromVideo(video, vw, vh);
        if (qrText) {
            const studentId = parseStudentQrPayload(qrText);
            if (studentId) {
                isFaceScanning = false;
                if (faceScanTimeout) clearTimeout(faceScanTimeout);
                faceScanBusy = false;
                resetLiveTrack();
                showStatus('Student QR Code Detected!', true);
                promptAttendance(eventId, studentId, 'qr');
                return;
            }
        }

        // 2. Facial Recognition with Anti-Spoofing Protection
        const doFaceScan = isFaceApiLoaded && typeof faceapi !== 'undefined' && (scanCycleCount % 2 === 0 || !!liveTrack);
        if (doFaceScan) {
            const sourceWidth = vw;
            const sourceHeight = vh;
            const targetWidth = Math.min(sourceWidth, 320);
            const targetHeight = Math.max(1, Math.round(sourceHeight * (targetWidth / sourceWidth)));
            if (faceDetectionCanvas.width !== targetWidth || faceDetectionCanvas.height !== targetHeight) {
                faceDetectionCanvas.width = targetWidth;
                faceDetectionCanvas.height = targetHeight;
            }
            faceDetectionCtx.drawImage(video, 0, 0, targetWidth, targetHeight);
            const opts = getFaceDetectionOptions();
            if (opts) {
                const detections = await faceapi.detectAllFaces(faceDetectionCanvas, opts)
                    .withFaceLandmarks()
                    .withFaceDescriptors();

                const kFace = vw / targetWidth; // detection ran on the downscaled canvas
                const toVideo = (b) => ({ x: b.x * kFace, y: b.y * kFace, width: b.width * kFace, height: b.height * kFace });
                trackFaces((detections || []).map(d => Object.assign(toVideo(d.detection.box), { state: 'checking', label: 'Checking…' })));

                // Keep tracking phones/screens even when no single face is in view
                if (screenSpoofReady && (!detections || detections.length !== 1) && Date.now() - tracker.lastDevicePoll > LIVE_CFG.DEVICE_CHECK_MS) {
                    tracker.lastDevicePoll = Date.now();
                    const r = await ScreenSpoof.check(video, null);
                    if (r.ready && !r.busy && !r.error) trackDevices(r.devices);
                }

                if (detections && detections.length > 1) {
                    setTrackedFaceState('spoof', 'Multiple faces');
                    faceScanBusy = false;
                    showStatus('Multiple faces detected! Only one person allowed at a time.', false);
                    if (isFaceScanning && stream) scheduleUnifiedScan(eventId, 1000);
                    return;
                }

                const detection = detections && detections.length === 1 ? detections[0] : null;
                if (!detection) {
                    resetLiveTrack();
                } else {
                    const live = evaluateLiveness(detection);
                    if (liveTrack && liveTrack.label) setTrackedFaceState('checking', liveTrack.label + ' …');

                    // Phone / tablet / monitor check on the RAW frame, throttled per track.
                    let deviceSpoof = null;
                    if (screenSpoofReady && liveTrack && Date.now() - liveTrack.lastDeviceCheck > LIVE_CFG.DEVICE_CHECK_MS) {
                        liveTrack.lastDeviceCheck = Date.now();
                        tracker.lastDevicePoll = Date.now();
                        const res = await ScreenSpoof.check(video, toVideo(detection.detection.box));
                        if (res.ready && !res.busy && !res.error) trackDevices(res.devices);
                        if (res.spoof) deviceSpoof = res;
                        else if (res.ready && !res.busy && !res.error && liveTrack) liveTrack.deviceChecks++;
                    }

                    if (deviceSpoof) setTrackedFaceState('spoof', 'Face on ' + deviceSpoof.device);
                    if (deviceSpoof && Date.now() - lastSpoofAlertTime > 5000) {
                        lastSpoofAlertTime = Date.now();
                        resetLiveTrack();
                        showAntiSpoofAlertModal('A ' + deviceSpoof.device + ' showing a face was detected in front of the camera (' + Math.round(deviceSpoof.score * 100) + '% confidence). Facial attendance requires the real person. If presenting a mobile screen, please show the Student QR Code instead.', 'Phone / Screen Replay');
                        return;
                    } else if (!live.live || !screenSpoofReady || !liveTrack || liveTrack.deviceChecks < 1) {
                        // A face is matched only after at least one clean phone/screen
                        // check (the detector must be loaded). No action is asked of the student.
                        showStatus(screenSpoofReady ? 'Checking for phone or screen spoofing…' : 'Loading phone-screen detector… (QR check-in works meanwhile)', true);
                    } else {
                        consecutiveSpoofFrames = 0;
                        if (!faceMatcher) await initFaceMatcher();
                        if (faceMatcher) {
                            const match = faceMatcher.findBestMatch(detection.descriptor);
                            const MATCH_DISTANCE_THRESHOLD = 0.45;
                            if (match && match._label !== 'unknown' && match.distance < MATCH_DISTANCE_THRESHOLD) {
                                // Identity must stay the same for 2 live frames (prevents a
                                // live person "unlocking" liveness then a photo being matched).
                                if (liveTrack.label === match._label) liveTrack.hits = (liveTrack.hits || 0) + 1;
                                else { liveTrack.label = match._label; liveTrack.hits = 1; }
                                setTrackedFaceState('ok', match._label + ' ✓');
                                if (liveTrack.hits >= 2) {
                                    isFaceScanning = false;
                                    if (faceScanTimeout) clearTimeout(faceScanTimeout);
                                    faceScanBusy = false;
                                    resetLiveTrack();
                                    showStatus('Live Face Verified: ' + match._label + ' ✓ (confidence: ' + ((1 - match.distance) * 100).toFixed(0) + '%)', true);
                                    promptAttendance(eventId, match._label, 'face');
                                    return;
                                }
                            } else {
                                setTrackedFaceState('unknown', 'Not registered');
                                showStatus('Live face confirmed, but not recognized as a registered student. Use the Student QR Code.', false);
                            }
                        }
                    }
                }
            }
        }
    } catch(e) {
        console.warn('[scanUnified] Error caught, recovering:', e.message || e);
    }

    faceScanBusy = false;
    if (isFaceScanning && stream) {
        const nextDelay = (scanCycleCount % 2 === 0) ? 200 : 80;
        scheduleUnifiedScan(eventId, nextDelay);
    }
}

async function promptAttendance(eventId, studentId, method) {
    // The same student stays in front of the camera after scanning: ignore
    // repeat scans of that student for a short while (prevents a double Time In
    // and an immediate check-out prompt).
    const now = Date.now();
    const last = recentScans[String(studentId)];
    if (last && now - last < SCAN_COOLDOWN_MS) {
        setTimeout(() => { if (stream) resumeFaceScan(eventId, 0); }, 800);
        return;
    }
    recentScans[String(studentId)] = now;

    showStatus('Looking up student details…', true);
    let studentName = '';
    let profilePhoto = '';
    let details = '';
    try {
        const res = await fetch(`../../config/API/endpoints/index.php?action=get_student_info&StudentId=${encodeURIComponent(studentId)}&EventId=${encodeURIComponent(eventId)}`);
        const data = await res.json();
        if (data.success && data.student) {
            studentName = data.student.name;
            recentScans[String(data.student.student_id)] = now;
            studentId = data.student.student_id;
            profilePhoto = data.student.profile_photo;
            details = [data.student.course, data.student.year_level, data.student.section].filter(Boolean).join(' - ');
            if (data.student.already_completed) {
                showStatus(`${studentName} has already checked in and checked out for this event.`, false);
                setTimeout(() => { if(stream) resumeFaceScan(eventId, 0); }, 2500);
                return;
            }
            const targetLogType = data.student.auto_log_type || (data.student.has_logged_in ? 'Log Out' : 'Log In');
            // Not time to log out yet (75% rule): no pop-up, just a short notice.
            if (targetLogType === 'Log Out' && data.student.can_log_out === false) {
                const s = Math.max(0, Number(data.student.logout_opens_in) || 0);
                const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60);
                const wait = h > 0 ? `${h}h ${m}m` : `${Math.max(1, m)} min`;
                showStatus(`${studentName} is already checked in. Check-out opens in ${wait}.`, true);
                setTimeout(() => { if (stream) resumeFaceScan(eventId, 0); }, 2500);
                return;
            }
            setLogType(targetLogType);
        } else {
            studentName = `Student #${studentId}`;
        }
    } catch (e) {
        studentName = `Student #${studentId}`;
    }

    pendingAttendance = { eventId, studentId, studentName, method, logType: currentLogType };
    const attModal = document.getElementById('attModal');
    if (attModal) {
        const modalTitle = attModal.querySelector('h3');
        if (modalTitle) modalTitle.textContent = `Confirm ${currentLogType}`;
        const recordBtn = document.getElementById('mdlBtnRecord');
        if (recordBtn) {
            recordBtn.textContent = `Record ${currentLogType}`;
            recordBtn.style.background = currentLogType === 'Log Out' ? '#dc2626' : '#10b981';
        }
        const photoEl = document.getElementById('mdlStudentPhoto');
        if (photoEl) photoEl.src = profilePhoto || '../../assets/img/philsca.png';
        const nameEl = document.getElementById('mdlStudentName');
        if (nameEl) nameEl.textContent = studentName;
        const idEl = document.getElementById('mdlStudentId');
        if (idEl) idEl.textContent = 'ID: ' + studentId;
        const detEl = document.getElementById('mdlStudentDetails');
        if (detEl) detEl.textContent = details || 'NAAP Student';
        attModal.style.display = 'flex';
    } else {
        recordAttendance(eventId, studentId, studentName, method, currentLogType);
    }
}

function confirmAttendanceModal(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const recordBtn = document.getElementById('mdlBtnRecord');
    if (!pendingAttendance) {
        closeAttendanceModal();
        return;
    }
    if (recordBtn) {
        recordBtn.disabled = true;
        recordBtn.innerHTML = '⏳ Recording…';
        recordBtn.style.opacity = '0.7';
    }
    const { eventId, studentId, studentName, method, logType } = pendingAttendance;
    const lType = logType || currentLogType;
    const fd = new FormData();
    fd.append('EventId', eventId);
    fd.append('StudentId', studentId);
    fd.append('StudentName', studentName || '');
    fd.append('Method', method || 'qr');
    fd.append('LogType', lType);
    showStatus(`Saving ${lType} record for ${studentName}…`, true);
    fetch('../../config/API/endpoints/index.php?action=record_attendance', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        closeAttendanceModal();
        showStatus(d.message || `${lType} recorded!`, d.success);
        if (typeof showModal === 'function') {
            showModal(d.message || `${lType} successfully recorded!`, d.success ? 'success' : 'error', d.success ? 'Attendance Recorded' : 'Attendance Notice');
        }
        if (d.success) {
            loadLog(eventId);
        }
        setTimeout(() => { if (stream) resumeFaceScan(eventId, 1000); }, 1500);
    })
    .catch(err => {
        closeAttendanceModal();
        showStatus('Error communicating with attendance server.', false);
        if (typeof showModal === 'function') {
            showModal('Error communicating with attendance server: ' + (err.message || err), 'error', 'Error');
        }
        setTimeout(() => { if (stream) resumeFaceScan(eventId, 1000); }, 1500);
    })
    .finally(() => {
        if (recordBtn) {
            recordBtn.disabled = false;
            recordBtn.style.opacity = '1';
            recordBtn.textContent = `Record ${currentLogType}`;
        }
        pendingAttendance = null;
    });
}
window.confirmAttendanceModal = confirmAttendanceModal;

function closeAttendanceModal(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const attModal = document.getElementById('attModal');
    if (attModal) attModal.style.display = 'none';
    const evId = pendingAttendance ? pendingAttendance.eventId : getEventId();
    pendingAttendance = null;
    const recordBtn = document.getElementById('mdlBtnRecord');
    if (recordBtn) {
        recordBtn.disabled = false;
        recordBtn.style.opacity = '1';
        recordBtn.textContent = `Record ${currentLogType}`;
    }
    if (stream && evId) {
        resumeFaceScan(evId, 300);
    }
}
window.closeAttendanceModal = closeAttendanceModal;

function recordAttendance(eventId, studentId, studentName, method, logType) {
    if (!eventId || !studentId) {
        showStatus('Event ID and Student ID are required.', false);
        return;
    }
    const lType = logType || currentLogType;
    const fd = new FormData();
    fd.append('EventId', eventId);
    fd.append('StudentId', studentId);
    fd.append('StudentName', studentName || '');
    fd.append('Method', method || 'manual');
    fd.append('LogType', lType);
    showStatus(`Saving ${lType} record…`, true);
    fetch('../../config/API/endpoints/index.php?action=record_attendance', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        showStatus(d.message || `${lType} recorded!`, d.success);
        if (typeof showModal === 'function') {
            showModal(d.message || `${lType} recorded!`, d.success ? 'success' : 'error', d.success ? 'Attendance Recorded' : 'Attendance Notice');
        }
        if (d.success) {
            loadLog(eventId);
        }
        setTimeout(() => { if (stream) resumeFaceScan(eventId, 1000); }, 1500);
    })
    .catch(err => {
        showStatus('Error communicating with attendance server.', false);
        if (typeof showModal === 'function') {
            showModal('Error communicating with attendance server: ' + (err.message || err), 'error', 'Error');
        }
        setTimeout(() => { if (stream) resumeFaceScan(eventId, 1000); }, 1500);
    });
}

function recordManual() {
    const evId = getEventId();
    if (!evId) return;
    const inp = document.getElementById('manualId');
    if (!inp) return;
    const sid = inp.value.trim();
    if (!sid) {
        showStatus('Please enter a Student ID or Student No.', false);
        return;
    }
    promptAttendance(evId, sid, 'manual');
    inp.value = '';
}

let allAttendanceData = [];
let attCurrentPage = 1;
const attPerPage = 15;

function loadLog(eventId) {
    if (!eventId) return;
    const tbody = document.getElementById('attLog');
    const attCount = document.getElementById('attCount');
    if (!tbody) return;

    fetch(`../../config/API/endpoints/index.php?action=get_attendance_log&EventId=${encodeURIComponent(eventId)}`)
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.attendance || data.attendance.length === 0) {
            allAttendanceData = [];
            attCurrentPage = 1;
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">No attendance records found for this event.</td></tr>';
            if (attCount) attCount.textContent = '0 recorded';
            renderAttendancePagination(eventId);
            return;
        }
        allAttendanceData = data.attendance;
        if (attCount) attCount.textContent = `${allAttendanceData.length} recorded`;
        renderAttendanceTable(eventId);
    })
    .catch(err => {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">Select an event to view attendance.</td></tr>';
        renderAttendancePagination(eventId);
    });
}

function renderAttendanceTable(eventId) {
    const tbody = document.getElementById('attLog');
    if (!tbody) return;

    const total = allAttendanceData.length;
    if (total === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">No attendance records found for this event.</td></tr>';
        renderAttendancePagination(eventId);
        return;
    }

    const totalPages = Math.max(1, Math.ceil(total / attPerPage));
    if (attCurrentPage > totalPages) attCurrentPage = totalPages;
    if (attCurrentPage < 1) attCurrentPage = 1;

    const startIndex = (attCurrentPage - 1) * attPerPage;
    const endIndex = Math.min(startIndex + attPerPage, total);
    const pageItems = allAttendanceData.slice(startIndex, endIndex);

    tbody.innerHTML = pageItems.map((a, i) => {
        const globalIdx = startIndex + i + 1;
        const dt = a.ScannedAt ? new Date(a.ScannedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';
        const lType = a.LogType || 'Log In';
        const typeBadge = lType === 'Log Out'
            ? `<span class="mode-badge" style="background:#fee2e2;color:#b91c1c;padding:3px 8px;border-radius:6px;font-size:0.75rem;font-weight:700;">Log Out</span>`
            : `<span class="mode-badge" style="background:#dbeafe;color:#1d4ed8;padding:3px 8px;border-radius:6px;font-size:0.75rem;font-weight:700;">Log In</span>`;
        const methodStr = htmlspecialchars(a.Method || 'manual');
        return `<tr>
            <td style="padding:12px 16px;color:#64748b;font-weight:600;">${globalIdx}</td>
            <td style="padding:12px 16px;font-weight:700;color:#0f172a;">${htmlspecialchars(a.StudentName || '—')}</td>
            <td style="padding:12px 16px;font-weight:600;color:#2563eb;">${htmlspecialchars(a.StudentId || '—')}</td>
            <td style="padding:12px 16px;">${typeBadge}</td>
            <td style="padding:12px 16px;"><span class="mode-badge mode-${methodStr}" style="padding:3px 8px;border-radius:6px;font-size:0.75rem;font-weight:600;background:#f1f5f9;color:#475569;">${methodStr}</span></td>
            <td style="padding:12px 16px;color:#64748b;font-size:0.85rem;">${dt}</td>
            <td style="padding:12px 16px;text-align:right;">
                <button type="button" onclick="deleteAttendanceRow(${a.AttendanceId}, ${eventId})" style="color:#ef4444;border:none;background:none;cursor:pointer;font-size:18px;display:inline-flex;align-items:center;" title="Delete Record">
                    <ion-icon name="trash-outline"></ion-icon>
                </button>
            </td>
        </tr>`;
    }).join('');

    renderAttendancePagination(eventId);
}

function renderAttendancePagination(eventId) {
    const infoEl = document.getElementById('attPaginationInfo');
    const controlsEl = document.getElementById('attPaginationControls');
    const total = allAttendanceData.length;

    if (!infoEl || !controlsEl) return;

    if (total === 0) {
        infoEl.textContent = 'Showing 0 to 0 of 0 records';
        controlsEl.innerHTML = '';
        return;
    }

    const totalPages = Math.max(1, Math.ceil(total / attPerPage));
    const startRecord = (attCurrentPage - 1) * attPerPage + 1;
    const endRecord = Math.min(attCurrentPage * attPerPage, total);

    infoEl.textContent = `Showing ${startRecord} to ${endRecord} of ${total} records`;

    let btnsHtml = '';

    // First & Prev Buttons
    const prevDisabled = attCurrentPage <= 1;
    btnsHtml += `
        <button type="button" onclick="goToAttendancePage(1, ${eventId})" ${prevDisabled ? 'disabled' : ''} style="padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#475569;font-size:0.8rem;font-weight:600;cursor:${prevDisabled ? 'not-allowed' : 'pointer'};opacity:${prevDisabled ? '0.5' : '1'};">
            « First
        </button>
        <button type="button" onclick="goToAttendancePage(${attCurrentPage - 1}, ${eventId})" ${prevDisabled ? 'disabled' : ''} style="padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#475569;font-size:0.8rem;font-weight:600;cursor:${prevDisabled ? 'not-allowed' : 'pointer'};opacity:${prevDisabled ? '0.5' : '1'};">
            ‹ Prev
        </button>
    `;

    // Dynamic Page Numbers (showing max 5 visible pages)
    const maxVisible = 5;
    let startPage = Math.max(1, attCurrentPage - 2);
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }

    for (let p = startPage; p <= endPage; p++) {
        const isActive = p === attCurrentPage;
        btnsHtml += `
            <button type="button" onclick="goToAttendancePage(${p}, ${eventId})" style="padding:6px 12px;border-radius:6px;border:1px solid ${isActive ? '#2563eb' : '#cbd5e1'};background:${isActive ? '#2563eb' : '#fff'};color:${isActive ? '#fff' : '#475569'};font-size:0.8rem;font-weight:700;cursor:pointer;">
                ${p}
            </button>
        `;
    }

    // Next & Last Buttons
    const nextDisabled = attCurrentPage >= totalPages;
    btnsHtml += `
        <button type="button" onclick="goToAttendancePage(${attCurrentPage + 1}, ${eventId})" ${nextDisabled ? 'disabled' : ''} style="padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#475569;font-size:0.8rem;font-weight:600;cursor:${nextDisabled ? 'not-allowed' : 'pointer'};opacity:${nextDisabled ? '0.5' : '1'};">
            Next ›
        </button>
        <button type="button" onclick="goToAttendancePage(${totalPages}, ${eventId})" ${nextDisabled ? 'disabled' : ''} style="padding:6px 10px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#475569;font-size:0.8rem;font-weight:600;cursor:${nextDisabled ? 'not-allowed' : 'pointer'};opacity:${nextDisabled ? '0.5' : '1'};">
            Last »
        </button>
    `;

    controlsEl.innerHTML = btnsHtml;
}

function goToAttendancePage(page, eventId) {
    attCurrentPage = page;
    renderAttendanceTable(eventId);
}
window.goToAttendancePage = goToAttendancePage;

function deleteAttendanceRow(attendanceId, eventId) {
    showConfirmModal('Are you sure you want to delete this attendance record?', function() {
        const fd = new FormData();
        fd.append('AttendanceId', attendanceId);
        fetch('../../config/API/endpoints/index.php?action=delete_attendance', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            showStatus(d.message || 'Record deleted', d.success);
            if (d.success) loadLog(eventId);
        })
        .catch(() => showStatus('Failed to delete attendance record.', false));
    }, 'Delete Attendance Record', 'danger');
}

function htmlspecialchars(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function handleQrFileUpload(e) {
    const file = e.target ? e.target.files[0] : null;
    if (!file) return;
    const evId = getEventId();
    if (!evId) return;
    showStatus('Decoding uploaded QR image…', true);
    const reader = new FileReader();
    reader.onload = function(evt) {
        const img = new Image();
        img.onload = function() {
            const tempCanvas = document.createElement('canvas');
            const tempCtx = tempCanvas.getContext('2d');
            tempCanvas.width = img.width;
            tempCanvas.height = img.height;
            tempCtx.drawImage(img, 0, 0);
            try {
                const imgData = tempCtx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
                if (typeof jsQR !== 'undefined') {
                    const decoded = jsQR(imgData.data, imgData.width, imgData.height);
                    if (decoded && decoded.data) {
                        const studentId = parseStudentQrPayload(decoded.data);
                        if (studentId) {
                            showStatus('QR code decoded successfully!', true);
                            promptAttendance(evId, studentId, 'qr_upload');
                        } else {
                            showStatus('Invalid QR format in uploaded image.', false);
                        }
                    } else {
                        showStatus('No QR code detected in the image. Please upload a clear QR photo.', false);
                    }
                } else {
                    showStatus('QR library loading, please try again in a moment.', false);
                }
            } catch (err) {
                showStatus('Error reading image: ' + err.message, false);
            }
        };
        img.src = evt.target.result;
    };
    reader.readAsDataURL(file);
    if (e.target) e.target.value = '';
}

// ── Anti-Spoofing Challenge Modal Handlers (Interactive Mode) ──────────
const CHALLENGES = [
    { id: 'LEFT', icon: 'arrow-back-circle-outline', text: 'Look LEFT', sub: 'Turn your head slowly to the left' },
    { id: 'RIGHT', icon: 'arrow-forward-circle-outline', text: 'Look RIGHT', sub: 'Turn your head slowly to the right' },
    { id: 'UP', icon: 'arrow-up-circle-outline', text: 'Look UP', sub: 'Tilt your head gently upward' },
    { id: 'DOWN', icon: 'arrow-down-circle-outline', text: 'Look DOWN', sub: 'Tilt your head gently downward' },
    { id: 'BLINK', icon: 'eye-outline', text: 'BLINK Eyes', sub: 'Blink your eyes firmly' },
];
let asStream = null, asChallenge = null, asRunning = false;
let asPollTimer = null, asTimeoutTimer = null, asHoldTimer = null, asCountdownInterval = null;
let asCanvas = document.createElement('canvas');
let asCtx = asCanvas.getContext('2d', { willReadFrequently: true });
let asApiLoaded = false;
let autoRefreshTimer = null;

function startAutoRefreshTimer() {
    clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(() => {
        const sel = document.getElementById('eventSelect');
        if (!sel || !sel.selectedOptions || !sel.selectedOptions[0]) return;
        const status = (sel.selectedOptions[0].dataset.status || '').toLowerCase();
        if (status === 'completed' || status === 'cancelled' || status === 'archived') {
            clearInterval(autoRefreshTimer);
            return;
        }
        const evId = getEventId();
        if (evId) loadLog(evId);
    }, 10000);
}

async function openAntiSpoofModal(eventId) {
    const overlay = document.getElementById('antiSpoofOverlay');
    if (!overlay) return;
    overlay.style.display = 'flex';
    setAsChallengeUI('help-circle-outline', 'Starting camera…', 'Please allow camera access');
    const fill = document.getElementById('asTimerFill');
    if (fill) { fill.style.transition = 'none'; fill.style.width = '100%'; }
    const statusEl = document.getElementById('asStatusText');
    if (statusEl) statusEl.textContent = '';
    const countdownText = document.getElementById('asCountdownText');
    if (countdownText) countdownText.textContent = '5.0s';

    if (!asApiLoaded) {
        setAsChallengeUI('hourglass-outline', 'Loading AI models…', 'First time takes a few seconds');
        const candidatePaths = [
            '../../assets/models',
            '../assets/models',
            '/Project/assets/models',
            'assets/models',
            'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/',
            'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/'
        ];
        let asSuccess = false;
        for (const p of candidatePaths) {
            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(p),
                    faceapi.nets.faceLandmark68Net.loadFromUri(p),
                ]);
                asApiLoaded = true;
                asSuccess = true;
                break;
            } catch(e) {
                console.warn(`AS Candidate model path failed (${p}):`, e);
            }
        }
        if (!asSuccess) {
            setAsChallengeUI('close-circle-outline', 'Model load failed', 'Could not load face models from local or CDN.');
            return;
        }
    }
    try {
        asStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 } });
        const vid = document.getElementById('asVideo');
        if (vid) {
            vid.srcObject = asStream;
            await new Promise(r => { vid.onloadedmetadata = r; });
            vid.play();
        }
    } catch(e) {
        setAsChallengeUI('ban-outline', 'Camera denied', 'Please allow camera access in your browser.');
        return;
    }
    setupFaceTrackerCanvas();
    asChallenge = CHALLENGES[Math.floor(Math.random() * CHALLENGES.length)];
    setAsChallengeUI(asChallenge.icon || 'help-circle-outline', asChallenge.text, asChallenge.sub);
    if (statusEl) statusEl.textContent = 'Face detection active...';
    if (fill) {
        fill.style.transition = `width 5000ms linear`;
        fill.style.width = '0%';
    }
    const startTime = Date.now();
    const duration = 5000;
    clearInterval(asCountdownInterval);
    asCountdownInterval = setInterval(() => {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, (duration - elapsed) / 1000);
        if (countdownText) countdownText.textContent = remaining.toFixed(1) + 's';
        if (remaining <= 0) clearInterval(asCountdownInterval);
    }, 100);
    asRunning = true;
    asPollTimer = setInterval(() => pollLiveness(eventId), 100);
    asTimeoutTimer = setTimeout(() => {
        if (asRunning) failAntiSpoof('Time ran out! Please try again.');
    }, 5000);
}

function setupFaceTrackerCanvas() {
    const vid = document.getElementById('asVideo');
    if (!vid) return;
    let trackerCanvas = document.getElementById('asFaceTracker');
    if (!trackerCanvas) {
        trackerCanvas = document.createElement('canvas');
        trackerCanvas.id = 'asFaceTracker';
        trackerCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;border-radius:12px;';
        vid.parentElement.style.position = 'relative';
        vid.insertAdjacentElement('afterend', trackerCanvas);
    }
}

function setAsChallengeUI(iconName, text, sub) {
    const txtEl = document.getElementById('asChallengeText');
    const subEl = document.getElementById('asChallengeSubText');
    if (txtEl) txtEl.textContent = text;
    if (subEl) subEl.textContent = sub;
}

async function pollLiveness(eventId) {
    if (!asRunning) return;
    const vid = document.getElementById('asVideo');
    if (!vid || vid.readyState < 2) return;
    asCanvas.width = vid.videoWidth || 320;
    asCanvas.height = vid.videoHeight || 240;
    asCtx.drawImage(vid, 0, 0, asCanvas.width, asCanvas.height);
    try {
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.35 });
        const allDets = await faceapi.detectAllFaces(asCanvas, opts).withFaceLandmarks();
        const statusEl = document.getElementById('asStatusText');
        if (allDets && allDets.length > 1) {
            if (statusEl) statusEl.textContent = 'Multiple faces detected! Only one person allowed. Please retry.';
            clearTimeout(asHoldTimer); asHoldTimer = null;
            return;
        }
        const det = allDets && allDets.length === 1 ? allDets[0] : null;
        if (!det) {
            if (statusEl) statusEl.textContent = 'No face detected — position yourself in camera frame';
            clearTimeout(asHoldTimer); asHoldTimer = null;
            return;
        }
        const passed = checkPose(det.landmarks, asChallenge.id);
        if (passed) {
            if (!asHoldTimer) {
                if (statusEl) statusEl.textContent = 'Hold position…';
                asHoldTimer = setTimeout(() => passAntiSpoof(eventId), 300);
            }
        } else {
            if (statusEl) statusEl.textContent = `Face detected — perform challenge`;
            clearTimeout(asHoldTimer); asHoldTimer = null;
        }
    } catch(e) {}
}

function checkPose(landmarks, direction) {
    const pts = landmarks.positions;
    const nose = pts[30];
    const lEye = pts[36];
    const rEye = pts[45];
    const eyeMidX = (lEye.x + rEye.x) / 2;
    const eyeWidth = Math.abs(rEye.x - lEye.x);
    const noseOffX = nose.x - eyeMidX;
    if (direction === 'LEFT') return (noseOffX / eyeWidth) < -0.22;
    if (direction === 'RIGHT') return (noseOffX / eyeWidth) > 0.22;
    const eyeMidY = (lEye.y + rEye.y) / 2;
    const noseOffY = nose.y - eyeMidY;
    if (direction === 'UP') return (noseOffY / eyeWidth) < 0.35;
    if (direction === 'DOWN') return (noseOffY / eyeWidth) > 0.65;
    if (direction === 'BLINK') {
        const eyeH = (pts[37].y + pts[38].y)/2 - (pts[40].y + pts[41].y)/2;
        return Math.abs(eyeH) < 4;
    }
    return false;
}

function passAntiSpoof(eventId) {
    if (!asRunning) return;
    stopAntiSpoofCamera();
    setAsChallengeUI('checkmark-circle-outline', 'Liveness Verified!', 'Challenge passed successfully');
    const statusEl = document.getElementById('asStatusText');
    if (statusEl) statusEl.textContent = 'Anti-spoofing passed! Starting face scanner…';
    setTimeout(async () => {
        closeAntiSpoofModal();
        await startCamera('unified');
    }, 1200);
}

function failAntiSpoof(reason) {
    if (!asRunning) return;
    stopAntiSpoofCamera();
    setAsChallengeUI('close-circle-outline', 'Liveness Failed', reason);
    const statusEl = document.getElementById('asStatusText');
    if (statusEl) statusEl.textContent = 'Spoofing attempt blocked or timed out.';
    setTimeout(() => closeAntiSpoofModal(), 2200);
}

function stopAntiSpoofCamera() {
    asRunning = false;
    clearInterval(asPollTimer);
    clearInterval(asCountdownInterval);
    clearTimeout(asTimeoutTimer);
    clearTimeout(asHoldTimer);
    asPollTimer = asCountdownInterval = asTimeoutTimer = asHoldTimer = null;
    if (asStream) { asStream.getTracks().forEach(t => t.stop()); asStream = null; }
    const vid = document.getElementById('asVideo');
    if (vid) vid.srcObject = null;
}

function closeAntiSpoofModal() {
    stopAntiSpoofCamera();
    const overlay = document.getElementById('antiSpoofOverlay');
    if (overlay) overlay.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const eventSelect = document.getElementById('eventSelect');
    if (eventSelect) {
        eventSelect.addEventListener('change', () => {
            const evId = getEventId();
            if (evId) loadLog(evId);
        });
        const initialEvId = eventSelect.value;
        if (initialEvId) {
            loadLog(initialEvId);
        }
    }
    const btnUnified = document.getElementById('btnUnified');
    if (btnUnified) {
        btnUnified.addEventListener('click', () => startCamera('unified'));
    }
    const btnUploadQR = document.getElementById('btnUploadQR');
    const qrFileInput = document.getElementById('qrFileInput');
    if (btnUploadQR && qrFileInput) {
        btnUploadQR.addEventListener('click', () => qrFileInput.click());
        qrFileInput.addEventListener('change', handleQrFileUpload);
    }
    const btnStop = document.getElementById('btnStop');
    if (btnStop) {
        btnStop.addEventListener('click', stopCamera);
    }
    const btnManual = document.getElementById('btnManual');
    if (btnManual) {
        btnManual.addEventListener('click', recordManual);
    }
    const manualInp = document.getElementById('manualId');
    if (manualInp) {
        manualInp.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') recordManual();
        });
    }
    const btnAntiSpoof = document.getElementById('btnAntiSpoof');
    if (btnAntiSpoof) {
        btnAntiSpoof.addEventListener('click', () => {
            const ev = getEventId();
            if (ev) openAntiSpoofModal(ev);
        });
    }
    const cancelModalBtn = document.getElementById('mdlBtnCancel');
    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', closeAttendanceModal);
    }
    const recordModalBtn = document.getElementById('mdlBtnRecord');
    if (recordModalBtn) {
        recordModalBtn.addEventListener('click', confirmAttendanceModal);
    }
});

window.triggerEventAntiSpoof = async function() {
    const ev = typeof getEventId === 'function' ? getEventId() : parseInt(document.getElementById('eventSelect')?.value || 0, 10);
    if (!ev) {
        if (typeof showModal === 'function') showModal('Please select an event first.', 'warning');
        else alert('Please select an event first.');
        return;
    }
    if (typeof openAntiSpoofModal === 'function') {
        openAntiSpoofModal(ev);
    } else {
        try {
            const fd = new FormData();
            fd.append('event_id', ev);
            const res = await fetch('../../config/API/endpoints/index.php?action=trigger_antispoofing', { method: 'POST', body: fd });
            const data = await res.json();
            if (typeof showModal === 'function') showModal(data.message || (data.success ? 'Anti-spoofing challenge triggered!' : 'Failed to trigger.'), data.success ? 'success' : 'error');
            else alert(data.message || (data.success ? 'Anti-spoofing challenge triggered!' : 'Failed to trigger.'));
        } catch(e) {
            alert('Error triggering anti-spoofing.');
        }
    }
};

window.startCamera = startCamera;
window.stopCamera = stopCamera;
window.handleQrFileUpload = handleQrFileUpload;
window.recordManual = recordManual;
window.getEventId = getEventId;
window.openAntiSpoofModal = openAntiSpoofModal;
window.closeAntiSpoofModal = closeAntiSpoofModal;
window.confirmAttendanceModal = confirmAttendanceModal;
window.closeAttendanceModal = closeAttendanceModal;
window.deleteAttendanceRow = deleteAttendanceRow;
window.setLogType = setLogType;
window.loadLog = loadLog;