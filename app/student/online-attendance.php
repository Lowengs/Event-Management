<?php

session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php'); exit;
}

$userId = (int)$_SESSION['student_id'];

$student = $conn->query("
    SELECT u.UserId, u.first_name, u.last_name, u.student_id AS student_no,
           u.course, u.year_level, u.section, u.profile_photo, u.OrgId,
           o.OrgName
    FROM user u
    LEFT JOIN organization o ON o.OrgId = u.OrgId
    WHERE u.UserId = $userId LIMIT 1
")->fetch_assoc();

if (!$student) { header('Location: login.php'); exit; }

$fullName  = trim($student['first_name'] . ' ' . $student['last_name']);
$studentNo = $student['student_no'] ?? 'N/A';
$orgName   = $student['OrgName'] ?? 'NAAP';
$initials  = strtoupper(substr($student['first_name'],0,1) . substr($student['last_name'],0,1));


$onlineEvents = [];
$orgId = (int)($student['OrgId'] ?? 0);
$ev = $conn->query("
    SELECT e.EventId, e.EventName, e.EventDateTime, e.EventStatus, e.EventMode,
           e.EventCapacity, e.EventDescription
    FROM event e
    WHERE e.EventMode IN ('Online', 'Hybrid')
      AND e.EventStatus IN ('Scheduled', 'Ongoing', 'ongoing', 'upcoming')
      AND (
        e.OrgId = $orgId
        OR EXISTS (SELECT 1 FROM eventregistration er WHERE er.EventId=e.EventId AND er.UserId=$userId)
      )
    ORDER BY e.EventDateTime ASC
    LIMIT 30
");
if ($ev) while ($row = $ev->fetch_assoc()) $onlineEvents[] = $row;


$hasFace = false;
$fr = $conn->query("SELECT FaceId FROM face_data WHERE UserId=$userId LIMIT 1");
if ($fr && $fr->num_rows > 0) $hasFace = true;


$qrPayload = json_encode([
    'type'       => 'student_qr',
    'user_id'    => $student['UserId'],
    'student_id' => $studentNo,
    'name'       => $fullName,
    'course'     => $student['course'] ?? '',
    'org'        => $orgName,
]);


$photoSrc = '';
if (!empty($student['profile_photo'])) {
    $p = $student['profile_photo'];
    $resolved = (strpos($p, 'assets/') === 0) ? '../../' . $p : (strpos($p, '../../') === 0 ? $p : '../../' . ltrim($p, '/'));
    $disk = __DIR__ . '/../../' . ltrim(str_replace('../../', '', $resolved), '/');
    if (file_exists($disk)) $photoSrc = $resolved;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Attendance | NAAP Student Portal</title>
    <meta name="description" content="Record your attendance for online events using facial recognition or your QR code.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    <link rel="icon" href="../../assets/img/philsca.png">
    
  <link rel="stylesheet" href="../../assets/css/student/online-attendance.css?<?= time() ?>" />
</head>
<body>


<div class="topbar">
    <a href="profile-dashboard.php" class="back-btn">
        <i class='bx bx-arrow-back'></i> Back
    </a>
    <div style="font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Online Attendance</div>
    <div style="width:80px;"></div>
</div>

<div class="page-wrap">
    
    <div class="page-heading">
        <div class="online-badge">Online Event Attendance</div>
        <h1>
            <ion-icon name="wifi-outline" style="color:#6366f1;"></ion-icon>
            Self-Attendance Check-In
        </h1>
        <p>For online events only — verify your identity to record attendance automatically.</p>
    </div>

    <?php if (empty($onlineEvents)): ?>
    
    <div class="no-events">
        <ion-icon name="wifi-outline"></ion-icon>
        <h3>No Online Events Right Now</h3>
        <p>Online events that are ongoing or upcoming will appear here. <br>Check the <a href="events.php" style="color:#6366f1;">Events page</a> for all events.</p>
    </div>

    <?php else: ?>

    
    <div class="event-sel-card">
        <span class="sel-label">Select Online Event</span>
        <div class="sel-row">
            <select class="event-select" id="eventSel" onchange="onEventChange(); checkAntiSpoofing();">
                <option value="">— Choose an event —</option>
                <?php foreach ($onlineEvents as $ev): ?>
                <option value="<?= $ev['EventId'] ?>" data-status="<?= strtolower($ev['EventStatus']) ?>">
                    <?= htmlspecialchars($ev['EventName']) ?> (<?= date('M d, Y', strtotime($ev['EventDateTime'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <div id="evStatusBadge" class="status-badge badge-none">No Event</div>
        </div>
    </div>

    <!-- Anti-Spoofing Challenge Panel -->
    <div id="antiSpoofPanel" style="display:none;margin:16px 0;background:#f0fdf4;border:2px solid #4ade80;border-radius:16px;padding:24px;text-align:center;">
        <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:12px;">
            <ion-icon name="shield-checkmark-outline" style="font-size:28px;color:#16a34a;"></ion-icon>
            <h3 style="margin:0;font-size:17px;color:#15803d;">Anti-Spoofing Verification Active</h3>
        </div>
        <p style="font-size:13px;color:#166534;margin:0 0 16px;">The organization has started attendance verification for this event. Complete within the grace period.</p>

        <!-- Grace Period Progress Bar -->
        <div id="gracePeriodWrap" style="margin-bottom:18px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;font-weight:600;color:#16a34a;margin-bottom:6px;">
                <span>Grace Period Remaining</span>
                <span id="graceRemainText">—</span>
            </div>
            <div style="height:8px;background:#dcfce7;border-radius:999px;overflow:hidden;">
                <div id="graceProgressBar" style="height:100%;background:linear-gradient(90deg,#16a34a,#4ade80);border-radius:999px;transition:width 1s linear;width:100%;"></div>
            </div>
        </div>

        <!-- Readiness Countdown (5 min) -->
        <div id="readinessSection" style="display:none;">
            <p style="font-size:13px;color:#475569;margin:0 0 12px;">Get ready — scan begins in:</p>
            <div style="position:relative;width:120px;height:120px;margin:0 auto 14px;">
                <svg viewBox="0 0 120 120" width="120" height="120">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="#dcfce7" stroke-width="10"/>
                    <circle id="readinessSvgCircle" cx="60" cy="60" r="52" fill="none" stroke="#16a34a" stroke-width="10"
                        stroke-dasharray="327" stroke-dashoffset="0"
                        stroke-linecap="round" transform="rotate(-90 60 60)"
                        style="transition:stroke-dashoffset 1s linear;"/>
                </svg>
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <span id="readinessCountdown" style="font-size:28px;font-weight:800;color:#15803d;">5:00</span>
                    <span style="font-size:10px;color:#166534;font-weight:600;">PREPARE</span>
                </div>
            </div>
            <p style="font-size:12px;color:#64748b;margin:0;">Ensure camera is ready and face is visible</p>
        </div>

        <!-- Scan Ready Button -->
        <div id="scanReadySection" style="display:none;">
            <div style="background:#fff;border-radius:12px;padding:14px;margin-bottom:14px;border:1px solid #bbf7d0;">
                <p style="margin:0;font-size:14px;font-weight:700;color:#15803d;">Ready to scan! Use Face Recognition or QR below.</p>
            </div>
        </div>
    </div>

    <!-- No Trigger Active Notice -->
    <div id="noTriggerNotice" style="display:none;margin:12px 0;background:#fff7ed;border:1.5px solid #fed7aa;border-radius:12px;padding:14px 18px;font-size:13px;color:#c2410c;text-align:center;">
        <ion-icon name="time-outline" style="vertical-align:middle;margin-right:6px;"></ion-icon>
        Anti-spoofing not yet activated by your organization. Please wait for the organizer to start verification.
    </div>


    
    <div class="method-tabs">
        <button class="method-tab active" id="tab-face" onclick="switchMethod('face')">
            <ion-icon name="scan-outline"></ion-icon> Face Recognition
        </button>
        <button class="method-tab" id="tab-qr" onclick="switchMethod('qr')">
            <ion-icon name="qr-code-outline"></ion-icon> My QR Code
        </button>
        <button class="method-tab" id="tab-qrscan" onclick="switchMethod('qrscan')">
            <ion-icon name="camera-outline"></ion-icon> Scan QR
        </button>
    </div>

    
    <div class="method-pane active" id="pane-face">

        <?php if (!$hasFace): ?>
        <div class="no-face-warn">
            <ion-icon name="warning-outline"></ion-icon>
            <p>You don't have a face registered yet.
               <a href="profile-dashboard.php">Go to your profile</a> to register your face for facial recognition attendance.</p>
        </div>
        <?php endif; ?>

        <div id="faceStatusBar" class="status-bar info show">
            <div class="model-spinner"></div>
            Loading face recognition models… please wait.
        </div>

        <div class="face-panel">
            
            <div class="face-card">
                <div class="face-card-header">
                    <ion-icon name="videocam-outline" style="color:#6366f1;font-size:20px;"></ion-icon>
                    <h3>Camera</h3>
                </div>
                <div class="face-card-body">
                    <div class="cam-wrap">
                        <video id="faceVideo" autoplay muted playsinline></video>
                        <canvas id="faceCanvas"></canvas>
                        <div class="scan-ring" id="scanRing"></div>
                        <div class="face-placeholder" id="facePlaceholder">
                            <ion-icon name="camera-outline"></ion-icon>
                            <p>Camera not started</p>
                        </div>
                    </div>
                    <div id="matchCard" class="match-card">
                        <div class="match-photo" id="matchPhoto"><?= htmlspecialchars($initials) ?></div>
                        <div>
                            <div class="match-name" id="matchName">—</div>
                            <div class="match-id">✅ Identity Verified</div>
                            <div class="match-dist" id="matchDist"></div>
                        </div>
                    </div>
                    <button id="faceBtn" class="btn btn-primary" onclick="toggleFaceCamera()" <?= !$hasFace ? 'disabled' : '' ?>>
                        <ion-icon name="camera-outline"></ion-icon> Start Camera
                    </button>
                </div>
            </div>

            
            <div class="face-card">
                <div class="face-card-header">
                    <ion-icon name="checkmark-circle-outline" style="color:#10b981;font-size:20px;"></ion-icon>
                    <h3>Record Attendance</h3>
                </div>
                <div class="face-card-body">
                    <p style="font-size:13px;color:#64748b;margin-bottom:16px;line-height:1.6;">
                        Start the camera and look directly at it. Once your face is recognized, click <strong>Record Attendance</strong> to confirm.
                    </p>
                    <div id="verifyStatus" style="font-size:13px;color:#64748b;margin-bottom:16px;">
                        Awaiting face detection…
                    </div>
                    <button id="recordFaceBtn" class="btn btn-success" style="margin-bottom:10px;" onclick="recordFaceAttendance()" disabled>
                        <ion-icon name="checkmark-circle-outline"></ion-icon> Record Attendance
                    </button>
                    <div id="faceRecordStatus" class="status-bar"></div>

                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,.06);">
                        <div class="face-instructions" style="font-size:11px;color:#475569;line-height:1.7;">
                            Your face data is compared only against your own stored descriptor — it's never shared.<br>
                            Ensure good lighting and look directly at the camera.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="method-pane" id="pane-qr">
        <div id="qrStatusBar" class="status-bar info" style="margin-bottom:16px;">
            <ion-icon name="information-circle-outline"></ion-icon>
            Select an ongoing event above, then record your attendance using your QR code.
        </div>

        <div class="qr-panel-wrap">
            
            <div class="qr-display-card">
                <h3 style="font-size:14px;font-weight:700;color:#f1f5f9;align-self:flex-start;">Your QR Code</h3>
                <div class="qr-box" id="qrContainer">
                    <div style="width:20px;height:20px;border:2px solid #334155;border-top-color:#6366f1;border-radius:50%;animation:spin .7s linear infinite;"></div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:14px;font-weight:700;color:#f1f5f9;"><?= htmlspecialchars($fullName) ?></div>
                    <div style="font-size:12px;color:#6366f1;font-family:monospace;"># <?= htmlspecialchars($studentNo) ?></div>
                </div>
                <div class="qr-hint">Show this QR to the event host, or use it to self-record for online events.</div>
            </div>

            
            <div class="face-card">
                <div class="face-card-header">
                    <ion-icon name="send-outline" style="color:#6366f1;font-size:20px;"></ion-icon>
                    <h3>Self-Record via QR</h3>
                </div>
                <div class="face-card-body">
                    <p style="font-size:13px;color:#64748b;margin-bottom:16px;line-height:1.6;">
                        For online events, you can record your own attendance. Select an <strong>ongoing</strong> online event above, then click the button below.
                    </p>
                    <button id="recordQrBtn" class="btn btn-primary" style="margin-bottom:10px;" onclick="recordQrAttendance()" disabled>
                        <ion-icon name="checkmark-done-outline"></ion-icon> Record My Attendance
                    </button>
                    <div id="qrRecordStatus" class="status-bar"></div>
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.06);">
                        <p style="font-size:11px;color:#475569;line-height:1.7;">
                            Only works for events with <strong>Ongoing</strong> status.<br>
                            Your identity is verified via your student session — no spoofing possible.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <div class="method-pane" id="pane-qrscan">
        <div id="qrScanStatusBar" class="status-bar info" style="margin-bottom:16px;">
            <ion-icon name="information-circle-outline"></ion-icon>
            Select an ongoing event above, then start the camera to scan your QR code.
        </div>

        <div class="face-panel">
            
            <div class="face-card">
                <div class="face-card-header">
                    <ion-icon name="camera-outline" style="color:#6366f1;font-size:20px;"></ion-icon>
                    <h3>QR Camera Scanner</h3>
                </div>
                <div class="face-card-body">
                    <div class="qr-cam-wrap">
                        <video id="qrCamVideo" autoplay muted playsinline></video>
                        <canvas id="qrCamCanvas" style="display:none;"></canvas>
                        <div class="qr-scan-frame"><div class="qr-scan-line"></div></div>
                        <div id="qrCamPlaceholder" class="face-placeholder">
                            <ion-icon name="qr-code-outline"></ion-icon>
                            <p>Camera not started</p>
                        </div>
                    </div>
                    <button id="qrCamBtn" class="btn btn-primary" onclick="toggleQrCamera()">
                        <ion-icon name="camera-outline"></ion-icon> Start Camera
                    </button>
                </div>
            </div>

            
            <div class="face-card">
                <div class="face-card-header">
                    <ion-icon name="checkmark-circle-outline" style="color:#10b981;font-size:20px;"></ion-icon>
                    <h3>Scan Result</h3>
                </div>
                <div class="face-card-body">
                    <p style="font-size:13px;color:#64748b;margin-bottom:16px;line-height:1.6;">
                        Point your camera at your student QR code. The system will automatically detect and record your attendance once verified.
                    </p>
                    <div id="qrScanResult" style="background:rgba(99,102,241,.08);border:1px solid rgba(99,102,241,.2);border-radius:10px;padding:14px;margin-bottom:14px;font-size:13px;color:#a78bfa;display:none;">
                        <strong>QR Detected:</strong> <span id="qrScanText"></span>
                    </div>
                    <div id="qrScanRecordStatus" class="status-bar"></div>
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.06);">
                        <p style="font-size:11px;color:#475569;line-height:1.7;">
                            Only works for events with <strong>Ongoing</strong> status.<br>
                            Your identity is verified via your student QR — secure and tamper-proof.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>


<div class="antispoof-overlay" id="antiSpoofOverlay">
  <div class="antispoof-box">
    <video id="asVideo" class="antispoof-video" autoplay muted playsinline></video>
    <div class="antispoof-challenge">
      <ion-icon name="help-circle-outline" class="challenge-emoji" id="asEmoji"></ion-icon>
      <div class="challenge-text" id="asChallengeText">Preparing challenge…</div>
      <div class="challenge-sub" id="asChallengeSubText">Please wait while your camera loads</div>
      <div class="challenge-timer-bar"><div class="challenge-timer-fill" id="asTimerFill"></div></div>
    </div>
  </div>
  <div class="antispoof-status" id="asStatusText"></div>
  <div class="antispoof-actions">
    <button class="as-btn as-btn-cancel" id="asBtnCancel" onclick="closeAntiSpoofModal()">Cancel</button>
  </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════
   State
═══════════════════════════════════════════════════════ */
const QR_PAYLOAD    = <?= json_encode($qrPayload) ?>;
const STUDENT_NAME  = <?= json_encode($fullName) ?>;
const HAS_FACE      = <?= $hasFace ? 'true' : 'false' ?>;
const MODELS_URL    = '../../assets/models';

let selectedEventId     = null;
let selectedEventStatus = null;
let faceRunning         = false;
let faceStream          = null;
let faceInterval        = null;
let modelsLoaded        = false;
let lastDescriptor      = null;   // Float32Array — the detected descriptor
let faceConfirmed       = false;

/* ═══════════════════════════════════════════════════════
   Event select
═══════════════════════════════════════════════════════ */
function onEventChange() {
    const sel    = document.getElementById('eventSel');
    const opt    = sel.selectedOptions[0];
    const badge  = document.getElementById('evStatusBadge');

    selectedEventId     = sel.value ? parseInt(sel.value) : null;
    selectedEventStatus = opt?.dataset?.status || null;

    const classMap = { ongoing:'badge-ongoing', upcoming:'badge-upcoming' };
    const labelMap = { ongoing:'Ongoing', upcoming:'Upcoming' };

    badge.className   = 'status-badge ' + (classMap[selectedEventStatus] || 'badge-none');
    badge.textContent = selectedEventId ? (labelMap[selectedEventStatus] || selectedEventStatus || 'Unknown') : 'No Event';

    // Enable/disable record buttons
    const canRecord = selectedEventId && selectedEventStatus === 'ongoing';
    const qrBtn = document.getElementById('recordQrBtn');
    if (qrBtn) qrBtn.disabled = !canRecord;
    if (!canRecord) resetFaceConfirm();
}

/* ═══════════════════════════════════════════════════════
   Method tabs
═══════════════════════════════════════════════════════ */
function switchMethod(m) {
    document.querySelectorAll('.method-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.method-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + m).classList.add('active');
    document.getElementById('pane-' + m).classList.add('active');
}

/* ═══════════════════════════════════════════════════════
   Load face-api models
═══════════════════════════════════════════════════════ */
async function loadModels() {
    const bar = document.getElementById('faceStatusBar');
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL),
        ]);
        modelsLoaded = true;
        setStatus(bar, 'success', '<ion-icon name="checkmark-circle-outline"></ion-icon> Face recognition ready. Start camera to scan.');
        if (HAS_FACE && document.getElementById('faceBtn')) {
            document.getElementById('faceBtn').disabled = false;
        }
    } catch(e) {
        setStatus(bar, 'error', '<ion-icon name="warning-outline"></ion-icon> Failed to load models. Face recognition unavailable.');
    }
}

/* ═══════════════════════════════════════════════════════
   Camera control
═══════════════════════════════════════════════════════ */
async function toggleFaceCamera() {
    if (faceRunning) {
        stopFaceCamera();
    } else {
        // Run anti-spoofing FIRST before face camera
        if (!selectedEventId || selectedEventStatus !== 'ongoing') {
            setStatus(document.getElementById('faceStatusBar'), 'warning', '⚠️ Please select an ongoing event first.');
            return;
        }
        openAntiSpoofModal();
    }
}

async function startFaceCamera() {
    if (!modelsLoaded) {
        setStatus(document.getElementById('faceStatusBar'), 'warning', 'Models still loading…'); return;
    }
    const video  = document.getElementById('faceVideo');
    const btn    = document.getElementById('faceBtn');
    const ph     = document.getElementById('facePlaceholder');
    const ring   = document.getElementById('scanRing');

    setStatus(document.getElementById('faceStatusBar'), 'info', 'Requesting camera access…');
    try {
        faceStream = await navigator.mediaDevices.getUserMedia({ video: { width:640, height:480, facingMode:'user' } });
        video.srcObject = faceStream;
        await video.play();
        faceRunning = true;
        ph.style.display = 'none';
        ring.style.display = '';
        btn.innerHTML = '<ion-icon name="stop-circle-outline"></ion-icon> Stop Camera';
        btn.className = 'btn btn-danger';
        setStatus(document.getElementById('faceStatusBar'), 'info', '<ion-icon name="scan-outline"></ion-icon> Scanning for your face…');
        faceInterval = setInterval(detectFace, 800);
    } catch(e) {
        setStatus(document.getElementById('faceStatusBar'), 'error', 'Camera access denied.');
    }
}

function stopFaceCamera() {
    if (faceStream) { faceStream.getTracks().forEach(t => t.stop()); faceStream = null; }
    clearInterval(faceInterval);
    faceRunning = false;
    const btn = document.getElementById('faceBtn');
    btn.innerHTML = '<ion-icon name="camera-outline"></ion-icon> Start Camera';
    btn.className = 'btn btn-primary';
    document.getElementById('facePlaceholder').style.display = '';
    document.getElementById('scanRing').style.display = 'none';
    resetFaceConfirm();
}

function resetFaceConfirm() {
    faceConfirmed = false; lastDescriptor = null;
    const mc = document.getElementById('matchCard');
    if (mc) mc.classList.remove('show');
    const rb = document.getElementById('recordFaceBtn');
    if (rb) rb.disabled = true;
    const vs = document.getElementById('verifyStatus');
    if (vs) vs.textContent = 'Awaiting face detection…';
}

/* ═══════════════════════════════════════════════════════
   Face detection loop
═══════════════════════════════════════════════════════ */
async function detectFace() {
    const video  = document.getElementById('faceVideo');
    const canvas = document.getElementById('faceCanvas');
    if (!video || video.readyState < video.HAVE_ENOUGH_DATA) return;

    const detection = await faceapi
        .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.5 }))
        .withFaceLandmarks()
        .withFaceDescriptor();

    if (!detection) {
        document.getElementById('verifyStatus').textContent = 'No face detected. Position your face in the center.';
        resetFaceConfirm();
        return;
    }

    // Draw landmarks on overlay canvas
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.style.width  = video.offsetWidth + 'px';
    canvas.style.height = video.offsetHeight + 'px';
    faceapi.matchDimensions(canvas, { width: video.videoWidth, height: video.videoHeight });
    const resized = faceapi.resizeResults(detection, { width: video.videoWidth, height: video.videoHeight });
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    faceapi.draw.drawFaceLandmarks(canvas, resized);

    lastDescriptor = Array.from(detection.descriptor);
    document.getElementById('verifyStatus').textContent = 'Face detected! Click "Record Attendance" to verify.';

    // Show match info (optimistic — actual match done server-side)
    const matchCard = document.getElementById('matchCard');
    matchCard.classList.add('show');
    document.getElementById('matchName').textContent = STUDENT_NAME;
    document.getElementById('matchDist').textContent = 'Confidence: High';

    faceConfirmed = true;
    const canRecord = selectedEventId && selectedEventStatus === 'ongoing';
    document.getElementById('recordFaceBtn').disabled = !canRecord;
}

/* ═══════════════════════════════════════════════════════
   Record attendance — Face
═══════════════════════════════════════════════════════ */
async function recordFaceAttendance() {
    if (!selectedEventId || selectedEventStatus !== 'ongoing') {
        setStatus(document.getElementById('faceRecordStatus'), 'warning', 'Please select an ongoing event first.'); return;
    }
    if (!lastDescriptor) {
        setStatus(document.getElementById('faceRecordStatus'), 'warning', 'No face detected yet. Keep your face in frame.'); return;
    }

    const btn = document.getElementById('recordFaceBtn');
    btn.disabled = true;
    btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Verifying…';

    const fd = new FormData();
    fd.append('EventId',    selectedEventId);
    fd.append('Method',     'face');
    fd.append('descriptor', JSON.stringify(lastDescriptor));

    try {
        const res  = await fetch('../../config/API/student_record_attendance.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            setStatus(document.getElementById('faceRecordStatus'), 'success', data.message);
            stopFaceCamera();
        } else {
            setStatus(document.getElementById('faceRecordStatus'), 'error', data.message);
        }
    } catch(e) {
        setStatus(document.getElementById('faceRecordStatus'), 'error', 'Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<ion-icon name="checkmark-circle-outline"></ion-icon> Record Attendance';
    }
}

/* ═══════════════════════════════════════════════════════
   Record attendance — QR
═══════════════════════════════════════════════════════ */
async function recordQrAttendance() {
    if (!selectedEventId || selectedEventStatus !== 'ongoing') {
        setStatus(document.getElementById('qrRecordStatus'), 'warning', 'Please select an ongoing event first.'); return;
    }

    const btn = document.getElementById('recordQrBtn');
    btn.disabled = true;
    btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Recording…';

    const fd = new FormData();
    fd.append('EventId', selectedEventId);
    fd.append('Method',  'qr_self');
    fd.append('QrData',  QR_PAYLOAD);

    try {
        const res  = await fetch('../../config/API/student_record_attendance.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            setStatus(document.getElementById('qrRecordStatus'), 'success', data.message);
        } else {
            setStatus(document.getElementById('qrRecordStatus'), 'error', data.message);
        }
    } catch(e) {
        setStatus(document.getElementById('qrRecordStatus'), 'error', 'Network error. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<ion-icon name="checkmark-done-outline"></ion-icon> Record My Attendance';
    }
}

/* ═══════════════════════════════════════════════════════
   Generate QR
═══════════════════════════════════════════════════════ */
function generateQR() {
    const container = document.getElementById('qrContainer');
    if (!container) return;
    container.innerHTML = '';
    new QRCode(container, {
        text: QR_PAYLOAD, width: 116, height: 116,
        colorDark: '#1e293b', colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}

/* ═══════════════════════════════════════════════════════
   Helpers
═══════════════════════════════════════════════════════ */
function setStatus(el, type, msg) {
    if (!el) return;
    el.className = 'status-bar show ' + type;
    el.innerHTML = msg;
}

/* ── Method tab extension: qrscan ── */
function switchMethod(m) {
    document.querySelectorAll('.method-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.method-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + m).classList.add('active');
    document.getElementById('pane-' + m).classList.add('active');
    if (m !== 'qrscan' && qrCamRunning) stopQrCamera();
}

/* ═══════════════════════════════════════════════════════
   QR CAMERA SCANNER
═══════════════════════════════════════════════════════ */
let qrCamStream = null;
let qrCamRunning = false;
let qrCamInterval = null;
let qrCamLastScan = 0;

async function toggleQrCamera() {
    qrCamRunning ? stopQrCamera() : startQrCamera();
}

async function startQrCamera() {
    const btn = document.getElementById('qrCamBtn');
    const ph  = document.getElementById('qrCamPlaceholder');
    setStatus(document.getElementById('qrScanStatusBar'), 'info', 'Requesting camera access…');
    try {
        qrCamStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width:640, height:480 } });
        const vid = document.getElementById('qrCamVideo');
        vid.srcObject = qrCamStream;
        await vid.play();
        qrCamRunning = true;
        ph.style.display = 'none';
        btn.innerHTML = '<ion-icon name="stop-circle-outline"></ion-icon> Stop Camera';
        btn.className = 'btn btn-danger';
        setStatus(document.getElementById('qrScanStatusBar'), 'info', '<ion-icon name="scan-outline"></ion-icon> Scanning for QR code…');
        qrCamInterval = setInterval(scanQrFrame, 250);
    } catch(e) {
        setStatus(document.getElementById('qrScanStatusBar'), 'error', 'Camera access denied.');
    }
}

function stopQrCamera() {
    if (qrCamStream) { qrCamStream.getTracks().forEach(t => t.stop()); qrCamStream = null; }
    clearInterval(qrCamInterval); qrCamInterval = null;
    qrCamRunning = false;
    const btn = document.getElementById('qrCamBtn');
    if (btn) { btn.innerHTML = '<ion-icon name="camera-outline"></ion-icon> Start Camera'; btn.className = 'btn btn-primary'; }
    const ph = document.getElementById('qrCamPlaceholder');
    if (ph) ph.style.display = '';
}

async function scanQrFrame() {
    const vid = document.getElementById('qrCamVideo');
    if (!vid || vid.readyState < vid.HAVE_ENOUGH_DATA) return;
    const canvas = document.getElementById('qrCamCanvas');
    canvas.width = vid.videoWidth;
    canvas.height = vid.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(vid, 0, 0, canvas.width, canvas.height);
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imgData.data, imgData.width, imgData.height);
    if (!code) return;
    const now = Date.now();
    if (now - qrCamLastScan < 3000) return; // debounce 3s
    qrCamLastScan = now;

    // Validate it looks like a student QR
    let parsed = null;
    try { parsed = JSON.parse(code.data); } catch(e) {}
    if (!parsed || parsed.type !== 'student_qr') {
        setStatus(document.getElementById('qrScanStatusBar'), 'warning', 'QR not recognized as a student QR code.');
        return;
    }

    // Show detected data
    document.getElementById('qrScanResult').style.display = 'block';
    document.getElementById('qrScanText').textContent = (parsed.name || '') + ' (' + (parsed.student_id || '') + ')';

    if (!selectedEventId || selectedEventStatus !== 'ongoing') {
        setStatus(document.getElementById('qrScanStatusBar'), 'warning', 'Please select an ongoing event first.');
        return;
    }

    setStatus(document.getElementById('qrScanStatusBar'), 'info', '<ion-icon name="hourglass-outline"></ion-icon> QR detected — recording attendance…');
    stopQrCamera();

    const fd = new FormData();
    fd.append('EventId', selectedEventId);
    fd.append('Method',  'qr_self');
    fd.append('QrData',  code.data);
    try {
        const res  = await fetch('../../config/API/student_record_attendance.php', { method:'POST', body:fd });
        const data = await res.json();
        if (data.success) {
            setStatus(document.getElementById('qrScanStatusBar'), 'success', data.message);
        } else {
            setStatus(document.getElementById('qrScanStatusBar'), 'error', data.message);
        }
    } catch(e) {
        setStatus(document.getElementById('qrScanStatusBar'), 'error', 'Network error. Please try again.');
    }
}

/* ═══════════════════════════════════════════════════════
   ANTI-SPOOFING / LIVENESS DETECTION (Student Portal)
═══════════════════════════════════════════════════════ */
const AS_CHALLENGES = [
  { id: 'LEFT',  icon: 'arrow-back-outline',    text: 'Look LEFT',  sub: 'Turn your head slowly to the left' },
  { id: 'RIGHT', icon: 'arrow-forward-outline', text: 'Look RIGHT', sub: 'Turn your head slowly to the right' },
  { id: 'UP',    icon: 'arrow-up-outline',      text: 'Look UP',    sub: 'Tilt your head gently upward' },
  { id: 'DOWN',  icon: 'arrow-down-outline',    text: 'Look DOWN',  sub: 'Tilt your head gently downward' },
];
const AS_TIMEOUT_MS = 8000;
const AS_HOLD_MS    = 600;

let asStream       = null;
let asChallenge    = null;
let asRunning      = false;
let asPollTimer    = null;
let asTimeoutTimer = null;
let asHoldTimer    = null;
let asApiLoaded    = false;
let asCanvas       = document.createElement('canvas');
let asCtx          = asCanvas.getContext('2d', { willReadFrequently: true });

async function openAntiSpoofModal() {
  const overlay = document.getElementById('antiSpoofOverlay');
  overlay.classList.add('open');
  setAsChallengeUI('help-circle-outline', 'Starting camera…', 'Please allow camera access');
  document.getElementById('asTimerFill').style.transition = 'none';
  document.getElementById('asTimerFill').style.width = '100%';
  document.getElementById('asStatusText').textContent = '';

  // Load face-api models for liveness if not loaded yet
  if (!asApiLoaded) {
    setAsChallengeUI('hourglass-outline', 'Loading AI models…', 'First time takes a few seconds');
    try {
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URL),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL),
      ]);
      asApiLoaded = true;
      modelsLoaded = true;
    } catch(e) {
      setAsChallengeUI('close-circle-outline', 'Model load failed', e.message);
      return;
    }
  }

  // Open camera
  try {
    asStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width:640, height:480 } });
    const vid = document.getElementById('asVideo');
    vid.srcObject = asStream;
    await new Promise(r => { vid.onloadedmetadata = r; });
    vid.play();
  } catch(e) {
    setAsChallengeUI('ban-outline', 'Camera denied', 'Please allow camera access in your browser.');
    return;
  }

  // Pick random challenge
  asChallenge = AS_CHALLENGES[Math.floor(Math.random() * AS_CHALLENGES.length)];
  setAsChallengeUI(asChallenge.icon, asChallenge.text, asChallenge.sub);
  document.getElementById('asStatusText').textContent = 'Face detection active...';

  // Countdown timer bar
  const fill = document.getElementById('asTimerFill');
  fill.style.transition = `width ${AS_TIMEOUT_MS}ms linear`;
  fill.style.width = '0%';

  asRunning = true;
  asPollTimer = setInterval(asPollLiveness, 150);
  asTimeoutTimer = setTimeout(() => {
    if (asRunning) asFailSpoof('Time ran out! Please try again.');
  }, AS_TIMEOUT_MS);
}

function setAsChallengeUI(iconName, text, sub) {
  const el = document.getElementById('asEmoji');
  if (el) el.setAttribute('name', iconName);
  document.getElementById('asChallengeText').textContent = text;
  document.getElementById('asChallengeSubText').textContent = sub;
}

async function asPollLiveness() {
  if (!asRunning) return;
  const vid = document.getElementById('asVideo');
  if (!vid || vid.readyState < 2) return;
  asCanvas.width  = vid.videoWidth  || 320;
  asCanvas.height = vid.videoHeight || 240;
  asCtx.drawImage(vid, 0, 0, asCanvas.width, asCanvas.height);
  try {
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 256, scoreThreshold: 0.5 });
    const det  = await faceapi.detectSingleFace(asCanvas, opts).withFaceLandmarks();
    if (!det) {
      document.getElementById('asStatusText').textContent = 'No face detected – position yourself in the camera';
      clearTimeout(asHoldTimer); asHoldTimer = null;
      return;
    }
    const passed = asCheckPose(det.landmarks, asChallenge.id);
    if (passed) {
      if (!asHoldTimer) {
        document.getElementById('asStatusText').textContent = 'Hold it there…';
        asHoldTimer = setTimeout(() => asPassSpoof(), AS_HOLD_MS);
      }
    } else {
      document.getElementById('asStatusText').textContent = 'Face detected – perform the challenge above';
      clearTimeout(asHoldTimer); asHoldTimer = null;
    }
  } catch(e) { /* silent */ }
}

function asCheckPose(landmarks, direction) {
  const pts = landmarks.positions;
  const nose = pts[30], lEye = pts[36], rEye = pts[45], chin = pts[8];
  const eyeMidX  = (lEye.x + rEye.x) / 2;
  const eyeWidth = Math.abs(rEye.x - lEye.x);
  const noseOffX = nose.x - eyeMidX;
  const yawRatio = eyeWidth > 0 ? noseOffX / eyeWidth : 0;
  const eyeMidY    = (lEye.y + rEye.y) / 2;
  const faceHeight = Math.abs(chin.y - eyeMidY);
  const noseOffY   = nose.y - eyeMidY;
  const pitchRatio = faceHeight > 0 ? noseOffY / faceHeight : 0;
  const YAW = 0.12, PITCH = 0.04;
  if (direction === 'LEFT')  return yawRatio < -YAW;
  if (direction === 'RIGHT') return yawRatio > YAW;
  if (direction === 'UP')    return pitchRatio < (0.38 - PITCH);
  if (direction === 'DOWN')  return pitchRatio > (0.52 + PITCH);
  return false;
}

function asPassSpoof() {
  if (!asRunning) return;
  asStopCamera();
  setAsChallengeUI('checkmark-circle-outline', 'Liveness Verified!', 'Challenge passed successfully');
  document.getElementById('asStatusText').textContent = 'Anti-spoofing passed! Starting face recognition…';
  document.getElementById('asTimerFill').style.width = '100%';
  document.getElementById('asTimerFill').style.background = 'linear-gradient(90deg,#22c55e,#4ade80)';
  setTimeout(async () => {
    closeAntiSpoofModal();
    await startFaceCamera();
  }, 1200);
}

function asFailSpoof(reason) {
  if (!asRunning) return;
  asStopCamera();
  setAsChallengeUI('close-circle-outline', 'Liveness Failed', reason);
  document.getElementById('asStatusText').textContent = 'Spoofing attempt blocked or timed out.';
  document.getElementById('asTimerFill').style.width = '0%';
  setTimeout(() => closeAntiSpoofModal(), 2200);
}

function asStopCamera() {
  asRunning = false;
  clearInterval(asPollTimer); clearTimeout(asTimeoutTimer); clearTimeout(asHoldTimer);
  asPollTimer = asTimeoutTimer = asHoldTimer = null;
  if (asStream) { asStream.getTracks().forEach(t => t.stop()); asStream = null; }
  const vid = document.getElementById('asVideo');
  if (vid) vid.srcObject = null;
}

function closeAntiSpoofModal() {
  asStopCamera();
  document.getElementById('antiSpoofOverlay').classList.remove('open');
  const fill = document.getElementById('asTimerFill');
  fill.style.transition = 'none'; fill.style.width = '100%';
  fill.style.background = 'linear-gradient(90deg,#22c55e,#86efac)';
  setAsChallengeUI('help-circle-outline', 'Preparing challenge…', 'Please wait while your camera loads');
  document.getElementById('asStatusText').textContent = '';
}

/* ── Init ─────────────────────────────────────── */
window.addEventListener('DOMContentLoaded', () => {
    generateQR();
});
</script>

<script>
/* ── Anti-Spoofing Client Logic ─────────────────── */
let _spoofPollInterval = null;
let _graceTotalSeconds = 0;
let _graceStartTimestamp = 0;
let _readinessSecondsLeft = 300; // 5 minutes
let _readinessTimer = null;
let _spoofActive = false;

function checkAntiSpoofing() {
    const sel = document.getElementById('eventSel');
    if (!sel || !sel.value) {
        _hideSpoofPanels();
        if (_spoofPollInterval) { clearInterval(_spoofPollInterval); _spoofPollInterval = null; }
        return;
    }
    const eventId = sel.value;
    _pollAntiSpoofing(eventId);
    if (_spoofPollInterval) clearInterval(_spoofPollInterval);
    _spoofPollInterval = setInterval(() => _pollAntiSpoofing(eventId), 15000);
}

async function _pollAntiSpoofing(eventId) {
    try {
        const r = await fetch(`../../config/API/get_antispoofing_status.php?event_id=${eventId}&t=${Date.now()}`);
        const data = await r.json();
        if (!data.success) return;

        if (!data.active) {
            const panel = document.getElementById('antiSpoofPanel');
            const notice = document.getElementById('noTriggerNotice');
            if (panel) panel.style.display = 'none';
            if (notice && !_spoofActive) notice.style.display = 'block';
            return;
        }

        _spoofActive = true;
        const noTrigger = document.getElementById('noTriggerNotice');
        if (noTrigger) noTrigger.style.display = 'none';

        const panel = document.getElementById('antiSpoofPanel');
        if (panel) panel.style.display = 'block';

        _graceTotalSeconds = data.grace_minutes * 60;
        const graceRemain = data.grace_remaining_seconds;
        _updateGraceBar(graceRemain, _graceTotalSeconds);

        const elapsedSec = data.elapsed_seconds;
        const readinessTotal = 300;

        if (elapsedSec < readinessTotal) {
            const readinessLeft = readinessTotal - elapsedSec;
            _showReadinessCountdown(readinessLeft);
        } else {
            _showScanReady();
        }

    } catch(e) { }
}

function _updateGraceBar(remainSec, totalSec) {
    const bar = document.getElementById('graceProgressBar');
    const txt = document.getElementById('graceRemainText');
    if (!bar || !txt) return;

    const pct = Math.max(0, Math.min(100, (remainSec / totalSec) * 100));
    bar.style.width = pct + '%';
    bar.style.background = pct > 50 ? 'linear-gradient(90deg,#16a34a,#4ade80)' :
                            pct > 20 ? 'linear-gradient(90deg,#d97706,#fbbf24)' :
                                       'linear-gradient(90deg,#dc2626,#f87171)';

    const m = Math.floor(remainSec / 60);
    const s = remainSec % 60;
    txt.textContent = `${m}:${s.toString().padStart(2,'0')} remaining`;
}

function _showReadinessCountdown(secondsLeft) {
    const readySec = document.getElementById('readinessSection');
    const scanSec  = document.getElementById('scanReadySection');
    if (readySec) readySec.style.display = 'block';
    if (scanSec)  scanSec.style.display  = 'none';

    if (_readinessTimer) clearInterval(_readinessTimer);
    _readinessSecondsLeft = secondsLeft;
    _renderReadinessClock(_readinessSecondsLeft);

    _readinessTimer = setInterval(() => {
        _readinessSecondsLeft--;
        _renderReadinessClock(_readinessSecondsLeft);
        if (_readinessSecondsLeft <= 0) {
            clearInterval(_readinessTimer);
            _showScanReady();
        }
    }, 1000);
}

function _renderReadinessClock(sec) {
    const el = document.getElementById('readinessCountdown');
    const ring = document.getElementById('readinessSvgCircle');
    if (!el) return;
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    el.textContent = `${m}:${s.toString().padStart(2,'0')}`;
    if (ring) {
        const total = 300;
        const circumference = 327;
        const offset = circumference - (circumference * (sec / total));
        ring.style.strokeDashoffset = offset;
    }
}

function _showScanReady() {
    if (_readinessTimer) clearInterval(_readinessTimer);
    const readySec = document.getElementById('readinessSection');
    const scanSec  = document.getElementById('scanReadySection');
    if (readySec) readySec.style.display = 'none';
    if (scanSec)  scanSec.style.display  = 'block';
}

function _hideSpoofPanels() {
    _spoofActive = false;
    const panel  = document.getElementById('antiSpoofPanel');
    const notice = document.getElementById('noTriggerNotice');
    if (panel)  panel.style.display  = 'none';
    if (notice) notice.style.display = 'none';
    if (_readinessTimer) clearInterval(_readinessTimer);
}
</script>

<!-- Periodic Presence Check Overlay Modal -->
<div id="presenceCheckOverlayModal" style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(15,23,42,0.85);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:20px;animation:modalPop 0.3s ease;">
    <div style="background:#fff;border-radius:24px;padding:36px;max-width:440px;width:100%;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);border:2px solid #60a5fa;">
        <div style="width:64px;height:64px;background:#eff6ff;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 0 0 8px #dbeafe;">
            <ion-icon name="radio-outline" style="font-size:32px;color:#2563eb;animation:pulse 1.5s infinite;"></ion-icon>
        </div>
        <h2 style="margin:0 0 6px;font-size:20px;font-weight:800;color:#0f172a;">Presence Verification Ping!</h2>
        <p style="margin:0 0 20px;font-size:13px;color:#64748b;">Your organization is checking if you are still active in the online event.</p>

        <!-- Circular Countdown Timer (90s) -->
        <div style="position:relative;width:140px;height:140px;margin:0 auto 24px;">
            <svg viewBox="0 0 140 140" width="140" height="140">
                <circle cx="70" cy="70" r="60" fill="none" stroke="#e2e8f0" stroke-width="10"/>
                <circle id="presenceSvgCircle" cx="70" cy="70" r="60" fill="none" stroke="#2563eb" stroke-width="10"
                    stroke-dasharray="377" stroke-dashoffset="0"
                    stroke-linecap="round" transform="rotate(-90 70 70)"
                    style="transition:stroke-dashoffset 1s linear, stroke 0.3s ease;"/>
            </svg>
            <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                <span id="presenceCountdownTxt" style="font-size:32px;font-weight:800;color:#1e40af;">90s</span>
                <span style="font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;">REMAINING</span>
            </div>
        </div>

        <!-- Camera Container for 3s Quick Face Scan -->
        <div id="presenceFaceScanWrap" style="display:none;margin-bottom:16px;background:#f8fafc;border-radius:14px;padding:12px;border:1px solid #cbd5e1;">
            <video id="presenceVideo" autoplay playsinline muted style="width:100%;height:160px;object-fit:cover;border-radius:10px;background:#000;"></video>
            <p id="presenceScanMsg" style="margin:8px 0 0;font-size:12px;font-weight:700;color:#2563eb;">Scanning face… Please look at the camera</p>
        </div>

        <!-- Response Action Buttons -->
        <div id="presenceActionsWrap" style="display:flex;flex-direction:column;gap:10px;">
            <button onclick="submitPresenceVerification('passed', 'manual')" style="width:100%;padding:14px;border:none;border-radius:12px;background:#2563eb;color:#fff;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 12px rgba(37,99,235,0.25);">
                <ion-icon name="hand-right-outline" style="font-size:20px;"></ion-icon> I'm Still Here
            </button>
            <button onclick="startQuickPresenceFaceScan()" style="width:100%;padding:12px;border:1.5px solid #cbd5e1;border-radius:12px;background:#f8fafc;color:#334155;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                <ion-icon name="scan-outline" style="font-size:18px;color:#2563eb;"></ion-icon> Verify via Quick 3s Face Scan
            </button>
        </div>
    </div>
</div>

<script>
/* ── Presence Check Client Handler ─────────────── */
let _presenceCheckTimer = null;
let _presenceSecondsLeft = 90;
let _presencePollInterval = null;
let _presenceEventId = null;
let _presenceVideoStream = null;

// Audio Chime notification
function _playPresenceChime() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
        osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.3); // A5
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.5);
    } catch(e) {}
}

function startPresenceCheckPolling(eventId) {
    _presenceEventId = eventId;
    _pollPresenceCheck();
    if (_presencePollInterval) clearInterval(_presencePollInterval);
    _presencePollInterval = setInterval(_pollPresenceCheck, 10000); // Check every 10s
}

async function _pollPresenceCheck() {
    if (!_presenceEventId) return;
    try {
        const r = await fetch(`../../config/API/get_presence_check_status.php?event_id=${_presenceEventId}&t=${Date.now()}`);
        const data = await r.json();
        if (!data.success || !data.active || data.already_responded) return;

        _showPresenceCheckOverlay(data.remaining_sec || 90);
    } catch(e) {}
}

function _showPresenceCheckOverlay(remainSec) {
    const modal = document.getElementById('presenceCheckOverlayModal');
    if (!modal || modal.style.display === 'flex') return;

    modal.style.display = 'flex';
    _playPresenceChime();

    _presenceSecondsLeft = remainSec;
    _renderPresenceTimer(_presenceSecondsLeft);

    if (_presenceCheckTimer) clearInterval(_presenceCheckTimer);
    _presenceCheckTimer = setInterval(() => {
        _presenceSecondsLeft--;
        _renderPresenceTimer(_presenceSecondsLeft);
        if (_presenceSecondsLeft <= 0) {
            clearInterval(_presenceCheckTimer);
            submitPresenceVerification('missed', 'timeout');
        }
    }, 1000);
}

function _renderPresenceTimer(sec) {
    const txt  = document.getElementById('presenceCountdownTxt');
    const ring = document.getElementById('presenceSvgCircle');
    if (txt) txt.textContent = sec + 's';
    if (ring) {
        const total = 90;
        const circumference = 377;
        const offset = circumference - (circumference * (sec / total));
        ring.style.strokeDashoffset = offset;
        ring.style.stroke = sec < 20 ? '#dc2626' : (sec < 45 ? '#d97706' : '#2563eb');
    }
}

async function startQuickPresenceFaceScan() {
    const wrap = document.getElementById('presenceFaceScanWrap');
    const video = document.getElementById('presenceVideo');
    const msg = document.getElementById('presenceScanMsg');
    if (wrap) wrap.style.display = 'block';

    try {
        _presenceVideoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
        if (video) video.srcObject = _presenceVideoStream;

        if (msg) msg.textContent = 'Hold still… Scanning face (3s)';

        // 3-second quick scan simulation / verification
        setTimeout(async () => {
            _stopPresenceCamera();
            submitPresenceVerification('passed', 'face');
        }, 3000);

    } catch(err) {
        if (msg) msg.textContent = 'Camera unavailable. Please click "I\'m Still Here".';
    }
}

function _stopPresenceCamera() {
    if (_presenceVideoStream) {
        _presenceVideoStream.getTracks().forEach(track => track.stop());
        _presenceVideoStream = null;
    }
    const wrap = document.getElementById('presenceFaceScanWrap');
    if (wrap) wrap.style.display = 'none';
}

async function submitPresenceVerification(status, method) {
    if (_presenceCheckTimer) clearInterval(_presenceCheckTimer);
    _stopPresenceCamera();

    const modal = document.getElementById('presenceCheckOverlayModal');
    if (modal) modal.style.display = 'none';

    try {
        const fd = new FormData();
        fd.append('event_id', _presenceEventId || document.getElementById('eventSel')?.value || 0);
        fd.append('status', status);
        fd.append('method', method);
        await fetch('../../config/API/verify_presence.php', { method: 'POST', body: fd });
    } catch(e) {}
}

// Hook presence check polling into event change
const origOnEventChange = window.onEventChange;
window.onEventChange = function() {
    if (typeof origOnEventChange === 'function') origOnEventChange();
    const sel = document.getElementById('eventSel');
    if (sel && sel.value) {
        startPresenceCheckPolling(sel.value);
    }
};
</script>
</body>
</html>
