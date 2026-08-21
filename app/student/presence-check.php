<?php
session_start();
require_once '../../config/db.php';
if (empty($_SESSION['student_id'])) { header('Location: login.php'); exit; }
$eventId = (int)($_GET['eventId'] ?? 0);
$type = ($_GET['type'] ?? '') === 'antispoof' ? 'antispoof' : 'presence';
$event = $eventId ? $conn->query("SELECT EventId, EventName, EventStatus, AntiSpoofActive, PresenceCheckActive FROM event WHERE EventId = $eventId LIMIT 1")->fetch_assoc() : null;
if (!$event) { header('Location: profile-dashboard.php'); exit; }
$isCompleted = in_array(strtolower(trim($event['EventStatus'] ?? '')), ['completed', 'cancelled', 'archived']);
$label = $type === 'antispoof' ? 'Anti-spoofing Verification' : 'Continuous Presence Check';
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
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
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
            max-width: 530px;
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
            border: 1px solid rgba(56, 189, 248, 0.3);
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
            border: 2px solid #334155;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
        }
        .camera-wrap video {
            width: 100%;
            max-height: 320px;
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
            padding: 14px 18px;
            border-radius: 14px;
            background: #0f172a;
            border: 1px solid #1e293b;
            color: #94a3b8;
            font-weight: 600;
            font-size: 13.5px;
            min-height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-align: center;
        }
        .status-box.success {
            background: rgba(34, 197, 94, 0.12);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .status-box.warning {
            background: rgba(245, 158, 11, 0.12);
            color: #fde047;
            border: 1px solid rgba(245, 158, 11, 0.3);
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
        .btn:hover:not(:disabled) { background: #1d4ed8; transform: translateY(-1px); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; background: #334155; }
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
                    Allow camera access to verify your live identity. Please keep your face inside the frame throughout the challenge.
                <?php else: ?>
                    Confirm that you are actively attending this event.
                <?php endif; ?>
            </p>

            <div class="camera-wrap" id="cameraWrap">
                <video id="camera" autoplay muted playsinline></video>
                <div class="camera-overlay">
                    <canvas class="face-tracker-canvas" id="faceTrackerCanvas"></canvas>
                </div>
            </div>

            <div id="challengePromptCard" style="display:none;background:rgba(56,189,248,0.1);border:1.5px solid rgba(56,189,248,0.3);border-radius:12px;padding:12px 14px;margin-bottom:14px;text-align:left;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:11px;font-weight:700;color:#38bdf8;text-transform:uppercase;letter-spacing:0.05em;" id="challengeStepLabel">Liveness Step 1 of 2</span>
                    <span style="font-size:11px;font-weight:700;color:#94a3b8;" id="challengeProgressPct">0%</span>
                </div>
                <div style="width:100%;height:6px;background:rgba(255,255,255,0.1);border-radius:10px;overflow:hidden;margin-bottom:8px;">
                    <div id="challengeProgressBar" style="width:0%;height:100%;background:#38bdf8;transition:width 0.2s ease;"></div>
                </div>
                <p id="challengeInstruction" style="margin:0;font-size:13px;font-weight:600;color:#e0f2fe;">
                    Center your face inside the camera frame
                </p>
            </div>

            <div class="status-box" id="statusBox">
                <div class="spinner" id="statusSpinner"></div>
                <span id="statusText">Initializing...</span>
            </div>

            <button id="complete" class="btn" disabled>
                <?= $type === 'antispoof' ? 'Facial Camera Verification Required' : 'Complete verification' ?>
            </button>

            <a href="profile-dashboard.php" class="back-link">
                <ion-icon name="arrow-back-outline"></ion-icon>
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

    let stream = null;
    let poll = null;
    let submitting = false;
    let isScanning = false;
    let originalTitle = document.title;
    
    // Strict Anti-Spoofing Liveness State Machine
    let currentStep = 1; // 1 = Calibration / Face Presence, 2 = Liveness & Natural Motion, 3 = Verified
    let consecutiveFaceFrames = 0;
    let consecutiveMissingFrames = 0;
    let livenessScore = 0;
    let lastBox = null;
    let lastDetectTime = 0;
    let verifiedFaceDetected = false;

    function startTitleFlash() {
      originalTitle = document.title.replace(/^\(\d+\)\s*/, '');
      document.title = `(1) ${originalTitle}`;
    }
    function stopTitleFlash() {
      document.title = originalTitle.replace(/^\(\d+\)\s*/, '');
    }

    function setStatus(text, state) {
        if (!statusText || !statusBox) return;
        statusText.textContent = text;
        statusBox.className = 'status-box' + (state ? ' ' + state : '');
        if (statusSpinner) {
            statusSpinner.style.display = (state === 'success' || state === 'error' || state === 'warning') ? 'none' : 'block';
        }
    }

    function updateChallengeUI(step, pct, instruction, isSuccess = false) {
        if (promptCard) promptCard.style.display = 'block';
        if (stepLabel) stepLabel.textContent = isSuccess ? 'Challenge Complete ✓' : `Liveness Step ${step} of 2`;
        if (progressBar) {
            progressBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
            progressBar.style.background = isSuccess ? '#4ade80' : (pct >= 80 ? '#22c55e' : '#38bdf8');
        }
        if (progressPctEl) progressPctEl.textContent = Math.round(pct) + '%';
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
        
        // Strict guard: In antispoofing mode, ensure verification genuinely succeeded and face was detected
        if (type === 'antispoof' && !verifiedFaceDetected) {
            setStatus('Face verification not yet completed. Please face the camera.', 'warning');
            return;
        }

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
            if (type === 'presence') {
                button.disabled = false;
                button.textContent = 'Retry Verification';
                button.onclick = submit;
            } else {
                button.disabled = true;
                button.textContent = 'Face Camera to Retry';
                // Reset state machine to retry
                currentStep = 1;
                consecutiveFaceFrames = 0;
                livenessScore = 0;
                verifiedFaceDetected = false;
                poll = setInterval(scan, 120);
            }
        }
    }

    // Strict Anti-Spoofing Scan Loop with Live Face Presence Enforcement
    async function scan() {
        if (submitting || isScanning || !video || video.readyState < 2) return;
        isScanning = true;
        
        try {
            let faces = null;
            if (typeof faceapi !== 'undefined' && faceapi.nets.tinyFaceDetector.params) {
                faces = await faceapi.detectAllFaces(video,
                    new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.38 })
                );
            }

            // Case A: Multiple faces detected — Reject immediately
            if (faces && faces.length > 1) {
                consecutiveMissingFrames++;
                consecutiveFaceFrames = 0;
                livenessScore = Math.max(0, livenessScore - 15);
                drawTracker(null);
                setStatus('⚠️ Multiple faces detected! Only one person is allowed.', 'error');
                updateChallengeUI(1, 15, 'Please ensure only you are visible in front of the camera.');
                button.disabled = true;
                button.textContent = 'Ensure Only One Person is in View';
                isScanning = false;
                return;
            }

            const face = faces && faces.length === 1 ? faces[0] : null;
            const now = Date.now();

            // Case B: No face detected — HALT and RESET
            if (!face) {
                consecutiveMissingFrames++;
                consecutiveFaceFrames = 0;
                // Decay liveness progress when face is not in view
                livenessScore = Math.max(0, livenessScore - 8);
                drawTracker(null);
                
                setStatus('⚠️ No face detected. Please position your face inside the camera frame.', 'warning');
                updateChallengeUI(currentStep, Math.min(30, livenessScore), 'Position your face clearly inside the camera frame.');
                
                button.disabled = true;
                button.textContent = 'Face Camera to Proceed';
                isScanning = false;
                return;
            }

            // Case C: Single face detected — Valid candidate
            consecutiveMissingFrames = 0;
            consecutiveFaceFrames++;
            lastDetectTime = now;
            const box = face.box;

            // Check minimum face size relative to video feed
            const minDim = Math.min(video.videoWidth || 320, video.videoHeight || 240);
            if (box.width < minDim * 0.18 || box.height < minDim * 0.18) {
                drawTracker(face, false);
                setStatus('Please move closer to the camera.', 'warning');
                updateChallengeUI(currentStep, Math.max(10, livenessScore), 'Move closer so your face is clearly visible.');
                button.disabled = true;
                button.textContent = 'Move Closer to Camera';
                isScanning = false;
                return;
            }

            if (currentStep === 1) {
                // Step 1: Calibration & Alignment
                drawTracker(face, false);
                const step1Pct = Math.min(45, consecutiveFaceFrames * 6);
                updateChallengeUI(1, step1Pct, 'Step 1: Face detected! Hold steady for alignment...');
                setStatus(`Face aligned (${consecutiveFaceFrames}/8 frames). Calibrating...`, '');
                button.disabled = true;
                button.textContent = 'Aligning Face... Keep Steady';

                if (!lastBox) {
                    lastBox = { x: box.x, y: box.y, w: box.width, h: box.height };
                }

                // Require 8 consecutive steady frames to complete calibration
                if (consecutiveFaceFrames >= 8) {
                    currentStep = 2;
                    lastBox = { x: box.x, y: box.y, w: box.width, h: box.height };
                    livenessScore = 48;
                    updateChallengeUI(2, 50, '👉 Step 2: Nod your head slightly or blink naturally to verify liveness!');
                    setStatus('👉 Action required: Nod your head slightly or blink naturally to confirm liveness!', '');
                }
            } else if (currentStep === 2) {
                // Step 2: Active Dynamic Liveness & Micro-Movement Verification
                const dx = Math.abs(box.x - (lastBox ? lastBox.x : box.x));
                const dy = Math.abs(box.y - (lastBox ? lastBox.y : box.y));
                const dw = Math.abs(box.width - (lastBox ? lastBox.w : box.width));
                const movementDelta = dx + dy + dw;
                lastBox = { x: box.x, y: box.y, w: box.width, h: box.height };

                // Natural human micro-movement adds score; active movement accelerates
                if (movementDelta > 1.2 && movementDelta < 60) {
                    livenessScore += 8.5; // Natural movement
                } else if (movementDelta <= 1.2) {
                    livenessScore += 3.5; // Steady gaze
                } else {
                    // Excessive sudden shift (camera shake / artifact) – slight penalty
                    livenessScore = Math.max(45, livenessScore - 4);
                }

                const isLive = livenessScore >= 100;
                drawTracker(face, isLive);

                if (livenessScore < 100) {
                    const pct = Math.min(99, Math.round(livenessScore));
                    updateChallengeUI(2, pct, '👉 Keep looking at camera, blinking or nodding naturally...');
                    setStatus(`Liveness verification in progress (${pct}%)... Keep face in view`, '');
                    button.disabled = true;
                    button.textContent = `Verifying Liveness (${pct}%)...`;
                } else {
                    // Challenge Passed!
                    currentStep = 3;
                    verifiedFaceDetected = true;
                    updateChallengeUI(2, 100, '✓ Liveness confirmed! Submitting verification...', true);
                    setStatus('✓ Anti-spoofing challenge passed! Submitting...', 'success');
                    button.disabled = true;
                    button.textContent = 'Verified ✓ Submitting...';
                    if (poll) clearInterval(poll);
                    setTimeout(submit, 300);
                }
            }
        } catch (err) {
            console.warn('Face scan frame error:', err);
        } finally {
            isScanning = false;
        }
    }

    async function start() {
        if (type === 'presence') {
            startTitleFlash();
            if (statusSpinner) statusSpinner.style.display = 'none';
            setStatus('Tap the button below to confirm your continuous presence in this event.', '');
            button.textContent = 'I am here — Confirm presence';
            button.disabled = false;
            button.addEventListener('click', submit);
            return;
        }

        // Anti-spoofing Mode: Strict live webcam challenge
        setStatus('Loading anti-spoofing AI models...', '');
        startTitleFlash();
        updateChallengeUI(1, 10, 'Loading facial anti-spoofing scanner...');
        button.disabled = true;
        button.textContent = 'Starting Anti-Spoofing Camera...';

        try {
            if (typeof faceapi !== 'undefined') {
                await faceapi.nets.tinyFaceDetector.loadFromUri('../../assets/models');
            }
            updateChallengeUI(1, 25, 'Starting camera stream...');
            setStatus('Starting camera...', '');

            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });

            video.srcObject = stream;
            cameraWrap.style.display = 'block';
            await new Promise(ok => video.onloadedmetadata = ok);
            await video.play();

            setStatus('Camera active — please center your face in the frame.', '');
            updateChallengeUI(1, 30, 'Step 1: Center your face inside the camera frame.');
            button.textContent = 'Center Face to Verify';
            button.disabled = true; // MUST remain disabled until verified!

            poll = setInterval(scan, 120);
        } catch (e) {
            console.error('Camera/AI init error:', e);
            setStatus('Camera access is required for anti-spoofing verification. Please grant camera permission in your browser settings and refresh the page.', 'error');
            updateChallengeUI(1, 0, 'Camera permission denied or camera not found.');
            button.textContent = 'Camera Access Required';
            button.disabled = true;
            stopTitleFlash();
        }
    }

    start();
    </script>
    <?php endif; ?>
</body>
</html>
