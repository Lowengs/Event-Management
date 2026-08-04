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
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Online Attendance | NAAP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
<style>
  body { margin:0; min-height:100vh; background:#0b0f19; color:#e2e8f0; font-family:Inter,sans-serif; display:grid; place-items:center; padding:24px; box-sizing:border-box; }
  .card { width:min(640px,100%); background:#17233b; border:1px solid rgba(255,255,255,0.1); border-radius:24px; padding:32px; box-shadow:0 20px 50px rgba(0,0,0,0.5); backdrop-filter:blur(16px); }
  .badge { display:inline-block; color:#34d399; background:rgba(16,185,129,0.15); border:1px solid rgba(52,211,153,0.3); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px; }
  .event { margin:20px 0; padding:20px; background:#0f172a; border-radius:16px; border:1px solid rgba(255,255,255,0.06); }
  .event h1 { font-size:22px; margin:0 0 8px; color:#fff; }
  .muted { color:#94a3b8; font-size:14px; line-height:1.55; }
  
  /* Participation Rate Stats Grid */
  .participation-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin:20px 0; }
  .part-box { background:rgba(15,23,42,0.8); border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:16px; text-align:center; }
  .part-val { font-size:22px; font-weight:800; color:#38bdf8; }
  .part-lbl { font-size:12px; color:#94a3b8; font-weight:600; text-transform:uppercase; margin-top:2px; }

  .actions { display:flex; gap:12px; margin-top:24px; flex-wrap:wrap; }
  button, a { border:0; border-radius:12px; padding:12px 22px; font:inherit; font-weight:700; cursor:pointer; text-decoration:none; transition:all 0.2s; }
  .in { background:linear-gradient(135deg,#2563eb,#3b82f6); color:#fff; box-shadow:0 4px 14px rgba(37,99,235,0.4); }
  .in:hover { opacity:0.9; }
  .out { background:linear-gradient(135deg,#0d9488,#14b8a6); color:#fff; box-shadow:0 4px 14px rgba(13,148,136,0.4); }
  .out:hover { opacity:0.9; }
  .back { background:rgba(255,255,255,0.08); color:#fff; border:1px solid rgba(255,255,255,0.12); }
  .back:hover { background:rgba(255,255,255,0.15); }

  #message { margin-top:18px; min-height:22px; font-size:14px; font-weight:600; }
  
  /* Periodic Presence Modal Prompt */
  .presence-modal { display:none; position:fixed; inset:0; z-index:99999; background:rgba(15,23,42,0.8); backdrop-filter:blur(8px); align-items:center; justify-content:center; padding:20px; }
  .presence-card { background:#1e293b; border:1px solid rgba(56,189,248,0.4); border-radius:20px; padding:28px; max-width:420px; width:100%; text-align:center; box-shadow:0 25px 50px rgba(0,0,0,0.5); }
</style>
</head>
<body>
<main class="card">
<?php if (!$event): ?>
  <span class="badge" style="color:#ef4444;background:rgba(239,68,68,0.15);border-color:rgba(239,68,68,0.3);">Event Unavailable</span>
  <h1 style="margin-top:14px;">Online Event Not Found</h1>
  <p class="muted">No event matching ID #<?= (int)$eventId ?> was found in the system database.</p>
  <a class="back" href="profile-dashboard.php">Back to Dashboard</a>
<?php else: ?>
  <span class="badge">Online Attendance</span>
  <div class="event">
    <h1><?= htmlspecialchars($event['EventName']) ?></h1>
    <p class="muted">Status: <strong style="color:#34d399;"><?= htmlspecialchars($event['EventStatus']) ?></strong> &middot; Mode: <strong><?= htmlspecialchars($event['EventMode']) ?></strong></p>
  </div>

  <p class="muted">Check in when you join the event and check out when leaving. Presence checks and anti-spoofing challenges, when requested by your organization, appear separately in the Student Portal.</p>
  
  <div class="actions">
    <button class="in" onclick="recordAttendance('Log In')">Check In</button>
    <a class="back" href="profile-dashboard.php">Back to Dashboard</a>
  </div>
  <div id="message" aria-live="polite"></div>

  <!-- Periodic Presence Check Modal -->
  <div id="presenceModal" class="presence-modal">
    <div class="presence-card">
      <div style="width:54px;height:54px;border-radius:50%;background:rgba(56,189,248,0.15);border:1.5px solid rgba(56,189,248,0.4);color:#38bdf8;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 16px;">⏱️</div>
      <h3 style="margin:0 0 8px;font-size:1.25rem;color:#fff;font-weight:800;">Periodic Presence Check</h3>
      <p style="color:#cbd5e1;font-size:0.9rem;line-height:1.5;margin-bottom:20px;">
        Anti-spoofing system periodic check: Please confirm your active participation in this event session.
      </p>
      <div style="font-weight:800;font-size:1.4rem;color:#f59e0b;margin-bottom:20px;" id="presenceTimerVal">30s</div>
      <button type="button" onclick="confirmPresence()" style="width:100%;padding:14px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;border-radius:12px;font-weight:800;font-size:1rem;cursor:pointer;">
        I Am Present
      </button>
    </div>
  </div>

  <script src="../../assets/js/modal_alert.js"></script>
  <script>
    async function recordAttendance(logType) {
      const message = document.getElementById('message');
      message.style.color = '#94a3b8';
      message.textContent = 'Recording ' + logType + '…';
      const fd = new FormData();
      fd.append('EventId', '<?= (int)$event['EventId'] ?>');
      fd.append('Method', 'online_self');
      fd.append('LogType', logType);

      try {
        const res = await fetch('../../config/API/endpoints/index.php?action=student_record_attendance', { method: 'POST', body: fd });
        const data = await res.json();
        message.style.color = data.success ? '#34d399' : '#fca5a5';
        message.textContent = data.message || (data.success ? 'Attendance recorded.' : 'Unable to record attendance.');
        
      } catch (e) {
        message.style.color = '#fca5a5';
        message.textContent = 'Unable to contact the attendance service.';
      }
    }

  </script>
<?php endif; ?>
</main>
</body>
</html>
