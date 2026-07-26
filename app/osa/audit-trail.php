<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';


$search      = trim($_GET['search'] ?? '');
$actionFilter = $_GET['action'] ?? '';
$userFilter   = $_GET['user']   ?? '';
$dateFilter   = $_GET['date']   ?? '';
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 5;


$today_count = $conn->query("SELECT COUNT(*) FROM auditlog WHERE ActorType = 'osa' AND DATE(Date) = CURDATE()")->fetch_row()[0] ?? 0;
$week_count  = $conn->query("SELECT COUNT(*) FROM auditlog WHERE ActorType = 'osa' AND YEARWEEK(Date,1) = YEARWEEK(NOW(),1)")->fetch_row()[0] ?? 0;
$success_count = $conn->query("SELECT COUNT(*) FROM auditlog WHERE ActorType = 'osa' AND Status = 'success'")->fetch_row()[0] ?? 0;
$failed_count  = $conn->query("SELECT COUNT(*) FROM auditlog WHERE ActorType = 'osa' AND Status = 'failed'")->fetch_row()[0] ?? 0;


$action_types = [];
$r_at = $conn->query("SELECT DISTINCT Action FROM auditlog WHERE ActorType = 'osa' ORDER BY Action ASC LIMIT 50");
if ($r_at) while ($row = $r_at->fetch_assoc()) $action_types[] = $row['Action'];


$users = [];
$r_u = $conn->query("SELECT DISTINCT ActorName FROM auditlog WHERE ActorType = 'osa' AND ActorName IS NOT NULL ORDER BY ActorName ASC LIMIT 50");
if ($r_u) while ($row = $r_u->fetch_assoc()) $users[] = $row['ActorName'];


$where_parts = ["ActorType = 'osa'"];
if ($search !== '') {
    $s = $conn->real_escape_string($search);
    $where_parts[] = "(Action LIKE '%$s%' OR ActorName LIKE '%$s%' OR Details LIKE '%$s%')";
}
if ($actionFilter !== '') {
    $a = $conn->real_escape_string($actionFilter);
    $where_parts[] = "Action = '$a'";
}
if ($userFilter !== '') {
    $u = $conn->real_escape_string($userFilter);
    $where_parts[] = "ActorName = '$u'";
}
if ($dateFilter !== '') {
    $d = $conn->real_escape_string($dateFilter);
    [$day,$month,$year] = explode('/', $d) + ['','',''];
    if ($year && $month && $day) {
        $where_parts[] = "DATE(Date) = '$year-$month-$day'";
    }
}

$where_sql = implode(' AND ', $where_parts);
$log_items = [];
$total_logs = (int)($conn->query("SELECT COUNT(*) FROM auditlog WHERE $where_sql")->fetch_row()[0] ?? 0);
$total_pages = max(1, (int)ceil($total_logs / $perPage));
$currentPage = min($currentPage, $total_pages);
$offset = ($currentPage - 1) * $perPage;
$r_log = $conn->query("SELECT * FROM auditlog WHERE $where_sql ORDER BY Date DESC LIMIT $perPage OFFSET $offset");
if ($r_log) while ($row = $r_log->fetch_assoc()) $log_items[] = $row;

$pageParams = array_filter([
  'search' => $search !== '' ? $search : null,
  'action' => $actionFilter !== '' ? $actionFilter : null,
  'user' => $userFilter !== '' ? $userFilter : null,
  'date' => $dateFilter !== '' ? $dateFilter : null,
]);

function pageUrl(array $baseParams, int $page): string {
  $params = $baseParams;
  $params['page'] = $page;
  return '?' . http_build_query($params);
}


function actionIcon(string $action): array {
    $a = strtolower($action);
    if (str_contains($a,'login'))   return ['checkmark-circle-outline','green'];
    if (str_contains($a,'logout'))  return ['log-out-outline','blue'];
    if (str_contains($a,'approv'))  return ['checkmark-circle-outline','green'];
    if (str_contains($a,'declin'))  return ['close-circle-outline','red'];
    if (str_contains($a,'creat'))   return ['add-circle-outline','blue'];
    if (str_contains($a,'updat') || str_contains($a,'edit')) return ['create-outline','blue'];
    if (str_contains($a,'delet'))   return ['trash-outline','red'];
    if (str_contains($a,'export') || str_contains($a,'report')) return ['document-text-outline','slate'];
    if (str_contains($a,'fail') || str_contains($a,'block') || str_contains($a,'wrong')) return ['shield-outline','red'];
    return ['ellipse-outline','slate'];
}

function actorChip(string $type): string {
    return match($type) {
        'osa'          => 'chip system',
        'organization' => 'chip organization',
        default        => 'chip event',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Audit Trail</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/audit-trail.css?<?= time() ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet" />

  <link rel="icon" href="../../assets/img/philsca.png" />
</head>
<body>
  <header>
    <button id="hamburger-btn" class="hamburger" aria-label="Open menu">
      <ion-icon name="menu-outline"></ion-icon>
    </button>
    <h1>NAAP OSA PORTAL</h1>
  </header>

  <main>
    <nav class="navigation" id="sidebar">
      <ul>
        <li>
          <div class="span">
            <div class="logo-border">
              <img src="../../assets/img/philsca.png" alt="NAAP Logo" />
            </div>
            <div class="text">
              <h1>NAAP</h1>
              <p>OSA Portal</p>
            </div>
          </div>
        </li>
        <li><a href="dashboard_final.php" class="nav"><ion-icon name="grid-outline"></ion-icon><span>Dashboard</span></a></li>
        <li><a href="organization.php"   class="nav"><ion-icon name="business-outline"></ion-icon><span>Organization</span></a></li>
        <li><a href="calendar.php"       class="nav"><ion-icon name="calendar-number-outline"></ion-icon><span>Calendar</span></a></li>
        <li><a href="events.php"         class="nav"><ion-icon name="calendar-outline"></ion-icon><span>Events</span></a></li>
        <li><a href="students.php"       class="nav"><ion-icon name="people-outline"></ion-icon><span>Students</span></a></li>
        <li><a href="announcement.php"   class="nav"><ion-icon name="megaphone-outline"></ion-icon><span>Announcements</span></a></li>
        <li><a href="reports.php"        class="nav"><ion-icon name="document-text-outline"></ion-icon><span>Reports</span></a></li>
        <li><a href="audit-trail.php"    class="nav active"><ion-icon name="analytics-outline"></ion-icon><span>Audit Trail</span></a></li>
        <li><a href="messages.php"       class="nav"><ion-icon name="chatbox-outline"></ion-icon><span>Messages</span></a></li>
        <li><a href="settings.php"       class="nav"><ion-icon name="cog-outline"></ion-icon><span>Settings</span></a></li>
        <li><a href="../../config/API/osa_logout.php" class="nav"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
      </ul>
    </nav>

    <div class="maincontent">
      <div class="pagebar">
        <a class="back-btn" href="dashboard_final.php" aria-label="Back to dashboard">
          <ion-icon name="arrow-back-outline"></ion-icon>
        </a>

        <div class="pagebar-text">
          <h2>Audit Trail</h2>
          <p>System activity logs and accountability tracking</p>
        </div>
      </div>

      <div class="divider"></div>

      <section class="audit-stats">
        <article class="audit-stat-card">
          <div class="stat-icon blue"><ion-icon name="timer-outline"></ion-icon></div>
          <div class="stat-content">
            <p>Today's Activities</p>
            <h3><?= (int)$today_count ?></h3>
          </div>
        </article>

        <article class="audit-stat-card">
          <div class="stat-icon purple"><ion-icon name="calendar-outline"></ion-icon></div>
          <div class="stat-content">
            <p>This Week</p>
            <h3><?= (int)$week_count ?></h3>
          </div>
        </article>

        <article class="audit-stat-card">
          <div class="stat-icon green"><ion-icon name="checkmark-circle-outline"></ion-icon></div>
          <div class="stat-content">
            <p>Successful</p>
            <h3><?= (int)$success_count ?></h3>
          </div>
        </article>

        <article class="audit-stat-card">
          <div class="stat-icon red"><ion-icon name="shield-outline"></ion-icon></div>
          <div class="stat-content">
            <p>Failed Attempts</p>
            <h3><?= (int)$failed_count ?></h3>
          </div>
        </article>
      </section>

      
      <form method="GET" action="">
        <section class="audit-filters">
          <div class="filter-field">
            <label><ion-icon name="search-outline"></ion-icon> Search</label>
            <input type="text" name="search" placeholder="Search logs..." value="<?= htmlspecialchars($search) ?>" />
          </div>

          <div class="filter-field">
            <label><ion-icon name="filter-outline"></ion-icon> Action Type</label>
            <select name="action">
              <option value="">All Actions</option>
              <?php foreach ($action_types as $at): ?>
              <option value="<?= htmlspecialchars($at) ?>" <?= $actionFilter === $at ? 'selected' : '' ?>><?= htmlspecialchars($at) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-field">
            <label><ion-icon name="person-outline"></ion-icon> User</label>
            <select name="user">
              <option value="">All Users</option>
              <?php foreach ($users as $u): ?>
              <option value="<?= htmlspecialchars($u) ?>" <?= $userFilter === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-field">
            <label><ion-icon name="calendar-clear-outline"></ion-icon> Date Range</label>
            <input type="text" name="date" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($dateFilter) ?>" />
          </div>
        </section>
        <div style="display:flex;gap:.5rem;margin-bottom:1rem;padding:0 .25rem;">
          <button type="submit" style="padding:.4rem 1rem;background:#003366;color:#fff;border:none;border-radius:6px;font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer;">Apply Filters</button>
          <a href="audit-trail.php" style="padding:.4rem 1rem;background:#f1f5f9;color:#334155;border:none;border-radius:6px;font-family:inherit;font-size:.82rem;font-weight:600;cursor:pointer;text-decoration:none;">Clear</a>
        </div>
      </form>

      <section class="activity-log-card">
        <div class="log-header">
          <h3>Activity Log</h3>
          <span style="font-size:.8rem;color:#64748b;"><?= $total_logs ?> record(s)</span>
        </div>

        <?php if (empty($log_items)): ?>
          <p style="padding:1.5rem;color:#64748b;">No audit log entries match your filters.</p>
        <?php else: ?>
        <?php foreach ($log_items as $log):
          [$icon, $color] = actionIcon($log['Action']);
          $chipClass = actorChip($log['ActorType'] ?? 'student');
          $isSuccess = strtolower($log['Status'] ?? '') === 'success';
          $statusPill = $isSuccess ? 'status-pill success' : 'status-pill error';
          $details = '';
          if (!empty($log['Details'])) {
              $d = json_decode($log['Details'], true);
              if (is_array($d)) {
                  $details = implode(' · ', array_map(fn($k,$v) => "$k: $v", array_keys($d), $d));
              }
          }
        ?>
        <article class="log-item">
          <div class="log-main">
            <div class="log-icon <?= $color ?>"><ion-icon name="<?= $icon ?>"></ion-icon></div>
            <div class="log-content">
              <div class="log-title-row">
                <h4><?= htmlspecialchars($log['Action']) ?></h4>
                <span class="<?= $chipClass ?>"><?= htmlspecialchars($log['ActorType'] ?? 'system') ?></span>
              </div>
              <?php if ($details): ?>
              <p class="log-target">Details: <strong><?= htmlspecialchars(substr($details,0,120)) ?></strong></p>
              <?php endif; ?>
              <div class="log-meta">
                <span><ion-icon name="person-outline"></ion-icon> <?= htmlspecialchars($log['ActorName'] ?? 'Unknown') ?></span>
                <span><ion-icon name="time-outline"></ion-icon> <?= htmlspecialchars($log['Date'] ?? '') ?></span>
                <?php if (!empty($log['IpAddress'])): ?>
                <span><ion-icon name="shield-outline"></ion-icon> IP: <?= htmlspecialchars($log['IpAddress']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <span class="<?= $statusPill ?>"><?= htmlspecialchars($log['Status'] ?? 'success') ?></span>
        </article>
        <?php endforeach; ?>
        <div class="pagination-bar">
          <div class="pagination-summary">
            Showing <?= $total_logs > 0 ? (($currentPage - 1) * $perPage + 1) : 0 ?>-<?= min($currentPage * $perPage, $total_logs) ?> of <?= $total_logs ?>
          </div>

          <div class="pagination-controls">
            <a class="page-link <?= $currentPage <= 1 ? 'disabled' : '' ?>" href="<?= $currentPage > 1 ? htmlspecialchars(pageUrl($pageParams, $currentPage - 1)) : '#' ?>" aria-disabled="<?= $currentPage <= 1 ? 'true' : 'false' ?>">Prev</a>

            <?php
              $startPage = max(1, $currentPage - 2);
              $endPage = min($total_pages, $startPage + 4);
              $startPage = max(1, $endPage - 4);
            ?>

            <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
              <a class="page-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= htmlspecialchars(pageUrl($pageParams, $page)) ?>"><?= $page ?></a>
            <?php endfor; ?>

            <a class="page-link <?= $currentPage >= $total_pages ? 'disabled' : '' ?>" href="<?= $currentPage < $total_pages ? htmlspecialchars(pageUrl($pageParams, $currentPage + 1)) : '#' ?>" aria-disabled="<?= $currentPage >= $total_pages ? 'true' : 'false' ?>">Next</a>
          </div>
        </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <script src="../../assets/js/admin/dashboard.js"></script>

  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
