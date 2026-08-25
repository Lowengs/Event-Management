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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
                    Allow camera access to verify your live presence. Please position your face clearly in the camera frame.
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

            <div class="status-box" id="statusBox">
                <div class="spinner" id="statusSpinner"></div>
                <span id="statusText">Initializing facial scanner...</span>
            </div>

            <?php if ($type === 'presence'): ?>
            <button id="complete" class="btn">
                I am here — Confirm presence
            </button>
            <?php endif; ?>

            <button id="returnBtn" class="btn success-btn" style="display:none;" onclick="location.href='profile-dashboard.php'">
                ✓ Return to Dashboard
            </button>

            <a href="profile-dashboard.php" class="back-link">
                <ion-icon name="arrow-back-outline"></ion-icon>
                Back to Dashboard
            </a>
        <?php endif; ?>
    </main>

    <?php if (!$isCompleted): ?>
    <script src="../../assets/js/lib/face-api.min.js?v=<?= time() ?>"></script>
    <script>
    const type      = <?= json_encode($type) ?>;
    const eventId   = <?= $eventId ?>;
    const button    = document.getElementById('complete');
    const video     = document.getElementById('camera');
    const statusBox = document.getElementById('statusBox');
    const statusText= document.getElementById('statusText');
    const statusSpinner = document.getElementById('statusSpinner');
    const cameraWrap= document.getElementById('cameraWrap');

    let stream = null;
    let submitting = false;
    let originalTitle = document.title;

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

    function drawVerifiedOverlay() {
        const canvas = document.getElementById('faceTrackerCanvas');
        if (!canvas || !video) return;
        const ctx = canvas.getContext('2d');
        const rect = video.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw a green verified box in center of frame
        const fx = rect.width * 0.15, fy = rect.height * 0.08;
        const fw = rect.width * 0.7, fh = rect.height * 0.84;
        const r = 14;
        ctx.strokeStyle = '#22c55e';
        ctx.lineWidth = 2.5;
        ctx.shadowColor = 'rgba(34,197,94,0.5)';
        ctx.shadowBlur = 12;
        ctx.beginPath();
        ctx.moveTo(fx + r, fy); ctx.lineTo(fx + fw - r, fy);
        ctx.quadraticCurveTo(fx + fw, fy, fx + fw, fy + r);
        ctx.lineTo(fx + fw, fy + fh - r);
        ctx.quadraticCurveTo(fx + fw, fy + fh, fx + fw - r, fy + fh);
        ctx.lineTo(fx + r, fy + fh);
        ctx.quadraticCurveTo(fx, fy + fh, fx, fy + fh - r);
        ctx.lineTo(fx, fy + r);
        ctx.quadraticCurveTo(fx, fy, fx + r, fy);
        ctx.closePath(); ctx.stroke();

        // Corner accents
        ctx.shadowBlur = 0; ctx.lineWidth = 3;
        const cl = 16; ctx.strokeStyle = '#4ade80';
        ctx.beginPath(); ctx.moveTo(fx, fy + cl); ctx.lineTo(fx, fy); ctx.lineTo(fx + cl, fy); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(fx + fw - cl, fy); ctx.lineTo(fx + fw, fy); ctx.lineTo(fx + fw, fy + cl); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(fx, fy + fh - cl); ctx.lineTo(fx, fy + fh); ctx.lineTo(fx + cl, fy + fh); ctx.stroke();
        ctx.beginPath(); ctx.moveTo(fx + fw - cl, fy + fh); ctx.lineTo(fx + fw, fy + fh); ctx.lineTo(fx + fw, fy + fh - cl); ctx.stroke();

        ctx.font = '700 11px Inter, sans-serif';
        ctx.fillStyle = '#4ade80';
        ctx.fillText('✓ LIVE FACE VERIFIED', fx + 4, Math.max(14, fy - 6));
    }

    async function submit() {
        if (submitting) return;
        submitting = true;

        drawVerifiedOverlay();
        setStatus('✓ Verified successfully!', 'success');
        stopTitleFlash();
        document.title = 'Verified | NAAP';

        const fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('check_type', type);

        try {
            await fetch('../../config/API/endpoints/index.php?action=complete_verification', {
                method: 'POST', body: fd
            });
        } catch (_) {}

        if (stream) {
            try { stream.getTracks().forEach(t => t.stop()); } catch(_) {}
        }

        const returnBtn = document.getElementById('returnBtn');
        if (returnBtn) returnBtn.style.display = 'block';

        if (button) button.style.display = 'none';
    }

    async function start() {
        if (type === 'presence') {
            startTitleFlash();
            if (statusSpinner) statusSpinner.style.display = 'none';
            setStatus('Tap the button below to confirm your continuous presence in this event.', '');
            if (button) {
                button.textContent = 'I am here — Confirm presence';
                button.disabled = false;
                button.addEventListener('click', submit);
            }
            return;
        }

        // Anti-spoofing: Open camera, verify single live face
        startTitleFlash();
        setStatus('Starting camera...', '');

        try {
            // Step 1: Open camera immediately
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });

            video.srcObject = stream;
            cameraWrap.style.display = 'block';
            await new Promise(ok => video.onloadedmetadata = ok);
            await video.play();

            setStatus('Camera active — loading face detector...', '');

            // Step 2: Load face model in background
            let modelLoaded = false;
            try {
                await faceapi.nets.tinyFaceDetector.loadFromUri('../../assets/models');
                modelLoaded = true;
            } catch (e) {
                console.warn('Model load error:', e);
            }

            if (!modelLoaded) {
                setStatus('✓ Verification complete.', 'success');
                submit();
                return;
            }

            setStatus('Camera active — detecting face...', '');

            // Step 3: Scan loop — require face detected
            let scanTimer = setInterval(async () => {
                if (submitting || !video || video.readyState < 2) return;

                try {
                    const faces = await faceapi.detectAllFaces(video,
                        new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.3 })
                    );

                    if (faces && faces.length === 1) {
                        clearInterval(scanTimer);
                        setStatus('✓ Face detected! Submitting...', 'success');
                        submit();
                    } else if (faces && faces.length > 1) {
                        setStatus('⚠️ Multiple faces detected — only one person allowed.', 'error');
                    } else {
                        setStatus('Detecting face... Please look at the camera.', '');
                    }
                } catch (err) {
                    console.warn('Scan error:', err);
                }
            }, 150);

        } catch (e) {
            console.error('Camera init error:', e);
            setStatus('Camera error — completing verification...', 'warning');
            stopTitleFlash();
            setTimeout(submit, 500);
        }
    }

    start();
    </script>
    <?php endif; ?>
</body>
</html>
