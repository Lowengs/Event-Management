<?php
session_start();
require_once '../../config/db.php';
if (empty($_SESSION['student_id'])) { header('Location: login.php'); exit; }
$eventId = (int)($_GET['eventId'] ?? 0);
$type = ($_GET['type'] ?? '') === 'antispoof' ? 'antispoof' : 'presence';
$event = $eventId ? $conn->query("SELECT EventId, EventName FROM event WHERE EventId = $eventId LIMIT 1")->fetch_assoc() : null;
if (!$event) { header('Location: profile-dashboard.php'); exit; }
$label = $type === 'antispoof' ? 'Anti-spoofing Verification' : 'Presence Check';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $label ?> | NAAP</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }
        .face-ring {
            width: 180px;
            height: 220px;
            border: 3px dashed rgba(125, 211, 252, 0.4);
            border-radius: 50%;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .face-ring.detected {
            border-color: #22c55e;
            border-style: solid;
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.3);
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
                <div class="face-ring" id="faceRing"></div>
            </div>
        </div>

        <div class="status-box" id="statusBox">
            <div class="spinner" id="statusSpinner"></div>
            <span id="statusText">Initializing...</span>
        </div>

        <button id="complete" class="btn" disabled>
            <?= $type === 'antispoof' ? 'Detecting face...' : 'Complete verification' ?>
        </button>

        <a href="profile-dashboard.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
            </svg>
            Back to Dashboard
        </a>
    </main>

    <script src="../../assets/js/lib/face-api.min.js"></script>
    <script>
    const type      = <?= json_encode($type) ?>;
    const eventId   = <?= $eventId ?>;
    const button    = document.getElementById('complete');
    const video     = document.getElementById('camera');
    const statusBox = document.getElementById('statusBox');
    const statusText= document.getElementById('statusText');
    const statusSpinner = document.getElementById('statusSpinner');
    const faceRing  = document.getElementById('faceRing');
    const cameraWrap= document.getElementById('cameraWrap');

    let stream, poll, submitting = false;

    function setStatus(text, state) {
        statusText.textContent = text;
        statusBox.className = 'status-box' + (state ? ' ' + state : '');
        statusSpinner.style.display = (state === 'success' || state === 'error') ? 'none' : 'block';
    }

    async function submit() {
        if (submitting) return;
        submitting = true;
        if (poll) clearInterval(poll);
        button.disabled = true;
        setStatus('Submitting verification...', '');

        const fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('check_type', type);

        try {
            const r = await fetch('../../config/API/endpoints/index.php?action=complete_verification', {
                method: 'POST', body: fd
            });
            const d = await r.json();
            if (!d.success) throw new Error(d.message);

            setStatus('✓ Verified successfully!', 'success');
            button.textContent = 'Verified ✓';
            button.classList.add('success-btn');
            faceRing.classList.add('detected');

            if (stream) stream.getTracks().forEach(t => t.stop());
            setTimeout(() => location.href = 'profile-dashboard.php', 1200);
        } catch (e) {
            setStatus(e.message || 'Unable to submit verification.', 'error');
            submitting = false;
            button.disabled = false;
            button.textContent = 'Retry verification';
        }
    }

    // For anti-spoofing: just detect a face, no challenge needed
    async function scan() {
        if (submitting || video.readyState < 2) return;
        try {
            const face = await faceapi.detectSingleFace(video,
                new faceapi.TinyFaceDetectorOptions({ inputSize: 256, scoreThreshold: 0.5 })
            );
            if (face) {
                faceRing.classList.add('detected');
                setStatus('Face detected — verifying...', '');
                clearInterval(poll);
                // Small delay to show the "detected" state visually
                setTimeout(submit, 600);
            } else {
                faceRing.classList.remove('detected');
                setStatus('Position your face inside the circle.', '');
            }
        } catch (_) {}
    }

    async function start() {
        // Simple presence check: just tap a button
        if (type === 'presence') {
            statusSpinner.style.display = 'none';
            setStatus('Tap the button below to confirm your presence.', '');
            button.textContent = 'I am here — Confirm presence';
            button.disabled = false;
            button.addEventListener('click', submit);
            return;
        }

        // Anti-spoofing: open camera, detect face, auto-submit
        setStatus('Loading face detection models...', '');

        try {
            await faceapi.nets.tinyFaceDetector.loadFromUri('../../assets/models');
            setStatus('Starting camera...', '');

            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                audio: false
            });

            video.srcObject = stream;
            cameraWrap.style.display = 'block';
            await new Promise(ok => video.onloadedmetadata = ok);
            await video.play();

            setStatus('Camera active — position your face in the circle.', '');
            button.textContent = 'Detecting face...';

            // Start scanning every 200ms
            poll = setInterval(scan, 200);
        } catch (e) {
            setStatus('Camera access is required. Please allow camera permission and reload.', 'error');
            button.textContent = 'Camera required';
            button.disabled = true;
        }
    }

    start();
    </script>
</body>
</html>
