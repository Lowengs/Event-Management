<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }

$orgId = (int)$_SESSION['org_id'];
$activePage = 'attendance';

// Fetch Organization Events via API
$_GET['action'] = 'get_org_events';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$evApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$allOrgEvents = $evApiRes['data'] ?? [];

// Prioritize Online and Hybrid events
$events = array_values(array_filter($allOrgEvents, function($ev) {
    $status = strtolower(trim($ev['EventStatus'] ?? ''));
    if (in_array($status, ['archived', 'cancelled'], true)) {
        return false;
    }
    return true;
}));

// Sort so Online/Hybrid events are at the top
usort($events, function($a, $b) {
    $modeA = strtolower(trim($a['EventMode'] ?? ''));
    $modeB = strtolower(trim($b['EventMode'] ?? ''));
    $isOnlineA = ($modeA === 'online' || $modeA === 'hybrid');
    $isOnlineB = ($modeB === 'online' || $modeB === 'hybrid');
    if ($isOnlineA && !$isOnlineB) return -1;
    if (!$isOnlineA && $isOnlineB) return 1;
    return strtotime($b['EventDateTime'] ?? '0') - strtotime($a['EventDateTime'] ?? '0');
});

$orgName = $_SESSION['org_name'] ?? 'Organization';
$selectedEventId = (int)($_GET['eventId'] ?? ($events[0]['EventId'] ?? 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Anti-Spoofing & Online Attendance</title>
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="stylesheet" href="../../assets/css/organization/attendance_org.css?<?= time() ?>" />
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <style>
    .tab-switcher {
      display: inline-flex;
      background: #f1f5f9;
      padding: 4px;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      margin-bottom: 20px;
      gap: 4px;
    }
    .tab-switch-btn {
      padding: 8px 18px;
      border-radius: 9px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      color: #64748b;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
    }
    .tab-switch-btn:hover {
      color: #0f172a;
      background: rgba(255,255,255,0.6);
    }
    .tab-switch-btn.active {
      background: #ffffff;
      color: #2563eb;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .columns-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      align-items: start;
    }
    @media (max-width: 1024px) {
      .columns-grid {
        grid-template-columns: 1fr;
      }
    }
    .column-card {
      background: #ffffff;
      border-radius: 18px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.03);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .column-card.passed {
      border: 1.5px solid #86efac;
    }
    .column-card.not-passed {
      border: 1.5px solid #fca5a5;
    }
    .column-header {
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #f1f5f9;
    }
    .column-header.passed {
      background: #f0fdf4;
    }
    .column-header.not-passed {
      background: #fef2f2;
    }
    .badge-status {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 3px 9px;
      border-radius: 7px;
      font-size: 11.5px;
      font-weight: 700;
    }
    .badge-status.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .badge-status.warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    .badge-status.danger  { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .badge-status.info    { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
    .badge-status.neutral { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
  </style>
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
          <h2>Online Event Attendance & Anti-Spoofing</h2>
          <p>Separate breakdown of students who passed anti-spoofing verification and those who did not</p>
        </div>
      </div>
    </header>

    <div class="maincontent">
      <div class="divider"></div>
      <div style="padding:20px 24px;">

        <!-- Tab Switcher: On-Site vs Online Attendance -->
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div class="tab-switcher">
            <a href="attendance_org.php" class="tab-switch-btn">
              <ion-icon name="qr-code-outline"></ion-icon> On-Site Attendance (QR & Kiosk)
            </a>
            <a href="online_attendance_org.php" class="tab-switch-btn active">
              <ion-icon name="videocam-outline"></ion-icon> Online Attendance & Live Monitoring
            </a>
          </div>
        </div>

        <!-- Event Selection & Search Bar -->
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;padding:20px 22px;box-shadow:0 6px 18px rgba(0,0,0,0.03);margin-bottom:24px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:center;">
            
            <!-- Event Selector -->
            <div>
              <label style="font-size:13px;font-weight:700;color:#334155;display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                <ion-icon name="calendar-outline" style="color:#2563eb;font-size:18px;"></ion-icon> Select Online Event
              </label>
              <select id="eventSelect" onchange="loadOnlineAttendance(this.value)" style="width:100%;height:44px;padding:0 14px;border:1.5px solid #cbd5e1;border-radius:12px;font-size:0.92rem;font-weight:700;color:#0f172a;outline:none;background:#f8fafc;">
                <?php if(empty($events)): ?>
                  <option value="">— No events available —</option>
                <?php else: ?>
                  <?php foreach($events as $ev): ?>
                  <?php 
                    $dt = $ev['EventDateTime'] ? date('M j, Y g:i A', strtotime($ev['EventDateTime'])) : '';
                    $evMode = trim($ev['EventMode'] ?? 'Online');
                    $isSel = ($ev['EventId'] == $selectedEventId);
                  ?>
                  <option value="<?= $ev['EventId'] ?>" <?= $isSel ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ev['EventName']) ?> (<?= $dt ?>) [<?= htmlspecialchars($evMode) ?> - <?= htmlspecialchars($ev['EventStatus'] ?? 'Scheduled') ?>]
                  </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <!-- Search Input -->
            <div>
              <label style="font-size:13px;font-weight:700;color:#334155;display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                <ion-icon name="search-outline" style="color:#2563eb;font-size:18px;"></ion-icon> Search Students
              </label>
              <input type="text" id="rosterSearchInput" oninput="renderTwoColumns()" placeholder="Search by name, student ID, course, section..." style="width:100%;height:44px;padding:0 14px;border:1.5px solid #cbd5e1;border-radius:12px;font-size:0.9rem;outline:none;background:#f8fafc;">
            </div>

          </div>
        </div>

        <!-- 2-Column Comparison Layout: Passed vs Did Not Pass -->
        <div class="columns-grid">

          <!-- Column 1: Passed Anti-Spoofing / Continuous Monitoring -->
          <div class="column-card passed">
            <div class="column-header passed">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:20px;">
                  <ion-icon name="checkmark-circle"></ion-icon>
                </div>
                <div>
                  <h3 style="margin:0;font-size:15px;font-weight:800;color:#14532d;">Passed Anti-Spoofing</h3>
                  <p style="margin:2px 0 0;font-size:11.5px;color:#166534;">Verified live face presence on camera</p>
                </div>
              </div>
              <span id="badgePassedCount" style="background:#16a34a;color:#fff;font-weight:800;font-size:12px;padding:4px 12px;border-radius:20px;">0 Students</span>
            </div>

            <div style="overflow-x:auto;max-height:600px;overflow-y:auto;">
              <table style="width:100%;border-collapse:collapse;text-align:left;font-size:12.5px;">
                <thead>
                  <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:2;">
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">#</th>
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">Student</th>
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">Check-In</th>
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">Verified At</th>
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">Status</th>
                  </tr>
                </thead>
                <tbody id="passedRosterBody">
                  <tr><td colspan="5" style="text-align:center;padding:30px;color:#94a3b8;">Loading passed students...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Column 2: Did Not Pass / Missing Anti-Spoofing -->
          <div class="column-card not-passed">
            <div class="column-header not-passed">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:#fee2e2;color:#b91c1c;display:flex;align-items:center;justify-content:center;font-size:20px;">
                  <ion-icon name="close-circle"></ion-icon>
                </div>
                <div>
                  <h3 style="margin:0;font-size:15px;font-weight:800;color:#7f1d1d;">Did Not Pass Anti-Spoofing</h3>
                  <p style="margin:2px 0 0;font-size:11.5px;color:#991b1b;">Missed, not completed, or pending verification</p>
                </div>
              </div>
              <span id="badgeMissingCount" style="background:#dc2626;color:#fff;font-weight:800;font-size:12px;padding:4px 12px;border-radius:20px;">0 Students</span>
            </div>

            <div style="overflow-x:auto;max-height:600px;overflow-y:auto;">
              <table style="width:100%;border-collapse:collapse;text-align:left;font-size:12.5px;">
                <thead>
                  <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:2;">
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">#</th>
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">Student</th>
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">Attendance</th>
                    <th style="padding:10px 14px;font-weight:700;color:#475569;">Anti-Spoofing State</th>
                  </tr>
                </thead>
                <tbody id="missingRosterBody">
                  <tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;">Loading missing students...</td></tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>

      </div>
    </div>
  </div>
</div>

<script src="../../assets/js/org/org.js"></script>
<script src="../../assets/js/org/online_attendance_org.js?v=<?= time() ?>"></script>
</body>
</html>
