<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/img_helpers.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }

$dashboardQuery = $_GET;
$dashboardQuery['action'] = 'get_org_dashboard';
$_GET = $dashboardQuery;
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$dashboardApi = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$stats = $dashboardApi['stats'] ?? [];
$monthlyEvents = $dashboardApi['monthly_events'] ?? [];
$events = $dashboardApi['events'] ?? [];
$orgName = $_SESSION['org_name'] ?? ($orgName ?? 'Organization');
$activePage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/organization/dashboard.css">
  <link rel="stylesheet" href="../../assets/css/organization/nav.css?v=<?= time() ?>">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/organization/dashboard_org.css?v=<?= time() ?>" />
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
        <button class="hamburger" id="hamburgerBtn" type="button" aria-label="Toggle Sidebar"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title">
          <h2>Dashboard</h2>
          <p>Welcome back, <?= htmlspecialchars($orgName) ?></p>
        </div>
      </div>
      <div class="topbar-right">
        <div class="user-box">
          <div><strong><?= htmlspecialchars($orgName) ?></strong><span>ORG Admin</span></div>
        </div>
      </div>
    </header>
    <div class="divider"></div>

    <main class="main-content">
      <!-- Stat Cards Grid -->
      <div class="stat-card-container">
        <div class="stat-card">
          <div class="stat-icon-bg blue"><ion-icon name="people-outline" class="stat-icon"></ion-icon></div>
          <div class="stat-text"><p class="stat-title">Total Members</p><p class="stat-value" id="statTotalMembers"><?= (int)($stats['total_members'] ?? 0) ?></p></div>
          <div class="stat-trend muted"><span>Registered</span></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-bg purple"><ion-icon name="calendar-clear-outline" class="stat-icon"></ion-icon></div>
          <div class="stat-text"><p class="stat-title">Upcoming Events</p><p class="stat-value" id="statUpcomingEvents"><?= (int)($stats['upcoming_events'] ?? 0) ?></p></div>
          <div class="stat-trend muted"><span>Scheduled</span></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-bg orange"><ion-icon name="stats-chart-outline" class="stat-icon"></ion-icon></div>
          <div class="stat-text"><p class="stat-title">Attendance Rate</p><p class="stat-value" id="statAttRate"><?= (int)($stats['attendance_rate'] ?? 100) ?>%</p></div>
          <div class="stat-trend muted"><span>All events</span></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-bg red"><ion-icon name="time-outline" class="stat-icon"></ion-icon></div>
          <div class="stat-text"><p class="stat-title">Pending Approvals</p><p class="stat-value" id="statPendingReports"><?= (int)($stats['pending_reports'] ?? 0) ?></p></div>
          <div class="stat-trend muted"><span>Needs action</span></div>
        </div>
      </div>

      <!-- Analytics Charts Section -->
      <section class="analytics-strip">
        <article class="analytics-card">
          <h4>Total Events Overview</h4>
          <p>Total Events Conducted: <strong id="anaTotal"><?= (int)($stats['total_events'] ?? 0) ?></strong></p>
          <div class="analytics-canvas-wrap" style="height:200px;position:relative;">
            <canvas id="eventsOverviewChart"></canvas>
          </div>
        </article>

        <article class="analytics-card">
          <h4>Overall Attendance Summary</h4>
          <p>General attendance performance across all events</p>
          <div class="analytics-canvas-wrap" style="display:flex;align-items:center;justify-content:center;height:200px;">
            <svg viewBox="0 0 200 200" style="width:180px;height:180px;" id="donutSvg">
              <circle cx="100" cy="100" r="60" fill="none" stroke="#e2e8f0" stroke-width="18"/>
              <circle cx="100" cy="100" r="60" fill="none" stroke="#16a34a" stroke-width="18" stroke-dasharray="377 0" id="donutArc" transform="rotate(-90 100 100)" style="transition:stroke-dasharray 1s ease;"/>
              <circle cx="100" cy="100" r="38" fill="white"/>
              <text x="100" y="106" font-size="22" font-weight="bold" fill="#16a34a" text-anchor="middle" id="donutLabel"><?= (int)($stats['attendance_rate'] ?? 100) ?>%</text>
            </svg>
          </div>
        </article>

        <article class="analytics-card">
          <h4>Events Per Month (Activity Trend)</h4>
          <p>Organization activity over time</p>
          <div class="analytics-canvas-wrap" style="height:200px;position:relative;">
            <canvas id="eventsTrendChart"></canvas>
          </div>
        </article>
      </section>

      <!-- Bottom Activity Panel Grid -->
      <div class="dashboard-panel">
        <!-- Recent Events -->
        <section class="recent-events">
          <div class="panel-head">
            <h4 class="panel-title">Recent Events</h4>
            <a href="events_org.php" class="panel-link" style="text-decoration:none;color:inherit;">View All <ion-icon name="chevron-forward-outline"></ion-icon></a>
          </div>
          <div class="events-list" id="dashboardEventsList">
            <?php if (empty($events)): ?>
              <p style="color:#94a3b8;text-align:center;padding:20px;">No events recorded yet.</p>
            <?php else: ?>
              <?php foreach (array_slice($events, 0, 5) as $ev): ?>
                <?php 
                  $dt = !empty($ev['EventDateTime']) ? date('M j, Y', strtotime($ev['EventDateTime'])) : 'TBA'; 
                  $st = strtolower($ev['EventStatus'] ?? 'scheduled');
                ?>
                <div class="event-item">
                  <div class="event-left">
                    <h5><?= htmlspecialchars($ev['EventName']) ?></h5>
                    <div class="event-meta">
                      <span><ion-icon name="calendar-outline"></ion-icon> <?= $dt ?></span>
                      <span><ion-icon name="location-outline"></ion-icon> <?= htmlspecialchars($ev['EventLocation'] ?: ($ev['EventPlace'] ?: 'TBA')) ?></span>
                    </div>
                  </div>
                  <div class="badge <?= $st ?>"><?= htmlspecialchars($ev['EventStatus'] ?: 'Scheduled') ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <!-- Notifications -->
        <section class="recent-notifications">
          <div class="panel-head">
            <h4 class="panel-title">Recent Announcements</h4>
            <a href="announcement.php" class="panel-link" style="text-decoration:none;color:inherit;">View All <ion-icon name="chevron-forward-outline"></ion-icon></a>
          </div>
          <div class="notifications-list" id="dashboardNotifList">
            <div class="notification-item">
              <ion-icon name="notifications-outline"></ion-icon>
              <div><h5>Loading announcements…</h5></div>
            </div>
          </div>
        </section>
      </div>

    </main>
  </div>
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="../../assets/js/org/org.js"></script>
<script src="../../assets/js/org/dashboard_org.js?v=<?= time() ?>"></script>
</body>
</html>
