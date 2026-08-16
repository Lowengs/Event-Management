<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }

$orgId   = (int)$_SESSION['org_id'];
$activePage = 'attendance';

// Fetch Organization Events via API
$_GET['action'] = 'get_org_events';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$evApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$allOrgEvents = $evApiRes['data'] ?? [];

$events = array_values(array_filter($allOrgEvents, function($ev) {
    $status = strtolower(trim($ev['EventStatus'] ?? ''));
    if (in_array($status, ['archived', 'cancelled'], true) || empty($ev['EventDateTime'])) {
        return false;
    }

    $eventStart = strtotime($ev['EventDateTime']);
    $eventEnd = !empty($ev['EndDateTime']) ? strtotime($ev['EndDateTime']) : false;
    if (!$eventStart) {
        return false;
    }

    // Scheduled events are available only during the one-hour pre-start
    // exception. Ongoing events are always available, while completed events
    // remain available for one hour after their end.
    // Events without an end time retain the existing two-hour default duration.
    $eventEnd = $eventEnd ?: ($eventStart + 7200);
    $now = time();

    if ($status === 'scheduled') {
        return $now >= ($eventStart - 3600) && $now < $eventStart;
    }

    if ($status === 'ongoing') {
        return true;
    }

    if ($status === 'completed') {
        return $now > $eventEnd && $now <= ($eventEnd + 3600);
    }

    return false;
}));
$orgName = $_SESSION['org_name'] ?? 'Organization';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Attendance Management</title>
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="stylesheet" href="../../assets/css/organization/attendance_org.css?<?= time() ?>" />
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title">
          <h2>Attendance Management</h2>
          <p>Scan QR code, use facial recognition, or manually enter student IDs to track event attendance</p>
        </div>
      </div>
    </header>

    <div class="maincontent">
      <div class="divider"></div>
      <div class="att-container" style="padding:20px 24px;">

        <!-- Status Toast Banner -->
        <div class="att-status" id="attStatus" style="display:none;padding:12px 18px;border-radius:12px;font-weight:700;font-size:0.9rem;margin-bottom:16px;box-shadow:0 4px 12px rgba(0,0,0,0.05);"></div>

        <!-- Controls Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:20px;margin-bottom:24px;">
          
          <!-- Card 1: Event Selection & Scanner Actions -->
          <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;padding:22px;box-shadow:0 6px 18px rgba(0,0,0,0.03);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
              <label style="font-size:13px;font-weight:700;color:#334155;display:flex;align-items:center;gap:6px;">
                <ion-icon name="calendar-outline" style="color:#2563eb;font-size:18px;"></ion-icon> Select Event
              </label>
              <!-- Log Type Toggle -->
              <div style="display:inline-flex;background:#f1f5f9;padding:3px;border-radius:10px;border:1px solid #e2e8f0;">
                <button type="button" id="btnLogTypeIn" onclick="setLogType('Log In')" style="padding:4px 12px;border:none;border-radius:7px;font-weight:700;font-size:11px;cursor:pointer;background:#2563eb;color:#fff;transition:all 0.2s;">Check-In</button>
                <button type="button" id="btnLogTypeOut" onclick="setLogType('Log Out')" style="padding:4px 12px;border:none;border-radius:7px;font-weight:700;font-size:11px;cursor:pointer;background:transparent;color:#64748b;transition:all 0.2s;">Check-Out</button>
              </div>
            </div>

            <select class="att-sel" id="eventSelect" style="width:100%;height:44px;padding:0 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:0.9rem;font-weight:600;color:#0f172a;outline:none;background:#f8fafc;margin-bottom:16px;">
              <?php if(empty($events)): ?>
                <option value="">— No events available —</option>
              <?php else: ?>
                <?php foreach($events as $idx => $ev): ?>
                <?php 
                  $dt = $ev['EventDateTime'] ? date('M j, Y g:i A', strtotime($ev['EventDateTime'])) : '';
                  $evMode = trim($ev['EventMode'] ?? '');
                  $place = strtolower(trim(($ev['EventPlace'] ?? '') . ' ' . ($ev['EventLocation'] ?? '')));
                  if (empty($evMode) || strtolower($evMode) === 'on-site') {
                      if (strpos($place, 'online') !== false || strpos($place, 'zoom') !== false || strpos($place, 'teams') !== false || strpos($place, 'gmeet') !== false) {
                          $evMode = 'Online';
                      } else {
                          $evMode = $evMode ?: 'On-site';
                      }
                  }
                ?>
                <option value="<?= $ev['EventId'] ?>" <?= $idx === 0 ? 'selected' : '' ?> data-status="<?= htmlspecialchars($ev['EventStatus'] ?? '') ?>" data-mode="<?= htmlspecialchars($evMode) ?>">
                  <?= htmlspecialchars($ev['EventName']) ?> (<?= $dt ?>) [<?= htmlspecialchars($evMode) ?>]
                </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <button class="ctrl-btn btn-unified" id="btnUnified" onclick="startCamera('unified')" style="flex:1;min-width:180px;height:42px;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;font-weight:700;border:none;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 12px rgba(37,99,235,0.3);">
                <ion-icon name="scan-circle-outline" style="font-size:20px;"></ion-icon> Start Unified Scanner
              </button>
              <button class="ctrl-btn" id="btnUploadQR" onclick="document.getElementById('qrFileInput').click()" style="height:42px;padding:0 16px;background:#0ea5e9;color:#fff;font-weight:700;border:none;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <ion-icon name="image-outline"></ion-icon> Upload QR
              </button>
              <input type="file" id="qrFileInput" accept="image/*" style="display:none;" onchange="handleQrFileUpload(event)">
              <button class="ctrl-btn btn-stop" id="btnStop" onclick="stopCamera()" style="display:none;height:42px;padding:0 16px;background:#ef4444;color:#fff;font-weight:700;border:none;border-radius:10px;cursor:pointer;align-items:center;gap:6px;">
                <ion-icon name="stop-circle-outline"></ion-icon> Stop Camera
              </button>
            </div>
          </div>

          <!-- Card 2: Manual Student Entry -->
          <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;padding:22px;box-shadow:0 6px 18px rgba(0,0,0,0.03);display:flex;flex-direction:column;justify-content:space-between;">
            <div>
              <label style="font-size:13px;font-weight:700;color:#334155;display:flex;align-items:center;gap:6px;margin-bottom:14px;">
                <ion-icon name="person-add-outline" style="color:#10b981;font-size:18px;"></ion-icon> Manual Student Attendance Entry
              </label>
              <p style="font-size:0.82rem;color:#64748b;margin:0 0 12px;line-height:1.4;">Enter Student ID / Student No. (e.g. 1006 or 2024-0001) to record attendance directly.</p>
              <div style="display:flex;gap:10px;">
                <input type="text" class="att-inp" id="manualId" placeholder="Enter Student ID / No. …" style="flex:1;height:42px;padding:0 14px;border:1.5px solid #cbd5e1;border-radius:10px;font-size:0.9rem;outline:none;">
                <button class="ctrl-btn" id="btnManual" onclick="recordManual()" style="height:42px;padding:0 20px;background:#10b981;color:#fff;font-weight:700;border:none;border-radius:10px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(16,185,129,0.3);">
                  <ion-icon name="add-circle-outline" style="font-size:18px;"></ion-icon> Record
                </button>
              </div>
            </div>
          </div>

        </div>

        <!-- Camera Scanner Feed Frame -->
        <div class="camera-box" id="cameraBox" style="display:none;margin-bottom:24px;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.2);position:relative;background:#0f172a;max-width:640px;margin-left:auto;margin-right:auto;">
          <video id="cameraFeed" autoplay muted playsinline style="width:100%;height:360px;object-fit:cover;"></video>
          <div class="camera-overlay" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;">
            <div class="scan-frame" id="scanFrame" style="width:240px;height:240px;border:3px dashed #38bdf8;border-radius:16px;position:relative;">
              <div class="scan-line" style="position:absolute;height:3px;background:linear-gradient(90deg,transparent,#38bdf8,transparent);width:100%;animation:scan 2s infinite ease-in-out;"></div>
            </div>
          </div>
          <canvas id="qrCanvas" style="display:none;"></canvas>
        </div>

        <!-- Attendance Log Table Section -->
        <div class="events-table-card" style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 6px 18px rgba(0,0,0,0.03);overflow:hidden;">
          <div style="padding:16px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
              <h3 style="margin:0;font-size:1.05rem;font-weight:800;color:#0f172a;">Live Attendance Log</h3>
              <span id="attCount" style="font-size:0.8rem;font-weight:700;color:#2563eb;background:#eff6ff;padding:4px 10px;border-radius:20px;border:1px solid #bfdbfe;">0 recorded</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <a href="certificate-templates.php" class="ctrl-btn" style="background:linear-gradient(135deg,#10b981,#059669);color:#fff;text-decoration:none;padding:9px 16px;font-size:0.85rem;font-weight:700;border-radius:10px;display:inline-flex;align-items:center;gap:6px;box-shadow:0 4px 12px rgba(16,185,129,0.3);">
                <ion-icon name="ribbon-outline" style="font-size:18px;"></ion-icon> Issue Certificates
              </a>
            </div>
          </div>
          <div class="att-table-wrap" style="overflow-x:auto;">
            <table class="att-table" style="width:100%;border-collapse:collapse;text-align:left;">
              <thead>
                <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                  <th style="padding:12px 16px;font-size:0.8rem;font-weight:700;color:#475569;">#</th>
                  <th style="padding:12px 16px;font-size:0.8rem;font-weight:700;color:#475569;">Student Name</th>
                  <th style="padding:12px 16px;font-size:0.8rem;font-weight:700;color:#475569;">Student ID</th>
                  <th style="padding:12px 16px;font-size:0.8rem;font-weight:700;color:#475569;">Log Type</th>
                  <th style="padding:12px 16px;font-size:0.8rem;font-weight:700;color:#475569;">Method</th>
                  <th style="padding:12px 16px;font-size:0.8rem;font-weight:700;color:#475569;">Time</th>
                  <th style="padding:12px 16px;font-size:0.8rem;font-weight:700;color:#475569;text-align:right;">Action</th>
                </tr>
              </thead>
              <tbody id="attLog">
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">Select an event to load attendance records.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
<!-- Attendance Confirmation Modal -->
<div id="attModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.75);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)closeAttendanceModal()">
    <div style="background:#ffffff;padding:28px 24px;border-radius:20px;width:100%;max-width:360px;text-align:center;box-shadow:0 25px 50px rgba(0,0,0,0.3);font-family:'Inter',sans-serif;position:relative;z-index:100000;">
        <div style="width:72px;height:72px;border-radius:50%;overflow:hidden;margin:0 auto 12px;border:3px solid #3b82f6;box-shadow:0 4px 14px rgba(59,130,246,0.3);">
            <img id="mdlStudentPhoto" src="../../assets/img/philsca.png" alt="Profile" style="width:100%;height:100%;object-fit:cover;">
        </div>
        <h3 id="mdlStudentName" style="margin:0 0 4px;color:#0f172a;font-size:18px;font-weight:800;">Student Name</h3>
        <p id="mdlStudentId" style="margin:0 0 4px;font-weight:700;color:#2563eb;font-size:14px;">ID</p>
        <p id="mdlStudentDetails" style="margin:0 0 20px;color:#64748b;font-size:13px;font-weight:500;">Course - Year - Section</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button type="button" id="mdlBtnCancel" onclick="closeAttendanceModal()" style="flex:1;padding:12px;border-radius:10px;border:1.5px solid #cbd5e1;background:#f8fafc;color:#334155;font-weight:700;font-size:14px;cursor:pointer;transition:all 0.2s;">Cancel</button>
            <button type="button" id="mdlBtnRecord" onclick="confirmAttendanceModal()" style="flex:1;padding:12px;border-radius:10px;border:none;background:#10b981;color:#fff;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 4px 12px rgba(16,185,129,0.3);transition:all 0.2s;">Record Log In</button>
        </div>
    </div>
</div>

<!-- Anti-Spoofing Overlay Modal -->
<div class="antispoof-overlay" id="antiSpoofOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.85);backdrop-filter:blur(8px);z-index:99999;align-items:center;justify-content:center;flex-direction:column;padding:20px;">
  <div class="antispoof-box" style="background:#1e293b;border:1px solid #334155;border-radius:20px;padding:24px;max-width:440px;width:100%;text-align:center;box-shadow:0 25px 50px rgba(0,0,0,0.5);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <span style="font-size:0.75rem;font-weight:700;color:#38bdf8;text-transform:uppercase;letter-spacing:0.06em;">Anti-Spoofing Verification</span>
      <span id="asCountdownBadge" style="font-size:0.82rem;font-weight:800;color:#38bdf8;background:rgba(56,189,248,0.15);padding:4px 10px;border-radius:20px;border:1px solid rgba(56,189,248,0.3);display:inline-flex;align-items:center;gap:4px;">
        <ion-icon name="time-outline"></ion-icon> <span id="asCountdownText">5.0s</span>
      </span>
    </div>
    <video id="asVideo" class="antispoof-video" autoplay muted playsinline style="width:100%;height:220px;border-radius:12px;object-fit:cover;border:2px solid #38bdf8;margin-bottom:16px;"></video>
    <div class="antispoof-challenge">
      <div class="challenge-text" id="asChallengeText" style="font-size:1.1rem;font-weight:800;color:#f8fafc;margin-bottom:6px;">Preparing Challenge…</div>
      <div class="challenge-sub" id="asChallengeSubText" style="font-size:0.85rem;color:#94a3b8;margin-bottom:14px;">Please position your face clearly inside the frame</div>
      <div class="challenge-timer-bar" style="height:6px;background:#334155;border-radius:999px;overflow:hidden;"><div class="challenge-timer-fill" id="asTimerFill" style="height:100%;width:100%;background:#38bdf8;transition:width 0.1s linear;"></div></div>
    </div>
  </div>
  <div class="antispoof-status" id="asStatusText" style="margin-top:14px;color:#f8fafc;font-weight:700;"></div>
  <div class="antispoof-actions" style="margin-top:16px;">
    <button class="as-btn as-btn-cancel" id="asBtnCancel" onclick="closeAntiSpoofModal()" style="padding:10px 24px;border-radius:10px;border:none;background:#ef4444;color:#fff;font-weight:700;cursor:pointer;">Cancel</button>
  </div>
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script src="../../assets/js/lib/face-api.min.js"></script>
<script src="../../assets/js/org/org.js"></script>
<script src="../../assets/js/org/attendance_org.js?v=<?= time() ?>"></script>
</body>
</html>
