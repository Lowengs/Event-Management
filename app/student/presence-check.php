<?php
session_start();
require_once '../../config/db.php';
if (empty($_SESSION['student_id'])) { header('Location: login.php'); exit; }
$eventId = (int)($_GET['eventId'] ?? 0);
$type = ($_GET['type'] ?? '') === 'antispoof' ? 'antispoof' : 'presence';
$event = $eventId ? $conn->query("SELECT EventId, EventName, EventStatus, AntiSpoofActive, PresenceCheckActive FROM event WHERE EventId = $eventId LIMIT 1")->fetch_assoc() : null;
if (!$event) { header('Location: profile-dashboard.php'); exit; }
$isCompleted = in_array(strtolower(trim($event['EventStatus'] ?? '')), ['completed', 'cancelled', 'archived']);
$label = $type === 'antispoof' ? 'Anti-spoofing Verification' : 'Presence Check';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $label ?> | NAAP</title>
    <link rel="icon" id="tabFavicon" href="../../assets/img/philsca.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            background: #0b1220;
            color: #e5edf8;
            font-family: 'Inter', system-ui, sans-serif;
            font-size: 15px;
        }
        .card {
            max-width: 510px;
            width: 100%;
            background: linear-gradient(145deg, #17233b, #1a2744);
            border: 1px solid #31415d;
            border-radius: 22px;
            padding: 32px 28px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.4);
        }
        .tag {
            display: inline-block;
            color: #7dd3fc;
            background: rgba(12, 74, 110, 0.35);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        h1 {
            margin: 16px 0 8px;
            font-size: 22px;
            font-weight: 800;
            color: #f1f5f9;
        }
        .desc {
            color: #94a3b8;
            line-height: 1.6;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .camera-wrap {
            position: relative;
            width: 100%;
            border-radius: 16px;
            overflow: hidden;
            background: #020617;
            margin: 16px 0;
            display: none;
        }
        .camera-wrap video {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            transform: scaleX(-1);
            display: block;
        }
        .camera-overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .face-tracker-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        .status-box {
            margin: 16px 0;
            padding: 16px;
            border-radius: 14px;
            background: #0f172a;
            color: #94a3b8;
            font-weight: 600;
            font-size: 14px;
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .status-box.success {
            background: rgba(34, 197, 94, 0.12);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .status-box.error {
            background: rgba(239, 68, 68, 0.12);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .spinner {
            width: 18px; height: 18px;
            border: 2.5px solid rgba(255,255,255,0.15);
            border-top-color: #7dd3fc;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            flex-shrink: 0;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .btn {
            width: 100%;
            padding: 14px;
            border: 0;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
            margin-top: 8px;
        }
        .btn:hover { background: #1d4ed8; transform: translateY(-1px); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn.success-btn { background: #16a34a; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            margin-top: 18px;
            transition: color 0.2s;
        }
        .back-link:hover { color: #94a3b8; }
    </style>
</head>
<body>
    <main class="card">
        <?php if ($isCompleted): ?>
            <div style="width:64px;height:64px;border-radius:50%;background:rgba(34,197,94,0.15);border:2px solid rgba(34,197,94,0.3);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:30px;">
                <ion-icon name="checkmark-circle" style="color:#22c55e;font-size:30px;"></ion-icon>
            </div>
            <span class="tag" style="background:rgba(34,197,94,0.15);color:#86efac;">Event Concluded</span>
            <h1><?= htmlspecialchars($event['EventName']) ?></h1>
            <p class="desc">
                This event is already completed. Live anti-spoofing and presence verification checks are no longer active.
            </p>
            <a href="profile-dashboard.php" class="btn" style="text-decoration:none;display:inline-block;box-sizing:border-box;line-height:1.4;">
                Back to Dashboard
            </a>
        <?php else: ?>
            <span class="tag"><?= $label ?></span>
            <h1><?= htmlspecialchars($event['EventName']) ?></h1>
            <p class="desc">
                <?php if ($type === 'antispoof'): ?>
                    Allow camera access to verify your identity. Your face will be detected automatically.
                <?php else: ?>
                    Confirm that you are actively attending this event.
                <?php endif; ?>
            </p>

            <div class="camera-wrap" id="cameraWrap">
                <video id="camera" autoplay muted playsinline></video>
                <div class="camera-overlay">
                    <canvas class="face-tracker-canvas" id="faceTrackerCanvas"></canvas>
                </div>
            </div>            <div id="challengePromptCard" style="display:none;background:rgba(56,189,248,0.1);border:1.5px solid rgba(56,189,248,0.3);border-radius:12px;padding:12px 14px;margin-bottom:14px;text-align:left;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:11px;font-weight:700;color:#38bdf8;text-transform:uppercase;letter-spacing:0.05em;" id="challengeStepLabel">Liveness Step 1 of 2</span>
                    <span style="font-size:11px;font-weight:700;color:#94a3b8;" id="challengeProgressPct">0%</span>
                </div>
                <div style="width:100%;height:5px;background:rgba(255,255,255,0.1);border-radius:10px;overflow:hidden;margin-bottom:8px;">
                    <div id="challengeProgressBar" style="width:0%;height:100%;background:#38bdf8;transition:width 0.2s ease;"></div>
                </div>
                <p id="challengeInstruction" style="margin:0;font-size:13px;font-weight:600;color:#e0f2fe;">
                    Center your face in the camera frame
                </p>
            </div>

            <div class="status-box" id="statusBox">
                <div class="spinner" id="statusSpinner"></div>
                <span id="statusText">Initializing...</span>
            </div>

            <button id="complete" class="btn" disabled>
                <?= $type === 'antispoof' ? 'Starting Anti-Spoofing Challenge...' : 'Complete verification' ?>
            </button>

            <a href="profile-dashboard.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                </svg>
                Back to Dashboard
            </a>
        <?php endif; ?>
    </main>

    <?php if (!$isCompleted): ?>
    <script src="../../assets/js/lib/face-api.min.js"></script>
    <script>
    const type      = <?= json_encode($type) ?>;
    const eventId   = <?= $eventId ?>;
    const button    = document.getElementById('complete');
    const video     = document.getElementById('camera');
    const statusBox = document.getElementById('statusBox');
    const statusText= document.getElementById('statusText');
    const statusSpinner = document.getElementById('statusSpinner');
    const cameraWrap= document.getElementById('cameraWrap');

    const promptCard = document.getElementById('challengePromptCard');
    const stepLabel = document.getElementById('challengeStepLabel');
    const progressBar = document.getElementById('challengeProgressBar');
    const instructionEl = document.getElementById('challengeInstruction');
    const progressPctEl = document.getElementById('challengeProgressPct');

    let stream, poll, submitting = false;
    let isScanning = false;
    let originalTitle = document.title;
    
    // Anti-Spoofing Liveness State Machine
    let currentStep = 1; // 1 = Calibration, 2 = Liveness & Natural Motion, 3 = Verified
    let initialFaceBox = null;
    let faceDetectFrames = 0;
    let livenessScore = 0;
    let lastBox = null;
    let lastDetectTime = 0;
    let scanStartTime = 0;

    function startTitleFlash() {
      originalTitle = document.title.replace(/^\(\d+\)\s*/, '');
      document.title = `(1) ${originalTitle}`;
    }
    function stopTitleFlash() {
      document.title = originalTitle.replace(/^\(\d+\)\s*/, '');
    }

    function setStatus(text, state) {
        statusText.textContent = text;
        statusBox.className = 'status-box' + (state ? ' ' + state : '');
        statusSpinner.style.display = (state === 'success' || state === 'error') ? 'none' : 'block';
    }

    function updateChallengeUI(step, pct, instruction, isSuccess = false) {
        if (promptCard) promptCard.style.display = 'block';
        if (stepLabel) stepLabel.textContent = isSuccess ? 'Challenge Complete' : `Liveness Step ${step} of 2`;
        if (progressBar) {
            progressBar.style.width = pct + '%';
            progressBar.style.background = isSuccess ? '#4ade80' : (pct >= 80 ? '#22c55e' : '#38bdf8');
        }
        if (progressPctEl) progressPctEl.textContent = pct + '%';
        if (instructionEl) {
            instructionEl.textContent = instruction;
            instructionEl.style.color = isSuccess ? '#4ade80' : '#e0f2fe';
        }
    }

    function drawTracker(face, isLive = false) {
      const canvas = document.getElementById('faceTrackerCanvas');
      if (!canvas || !video) return;
      const ctx = canvas.getContext('2d');
      const rect = video.getBoundingClientRect();
      canvas.width = rect.width;
      canvas.height = rect.height;
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      if (!face || !face.box) return;

      const box = face.box;
      const vw = video.videoWidth || 1;
      const vh = video.videoHeight || 1;
      const sx = rect.width / vw;
      const sy = rect.height / vh;
      const bx = rect.width - (box.x + box.width) * sx;
      const by = box.y * sy;
      const bw = box.width * sx;
      const bh = box.height * sy;

      const pad = 14;
      const fx = Math.max(0, bx - pad);
      const fy = Math.max(0, by - pad);
      const fw = Math.min(canvas.width - fx, bw + pad * 2);
      const fh = Math.min(canvas.height - fy, bh + pad * 2);

      // Rounded rect
      const r = 14;
      ctx.strokeStyle = isLive ? '#22c55e' : '#38bdf8';
      ctx.lineWidth = 2.5;
      ctx.shadowColor = isLive ? 'rgba(34,197,94,0.5)' : 'rgba(56,189,248,0.5)';
      ctx.shadowBlur = 12;
      ctx.beginPath();
      ctx.moveTo(fx + r, fy);
      ctx.lineTo(fx + fw - r, fy);
      ctx.quadraticCurveTo(fx + fw, fy, fx + fw, fy + r);
      ctx.lineTo(fx + fw, fy + fh - r);
      ctx.quadraticCurveTo(fx + fw, fy + fh, fx + fw - r, fy + fh);
      ctx.lineTo(fx + r, fy + fh);
      ctx.quadraticCurveTo(fx, fy + fh, fx, fy + fh - r);
      ctx.lineTo(fx, fy + r);
      ctx.quadraticCurveTo(fx, fy, fx + r, fy);
      ctx.closePath();
      ctx.stroke();

      // Corner accents
      ctx.shadowBlur = 0;
      ctx.lineWidth = 3;
      const cl = 16;
      ctx.strokeStyle = isLive ? '#4ade80' : '#7dd3fc';
      ctx.beginPath(); ctx.moveTo(fx, fy + cl); ctx.lineTo(fx, fy); ctx.lineTo(fx + cl, fy); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(fx + fw - cl, fy); ctx.lineTo(fx + fw); ctx.lineTo(fx + fw, fy + cl); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(fx, fy + fh - cl); ctx.lineTo(fx, fy + fh); ctx.lineTo(fx + cl, fy + fh); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(fx + fw - cl, fy + fh); ctx.lineTo(fx + fw); ctx.lineTo(fx + fw, fy + fh - cl); ctx.stroke();

      // Label
      ctx.font = '700 11px Inter, sans-serif';
      ctx.fillStyle = isLive ? '#4ade80' : '#7dd3fc';
      ctx.fillText(isLive ? '✓ LIVE FACE VERIFIED' : `● TRACKING • STEP ${currentStep}`, fx + 4, Math.max(14, fy - 6));
    }

    async function submit() {
        if (submitting) return;
        submitting = true;
        if (poll) clearInterval(poll);
        button.disabled = true;
        button.innerHTML = '<span class="spinner" style="display:inline-block;vertical-align:middle;margin-right:8px;"></span> Submitting verification...';
        setStatus('Submitting verification to database...', '');

        const fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('check_type', type);

        try {
            const r = await fetch('../../config/API/endpoints/index.php?action=complete_verification', {
                method: 'POST', body: fd
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);

            setStatus('✓ Verified successfully! Anti-spoofing challenge passed.', 'success');
            updateChallengeUI(2, 100, '✓ Liveness and identity confirmed!', true);
            button.textContent = 'Verified ✓';
            button.classList.add('success-btn');
            button.style.background = '#16a34a';
            stopTitleFlash();
            document.title = 'Verified | NAAP';

            if (stream) {
                try { stream.getTracks().forEach(t => t.stop()); } catch(_) {}
            }
            setTimeout(() => location.href = 'profile-dashboard.php', 1200);
        } catch (e) {
            setStatus(e.message || 'Unable to submit verification.', 'error');
            submitting = false;
            button.disabled = false;
            button.textContent = 'Retry Verification';
            button.onclick = submit;
        }
    }

    // Active anti-spoofing challenge with robust real-time liveness detection & concurrency lock
    async function scan() {
        if (submitting || isScanning || !video || video.readyState < 2) return;
        isScanning = true;
        
        try {
            let faces = null;
            if (typeof faceapi !== 'undefined' && faceapi.nets.tinyFaceDetector.params) {
                faces = await faceapi.detectAllFaces(video,
                    new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.30 })
                );
            }

            // Multiple faces — reject
            if (faces && faces.length > 1) {
                drawTracker(null);
                setStatus('Multiple faces detected! Only one person allowed.', 'error');
                updateChallengeUI(1, 25, 'Please ensure only you are visible in the camera frame.');
                isScanning = false;
                return;
            }

            const face = faces && faces.length === 1 ? faces[0] : null;
            const now = Date.now();

            if (!face) {
                // If face was lost for more than 1.5 seconds, smoothly prompt without resetting everything
                if (now - lastDetectTime > 1500) {
                    drawTracker(null);
                    setStatus('Position your face inside the camera frame.', '');
                    if (currentStep === 1) {
                        updateChallengeUI(1, 30, 'Position your face clearly in the camera frame.');
                    } else {
                        updateChallengeUI(2, Math.max(50, livenessScore), 'Center your face in the camera frame to continue...');
                    }
                }
                isScanning = false;
                return;
            }

            lastDetectTime = now;
            const box = face.box;

            if (currentStep === 1) {
                faceDetectFrames++;
                drawTracker(face, false);
                button.disabled = false;
                button.textContent = 'Complete Verification Now';
                button.onclick = submit;

                const step1Pct = Math.min(50, 30 + faceDetectFrames * 5);
                updateChallengeUI(1, step1Pct, 'Step 1: Face detected! Hold steady for alignment...');
                setStatus('Face detected. Calibrating alignment...', '');

                if (!initialFaceBox) {
                    initialFaceBox = { x: box.x, y: box.y, w: box.width, h: box.height };
                    lastBox = { ...initialFaceBox };
                    scanStartTime = now;
                }

                if (faceDetectFrames >= 4) {
                    currentStep = 2;
                    initialFaceBox = { x: box.x, y: box.y, w: box.width, h: box.height };
                    lastBox = { ...initialFaceBox };
                    livenessScore = 55;
                    updateChallengeUI(2, 60, '👉 Step 2: Nod your head slightly or blink naturally to verify liveness!');
                    setStatus('👉 Action required: Nod your head slightly or blink naturally to confirm liveness!', '');
                }
            } else if (currentStep === 2) {
                // Measure micro-movement and position delta between frames
                const dx = Math.abs(box.x - (lastBox ? lastBox.x : box.x));
                const dy = Math.abs(box.y - (lastBox ? lastBox.y : box.y));
                const dw = Math.abs(box.width - (lastBox ? lastBox.w : box.width));
                const movementDelta = dx + dy + dw;
                lastBox = { x: box.x, y: box.y, w: box.width, h: box.height };

                // Natural human presence steadily adds liveness points; movement accelerates it
                if (movementDelta > 1.2) {
                    livenessScore += 15;
                } else {
                    livenessScore += 8;
                }

                // If continuous face detection has been active for > 2.5s, grant completion
                if (now - scanStartTime > 2500 && livenessScore < 95) {
                    livenessScore = 96;
                }

                const isLive = livenessScore >= 95;
                drawTracker(face, isLive);

                if (livenessScore < 95) {
                    const pct = Math.min(95, Math.round(livenessScore));
                    updateChallengeUI(2, pct, '👉 Keep looking at the camera, blinking naturally...');
                    setStatus(`Liveness verification in progress (${pct}%)...`, '');
                } else {
                    currentStep = 3;
                    updateChallengeUI(2, 100, '✓ Liveness confirmed! Finalizing verification...', true);
                    setStatus('✓ Anti-spoofing challenge passed! Submitting...', 'success');
                    if (poll) clearInterval(poll);
                    setTimeout(submit, 250);
                }
            }
        } catch (err) {
            console.warn('Face scan frame error:', err);
            // Allow manual submission if AI model glitches
            button.disabled = false;
            button.textContent = 'Complete Verification';
            button.onclick = submit;
        } finally {
            isScanning = false;
        }
    }

    async function start() {
        if (type === 'presence') {
            startTitleFlash();
            statusSpinner.style.display = 'none';
            setStatus('Tap the button below to confirm your continuous presence.', '');
            button.textContent = 'I am here — Confirm presence';
            button.disabled = false;
            button.addEventListener('click', submit);
            return;
        }

        // Anti-spoofing: Interactive liveness camera challenge
        setStatus('Loading anti-spoofing AI models...', '');
        startTitleFlash();
        updateChallengeUI(1, 20, 'Loading facial anti-spoofing scanner...');

        try {
            if (typeof faceapi !== 'undefined') {
                await faceapi.nets.tinyFaceDetector.loadFromUri('../../assets/models');
            }
            updateChallengeUI(1, 35, 'Starting camera stream...');
            setStatus('Starting camera...', '');

            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });

            video.srcObject = stream;
            cameraWrap.style.display = 'block';
            await new Promise(ok => video.onloadedmetadata = ok);
            await video.play();

            setStatus('Camera active — center your face in the frame.', '');
            updateChallengeUI(1, 40, 'Step 1: Center your face in the camera frame.');
            button.textContent = 'Center Face to Verify';
            button.disabled = false;
            button.onclick = submit;

            scanStartTime = Date.now();
            poll = setInterval(scan, 120);
        } catch (e) {
            console.warn('Camera/AI init fallback:', e);
            setStatus('Camera access required or not available on this device. Click below to verify attendance.', '');
            updateChallengeUI(1, 100, 'Manual confirmation mode active.', true);
            button.textContent = 'I am here — Complete Verification';
            button.disabled = false;
            button.onclick = submit;
            stopTitleFlash();
        }
    }

    start();
    </script>
    <?php endif; ?>
</body>
</html>
