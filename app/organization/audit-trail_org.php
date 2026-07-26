<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}
$orgId   = (int)$_SESSION['org_id'];
$org_id  = $orgId;
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();
$orgName = $orgData['OrgName'] ?? ($_SESSION['org_name'] ?? 'Organization');
$activePage = 'audit';


$now  = date('Y-m-d');
$week = date('Y-m-d', strtotime('monday this week'));
$mon  = date('Y-m-01');
$auditTotal  = (int)($conn->query("SELECT COUNT(*) FROM auditlog WHERE ActorType='org' AND ActorId=$orgId")->fetch_row()[0] ?? 0);
$auditToday  = (int)($conn->query("SELECT COUNT(*) FROM auditlog WHERE ActorType='org' AND ActorId=$orgId AND DATE(`Date`)='$now'")->fetch_row()[0] ?? 0);
$auditWeek   = (int)($conn->query("SELECT COUNT(*) FROM auditlog WHERE ActorType='org' AND ActorId=$orgId AND DATE(`Date`)>='$week'")->fetch_row()[0] ?? 0);
$auditMonth  = (int)($conn->query("SELECT COUNT(*) FROM auditlog WHERE ActorType='org' AND ActorId=$orgId AND DATE(`Date`)>='$mon'")->fetch_row()[0] ?? 0);


$log_items = [];
$r_log = $conn->query("SELECT * FROM auditlog WHERE ActorType='org' AND ActorId=$orgId ORDER BY Date DESC LIMIT 500");
if ($r_log) {
    while ($row = $r_log->fetch_assoc()) {
        
        if (empty($row['ActorName'])) {
            $row['ActorName'] = $orgName;
        }
        $log_items[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP ORG Portal - Audit Trail</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="../../assets/css/organization/audit-trail.css" />
  <link rel="stylesheet" href="../../assets/css/organization/nav.css" />
  <link rel="icon" href="../../assets/img/philsca.png" />
  
  <link rel="stylesheet" href="../../assets/css/organization/audit-trail_org.css?<?= time() ?>" />
</head>
<body>
  <div class="dashboard-layout">
    <?php include '_org_sidebar.php'; ?>
    <div class="overlay" id="sidebarOverlay"></div>

    <div class="content-shell">
      <header class="topbar">
        <div class="topbar-left">
          <button class="hamburger" id="hamburgerBtn" aria-label="Open menu">
            <ion-icon name="menu-outline"></ion-icon>
          </button>
          <a class="back-btn" href="dashboard_org.php" aria-label="Back to dashboard">
            <ion-icon name="arrow-back-outline"></ion-icon>
          </a>
          <div class="page-title">
            <h2>Audit Trail</h2>
            <p>Track organization activities and actions</p>
          </div>
        </div>
      </header>

      <div class="divider"></div>

      <main class="main-content audit-page">
        <section class="audit-stats" aria-label="Audit trail summary">
          <article class="audit-stat-card">
            <p class="stat-label">Total Activities</p>
            <h3 class="stat-value stat-blue"><?= $auditTotal ?></h3>
          </article>

          <article class="audit-stat-card">
            <p class="stat-label">Today</p>
            <h3 class="stat-value stat-green"><?= $auditToday ?></h3>
          </article>

          <article class="audit-stat-card">
            <p class="stat-label">This Week</p>
            <h3 class="stat-value stat-violet"><?= $auditWeek ?></h3>
          </article>

          <article class="audit-stat-card">
            <p class="stat-label">This Month</p>
            <h3 class="stat-value stat-orange"><?= $auditMonth ?></h3>
          </article>
        </section>

        <section class="filter-bar" aria-label="Audit trail filters">
          <div class="search-box">
            <ion-icon name="search-outline"></ion-icon>
            <input type="search" id="auditSearch" placeholder="Search activities..." aria-label="Search activities" />
          </div>
          <select id="auditCategoryFilter" style="padding:8px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;color:#374151;background:#fff;cursor:pointer;">
            <option value="">All Actions</option>
            <option value="certificate">Certificates</option>
            <option value="event cancelled">Event Cancelled</option>
            <option value="event delayed">Event Delayed</option>
            <option value="event rescheduled">Event Rescheduled</option>
            <option value="update event">Event Updated</option>
            <option value="create event">Event Created</option>
            <option value="login">Login / Logout</option>
            <option value="delete">Delete</option>
            <option value="announcement">Announcements</option>
          </select>
        </section>

        <section class="activity-card" aria-label="Activity logs">
          <header class="activity-header">
            <h3>Activity Logs</h3>
          </header>

          <div class="activity-table-wrap">
            <table class="activity-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Action</th>
                  <th>Details</th>
                  <th>IP Address</th>
                  <th>Timestamp</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="auditLogBody">
                <?php if (empty($log_items)): ?>
                <tr><td colspan="5" data-label="" style="text-align:center;padding:30px;color:#94a3b8;">No activity logs found.</td></tr>
                <?php else: ?>
                  <?php foreach ($log_items as $l): ?>
                  <tr>
                    <td data-label="User">
                      <div style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($l['ActorName']) ?></div>
                    </td>
                    <td data-label="Action">
                      <div style="display:flex;align-items:center;gap:6px;font-weight:500;">
                        <?php
                          $act = strtolower($l['Action'] ?? '');
                          if (str_contains($act, 'cancel')) {
                              echo '<ion-icon name="close-circle-outline" style="color:#ef4444;font-size:1.1rem;"></ion-icon>';
                          } elseif (str_contains($act, 'delay')) {
                              echo '<ion-icon name="hourglass-outline" style="color:#f59e0b;font-size:1.1rem;"></ion-icon>';
                          } elseif (str_contains($act, 'reschedul') || str_contains($act, 'scheduled')) {
                              echo '<ion-icon name="calendar-number-outline" style="color:#0ea5e9;font-size:1.1rem;"></ion-icon>';
                          } elseif (str_contains($act, 'issue cert') || str_contains($act, 'certificate template')) {
                              echo '<ion-icon name="ribbon-outline" style="color:#8b5cf6;font-size:1.1rem;"></ion-icon>';
                          } elseif (str_contains($act, 'update event') || str_contains($act, 'create event')) {
                              echo '<ion-icon name="calendar-outline" style="color:#3b82f6;font-size:1.1rem;"></ion-icon>';
                          } elseif (str_contains($act, 'login') || str_contains($act, 'logout')) {
                              echo '<ion-icon name="log-in-outline" style="color:#10b981;font-size:1.1rem;"></ion-icon>';
                          } elseif (str_contains($act, 'delete')) {
                              echo '<ion-icon name="trash-outline" style="color:#f43f5e;font-size:1.1rem;"></ion-icon>';
                          } elseif (str_contains($act, 'ongoing') || str_contains($act, 'completed')) {
                              echo '<ion-icon name="checkmark-circle-outline" style="color:#22c55e;font-size:1.1rem;"></ion-icon>';
                          } else {
                              echo '<ion-icon name="analytics-outline" style="color:#003366;font-size:1.1rem;"></ion-icon>';
                          }
                        ?>
                        <?= htmlspecialchars($l['Action'] ?? 'Unknown') ?>
                      </div>
                    </td>
                    <td data-label="Details" style="color:#475569;font-size:0.85rem;max-width:300px;">
                      <?php
                        $raw = $l['Details'] ?? '';
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                          $parts = [];
                          foreach ($decoded as $k => $v) {
                            if (strtolower($k) === 'event_id' || strtolower($k) === 'id' || substr(strtolower($k), -3) === '_id') continue;
                            
                            $label = ucwords(str_replace('_', ' ', $k));
                            $parts[] = '<span style="font-weight:600;color:#334155;">' . htmlspecialchars($label) . ':</span> ' . htmlspecialchars((string)$v);
                          }
                          echo implode('<br>', $parts);
                        } else {
                          echo htmlspecialchars($raw);
                        }
                      ?>
                    </td>
                    <td data-label="IP" style="color:#94a3b8;font-size:0.8rem;font-family:monospace;">
                      <?= htmlspecialchars($l['IpAddress'] ?? '—') ?>
                    </td>
                    <td data-label="Timestamp" style="color:#64748b;font-size:0.85rem;">
                      <div><?= date('M j, Y', strtotime($l['Date'])) ?></div>
                      <div style="font-size:0.75rem;margin-top:2px;"><?= date('g:i A', strtotime($l['Date'])) ?></div>
                    </td>
                    <td data-label="Status">
                      <?php if (($l['Status'] ?? 'success') === 'success'): ?>
                      <span style="display:inline-block;padding:4px 10px;background:#dcfce7;color:#166534;font-size:0.75rem;border-radius:99px;font-weight:600;">Success</span>
                      <?php else: ?>
                      <span style="display:inline-block;padding:4px 10px;background:#fee2e2;color:#991b1b;font-size:0.75rem;border-radius:99px;font-weight:600;">Failed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          
          <div class="pagination-bar" id="auditPaginationBar">
            <span class="page-info" id="auditPageInfo">Showing <strong>1–25</strong> of <strong>0</strong> entries</span>
            <div class="pagination-controls" id="auditPageControls"></div>
          </div>
        </section>
      </main>
    </div>
  </div>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/org.js"></script>

  <script src="../../assets/js/org/audit-trail_org.js"></script>
</body>
</html>