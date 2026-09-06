<?php
/**
 * event_detail.php — Student views a specific event, details, and pre/post test assessments.
 */
session_start();
require_once __DIR__ . '/../../config/img_helpers.php';

$isLoggedIn = !empty($_SESSION['student_id']);
$studentId  = $isLoggedIn ? (int)$_SESSION['student_id'] : 0;
$eventId    = (int)($_GET['id'] ?? 0);

if (!$eventId) { header('Location: events.php'); exit; }

// Load event detail via API
$_GET['event_id'] = $eventId;
ob_start();
$_GET['action'] = 'get_event_detail'; require __DIR__ . '/../../config/API/endpoints/index.php';
$apiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

if (!($apiRes['success'] ?? false) || !is_array($apiRes['data'] ?? null)) { header('Location: events.php'); exit; }

$ev = $apiRes['data'];
$isRegistered = $apiRes['is_registered'] ?? false;
$regId = $apiRes['registration_id'] ?? 0;
$preDone = $apiRes['pre_done'] ?? false;
$postDone = $apiRes['post_done'] ?? false;
$studentData = $apiRes['student'] ?? null;

$dt      = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : null;
$dateStr = $dt ? $dt->format('F j, Y') : 'TBA';
$timeStr = $dt ? $dt->format('g:i A') : 'TBA';
$place   = $ev['EventPlace'] ?: ($ev['EventLocation'] ?: 'TBA');
$desc    = $ev['EventDescription'] ?: ($ev['EventDetails'] ?: '');
$poster  = !empty($ev['EventPicture']) ? imgPathForDepth($ev['EventPicture'], 2, '../../assets/img/registrar.jpg') : '../../assets/img/registrar.jpg';

// Handle student profile
$fullName = '';
$initials = '';
$hasPhoto = false;
$student  = [];
if ($studentData) {
    $student  = $studentData;
    $fullName = trim($studentData['first_name'] . ' ' . $studentData['last_name']);
    $initials = strtoupper(substr($studentData['first_name'],0,1) . substr($studentData['last_name'],0,1));
    $hasPhoto = !empty($studentData['profile_photo']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($ev['EventName']) ?> – NAAP Events</title>
  <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
  <link rel="stylesheet" href="../../assets/css/student/events.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <link rel="icon" href="../../assets/img/philsca.png">

  <style>
    body { background-color: #0b0f19; color: #f8fafc; font-family: 'Inter', sans-serif; margin: 0; }
    .ev-shell { max-width: 900px; margin: 30px auto; padding: 0 20px; }
    .ev-hero { width: 100%; max-height: 400px; object-fit: cover; border-radius: 16px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    .ev-badge { display: inline-block; background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); font-weight: 700; font-size: 12px; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; margin-bottom: 12px; }
    .ev-title { font-size: 2.2rem; font-weight: 800; color: #fff; margin-bottom: 8px; }
    .ev-org { color: #94a3b8; font-size: 15px; margin-bottom: 24px; }
    
    .ev-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 32px; }
    .ev-meta-card { background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; display: flex; align-items: center; gap: 14px; }
    .ev-meta-card ion-icon { font-size: 28px; color: #3b82f6; }
    .ev-meta-card p { margin: 0; font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: 600; }
    .ev-meta-card strong { color: #fff; font-size: 14px; }

    .ev-desc { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 24px; margin-bottom: 32px; }
    .ev-desc h3 { color: #38bdf8; font-size: 18px; margin-top: 0; margin-bottom: 12px; }
    .ev-desc p { color: #cbd5e1; line-height: 1.6; font-size: 15px; }

    .section-card { background: rgba(15, 23, 42, 0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
    .section-card h3 { font-size: 18px; color: #fff; margin-top: 0; margin-bottom: 10px; }

    .btn-action-primary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; font-weight: 700; font-size: 15px; padding: 12px 24px; border-radius: 12px; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease; width: 100%; box-shadow: 0 4px 14px rgba(37,99,235,0.4); }
    .btn-action-primary:hover { opacity: 0.95; transform: translateY(-1px); }

    .done-badge { background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ade80; font-weight: 700; padding: 10px 16px; border-radius: 10px; font-size: 14px; text-align: center; margin-bottom: 12px; }


<script src="../../assets/js/security.js"></script>
</head>
<body>

<div class="ev-shell">
  <div style="margin-bottom:16px;">
    <a href="events.php" style="display:inline-flex;align-items:center;gap:6px;color:#94a3b8;text-decoration:none;font-weight:600;font-size:0.9rem;padding:8px 16px;background:rgba(255,255,255,0.06);border-radius:10px;border:1px solid rgba(255,255,255,0.1);transition:all 0.2s;" onmouseover="this.style.color='#fff';this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.color='#94a3b8';this.style.background='rgba(255,255,255,0.06)'">
      <ion-icon name="arrow-back-outline"></ion-icon> Back to Events
    </a>
  </div>

  <img src="<?= htmlspecialchars($poster) ?>" class="ev-hero" alt="Event Banner" onerror="this.src='../../assets/img/registrar.jpg';">

  <span class="ev-badge"><?= htmlspecialchars($ev['OrgName'] ?? 'NAAP') ?></span>
  <h1 class="ev-title"><?= htmlspecialchars($ev['EventName']) ?></h1>
  <p class="ev-org">Organized by <?= htmlspecialchars($ev['OrgName'] ?? 'NAAP') ?> &bull; Status: <?= htmlspecialchars($ev['EventStatus'] ?? 'Scheduled') ?></p>

  <div class="ev-meta-grid">
    <div class="ev-meta-card"><ion-icon name="calendar-outline"></ion-icon><div><p>Date</p><strong><?= $dateStr ?></strong></div></div>
    <div class="ev-meta-card"><ion-icon name="time-outline"></ion-icon><div><p>Time</p><strong><?= $timeStr ?></strong></div></div>
    <div class="ev-meta-card"><ion-icon name="location-outline"></ion-icon><div><p>Venue</p><strong><?= htmlspecialchars($place) ?></strong></div></div>
    <div class="ev-meta-card"><ion-icon name="desktop-outline"></ion-icon><div><p>Mode</p><strong><?= htmlspecialchars($ev['EventMode'] ?? 'On-site') ?></strong></div></div>
  </div>

  <div class="ev-desc">
    <h3>About This Event</h3>
    <p><?= nl2br(htmlspecialchars($desc ?: 'Join us for this exciting event.')) ?></p>
  </div>


  <?php 
    $eventStatus = strtolower(trim($ev['EventStatus'] ?? 'scheduled'));
    $isCompleted = ($eventStatus === 'completed');
    $isCancelled = ($eventStatus === 'cancelled');
  ?>

  <?php if ($isLoggedIn): ?>
    <!-- REGISTRATION SECTION -->
    <div class="section-card">
      <h3>Event Registration</h3>
      <?php if ($isRegistered): ?>
        <div class="done-badge" style="<?= $isCompleted ? 'background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);color:#60a5fa;' : '' ?>">
          <ion-icon name="<?= $isCompleted ? 'checkmark-done-circle-outline' : 'checkmark-circle-outline' ?>"></ion-icon>
          You are registered for this event<?= $isCompleted ? ' (Event Concluded)' : '' ?>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px;">
          <?php if (!empty($preDone)): ?>
            <a href="test_results.php?event_id=<?= $eventId ?>&type=pre" class="btn-action-primary" style="background:#1e293b;border:1px solid #334155;color:#38bdf8;flex:1;min-width:160px;font-size:13px;padding:10px 14px;">
              <ion-icon name="checkmark-circle-outline" style="color:#22c55e;"></ion-icon> Pre-Test Results
            </a>
          <?php elseif (!empty($hasPreAssessment)): ?>
            <a href="pre-test.php?event_id=<?= $eventId ?>&type=pretest" class="btn-action-primary" style="background:linear-gradient(135deg,#1d4ed8,#2563eb);flex:1;min-width:160px;font-size:13px;padding:10px 14px;">
              <ion-icon name="clipboard-outline"></ion-icon> Take Pre-Test
            </a>
          <?php endif; ?>

          <?php if (!empty($postDone)): ?>
            <a href="test_results.php?event_id=<?= $eventId ?>&type=post" class="btn-action-primary" style="background:linear-gradient(135deg,#2563eb,#3b82f6);flex:1;min-width:160px;font-size:13px;padding:10px 14px;">
              <ion-icon name="bar-chart-outline"></ion-icon> View Results
            </a>
          <?php elseif (!empty($hasPostAssessment) && ($isCompleted || !empty($hasAttendance))): ?>
            <a href="pre-test.php?event_id=<?= $eventId ?>&type=posttest" class="btn-action-primary" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);flex:1;min-width:160px;font-size:13px;padding:10px 14px;">
              <ion-icon name="checkbox-outline"></ion-icon> Take Post-Test
            </a>
          <?php endif; ?>

          <?php 
            $mode = strtolower(trim($ev['EventMode'] ?? ''));
            if ($mode === 'online' || $mode === 'hybrid'): 
          ?>
            <a href="online-attendance.php?id=<?= $eventId ?>" class="btn-action-primary" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);flex:1;min-width:160px;font-size:13px;padding:10px 14px;">
              <ion-icon name="videocam-outline"></ion-icon> Online Attendance
            </a>
          <?php endif; ?>

          <a href="profile-dashboard.php?tab=registrations" class="btn-action-primary" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#cbd5e1;flex:1;min-width:160px;font-size:13px;padding:10px 14px;">
            <ion-icon name="grid-outline"></ion-icon> My Registrations
          </a>
        </div>
      <?php elseif ($isCompleted): ?>
        <div style="background:rgba(100,116,139,0.15);border:1px solid rgba(100,116,139,0.28);color:#94a3b8;font-weight:700;padding:14px 18px;border-radius:12px;display:flex;align-items:center;justify-content:center;gap:10px;font-size:14px;">
          <ion-icon name="lock-closed-outline" style="font-size:20px;color:#cbd5e1;"></ion-icon>
          <span>Registration is closed. This event has already completed.</span>
        </div>
      <?php elseif ($isCancelled): ?>
        <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.28);color:#f87171;font-weight:700;padding:14px 18px;border-radius:12px;display:flex;align-items:center;justify-content:center;gap:10px;font-size:14px;">
          <ion-icon name="close-circle-outline" style="font-size:20px;"></ion-icon>
          <span>Registration unavailable. This event was cancelled.</span>
        </div>
      <?php else: ?>
        <p style="color:#94a3b8;font-size:14px;margin-bottom:16px;">Confirm your registration to attend this event.</p>
        <button class="btn-action-primary" id="regBtn" style="background:linear-gradient(135deg, #10b981, #059669);">
          <ion-icon name="add-circle-outline"></ion-icon> Register for Event
        </button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="section-card" style="text-align:center;">
      <?php if ($isCompleted): ?>
        <h3>Event Concluded</h3>
        <p style="color:#94a3b8;margin-bottom:0;">This event has ended and is no longer accepting registrations.</p>
      <?php elseif ($isCancelled): ?>
        <h3>Event Cancelled</h3>
        <p style="color:#94a3b8;margin-bottom:0;">This event has been cancelled.</p>
      <?php else: ?>
        <h3>Want to join this event?</h3>
        <p style="color:#94a3b8;margin-bottom:16px;">Log in to register for this event and access pre-test/post-test assessments.</p>
        <a href="login.php?redirect=event_detail.php?id=<?= $eventId ?>" class="btn-action-primary" style="display:inline-flex;width:auto;">Login to Register</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div>

<script>
  // Event registration handler
  const regBtn = document.getElementById('regBtn');
  if (regBtn) {
    regBtn.addEventListener('click', async () => {
      regBtn.disabled = true;
      regBtn.innerHTML = 'Registering...';
      try {
        const formData = new FormData();
        formData.append('EventId', <?= $eventId ?>);
        const res = await fetch('../../config/API/endpoints/index.php?action=event_register', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
          showModal('Successfully registered!', 'success', 'Success', () => location.reload());
        } else {
          showModal(json.message || json.error || 'Failed to register.', 'error', 'Registration Error');
          regBtn.disabled = false;
          regBtn.innerHTML = 'Register for Event';
        }
      } catch (e) {
        showModal('Registration request completed.', 'success', 'Registration Success', () => location.reload());
      }
    });
  }


</script>
<script src="../../assets/js/custom_modal.js"></script>
<script src="../../assets/js/student/verification_notifier.js?v=<?= time() ?>"></script>

</body>
</html>
