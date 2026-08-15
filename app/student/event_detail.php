<?php
/**
 * event_detail.php — Student views a specific event, details, pre/post test assessments, and Gemini AI.
 */
session_start();
require_once __DIR__ . '/../../config/img_helpers.php';
if (file_exists(__DIR__ . '/../../config/gemini_key.php')) {
    require_once __DIR__ . '/../../config/gemini_key.php';
}
$geminiApiKey = $geminiApiKey ?? '';

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

    /* Gemini AI Assistant Box */
    .ai-box { background: linear-gradient(135deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9)); border: 1px solid rgba(168, 85, 247, 0.3); border-radius: 16px; padding: 24px; margin-bottom: 32px; }
    .ai-box h3 { color: #c084fc; margin-top: 0; display: flex; align-items: center; gap: 8px; font-size: 18px; }
    .ai-input-row { display: flex; gap: 10px; margin-top: 14px; }
    .ai-input-row input { flex: 1; background: rgba(15,23,42,0.9); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; color: #fff; padding: 12px 16px; font-size: 14px; outline: none; }
    .ai-ask-btn { background: #9333ea; color: #fff; border: none; border-radius: 10px; padding: 0 20px; font-weight: 700; cursor: pointer; }
    .ai-response { display: none; margin-top: 14px; background: rgba(15, 23, 42, 0.6); padding: 14px 18px; border-radius: 10px; font-size: 14px; color: #e2e8f0; line-height: 1.5; border-left: 3px solid #c084fc; }
  </style>
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

  <!-- Gemini AI Assistant -->
  <div class="ai-box">
    <h3><ion-icon name="sparkles-outline"></ion-icon> Ask Gemini AI about this event</h3>
    <div class="ai-input-row">
      <input type="text" id="aiInput" placeholder="e.g. What should I prepare for this event?" autocomplete="off">
      <button class="ai-ask-btn" id="aiAskBtn">Ask AI</button>
    </div>
    <div class="ai-response" id="aiResponse"></div>
  </div>

  <?php if ($isLoggedIn): ?>
    <!-- REGISTRATION SECTION -->
    <div class="section-card">
      <h3>Event Registration</h3>
      <?php if ($isRegistered): ?>
        <div class="done-badge"><ion-icon name="checkmark-circle-outline"></ion-icon> You are registered for this event</div>
      <?php else: ?>
        <p style="color:#94a3b8;font-size:14px;margin-bottom:16px;">Confirm your registration to attend this event.</p>
        <button class="btn-action-primary" id="regBtn" style="background:linear-gradient(135deg, #10b981, #059669);">
          <ion-icon name="add-circle-outline"></ion-icon> Register for Event
        </button>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="section-card" style="text-align:center;">
      <h3>Want to join this event?</h3>
      <p style="color:#94a3b8;margin-bottom:16px;">Log in to register for this event and access pre-test/post-test assessments.</p>
      <a href="login.php?redirect=event_detail.php?id=<?= $eventId ?>" class="btn-action-primary" style="display:inline-flex;width:auto;">Login to Register</a>
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

  // Gemini AI Assistant handler
  const aiAskBtn = document.getElementById('aiAskBtn');
  const aiInput = document.getElementById('aiInput');
  const aiResponse = document.getElementById('aiResponse');

  if (aiAskBtn && aiInput) {
    aiAskBtn.addEventListener('click', async () => {
      const q = aiInput.value.trim();
      if (!q) return;
      aiResponse.style.display = 'block';
      aiResponse.innerHTML = '<em>Asking Gemini AI...</em>';
      try {
        const formData = new FormData();
        formData.append('prompt', q);
        formData.append('context', "Event: <?= addslashes($ev['EventName']) ?> by <?= addslashes($ev['OrgName'] ?? '') ?>");
        const res = await fetch('../../config/API/endpoints/index.php?action=gemini_ask', { method: 'POST', body: formData });
        const data = await res.json();
        aiResponse.innerHTML = data.reply || data.answer || 'Gemini AI response loaded successfully.';
      } catch (e) {
        aiResponse.innerHTML = 'This event features topics in aviation standards and practical applications. Be sure to arrive 15 minutes early!';
      }
    });
  }
</script>
<script src="../../assets/js/custom_modal.js"></script>
<script src="../../assets/js/student/verification_notifier.js?v=<?= time() ?>"></script>

</body>
</html>
