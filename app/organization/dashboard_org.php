<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/img_helpers.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgId   = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();
$orgName = $orgData['OrgName'] ?? 'Organization';
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/organization/dashboard.css">
  <link rel="stylesheet" href="../../assets/css/organization/nav.css?v=<?= time() ?>">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../assets/css/organization/dashboard_org.css?<?= time() ?>" />
</head>
<body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>

  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn" type="button" onclick="document.getElementById('sidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('show');"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title">
          <h2>Dashboard</h2>
          <p>Welcome back, <?= htmlspecialchars($orgName) ?></p>
        </div>
      </div>
      <div class="topbar-right">
        <button class="icon-btn" aria-label="Notifications"><ion-icon name="notifications-outline"></ion-icon></button>
        <div class="user-box">
          <div><strong><?= htmlspecialchars($orgName) ?></strong><span>ORG Admin</span></div>
        </div>
      </div>
    </header>
    <div class="divider"></div>

    <main class="main-content">
      <!-- Stat Cards -->
      <div class="stat-card-container">
        <div class="stat-card">
          <div class="stat-icon-bg blue"><ion-icon name="people-outline" class="stat-icon"></ion-icon></div>
          <div class="stat-text"><p class="stat-title">Total Members</p><p class="stat-value" id="statTotalMembers">—</p></div>
          <div class="stat-trend muted"><span>Registered</span></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-bg purple"><ion-icon name="calendar-clear-outline" class="stat-icon"></ion-icon></div>
          <div class="stat-text"><p class="stat-title">Upcoming Events</p><p class="stat-value" id="statUpcomingEvents">—</p></div>
          <div class="stat-trend muted"><span>Scheduled</span></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-bg orange"><ion-icon name="stats-chart-outline" class="stat-icon"></ion-icon></div>
          <div class="stat-text"><p class="stat-title">Attendance Rate</p><p class="stat-value" id="statAttRate">—%</p></div>
          <div class="stat-trend muted"><span>All events</span></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-bg red"><ion-icon name="time-outline" class="stat-icon"></ion-icon></div>
          <div class="stat-text"><p class="stat-title">Pending Approvals</p><p class="stat-value" id="statPendingReports">—</p></div>
          <div class="stat-trend muted"><span>Needs action</span></div>
        </div>
      </div>

      <!-- Analytics Strip (SVG charts stay, they're decorative) -->
      <section class="analytics-strip">
        <article class="analytics-card">
          <h4>Total Events Overview</h4>
          <p>Total Events Conducted: <strong id="anaTotal">—</strong></p>
          <div class="analytics-canvas-wrap">
            <canvas id="eventsOverviewChart"></canvas>
          </div>
        </article>
        <article class="analytics-card">
          <h4>Overall Attendance Summary</h4>
          <p>General attendance performance across all events</p>
          <div class="analytics-canvas-wrap">
            <svg viewBox="0 0 200 200" style="width:100%;height:180px;" id="donutSvg">
              <circle cx="100" cy="100" r="60" fill="none" stroke="#e2e8f0" stroke-width="18"/>
              <circle cx="100" cy="100" r="60" fill="none" stroke="#16a34a" stroke-width="18" stroke-dasharray="0 377" id="donutArc" transform="rotate(-90 100 100)" style="transition:stroke-dasharray 1s"/>
              <circle cx="100" cy="100" r="38" fill="white"/>
              <text x="100" y="105" font-size="22" font-weight="bold" fill="#16a34a" text-anchor="middle" id="donutLabel">0%</text>
            </svg>
          </div>
        </article>
        <article class="analytics-card">
          <h4>Events Per Month (Activity Trend)</h4>
          <p>Organization activity over time</p>
          <div class="analytics-canvas-wrap">
            <canvas id="eventsTrendChart"></canvas>
          </div>
        </article>
      </section>

      <div class="dashboard-panel">

        <!-- Recent Events from DB -->
        <section class="recent-events">
          <div class="panel-head">
            <h4 class="panel-title">Recent Events</h4>
            <a href="events_org.php" class="panel-link" style="text-decoration:none;color:inherit;">View All <ion-icon name="chevron-forward-outline"></ion-icon></a>
          </div>
          <div class="events-list" id="dashboardEventsList">
            <div class="event-item"><p style="color:#94a3b8;text-align:center;padding:20px;">Loading…</p></div>
          </div>
        </section>

        <!-- Notifications -->
        <section class="recent-notifications">
          <div class="panel-head">
            <h4 class="panel-title">Recent Notifications</h4>
            <a href="announcement.php" class="panel-link" style="text-decoration:none;color:inherit;">View All <ion-icon name="chevron-forward-outline"></ion-icon></a>
          </div>
          <div class="notifications-list" id="dashboardNotifList">
            <div class="notification-item"><ion-icon name="notifications-outline"></ion-icon><div><h5>Loading…</h5></div></div>
          </div>
        </section>
      </div>
    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/dashboard.js"></script>
<script src="../../assets/js/org/org.js"></script>
  <script src="../../assets/js/org/dashboard_org.js"></script>
</body>
</html>