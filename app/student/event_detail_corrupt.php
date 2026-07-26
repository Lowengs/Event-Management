<?php
/** event_detail.php — Student views a specific event, details, and Gemini AI. Exam parts removed. */
session_start();
require_once '../../config/db.php';
require_once '../../config/gemini_key.php'; // $geminiApiKey

$isLoggedIn = !empty($_SESSION['student_id']);
$studentId  = $isLoggedIn ? (int)$_SESSION['student_id'] : 0;
$eventId    = (int)($_GET['id'] ?? 0);

if (!$eventId) { header('Location: events.php'); exit; }

// Load event
$ev = $conn->query("SELECT e.*, o.OrgName FROM event e LEFT JOIN organization o ON e.OrgId=o.OrgId WHERE e.EventId=$eventId")->fetch_assoc();
if (!$ev) { header('Location: events.php'); exit; }

// Check if student already registered
$isRegistered = false;
$regId = 0;
if ($isLoggedIn) {
    $rr = $conn->query("SELECT RegistrationId FROM eventregistration WHERE EventId=$eventId AND UserId=$studentId")->fetch_assoc();
    if ($rr) { $isRegistered = true; $regId = (int)$rr['RegistrationId']; }
}

$conn->query("CREATE TABLE IF NOT EXISTS eventregistration (
    RegistrationId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
    Status VARCHAR(50) DEFAULT 'Registered', RegisteredAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$dt      = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : null;
$dateStr = $dt ? $dt->format('F j, Y') : 'TBA';
$timeStr = $dt ? $dt->format('g:i A') : 'TBA';
$place   = $ev['EventPlace'] ?: ($ev['EventLocation'] ?: 'TBA');
$desc    = $ev['EventDescription'] ?: ($ev['EventDetails'] ?: '');
$poster  = $ev['EventPicture'] ? '../../'.$ev['EventPicture'] : '';

// Handle student profile
$fullName = '';
$initials = '';
$hasPhoto = false;
$student  = [];
if ($isLoggedIn) {
    $uRow = $conn->query("SELECT first_name, last_name, profile_photo FROM user WHERE UserId=$studentId")->fetch_assoc();
    if ($uRow) {
        $student  = $uRow;
        $fullName = trim($uRow['first_name'] . ' ' . $uRow['last_name']);
        $initials = strtoupper(substr($uRow['first_name'],0,1) . substr($uRow['last_name'],0,1));
        $hasPhoto = !empty($uRow['profile_photo']);
    }
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
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../assets/css/student/event_detail.css?<?= time() ?>" />
</head>
<body>

<nav>
  <div class="nav-left">
    <img src="../../assets/img/naap logo.png" alt="NAAP Logo">
    <div class="nav-links">
      <a href="../index.php">Home</a>
      <a href="organization.php">Organizations</a>
      <a href="events.php" class="active">Events</a>
    </div>
  </div>
  <div class="nav-actions">
        <?php 
            $is_logged = isset($isLoggedIn) ? $isLoggedIn : (isset($_SESSION['student_id']) && !empty($_SESSION['student_id']));
        ?>
        <?php if ($is_logged): ?>
            <a href="profile-dashboard.php" class="nav-profile" style="text-decoration:none;cursor:pointer;">
                <div class="nav-avatar" style="box-shadow:0 0 0 3px rgba(59,130,246,.5);">
                    <?php 
                        $src = '';
                        if (isset($photoSrc) && !empty($photoSrc)) {
                            $src = $photoSrc;
                        } elseif (isset($student['profile_photo']) && !empty($student['profile_photo'])) {
                            $p = $student['profile_photo'];
                            if (strpos($p, '../../') === 0) { $src = $p; }
                            else { $src = '../../' . ltrim($p, '/'); }
                            $disk_path = __DIR__ . '/../../' . ltrim(str_replace('../../', '', $src), '/');
                            if (!file_exists($disk_path)) $src = '';
                        }
                    ?>
                    <?php if ($src != ''): ?>
                        <img src="<?= htmlspecialchars($src) ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <span style="display:none;"><?= isset($initials) ? htmlspecialchars($initials) : 'S' ?></span>
                    <?php else: ?>
                        <?= isset($initials) ? htmlspecialchars($initials) : (isset($student['first_name']) ? strtoupper(substr($student['first_name'],0,1)) : 'U') ?>
                    <?php endif; ?>
                </div>
                <div class="nav-user-info">
                    <span class="nav-user-name"><?= htmlspecialchars(isset($fullName) ? $fullName : (isset($student['first_name']) ? trim($student['first_name'] . ' ' . $student['last_name']) : 'Student')) ?></span>
                    <span class="nav-user-role">Student</span>
                </div>
            </a>
            <a class="nav-btn-logout" href="../../config/API/student_logout.php">Logout</a>
        <?php else: ?>
            <a class="nav-btn nav-btn-login" href="login.php">Login</a>
            <a class="nav-btn nav-btn-register" href="register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<?php if ($poster): ?>
<img src="<?= htmlspecialchars($poster) ?>" alt="Hero" class="ev-hero">
<?php endif; ?>

<div class="ev-shell">
  <span class="ev-badge"><?= htmlspecialchars($ev['EventStatus'] ?? 'Upcoming') ?></span>
  <h1 class="ev-title"><?= htmlspecialchars($ev['EventName']) ?></h1>
  <p class="ev-org">Hosted by <strong><?= htmlspecialchars($ev['OrgName'] ?? 'NAAP') ?></strong></p>

  <div class="ev-meta-grid">
    <div class="ev-meta-card">
      <ion-icon name="calendar-outline"></ion-icon>
      <div><p>Date</p><strong><?= $dateStr ?></strong></div>
    </div>
    <div class="ev-meta-card">
      <ion-icon name="time-outline"></ion-icon>
      <div><p>Time</p><strong><?= $timeStr ?></strong></div>
    </div>
    <div class="ev-meta-card">
      <ion-icon name="location-outline"></ion-icon>
      <div><p>Location</p><strong><?= htmlspecialchars($place) ?></strong></div>
    </div>
  </div>

  <div class="ev-desc">
    <h3>About this Event</h3>
    <?= nl2br(htmlspecialchars($desc)) ?>
  </div>

  <div class="section-card">
    <h3>Registration</h3>
    <?php if (!$isLoggedIn): ?>
      <p style="color:#94a3b8;font-size:14px;margin-bottom:0;">You must be logged in to register for this event.</p>
      <a href="login.php?redirect=event_detail.php?id=<?= $eventId ?>" class="register-btn" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">Login to Register</a>
    <?php elseif ($isRegistered): ?>
      <div class="register-btn registered"><ion-icon name="checkmark-circle-outline"></ion-icon> You are registered for this event</div>
    <?php else: ?>
      <button class="register-btn" id="regBtn"><ion-icon name="person-add-outline"></ion-icon> Register Now</button>
    <?php endif; ?>
  </div>

  <div class="ai-box">
    <h3><ion-icon name="sparkles" style="color:#a855f7;"></ion-icon> Ask AI about this event</h3>
    <div class="ai-input-row">
      <input type="text" id="aiInput" placeholder="e.g. What should I bring? What is this event about?">
      <button class="ai-ask-btn" id="askBtn">Ask</button>
    </div>
    <div class="ai-response" id="aiResp"></div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script>
  let isAiLoading = false;
  const evContext = {
      name: <?= json_encode($ev['EventName']) ?>,
      date: <?= json_encode($dateStr) ?>,
      time: <?= json_encode($timeStr) ?>,
      loc:  <?= json_encode($place) ?>,
      desc: <?= json_encode($desc) ?>,
      org:  <?= json_encode($ev['OrgName'] ?? '') ?>
  };

  document.getElementById('askBtn')?.addEventListener('click', async () => {
      const q = document.getElementById('aiInput').value.trim();
      const respBox = document.getElementById('aiResp');
      if(!q || isAiLoading) return;
      isAiLoading = true;
      document.getElementById('askBtn').textContent = 'Thinking...';
      respBox.style.display = 'block';
      respBox.innerHTML = '<span style="opacity:0.6;"><ion-icon name="sync-outline" style="animation:spin 1s linear infinite;"></ion-icon> Analyzing event details...</span>';
      
      const apiKey = <?= json_encode($geminiApiKey ?: '') ?>;
      if(!apiKey) {
          respBox.innerHTML = "AI Error: API key missing.";
          resetAiBtn(); return;
      }
      const p = `Context: Event name="${evContext.name}", date="${evContext.date}", time="${evContext.time}", loc="${evContext.loc}", org="${evContext.org}". Desc="${evContext.desc}".\nStudent asks: ${q}\nAnswer concisely and helpfully based strictly on the context. If not in the context, say "I'm not sure based on the provided event details."`;
      
      try {
          const u = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" + apiKey;
          const r = await fetch(u, {
              method:"POST",
              headers:{"Content-Type":"application/json"},
              body: JSON.stringify({contents:[{parts:[{text:p}]}]})
          });
          const d = await r.json();
          if(d.error) throw new Error(d.error.message);
          const txt = d.candidates[0].content.parts[0].text;
          respBox.innerHTML = marked.parse(txt);
      } catch(e) {
          respBox.innerHTML = "<span style='color:#f87171;'>Error: " + e.message + "</span>";
      }
      resetAiBtn();
  });
  
  function resetAiBtn() {
      document.getElementById('askBtn').textContent = 'Ask';
      isAiLoading = false;
  }
  
  document.getElementById('aiInput')?.addEventListener('keydown', e => { if(e.key === 'Enter') document.getElementById('askBtn').click(); });

  // Registration logic
  document.getElementById('regBtn')?.addEventListener('click', async () => {
      const btn = document.getElementById('regBtn');
      btn.disabled = true;
      btn.classList.add('disabled');
      btn.innerHTML = 'Registering...';
      try {
          let fd = new FormData();
          fd.append('EventId', <?= $eventId ?>);
          let res = await fetch('../../config/API/event_register.php', {
              method:'POST',
              body: fd
          });
          let json = await res.json();
          if (json.success) {
              btn.className = 'register-btn registered';
              btn.innerHTML = '<ion-icon name="checkmark-circle-outline"></ion-icon> You are registered for this event';
          } else {
              alert(json.message || 'Failed to register.');
              btn.disabled = false;
              btn.classList.remove('disabled');
              btn.innerHTML = '<ion-icon name="person-add-outline"></ion-icon> Register Now';
          }
      } catch (e) {
          alert('Network Error.');
          btn.disabled = false;
          btn.classList.remove('disabled');
          btn.innerHTML = '<ion-icon name="person-add-outline"></ion-icon> Register Now';
      }
  });
</script>

</body>
</html>
<?php
/** event_detail.php — Student views a specific event, details, and Gemini AI. Exam parts removed. */
session_start();
require_once '../../config/db.php';
require_once '../../config/gemini_key.php'; // $geminiApiKey

$isLoggedIn = !empty($_SESSION['student_id']);
$studentId  = $isLoggedIn ? (int)$_SESSION['student_id'] : 0;
$eventId    = (int)($_GET['id'] ?? 0);

if (!$eventId) { header('Location: events.php'); exit; }

// Load event
$ev = $conn->query("SELECT e.*, o.OrgName FROM event e LEFT JOIN organization o ON e.OrgId=o.OrgId WHERE e.EventId=$eventId")->fetch_assoc();
if (!$ev) { header('Location: events.php'); exit; }

// Check if student already registered
$isRegistered = false;
$regId = 0;
if ($isLoggedIn) {
    $rr = $conn->query("SELECT RegistrationId FROM eventregistration WHERE EventId=$eventId AND UserId=$studentId")->fetch_assoc();
    if ($rr) { $isRegistered = true; $regId = (int)$rr['RegistrationId']; }
}

$dt      = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : null;
$dateStr = $dt ? $dt->format('F j, Y') : 'TBA';
$timeStr = $dt ? $dt->format('g:i A') : 'TBA';
$place   = $ev['EventPlace'] ?: ($ev['EventLocation'] ?: 'TBA');
$desc    = $ev['EventDescription'] ?: ($ev['EventDetails'] ?: '');
$poster  = $ev['EventPicture'] ? '../../'.$ev['EventPicture'] : '';

// Ensure poster exists, else fallback
if (empty($poster) || !file_exists(__DIR__ . '/../../' . ltrim($poster, '../../'))) {
    $poster = '../../assets/img/registrar.jpg';
}

// Handle student profile
$fullName = '';
$initials = '';
$hasPhoto = false;
$student  = [];
if ($isLoggedIn) {
    $uRow = $conn->query("SELECT first_name, last_name, profile_photo FROM user WHERE UserId=$studentId")->fetch_assoc();
    if ($uRow) {
        $student  = $uRow;
        $fullName = trim($uRow['first_name'] . ' ' . $uRow['last_name']);
        $initials = strtoupper(substr($uRow['first_name'],0,1) . substr($uRow['last_name'],0,1));
        $hasPhoto = !empty($uRow['profile_photo']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($ev['EventName']) ?> – NAAP Events</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
</head>
<body>

<nav>
  <div class="nav-left">
    <img src="../../assets/img/naap logo.png" alt="NAAP Logo">
    <div class="nav-links">
      <a href="../index.php">Home</a>
      <a href="organization.php">Organizations</a>
      <a href="events.php">Events</a>
      <a href="../index.php#footer">Contact</a>
    </div>
  </div>
  <div class="nav-actions">
        <?php 
            $is_logged = isset($isLoggedIn) ? $isLoggedIn : (isset($_SESSION['student_id']) && !empty($_SESSION['student_id']));
        ?>
        <?php if ($is_logged): ?>
            <a href="profile-dashboard.php" class="nav-profile" style="text-decoration:none;cursor:pointer;">
                <div class="nav-avatar" style="box-shadow:0 0 0 3px rgba(59,130,246,.5);">
                    <?php 
                        $src = '';
                        if (isset($photoSrc) && !empty($photoSrc)) {
                            $src = $photoSrc;
                        } elseif (isset($student['profile_photo']) && !empty($student['profile_photo'])) {
                            $p = $student['profile_photo'];
                            if (strpos($p, '../../') === 0) { $src = $p; }
                            else { $src = '../../' . ltrim($p, '/'); }
                            $disk_path = __DIR__ . '/../../' . ltrim(str_replace('../../', '', $src), '/');
                            if (!file_exists($disk_path)) $src = '';
                        }
                    ?>
                    <?php if ($src != ''): ?>
                        <img src="<?= htmlspecialchars($src) ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <span style="display:none;"><?= isset($initials) ? htmlspecialchars($initials) : 'S' ?></span>
                    <?php else: ?>
                        <?= isset($initials) ? htmlspecialchars($initials) : (isset($student['first_name']) ? strtoupper(substr($student['first_name'],0,1)) : 'U') ?>
                    <?php endif; ?>
                </div>
                <div class="nav-user-info">
                    <span class="nav-user-name"><?= htmlspecialchars(isset($fullName) ? $fullName : (isset($student['first_name']) ? trim($student['first_name'] . ' ' . $student['last_name']) : 'Student')) ?></span>
                    <span class="nav-user-role">Student</span>
                </div>
            </a>
            <a class="nav-btn-logout" href="../../config/API/student_logout.php">Logout</a>
        <?php else: ?>
            <a class="nav-btn nav-btn-login" href="login.php">Login</a>
            <a class="nav-btn nav-btn-register" href="register.php">Register</a>
        <?php endif; ?>
    </div>
</nav>

<?php if ($poster && $poster !== '../../assets/img/registrar.jpg'): ?>
    <img src="<?= htmlspecialchars($poster) ?>" alt="Hero" class="ev-hero">
<?php else: ?>
    <div class="ev-hero-fallback"><ion-icon name="image-outline"></ion-icon></div>
<?php endif; ?>

<div class="ev-shell">
    
  <div class="event-main-card">
      <span class="ev-badge"><?= htmlspecialchars($ev['EventStatus']) ?></span>
      <h1 class="ev-title"><?= htmlspecialchars($ev['EventName']) ?></h1>
      <p class="ev-org"><ion-icon name="business-outline"></ion-icon> Hosted by <strong><?= htmlspecialchars($ev['OrgName'] ?? 'NAAP') ?></strong></p>
    
      <div class="ev-meta-grid">
        <div class="ev-meta-card">
          <ion-icon name="calendar-clear-outline"></ion-icon>
          <div><p>Date</p><strong><?= $dateStr ?></strong></div>
        </div>
        <div class="ev-meta-card">
          <ion-icon name="time-outline"></ion-icon>
          <div><p>Time</p><strong><?= $timeStr ?></strong></div>
        </div>
        <div class="ev-meta-card">
          <ion-icon name="location-outline"></ion-icon>
          <div><p>Location</p><strong><?= htmlspecialchars($place) ?></strong></div>
        </div>
      </div>
    
      <div class="ev-desc-section">
        <h3><ion-icon name="information-circle-outline"></ion-icon> About this Event</h3>
        <?= nl2br(htmlspecialchars($desc)) ?>
      </div>
    
      <div class="registration-area">
        <?php if (!$isLoggedIn): ?>
          <p>You must be logged in to register for this event.</p>
          <a href="login.php?redirect=event_detail.php?id=<?= $eventId ?>" class="register-btn login"><ion-icon name="log-in-outline"></ion-icon> Login to Register</a>
        <?php elseif ($isRegistered): ?>
          <button class="register-btn registered" disabled><ion-icon name="checkmark-circle"></ion-icon> You are registered for this event</button>
        <?php else: ?>
          <button class="register-btn" id="regBtn"><ion-icon name="add-circle-outline"></ion-icon> Register Now</button>
        <?php endif; ?>
      </div>
  </div>

  <div class="ai-box">
    <h3><ion-icon name="sparkles" style="color:#a855f7;"></ion-icon> Event Assistant AI</h3>
    <div class="ai-input-row">
      <input type="text" id="aiInput" placeholder="Ask anything about this event... e.g. What should I bring?">
      <button class="ai-ask-btn" id="askBtn"><ion-icon name="send"></ion-icon> Ask</button>
    </div>
    <div class="ai-response" id="aiResp"></div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
  let isAiLoading = false;
  const evContext = {
      name: <?= json_encode($ev['EventName']) ?>,
      date: <?= json_encode($dateStr) ?>,
      time: <?= json_encode($timeStr) ?>,
      loc:  <?= json_encode($place) ?>,
      desc: <?= json_encode($desc) ?>,
      org:  <?= json_encode($ev['OrgName']) ?>
  };

  document.getElementById('askBtn')?.addEventListener('click', async () => {
      const q = document.getElementById('aiInput').value.trim();
      const respBox = document.getElementById('aiResp');
      const btn = document.getElementById('askBtn');
      if(!q || isAiLoading) return;
      isAiLoading = true;
      
      btn.innerHTML = '<ion-icon name="sync-outline" class="spin"></ion-icon> Thinking...';
      respBox.style.display = 'block';
      respBox.innerHTML = '<span style="opacity:0.6;display:flex;align-items:center;gap:6px;"><ion-icon name="sync-outline" style="animation:spin 1s linear infinite;"></ion-icon> Analyzing event details...</span>';
      
      const apiKey = <?= json_encode($geminiApiKey ?: '') ?>;
      if(!apiKey) {
          respBox.innerHTML = "AI Error: API key missing.";
          resetAiBtn(); return;
      }
      const p = `Context: Event name="${evContext.name}", date="${evContext.date}", time="${evContext.time}", loc="${evContext.loc}", org="${evContext.org}". Desc="${evContext.desc}".\nStudent asks: ${q}\nAnswer concisely and helpfully based strictly on the context. If not in the context, say "I'm not sure based on the provided event details."`;
      
      try {
          const u = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key=" + apiKey;
          const r = await fetch(u, {
              method:"POST",
              headers:{"Content-Type":"application/json"},
              body: JSON.stringify({contents:[{parts:[{text:p}]}]})
          });
          const d = await r.json();
          if(d.error) throw new Error(d.error.message);
          const txt = d.candidates[0].content.parts[0].text;
          respBox.innerHTML = marked.parse(txt);
      } catch(e) {
          respBox.innerHTML = "<span style='color:#f87171;'>Error: " + e.message + "</span>";
      }
      resetAiBtn();
  });
  
  function resetAiBtn() {
      document.getElementById('askBtn').innerHTML = '<ion-icon name="send"></ion-icon> Ask';
      isAiLoading = false;
  }

  // Handle Enter key for AI
  document.getElementById('aiInput')?.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
          document.getElementById('askBtn').click();
      }
  });

  // Registration logic
  document.getElementById('regBtn')?.addEventListener('click', async () => {
      const btn = document.getElementById('regBtn');
      btn.disabled = true;
      btn.innerHTML = '<ion-icon name="sync-outline" style="animation:spin 1s linear infinite;"></ion-icon> Registering...';
      try {
          let fd = new URLSearchParams();
          fd.append('event_id', <?= $eventId ?>);
          let res = await fetch('../../config/API/register_event.php', {
              method:'POST',
              headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body: fd.toString()
          });
          let json = await res.json();
          if (json.success) {
              btn.className = 'register-btn registered';
              btn.innerHTML = '<ion-icon name="checkmark-circle"></ion-icon> You are registered for this event';
              btn.disabled = true;
          } else {
              alert(json.error || 'Failed to register.');
              btn.disabled = false;
              btn.innerHTML = '<ion-icon name="add-circle-outline"></ion-icon> Register Now';
          }
      } catch (e) {
          alert('Network Error.');
          btn.disabled = false;
          btn.innerHTML = '<ion-icon name="add-circle-outline"></ion-icon> Register Now';
      }
  });
</script>

</body>
</html>
<?php
/** event_detail.php — Student registers for / views a specific event, with pre/post test and Gemini AI */
session_start();
require_once '../../config/db.php';
require_once '../../config/gemini_key.php'; // $geminiApiKey

$isLoggedIn = !empty($_SESSION['student_id']);
$studentId  = $isLoggedIn ? (int)$_SESSION['student_id'] : 0;
$eventId    = (int)($_GET['id'] ?? 0);

if (!$eventId) { header('Location: events.php'); exit; }

// Load event
$ev = $conn->query("SELECT e.*, o.OrgName FROM event e LEFT JOIN organization o ON e.OrgId=o.OrgId WHERE e.EventId=$eventId")->fetch_assoc();
if (!$ev) { header('Location: events.php'); exit; }

// Check if student already registered
$isRegistered = false;
$regId = 0;
if ($isLoggedIn) {
    $rr = $conn->query("SELECT RegistrationId FROM eventregistration WHERE EventId=$eventId AND UserId=$studentId")->fetch_assoc();
    if ($rr) { $isRegistered = true; $regId = (int)$rr['RegistrationId']; }
}

// Check pre/post test tables exist
$conn->query("CREATE TABLE IF NOT EXISTS event_pretest (
    TestId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
    Q1 VARCHAR(10), Q2 VARCHAR(10), Q3 VARCHAR(10), Q4 VARCHAR(10), Q5 VARCHAR(10),
    Score INT DEFAULT 0, SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$conn->query("CREATE TABLE IF NOT EXISTS event_posttest (
    TestId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
    Q1 VARCHAR(10), Q2 VARCHAR(10), Q3 VARCHAR(10), Q4 VARCHAR(10), Q5 VARCHAR(10),
    Score INT DEFAULT 0, SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$conn->query("CREATE TABLE IF NOT EXISTS eventregistration (
    RegistrationId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
    Status VARCHAR(50) DEFAULT 'Registered', RegisteredAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Check pre/post done
$preDone  = $isLoggedIn && $conn->query("SELECT TestId FROM event_pretest WHERE EventId=$eventId AND UserId=$studentId")->num_rows > 0;
$postDone = $isLoggedIn && $conn->query("SELECT TestId FROM event_posttest WHERE EventId=$eventId AND UserId=$studentId")->num_rows > 0;

$dt      = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : null;
$dateStr = $dt ? $dt->format('F j, Y') : 'TBA';
$timeStr = $dt ? $dt->format('g:i A') : 'TBA';
$place   = $ev['EventPlace'] ?: ($ev['EventLocation'] ?: 'TBA');
$desc    = $ev['EventDescription'] ?: ($ev['EventDetails'] ?: '');
$poster  = $ev['EventPicture'] ? '../../'.$ev['EventPicture'] : '../../assets/img/registrar.jpg';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= htmlspecialchars($ev['EventName']) ?> – NAAP Events</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
</head><body>
<nav>
  <div class="nav-left">
    <img src="../../assets/img/naap logo.png" alt="NAAP Logo">
    <div class="nav-links">
      <a href="../index.php">Home</a>
      <a href="organization.php">Organizations</a>
      <a href="events.php" class="active">Events</a>
    </div>
  </div>
  <div class="nav-actions">
            <?php 
                $is_logged = isset($isLoggedIn) ? $isLoggedIn : (isset($_SESSION['student_id']) && !empty($_SESSION['student_id']));
            ?>
            <?php if ($is_logged): ?>
                <a href="profile-dashboard.php" class="nav-profile" style="text-decoration:none;cursor:pointer;">
                    <div class="nav-avatar" style="box-shadow:0 0 0 3px rgba(59,130,246,.5);">
                        <?php 
                            $src = '';
                            if (isset($photoSrc) && !empty($photoSrc)) {
                                $src = $photoSrc;
                            } elseif (isset($student['profile_photo']) && !empty($student['profile_photo'])) {
                                $p = $student['profile_photo'];
                                if (strpos($p, '../../') === 0) { $src = $p; }
                                else { $src = '../../' . ltrim($p, '/'); }
                                // Ensure file actually exists before rendering a broken image
                                $disk_path = __DIR__ . '/../../' . ltrim(str_replace('../../', '', $src), '/');
                                if (!file_exists($disk_path)) $src = '';
                            }
                        ?>
                        <?php if ($src != ''): ?>
                            <img src="<?= htmlspecialchars($src) ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <span style="display:none;"><?= isset($initials) ? htmlspecialchars($initials) : 'S' ?></span>
                        <?php else: ?>
                            <?= isset($initials) ? htmlspecialchars($initials) : (isset($student['first_name']) ? strtoupper(substr($student['first_name'],0,1)) : 'U') ?>
                        <?php endif; ?>
                    </div>
                    <div class="nav-user-info">
                        <span class="nav-user-name"><?= htmlspecialchars(isset($fullName) ? $fullName : (isset($student['first_name']) ? trim($student['first_name'] . ' ' . $student['last_name']) : 'Student')) ?></span>
                        <span class="nav-user-role">Student</span>
                    </div>
                </a>
                <a class="nav-btn-logout" href="../../config/API/student_logout.php">Logout</a>
            <?php else: ?>
                <a class="nav-btn nav-btn-login" href="login.php">Login</a>
                <a class="nav-btn nav-btn-register" href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>

<img src="<?= $poster ?>" class="ev-hero" alt="Event Banner">

<div class="ev-shell">
  <span class="ev-badge"><?= htmlspecialchars($ev['OrgName'] ?? 'NAAP') ?></span>
  <h1 class="ev-title"><?= htmlspecialchars($ev['EventName']) ?></h1>
  <p class="ev-org">Organized by <?= htmlspecialchars($ev['OrgName'] ?? 'NAAP') ?> &bull; Status: <?= htmlspecialchars($ev['EventStatus'] ?? 'Scheduled') ?></p>

  <div class="ev-meta-grid">
    <div class="ev-meta-card"><ion-icon name="calendar-outline"></ion-icon><div><p>Date</p><strong><?= $dateStr ?></strong></div></div>
    <div class="ev-meta-card"><ion-icon name="time-outline"></ion-icon><div><p>Time</p><strong><?= $timeStr ?></strong></div></div>
    <div class="ev-meta-card"><ion-icon name="location-outline"></ion-icon><div><p>Venue</p><strong><?= htmlspecialchars($place) ?></strong></div></div>
    <div class="ev-meta-card"><ion-icon name="desktop-outline"></ion-icon><div><p>Mode</p><strong><?= htmlspecialchars($ev['EventMode'] ?? 'On-site') ?></strong></div></div>
    <?php if ($ev['EventSpeaker']): ?>
    <div class="ev-meta-card"><ion-icon name="mic-outline"></ion-icon><div><p>Speaker</p><strong><?= htmlspecialchars($ev['EventSpeaker']) ?></strong></div></div>
    <?php endif; ?>
    <?php if ($ev['EventCapacity']): ?>
    <div class="ev-meta-card"><ion-icon name="people-outline"></ion-icon><div><p>Capacity</p><strong><?= number_format((int)$ev['EventCapacity']) ?> attendees</strong></div></div>
    <?php endif; ?>
  </div>

  <div class="ev-desc">
    <h3>About This Event</h3>
    <p><?= nl2br(htmlspecialchars($desc ?: 'Join us for this exciting event. More details coming soon.')) ?></p>
  </div>

  <!-- Gemini AI Assistant -->
  <div class="ai-box">
    <h3><ion-icon name="sparkles-outline"></ion-icon> Ask Gemini AI about this event</h3>
    <div class="ai-input-row">
      <input type="text" id="aiInput" placeholder="e.g. What should I prepare for this event?" autocomplete="off">
      <button class="ai-ask-btn" id="aiAskBtn">Ask AI</button>
    </div>
    <div class="ai-response" id="aiResponse">Thinking...</div>
  </div>

  <?php if ($isLoggedIn): ?>
    <!-- PRE-TEST -->
    <?php if (!$preDone): ?>
    <div class="section-card" id="preTestSection">
      <h3>Pre-Event Assessment (Required before registration)</h3>
      <p style="color:#94a3b8;font-size:13px;margin-bottom:16px;">Complete this quick 5-question pre-test about the event topic before registering.</p>
      <form id="preTestForm">
        <?php
        $preQs = [
          ['q'=>'1. What is the primary purpose of this event?','opts'=>['a'=>'Entertainment','b'=>'Academic/Professional Development','c'=>'Sports','d'=>'Fundraising']],
          ['q'=>'2. Which organization is hosting this event?','opts'=>['a'=>$ev['OrgName'],'b'=>'OSA','c'=>'University Admin','d'=>'External Group']],
          ['q'=>'3. What is the event mode?','opts'=>['a'=>$ev['EventMode']??'On-site','b'=>'Hybrid only','c'=>'Virtual only','d'=>'Not specified']],
          ['q'=>'4. Who is the intended audience of this event?','opts'=>['a'=>'Faculty only','b'=>'All students','c'=>'Organization members / general students','d'=>'External guests only']],
          ['q'=>'5. What is the venue of this event?','opts'=>['a'=>htmlspecialchars($place),'b'=>'Online only','c'=>'Main Library','d'=>'Not confirmed']],
        ];
        foreach ($preQs as $qi => $pq):
          $qn = $qi+1;
        ?>
        <div style="margin-bottom:20px;">
          <p style="color:#f1f5f9;font-size:14px;margin-bottom:8px;"><?= $pq['q'] ?></p>
          <div class="test-options">
            <?php foreach ($pq['opts'] as $ok => $ov): ?>
            <label class="test-opt">
              <input type="radio" name="q<?= $qn ?>" value="<?= $ok ?>" required>
              <span><?= strtoupper($ok) ?>. <?= $ov ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <input type="hidden" name="EventId" value="<?= $eventId ?>">
        <button type="submit" class="submit-test-btn">Submit Pre-Test & Continue</button>
      </form>
    </div>
    <?php else: ?>
    <!-- Pre-test done; show registration -->
    <div class="section-card">
      <h3>Event Registration</h3>
      <div class="done-badge">Pre-test completed!</div>
      <?php if ($isRegistered): ?>
        <div class="done-badge">You are registered for this event.</div>
        <div style="color:#94a3b8;font-size:13px;text-align:center;margin-top:8px;">Check your profile dashboard for event details and QR code.</div>
      <?php else: ?>
        <p style="color:#94a3b8;font-size:13px;margin-bottom:12px;">Click below to confirm your attendance registration.</p>
        <button class="register-btn" id="regBtn">Register for this Event</button>
      <?php endif; ?>
    </div>

    <!-- POST-TEST (only show if registered) -->
    <?php if ($isRegistered): ?>
    <div class="section-card">
      <h3>Post-Event Assessment</h3>
      <?php if ($postDone): ?>
        <div class="done-badge">Post-test completed! Your certificate will be available on your dashboard.</div>
      <?php else: ?>
      <p style="color:#94a3b8;font-size:13px;margin-bottom:16px;">Complete the 5-question post-event assessment to receive your certificate.</p>
      <form id="postTestForm">
        <?php
        $postQs = [
          ['q'=>'1. How would you rate the overall quality of this event?','opts'=>['a'=>'Excellent','b'=>'Good','c'=>'Average','d'=>'Poor']],
          ['q'=>'2. Did this event meet your expectations?','opts'=>['a'=>'Yes, exceeded them','b'=>'Yes, met them','c'=>'Partially','d'=>'No']],
          ['q'=>'3. Would you recommend this event to fellow students?','opts'=>['a'=>'Definitely yes','b'=>'Probably yes','c'=>'Not sure','d'=>'No']],
          ['q'=>'4. What was the most valuable takeaway from this event?','opts'=>['a'=>'New skills/knowledge','b'=>'Networking','c'=>'Exposure to industry','d'=>'Entertainment']],
          ['q'=>'5. How organized was the event?','opts'=>['a'=>'Very organized','b'=>'Mostly organized','c'=>'Somewhat disorganized','d'=>'Very disorganized']],
        ];
        foreach ($postQs as $qi => $pq):
          $qn = $qi+1;
        ?>
        <div style="margin-bottom:20px;">
          <p style="color:#f1f5f9;font-size:14px;margin-bottom:8px;"><?= $pq['q'] ?></p>
          <div class="test-options">
            <?php foreach ($pq['opts'] as $ok => $ov): ?>
            <label class="test-opt">
              <input type="radio" name="q<?= $qn ?>" value="<?= $ok ?>" required>
              <span><?= strtoupper($ok) ?>. <?= $ov ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <input type="hidden" name="EventId" value="<?= $eventId ?>">
        <button type="submit" class="submit-test-btn">Submit Post-Event Assessment</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  <?php else: ?>
  <div class="section-card" style="text-align:center;">
    <h3>Want to join this event?</h3>
    <p style="color:#94a3b8;margin-bottom:16px;">You need to be logged in to register for events and access the pre/post assessments.</p>
    <a href="login.php" class="register-btn" style="display:inline-block;width:auto;padding:12px 32px;">Login to Register</a>
  </div>
  <?php endif; ?>
</div>

<script>
// Mark test options as selected on click
document.querySelectorAll('.test-options').forEach(group=>{
  group.querySelectorAll('.test-opt').forEach(lbl=>{
    lbl.addEventListener('click',()=>{
      group.querySelectorAll('.test-opt').forEach(l=>l.classList.remove('selected'));
      lbl.classList.add('selected');
    });
  });
});

// Pre-test submission
const preForm=document.getElementById('preTestForm');
if(preForm){
  preForm.addEventListener('submit',async(e)=>{
    e.preventDefault();
    const fd=new FormData(preForm);
    const btn=preForm.querySelector('button[type=submit]');
    btn.textContent='Submitting...';btn.disabled=true;
    const r=await fetch('../../config/API/submit_pretest.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.success){
      window.location.href=`test_results.php?event_id=<?= $eventId ?>&type=pre`;
    } else {
      alert(d.message||'Error submitting. Try again.');
      btn.textContent='Submit Pre-Test & Continue';btn.disabled=false;
    }
  });
}

// Post-test submission
const postForm=document.getElementById('postTestForm');
if(postForm){
  postForm.addEventListener('submit',async(e)=>{
    e.preventDefault();
    const fd=new FormData(postForm);
    const btn=postForm.querySelector('button[type=submit]');
    btn.textContent='Submitting...';btn.disabled=true;
    const r=await fetch('../../config/API/submit_posttest.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.success){
      window.location.href=`test_results.php?event_id=<?= $eventId ?>&type=post`;
    } else {
      alert(d.message||'Error submitting.');
      btn.textContent='Submit Post-Event Assessment';btn.disabled=false;
    }
  });
}

// Event registration
const regBtn=document.getElementById('regBtn');
if(regBtn){
  regBtn.addEventListener('click',async()=>{
    regBtn.textContent='Registering...';regBtn.disabled=true;
    const fd=new FormData();fd.append('EventId','<?= $eventId ?>');
    const r=await fetch('../../config/API/event_register.php',{method:'POST',body:fd});
    const d=await r.json();
    if(d.success){
      regBtn.textContent='Registered!';regBtn.className='register-btn registered';
      alert('Successfully registered for this event!');
      location.reload();
    } else {
      alert(d.message||'Registration failed.');
      regBtn.textContent='Register for this Event';regBtn.disabled=false;
    }
  });
}

// Gemini AI
document.getElementById('aiAskBtn').addEventListener('click', async()=>{
  const q=document.getElementById('aiInput').value.trim();
  if(!q) return;
  const resp=document.getElementById('aiResponse');
  resp.style.display='block';
  resp.innerHTML='<em style="color:#64748b;">Thinking...</em>';
  const context=`You are an assistant for a student event portal. The event is: "${<?= json_encode($ev['EventName']) ?>}". It is organized by "${<?= json_encode($ev['OrgName'] ?? 'NAAP') ?>}". It will be held on ${<?= json_encode($dateStr) ?>} at ${<?= json_encode($timeStr) ?>} at "${<?= json_encode($place) ?>}". Mode: ${<?= json_encode($ev['EventMode'] ?? 'On-site') ?>}. Description: "${<?= json_encode(substr($desc,0,300)) ?>}". Now answer this student question: "${q}"`;
  try {
    const r=await fetch('../../config/API/gemini_chat.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({prompt:context})});
    const d=await r.json();
    resp.innerHTML=d.text ? d.text.replace(/\n/g,'<br>') : '<em style="color:#ef4444;">No response. Try again.</em>';
  } catch(e) {
    resp.innerHTML='<em style="color:#ef4444;">Could not connect to AI. Check your Gemini API key.</em>';
  }
});
document.getElementById('aiInput').addEventListener('keydown',e=>{ if(e.key==='Enter') document.getElementById('aiAskBtn').click(); });
</script>
</body></html>
