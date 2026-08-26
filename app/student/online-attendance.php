<?php
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) { header('Location: login.php'); exit; }
$studentId = (int)$_SESSION['student_id'];
$eventId   = (int)($_GET['eventId'] ?? $_GET['event_id'] ?? $_GET['id'] ?? 0);
$event     = null;

if ($eventId) {
    $stmt = $conn->prepare("SELECT e.EventId, e.EventName, e.EventDateTime, e.EndDateTime, COALESCE(NULLIF(TRIM(e.EventStatus),''), 'Scheduled') AS EventStatus, COALESCE(NULLIF(TRIM(e.EventMode),''), 'Online') AS EventMode
        FROM event e
        WHERE e.EventId = ? LIMIT 1");
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
}

$isRegistered = false;
if ($eventId && $studentId) {
    $regChk = $conn->prepare("SELECT RegistrationId FROM eventregistration WHERE EventId = ? AND UserId = ? LIMIT 1");
    if ($regChk) {
        $regChk->bind_param('ii', $eventId, $studentId);
        $regChk->execute();
        $isRegistered = (bool)$regChk->get_result()->fetch_assoc();
        $regChk->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Online Facial Attendance | NAAP</title>
<link rel="icon" href="../../assets/img/philsca.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
<style>
  body { margin:0; min-height:100vh; background:#0b0f19; color:#e2e8f0; font-family:'Inter',system-ui,sans-serif; display:grid; place-items:center; padding:20px; box-sizing:border-box; }
  .card { width:min(680px,100%); background:#131c31; border:1px solid rgba(255,255,255,0.1); border-radius:24px; padding:28px 32px; box-shadow:0 25px 60px rgba(0,0,0,0.6); backdrop-filter:blur(16px); }
  .badge { display:inline-flex; align-items:center; gap:6px; color:#34d399; background:rgba(16,185,129,0.15); border:1px solid rgba(52,211,153,0.3); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; }
  .event { margin:18px 0; padding:18px 20px; background:#0f172a; border-radius:16px; border:1px solid rgba(255,255,255,0.08); }
  .event h1 { font-size:22px; margin:0 0 6px; color:#fff; font-weight:800; }
  .muted { color:#94a3b8; font-size:14px; line-height:1.5; margin:0; }
  
  /* Camera & Face Scan Frame */
  .camera-container {
    position: relative;
    width: 100%;
    max-width: 480px;
    margin: 20px auto 16px;
    background: #020617;
    border-radius: 20px;
    overflow: hidden;
    aspect-ratio: 4 / 3;
    border: 2px solid rgba(56, 189, 248, 0.3);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(0, 0, 0, 0.8);
  }
  .camera-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
    display: block;
  }
  .camera-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    transform: scaleX(-1);
  }
  .scanner-overlay {
    position: absolute;
    inset: 0;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .scanner-reticle {
    width: 220px;
    height: 220px;
    border: 2px dashed rgba(56, 189, 248, 0.6);
    border-radius: 50%;
    position: relative;
    animation: scanPulse 2.5s infinite ease-in-out;
  }
  .scanner-reticle.verified {
    border-color: #10b981;
    border-style: solid;
    box-shadow: 0 0 25px rgba(16, 185, 129, 0.4);
  }
  @keyframes scanPulse {
    0%, 100% { transform: scale(0.98); opacity: 0.7; }
    50% { transform: scale(1.03); opacity: 1; border-color: #38bdf8; }
  }

  /* Status Bar */
  .face-status-bar {
    background: rgba(15, 23, 42, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
  }
  .status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #f59e0b;
    flex-shrink: 0;
    animation: pulse 1.5s infinite;
  }
  .status-indicator.ready { background: #10b981; }
  .status-indicator.error { background: #ef4444; }
  @keyframes pulse {
    0% { transform: scale(0.9); opacity: 0.7; }
    50% { transform: scale(1.2); opacity: 1; }
    100% { transform: scale(0.9); opacity: 0.7; }
  }

  .actions { display:flex; gap:12px; margin-top:20px; flex-wrap:wrap; justify-content:center; }
  button, a { border:0; border-radius:12px; padding:12px 24px; font:inherit; font-weight:700; cursor:pointer; text-decoration:none; transition:all 0.2s; display:inline-flex; align-items:center; justify-content:center; gap:8px; }
  .in { background:linear-gradient(135deg,#2563eb,#3b82f6); color:#fff; box-shadow:0 4px 14px rgba(37,99,235,0.4); flex:1; min-width:180px; }
  .in:hover { opacity:0.95; transform:translateY(-1px); }
  .out { background:linear-gradient(135deg,#0d9488,#14b8a6); color:#fff; box-shadow:0 4px 14px rgba(13,148,136,0.4); flex:1; min-width:180px; }
  .out:hover { opacity:0.95; transform:translateY(-1px); }
  .back { background:rgba(255,255,255,0.08); color:#fff; border:1px solid rgba(255,255,255,0.12); width:100%; }
  .back:hover { background:rgba(255,255,255,0.15); }
  button:disabled { opacity:0.5; cursor:not-allowed; transform:none !important; }

  #message { margin-top:14px; min-height:22px; font-size:14px; font-weight:600; text-align:center; }
</style>
<script src="../../assets/js/security.js"></script>
</head>
<body>
<main class="card">
<?php if (!$event): ?>
  <span class="badge" style="color:#ef4444;background:rgba(239,68,68,0.15);border-color:rgba(239,68,68,0.3);">Event Unavailable</span>
  <h1 style="margin-top:14px;">Online Event Not Found</h1>
  <p class="muted">No event matching ID #<?= (int)$eventId ?> was found in the system database.</p>
  <div style="margin-top:20px;">
    <a class="back" href="profile-dashboard.php">Back to Dashboard</a>
  </div>
<?php elseif (!$isRegistered): ?>
  <span class="badge" style="color:#f59e0b;background:rgba(245,158,11,0.15);border-color:rgba(245,158,11,0.3);">Registration Required</span>
  <h1 style="margin-top:14px;"><?= htmlspecialchars($event['EventName']) ?></h1>
  <p class="muted" style="margin-bottom:20px;">Online facial attendance check-in is strictly reserved for pre-registered participants. You have not registered for this event yet.</p>
  <div style="display:flex;gap:12px;flex-wrap:wrap;">
    <a class="in" href="event_detail.php?id=<?= (int)$event['EventId'] ?>" style="flex:1;">Pre-Register for Event</a>
    <a class="back" href="profile-dashboard.php" style="flex:1;">Back to Dashboard</a>
  </div>
<?php else: ?>
  <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <span class="badge"><ion-icon name="camera-outline"></ion-icon> Facial Recognition Online Attendance</span>
    <span style="font-size:12px;color:#94a3b8;font-weight:600;">ID: #<?= (int)$event['EventId'] ?></span>
  </div>

  <div class="event">
    <h1><?= htmlspecialchars($event['EventName']) ?></h1>
    <p class="muted">Status: <strong style="color:#34d399;"><?= htmlspecialchars($event['EventStatus']) ?></strong> &middot; Mode: <strong><?= htmlspecialchars($event['EventMode']) ?></strong></p>
  </div>

<?php
$existingAtt = null;
$hasLoggedIn = false;
$hasLoggedOut = false;
$loginTimestamp = 0;
$remainingStaySec = 0;

// Calculate minimum stay: 80% of event duration, default 1 hour
$evStartTs = !empty($event['EventDateTime']) ? strtotime($event['EventDateTime']) : 0;
$evEndTs   = !empty($event['EndDateTime']) ? strtotime($event['EndDateTime']) : 0;
if ($evStartTs && $evEndTs && $evEndTs > $evStartTs) {
    $minStaySeconds = (int)floor(($evEndTs - $evStartTs) * 0.8);
} else {
    $minStaySeconds = 3600; // Default: 1 hour
}

if ($eventId && $studentId) {
    $attStmt = $conn->prepare("SELECT * FROM attendance WHERE EventId = ? AND UserId = ? ORDER BY AttendanceId DESC LIMIT 1");
    if ($attStmt) {
        $attStmt->bind_param("ii", $eventId, $studentId);
        $attStmt->execute();
        $existingAtt = $attStmt->get_result()->fetch_assoc();
        $existingAtt = $existingAtt ?: null;
        $attStmt->close();
    }
}

if ($existingAtt) {
    $hasLoggedIn = !empty($existingAtt['CheckInTime']) || strtolower(trim($existingAtt['LogType'] ?? '')) === 'log in' || !empty($existingAtt['Timestamp']);
    $hasLoggedOut = !empty($existingAtt['CheckOutTime']) || strtolower(trim($existingAtt['LogType'] ?? '')) === 'log out';
    
    $loginTimeStr = $existingAtt['CheckInTime'] ?? $existingAtt['Timestamp'] ?? null;
    if ($loginTimeStr) {
        $loginTimestamp = strtotime($loginTimeStr);
        $elapsed = time() - $loginTimestamp;
        if ($elapsed < $minStaySeconds) {
            $remainingStaySec = $minStaySeconds - $elapsed;
        }
    }
}

// Format the remaining time for display (supports hours)
$coTimerFormatted = '';
if ($remainingStaySec > 0) {
    $h = floor($remainingStaySec / 3600);
    $m = floor(($remainingStaySec % 3600) / 60);
    $s = $remainingStaySec % 60;
    $coTimerFormatted = $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
}
?>

  <?php if ($existingAtt): ?>
  <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);padding:14px 18px;border-radius:12px;margin-bottom:16px;font-size:0.9rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <div>
        <strong style="color:#10b981;">Recorded Attendance Status:</strong>
        <span style="color:#e2e8f0;margin-left:6px;">
          <?= !empty($existingAtt['CheckInTime']) ? 'Checked In: ' . date('g:i A', strtotime($existingAtt['CheckInTime'])) : (!empty($existingAtt['Timestamp']) ? 'Logged In: ' . date('g:i A', strtotime($existingAtt['Timestamp'])) : 'Logged In') ?>
          <?= !empty($existingAtt['CheckOutTime']) ? ' &bull; Checked Out: ' . date('g:i A', strtotime($existingAtt['CheckOutTime'])) : '' ?>
        </span>
      </div>
      <?php if ($hasLoggedIn && !$hasLoggedOut && $remainingStaySec > 0): ?>
      <div id="coNotice" style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);color:#fbbf24;padding:4px 10px;border-radius:8px;font-size:12px;font-weight:700;">
        <i class='bx bx-time'></i> 80% Event Participation Required: <span id="coTimerBanner"><?= $coTimerFormatted ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Camera Scanner Section -->
  <div class="camera-container">
    <video id="faceCamera" class="camera-video" autoplay muted playsinline></video>
    <canvas id="faceTrackerCanvas" class="camera-canvas"></canvas>
    <div class="scanner-overlay">
      <div id="scannerReticle" class="scanner-reticle"></div>
    </div>
  </div>

  <div class="face-status-bar">
    <div class="status-indicator" id="statusIndicator"></div>
    <span id="faceStatusText" style="font-size:0.9rem;font-weight:600;color:#cbd5e1;">Initializing facial detection scanner…</span>
  </div>
  
  <div class="actions">
    <?php if ($hasLoggedOut): ?>
      <button class="in" id="checkInBtn" disabled style="opacity:0.5;background:#1e293b;cursor:not-allowed;">
        <span>Checked In ✓</span>
      </button>
      <button class="out" id="checkOutBtn" disabled style="opacity:0.5;background:#1e293b;cursor:not-allowed;">
        <span>Checked Out ✓</span>
      </button>
    <?php elseif ($hasLoggedIn): ?>
      <button class="in" id="checkInBtn" disabled style="opacity:0.5;background:#1e293b;cursor:not-allowed;">
        <span>Already Checked In ✓</span>
      </button>
      <button class="out" id="checkOutBtn" <?= $remainingStaySec > 0 ? 'disabled' : '' ?> onclick="submitFacialAttendance('Log Out')">
        <span id="checkOutBtnText"><?= $remainingStaySec > 0 ? 'Check Out in ' . $coTimerFormatted : 'Check Out (Log Out)' ?></span>
      </button>
    <?php else: ?>
      <button class="in" id="checkInBtn" onclick="submitFacialAttendance('Log In')">
        <span>Check In (Log In)</span>
      </button>
      <button class="out" id="checkOutBtn" disabled style="opacity:0.4;cursor:not-allowed;" title="You must check in first before checking out">
        <span>Check Out (Log Out)</span>
      </button>
    <?php endif; ?>
    <a class="back" href="profile-dashboard.php">Back to Dashboard</a>
  </div>
  <div id="message" aria-live="polite"></div>

  <script src="../../assets/js/lib/face-api.min.js"></script>
  <script src="../../assets/js/custom_modal.js"></script>
  <script>
    const eventId = <?= (int)$event['EventId'] ?>;
    const video = document.getElementById('faceCamera');
    const canvas = document.getElementById('faceTrackerCanvas');
    const statusText = document.getElementById('faceStatusText');
    const statusIndicator = document.getElementById('statusIndicator');
    const reticle = document.getElementById('scannerReticle');
    const checkInBtn = document.getElementById('checkInBtn');
    const checkOutBtn = document.getElementById('checkOutBtn');
    let remainingStaySeconds = <?= (int)$remainingStaySec ?>;
    const hasLoggedIn = <?= $hasLoggedIn ? 'true' : 'false' ?>;
    const hasLoggedOut = <?= $hasLoggedOut ? 'true' : 'false' ?>;

    let stream = null;
    let pollInterval = null;
    let isFaceDetected = false;
    let isSubmitting = false;

    function setFaceStatus(text, type = 'pending') {
      if (statusText) statusText.textContent = text;
      if (statusIndicator) {
        statusIndicator.className = 'status-indicator ' + (type === 'success' ? 'ready' : (type === 'error' ? 'error' : ''));
      }
      if (reticle) {
        if (type === 'success') reticle.classList.add('verified');
        else reticle.classList.remove('verified');
      }
    }

    async function initFaceCamera() {
      setFaceStatus('Loading AI Face Recognition models…', 'pending');
      try {
        await faceapi.nets.tinyFaceDetector.loadFromUri('../../assets/models');
        setFaceStatus('Starting live camera…', 'pending');

        stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
          audio: false
        });

        video.srcObject = stream;
        await new Promise(resolve => video.onloadedmetadata = resolve);
        await video.play();

        setFaceStatus('Camera active. Please center your face in the circle.', 'pending');
        pollInterval = setInterval(scanFace, 150);
      } catch (err) {
        console.error('Camera error:', err);
        setFaceStatus('Camera access required for facial recognition. Please grant camera permission.', 'error');
      }
    }

    async function scanFace() {
      if (isSubmitting || !video || video.readyState < 2) return;

      try {
        const faces = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.22 }));
        
        if (faces && faces.length === 1) {
          isFaceDetected = true;
          setFaceStatus('Face detected ✓ Ready for attendance verification.', 'success');
        } else if (faces && faces.length > 1) {
          isFaceDetected = false;
          setFaceStatus('Multiple faces detected. Only one person allowed.', 'error');
        } else {
          isFaceDetected = false;
          setFaceStatus('Position your face inside the scanner frame…', 'pending');
        }
      } catch (e) {
        // scanner loop
      }
    }

    async function submitFacialAttendance(logType) {
      if (isSubmitting) return;

      if (!isFaceDetected) {
        if (typeof showModal === 'function') {
          showModal('Please position your face clearly in the camera scanner before submitting attendance.', 'warning', 'Face Recognition Required');
        } else {
          alert('Please position your face in the camera scanner.');
        }
        return;
      }

      isSubmitting = true;
      if (checkInBtn) checkInBtn.disabled = true;
      if (checkOutBtn) checkOutBtn.disabled = true;

      const message = document.getElementById('message');
      message.style.color = '#38bdf8';
      message.textContent = 'Verifying face and recording ' + logType + '…';

      const fd = new FormData();
      fd.append('EventId', eventId);
      fd.append('Method', 'Online Attendance');
      fd.append('LogType', logType);

      try {
        const res = await fetch('../../config/API/endpoints/index.php?action=student_record_attendance', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();

        if (data.success) {
          message.style.color = '#34d399';
          message.textContent = '✓ ' + (data.message || (logType + ' recorded successfully with Face ID!'));
          setFaceStatus('✓ ' + logType + ' Verified & Recorded!', 'success');
          setTimeout(() => location.reload(), 1500);
        } else {
          message.style.color = '#fca5a5';
          message.textContent = data.message || 'Unable to record attendance.';
          isSubmitting = false;
          // Only re-enable buttons that were actionable before the attempt
          if (checkInBtn && !hasLoggedIn && !hasLoggedOut) checkInBtn.disabled = false;
          if (checkOutBtn && hasLoggedIn && !hasLoggedOut && remainingStaySeconds <= 0) checkOutBtn.disabled = false;
        }
      } catch (err) {
        message.style.color = '#fca5a5';
        message.textContent = 'Network error while contacting attendance service.';
        isSubmitting = false;
        if (checkInBtn && !hasLoggedIn && !hasLoggedOut) checkInBtn.disabled = false;
        if (checkOutBtn && hasLoggedIn && !hasLoggedOut && remainingStaySeconds <= 0) checkOutBtn.disabled = false;
      }
    }

    // ── Cooldown Countdown Timer ──────────────────────────────────────
    function startCooldownTimer() {
      if (remainingStaySeconds <= 0 || hasLoggedOut) return;

      const btnText = document.getElementById('checkOutBtnText');
      const bannerTimer = document.getElementById('coTimerBanner');
      const bannerNotice = document.getElementById('coNotice');

      const tick = () => {
        remainingStaySeconds--;
        if (remainingStaySeconds <= 0) {
          // Cooldown expired – enable checkout
          if (checkOutBtn) {
            checkOutBtn.disabled = false;
            checkOutBtn.style.opacity = '';
            checkOutBtn.style.cursor = '';
          }
          if (btnText) btnText.textContent = 'Check Out (Log Out)';
          if (bannerNotice) {
            bannerNotice.style.background = 'rgba(16,185,129,0.15)';
            bannerNotice.style.borderColor = 'rgba(16,185,129,0.3)';
            bannerNotice.style.color = '#34d399';
            bannerNotice.innerHTML = '<i class="bx bx-check-circle"></i> You may now check out.';
          }
          return;
        }

        const hh = Math.floor(remainingStaySeconds / 3600);
        const mm = String(Math.floor((remainingStaySeconds % 3600) / 60)).padStart(2, '0');
        const ss = String(remainingStaySeconds % 60).padStart(2, '0');
        const timeStr = hh > 0 ? hh + ':' + mm + ':' + ss : mm + ':' + ss;
        if (btnText) btnText.textContent = 'Check Out in ' + timeStr;
        if (bannerTimer) bannerTimer.textContent = timeStr;
        setTimeout(tick, 1000);
      };

      setTimeout(tick, 1000);
    }

    window.addEventListener('DOMContentLoaded', () => {
      initFaceCamera();
      startCooldownTimer();
    });
  </script>
  <script src="../../assets/js/student/verification_notifier.js?v=<?= time() ?>"></script>
<?php endif; ?>
</main>
</body>
</html>
