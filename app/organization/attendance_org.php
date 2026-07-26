<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgId   = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();
$activePage = 'attendance';


$events = [];
$r = $conn->query("SELECT EventId, EventName, EventDateTime, EventStatus FROM event WHERE OrgId=$orgId AND EventStatus = 'Ongoing' ORDER BY EventDateTime DESC LIMIT 30");
if ($r) while($row=$r->fetch_assoc()) $events[] = $row;
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Attendance</title>
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../assets/css/organization/attendance_org.css?<?= time() ?>" />
</head><body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title"><h2>Attendance</h2><p>Track event attendance via QR code or facial recognition</p></div>
      </div>
    </header>
    <div class="maincontent"><div class="divider"></div>
      <div class="att-container">

        
        <div class="att-controls">
          <div>
            <label>Select Event</label>
            <select class="att-sel" id="eventSelect" style="min-width:260px;">
              <option value="">— Choose an event —</option>
              <?php foreach($events as $ev): ?>
              <?php $dt = $ev['EventDateTime'] ? date('M j, Y', strtotime($ev['EventDateTime'])) : ''; ?>
              <option value="<?= $ev['EventId'] ?>" data-status="<?= htmlspecialchars($ev['EventStatus'] ?? '') ?>"><?= htmlspecialchars($ev['EventName']) ?> (<?= $dt ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>



          <div>
            <label>Manual Entry</label>
            <input type="text" class="att-inp" id="manualId" placeholder="Student ID…" style="min-width:160px;">
          </div>

          <button class="ctrl-btn btn-unified" id="btnUnified" style="background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;font-weight:700;display:inline-flex;align-items:center;gap:6px;"><ion-icon name="scan-circle-outline" style="font-size:18px;"></ion-icon> Start Unified Scanner (Face &amp; QR)</button>
          <button class="ctrl-btn" style="background:#0ea5e9;color:#fff;" id="btnUploadQR"><ion-icon name="image-outline"></ion-icon> Upload QR Image</button>
          <input type="file" id="qrFileInput" accept="image/*" style="display:none;">
          <button class="ctrl-btn btn-antispoof" id="btnAntiSpoof" disabled title="Enable anti-spoofing liveness check (ongoing events only)">
            <ion-icon name="shield-checkmark-outline"></ion-icon> Anti-Spoofing
          </button>
          <button class="ctrl-btn btn-stop" id="btnStop" style="display:none;"><ion-icon name="stop-circle-outline"></ion-icon> Stop Camera</button>
          <button class="ctrl-btn" style="background:#10b981;color:#fff;" id="btnManual"><ion-icon name="add-outline"></ion-icon> Record</button>
          <button class="ctrl-btn" style="background:#f59e0b;color:#fff;display:none;" id="btnSync"><ion-icon name="cloud-upload-outline"></ion-icon> Sync Offline (<span id="offlineCount">0</span>)</button>
        </div>

        
        <div class="att-status" id="attStatus"></div>

        
        <div class="camera-box" id="cameraBox" style="display:none;">
          <video id="cameraFeed" autoplay muted playsinline></video>
          <div class="camera-overlay">
            <div class="scan-frame" id="scanFrame">
              <div class="scan-line"></div>
            </div>
          </div>
          <canvas id="qrCanvas" style="display:none;"></canvas>
        </div>

        
        <div class="events-table-card" style="margin-top:8px;">
          <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:12px;">
              <h4 style="margin:0;">Attendance Log</h4>
              <span id="attCount" style="font-size:13px;color:#64748b;">0 recorded</span>
            </div>
            <a href="certificate-templates.php" class="ctrl-btn" style="background:#10b981;color:#fff;text-decoration:none;padding:8px 14px;font-size:12px;display:flex;align-items:center;gap:6px;">
              <ion-icon name="ribbon-outline"></ion-icon> Issue Certificates
            </a>
          </div>
          <div class="att-table-wrap">
            <table class="att-table">
              <thead><tr><th>#</th><th>Student Name</th><th>Student ID</th><th>Type</th><th>Method</th><th>Time</th><th>Action</th></tr></thead>
              <tbody id="attLog"><tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">Select an event to view attendance.</td></tr></tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>


<div id="attModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:24px 24px;border-radius:16px;width:100%;max-width:320px;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h3 style="margin:0 0 16px 0;color:#1e293b;font-size:18px;">Confirm Attendance</h3>
        <img id="mdlStudentPhoto" src="../../assets/img/default-avatar.png" alt="Profile" style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:12px;border:3px solid #e2e8f0;">
        <h2 id="mdlStudentName" style="margin:0 0 4px 0;color:#0f172a;font-size:20px;">Name</h2>
        <p id="mdlStudentId" style="margin:0 0 4px 0;font-weight:600;color:#3b82f6;font-size:14px;">ID</p>
        <p id="mdlStudentDetails" style="margin:0 0 20px 0;color:#64748b;font-size:13px;">Course - Year - Section</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button id="mdlBtnCancel" style="flex:1;padding:10px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#475569;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.2s;">Cancel</button>
            <button id="mdlBtnRecord" style="flex:1;padding:10px;border-radius:8px;border:none;background:#10b981;color:#fff;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.2s;">Record</button>
        </div>
    </div>
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


<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

<script src="../../assets/js/lib/face-api.min.js"></script>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/org.js"></script>
<script src="../../assets/js/org/attendance_org.js?v=<?= time() ?>"></script>
</body></html>