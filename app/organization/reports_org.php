<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgData = [
    'OrgName' => $_SESSION['org_name'] ?? 'Organization',
    'OrgPicture' => $_SESSION['org_logo'] ?? ''
];
$activePage = 'reports';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Reports</title>
  <link rel="stylesheet" href="../../assets/css/organization/reports.css">
  <link rel="stylesheet" href="../../assets/css/organization/nav.css?v=<?= time() ?>">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../assets/css/organization/reports_org.css?<?= time() ?>" />
<script src="../../assets/js/security.js"></script>
</head><body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn" type="button" aria-label="Toggle Sidebar"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title"><h2>Reports</h2><p>Organization performance and attendance analytics</p></div>
      </div>
      <div class="topbar-right">
        <div class="user-box" style="display:flex;align-items:center;gap:10px;padding:6px 12px;border-radius:14px;background:#ffffff;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
          <img src="<?= $logoSrc ?>" alt="Logo" style="width:36px;height:36px;border-radius:10px;object-fit:cover;border:1px solid #e2e8f0;display:block;" onerror="this.src='../../assets/img/philsca.png'">
          <div><strong style="font-size:13.5px;color:#0f172a;"><?= htmlspecialchars($orgName) ?></strong><span style="font-size:11.5px;color:#64748b;display:block;">ORG Admin</span></div>
        </div>
      </div>
    </header>
    <div class="maincontent"><div class="divider"></div>
      <section style="padding:20px 24px;">

        <!-- Summary Cards -->
        <div class="stats-grid" style="margin-bottom:24px;">
          <article class="stat-card"><p>Total Events</p><strong class="text-blue" id="repTotalEvents">—</strong></article>
          <article class="stat-card"><p>Total Members</p><strong class="text-green" id="repTotalMembers">—</strong></article>
          <article class="stat-card"><p>Total Attended</p><strong class="text-orange" id="repTotalAttended">—</strong></article>
          <article class="stat-card"><p>Avg Attendance Rate</p><strong class="text-purple" id="repAttRate">—%</strong></article>
        </div>

        <!-- Events Per Month Chart -->
        <div class="analytics-card" style="margin-bottom:24px;">
          <h4 style="margin-top:0;margin-bottom:14px;font-size:16px;color:#0f172a;">Events Per Month</h4>
          <div class="analytics-canvas-wrap">
            <svg id="repBarChart" viewBox="0 0 400 180" style="width:100%;height:180px;">
              <line x1="40" y1="150" x2="390" y2="150" stroke="#e2e8f0" stroke-width="1"/>
              <text x="200" y="170" text-anchor="middle" font-size="12" fill="#94a3b8">No data yet</text>
            </svg>
          </div>
        </div>

        <!-- Selected Event Diagram Report -->
        <div class="analytics-card" style="margin-bottom:24px;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:20px;">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
            <div>
              <h4 style="margin:0;font-size:16px;color:#0f172a;display:flex;align-items:center;gap:8px;">
                <ion-icon name="pie-chart-outline" style="color:#2563eb;font-size:20px;"></ion-icon>
                Event Diagram Report
              </h4>
              <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Visual diagram breakdown for selected student organization event</p>
            </div>
            <div class="searchable-select-wrap" style="position:relative; min-width:320px; z-index:50;">
              <input type="text" id="eventDiagramComboInput" placeholder="Search & Select Event for Diagram Report..." 
                style="width:100%; padding:9px 14px; border:1px solid #cbd5e1; border-radius:10px; font-size:13px; font-weight:500; color:#1e293b; outline:none; background:#ffffff; cursor:pointer;"
                autocomplete="off">
              <div id="eventDiagramComboDropdown" style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; max-height:220px; overflow-y:auto; background:#ffffff; border:1px solid #cbd5e1; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.1); z-index:9999;">
              </div>
              <input type="hidden" id="eventDiagramSelect" value="">
            </div>
          </div>

          <div id="eventDiagramContainer" style="display:none;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:20px;">
              <!-- Turnout Diagram -->
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;text-align:center;">
                <h5 style="margin:0 0 10px;font-size:12.5px;color:#475569;text-transform:uppercase;letter-spacing:0.04em;font-weight:700;">Turnout &amp; Attendance</h5>
                <svg id="turnoutGaugeSvg" viewBox="0 0 120 120" style="width:100px;height:100px;margin:0 auto;display:block;">
                  <circle cx="60" cy="60" r="50" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                  <circle id="turnoutGaugeFill" cx="60" cy="60" r="50" fill="none" stroke="#2563eb" stroke-width="12" stroke-dasharray="314" stroke-dashoffset="314" stroke-linecap="round" transform="rotate(-90 60 60)"/>
                  <text id="turnoutGaugeText" x="60" y="65" text-anchor="middle" font-size="18" font-weight="700" fill="#0f172a">0%</text>
                </svg>
                <div style="display:flex;justify-content:center;gap:16px;margin-top:10px;font-size:12px;">
                  <span style="color:#2563eb;font-weight:600;">Attended: <strong id="diagAttended">0</strong></span>
                  <span style="color:#64748b;">Capacity: <strong id="diagCapacity">0</strong></span>
                </div>
              </div>

              <!-- Pre vs Post Test Score Diagram -->
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;text-align:center;">
                <h5 style="margin:0 0 14px;font-size:12.5px;color:#475569;text-transform:uppercase;letter-spacing:0.04em;font-weight:700;">Pre-Test vs Post-Test Avg</h5>
                <div style="display:flex;align-items:flex-end;justify-content:center;gap:24px;height:95px;padding-bottom:6px;">
                  <div style="display:flex;flex-direction:column;align-items:center;">
                    <span id="preVal" style="font-size:11px;font-weight:700;color:#64748b;margin-bottom:4px;display:block;">0%</span>
                    <div id="preBar" style="width:34px;height:4px;max-height:50px;background:#94a3b8;border-radius:6px 6px 0 0;transition:height 0.5s ease;"></div>
                    <span style="font-size:11px;font-weight:600;color:#475569;margin-top:6px;">Pre-Test</span>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:center;">
                    <span id="postVal" style="font-size:11px;font-weight:700;color:#16a34a;margin-bottom:4px;display:block;">0%</span>
                    <div id="postBar" style="width:34px;height:4px;max-height:50px;background:#16a34a;border-radius:6px 6px 0 0;transition:height 0.5s ease;"></div>
                    <span style="font-size:11px;font-weight:600;color:#16a34a;margin-top:6px;">Post-Test</span>
                  </div>
                </div>
                <small style="color:#64748b;font-size:11px;display:block;margin-top:4px;" id="scoreGainText">Select event to compare scores</small>
              </div>

              <!-- Participation Rate -->
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;text-align:center;">
                <h5 style="margin:0 0 10px;font-size:12.5px;color:#475569;text-transform:uppercase;letter-spacing:0.04em;font-weight:700;">Participation Rate</h5>
                <svg viewBox="0 0 120 120" style="width:100px;height:100px;margin:auto;display:block;">
                  <circle cx="60" cy="60" r="50" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                  <circle id="participationGaugeFill" cx="60" cy="60" r="50" fill="none" stroke="#8b5cf6" stroke-width="12" stroke-dasharray="314" stroke-dashoffset="314" stroke-linecap="round" transform="rotate(-90 60 60)"/>
                  <text id="participationGaugeText" x="60" y="65" text-anchor="middle" font-size="18" font-weight="700" fill="#0f172a">0%</text>
                </svg>
                <small id="participationText" style="color:#64748b;font-size:11px;display:block;margin-top:6px;">0 registered participants</small>
              </div>

              <!-- Anti-Spoofing & Live Monitoring Stats -->
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;text-align:center;display:flex;flex-direction:column;justify-content:space-between;">
                <div>
                  <h5 style="margin:0 0 12px;font-size:12.5px;color:#475569;text-transform:uppercase;letter-spacing:0.04em;font-weight:700;">Live Monitoring &amp; Anti-Spoofing</h5>
                  <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:#fff;border-radius:8px;border:1px solid #e2e8f0;">
                      <span style="font-size:12px;color:#475569;display:flex;align-items:center;gap:4px;"><ion-icon name="camera-outline"></ion-icon> Anti-Spoofing:</span>
                      <strong id="diagAntiSpoofCount" style="color:#0284c7;font-size:13px;">0 Completed</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:#fff;border-radius:8px;border:1px solid #e2e8f0;">
                      <span style="font-size:12px;color:#475569;display:flex;align-items:center;gap:4px;"><ion-icon name="timer-outline"></ion-icon> Presence Checks:</span>
                      <strong id="diagPresenceCount" style="color:#16a34a;font-size:13px;">0 Completed</strong>
                    </div>
                    <small id="diagMonitoringSummary" style="color:#64748b;font-size:11px;margin-top:2px;">Live verification stats logged</small>
                  </div>
                </div>
                <div style="margin-top:12px;">
                  <a id="diagViewOnlineRosterBtn" href="online_attendance_org.php" style="display:inline-flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:7px 12px;border-radius:8px;background:#eff6ff;border:1px solid #bfdbfe;color:#2563eb;font-size:12px;font-weight:700;text-decoration:none;transition:all 0.2s;">
                    <ion-icon name="list-outline"></ion-icon> View Verification List
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div id="noDiagramMsg" style="text-align:center;padding:30px 10px;color:#94a3b8;font-size:13px;">
            Please select an event above to generate its visual diagram report.
          </div>
        </div>

        <!-- Per-Event Attendance Table -->
        <div class="events-table-card" style="margin-bottom:24px;">
          <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <h4 style="margin:0;">Attendance by Event</h4>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <input type="text" id="repEventSearch" placeholder="Search event name..." style="padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;outline:none;font-family:inherit;min-width:180px;">
              <select id="repStatusFilter" style="padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;outline:none;font-family:inherit;background:#fff;cursor:pointer;">
                <option value="">All Statuses</option>
                <option value="Completed">Completed</option>
                <option value="Ongoing">Ongoing</option>
                <option value="Scheduled">Scheduled</option>
                <option value="Cancelled">Cancelled</option>
              </select>
            </div>
          </div>
          <div style="overflow-x:auto;">
            <table class="rep-table">
              <thead><tr><th>Event</th><th>Date</th><th>Capacity</th><th>Attended</th><th>Rate</th><th>Status</th></tr></thead>
              <tbody id="repEventTable"><tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>

        <!-- Members by Year Level -->
        <div class="events-table-card">
          <div style="padding:16px 20px;border-bottom:1px solid #f1f5f9;"><h4 style="margin:0;">Members by Year Level</h4></div>
          <div style="overflow-x:auto;">
            <table class="rep-table">
              <thead><tr><th>Year Level</th><th>Count</th><th>Share</th></tr></thead>
              <tbody id="repYearTable"><tr><td colspan="3" style="text-align:center;padding:30px;color:#94a3b8;">Loading…</td></tr></tbody>
            </table>
          </div>
        </div>

      </section>
    </div>
  </div>
</div>


<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/org.js"></script>
  <script src="../../assets/js/org/reports_org.js?v=<?= time() ?>"></script>
</body></html>
