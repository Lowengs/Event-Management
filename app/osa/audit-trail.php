<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';

$_GET['action'] = 'get_osa_audit_trail';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$atApiRes       = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$stats          = $atApiRes['stats']        ?? [];
$today_count    = (int)($stats['today']     ?? 0);
$week_count     = (int)($stats['week']      ?? 0);
$success_count  = (int)($stats['success']   ?? 0);
$failed_count   = (int)($stats['failed']    ?? 0);
$action_types   = $atApiRes['action_types'] ?? [];
$users          = $atApiRes['users']        ?? [];
$log_items      = $atApiRes['logs']         ?? [];
$pagination     = $atApiRes['pagination']   ?? [];
$currentPage    = (int)($pagination['current_page'] ?? 1);
$total_pages    = (int)($pagination['total_pages']  ?? 1);
$total_logs     = (int)($pagination['total_rows']   ?? 0);

$search         = trim($_GET['search'] ?? '');
$actionFilter   = $_GET['action_filter'] ?? '';
$userFilter     = $_GET['user']   ?? '';
$dateFilter     = $_GET['date']   ?? '';

$pageParams = array_filter([
  'search' => $search !== '' ? $search : null,
  'action_filter' => $actionFilter !== '' ? $actionFilter : null,
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
    if (str_contains($a,'declin'))  return ['close-outline','red'];
    if (str_contains($a,'creat'))   return ['add-circle-outline','blue'];
    if (str_contains($a,'updat') || str_contains($a,'edit')) return ['create-outline','blue'];
    if (str_contains($a,'delet'))   return ['trash-outline','red'];
    if (str_contains($a,'export') || str_contains($a,'report')) return ['document-text-outline','slate'];
    if (str_contains($a,'fail') || str_contains($a,'block') || str_contains($a,'wrong')) return ['warning-outline','red'];
    return ['information-circle-outline','slate'];
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
<script src="../../assets/js/security.js"></script>
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
        <li><a href="../../config/API/endpoints/index.php?action=osa_logout" class="nav"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
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
          <div class="stat-icon blue"><ion-icon name="time-outline"></ion-icon></div>
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
          <div class="stat-icon red"><ion-icon name="warning-outline"></ion-icon></div>
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
            <select name="action_filter">
              <option value="">All Actions</option>
              <?php foreach ($action_types as $at): ?>
              <option value="<?= htmlspecialchars($at) ?>" <?= $actionFilter === $at ? 'selected' : '' ?>><?= htmlspecialchars($at) ?></option>
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
              <div class="log-meta">
                <span><ion-icon name="person-outline"></ion-icon> <?= htmlspecialchars($log['ActorName'] ?? 'Unknown') ?></span>
                <span><ion-icon name="time-outline"></ion-icon> <?= htmlspecialchars($log['Date'] ?? '') ?></span>
                <?php
                  $ipDisplay = trim($log['IpAddress'] ?? '');
                  if (empty($ipDisplay) || $ipDisplay === '::1' || $ipDisplay === 'localhost') {
                      $ipDisplay = '127.0.0.1';
                  }
                ?>
                <span><ion-icon name="desktop-outline"></ion-icon> IP: <?= htmlspecialchars($ipDisplay) ?></span>
                <button type="button" onclick='showAuditDetails(<?= json_encode([
                    "action" => $log["Action"],
                    "actor"  => $log["ActorName"] ?? "Unknown",
                    "type"   => $log["ActorType"] ?? "system",
                    "ip"     => $ipDisplay,
                    "date"   => $log["Date"] ?? "",
                    "status" => $log["Status"] ?? "success",
                    "details"=> $details ?: "No additional metadata logged"
                ]) ?>)' style="padding:3px 8px;background:#f0f7ff;color:#2563eb;border:1px solid #bfdbfe;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:4px;margin-left:auto;">
                  <ion-icon name="eye-outline"></ion-icon> View Details
                </button>
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

<!-- Audit Trail Detail Modal -->
<div id="auditDetailsModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;">
  <div style="background:#ffffff;border-radius:16px;max-width:500px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:hidden;">
    <div style="padding:16px 20px;background:#0f172a;color:#fff;display:flex;align-items:center;justify-content:space-between;">
      <h3 style="margin:0;font-size:16px;color:#fff;display:flex;align-items:center;gap:8px;">
        <ion-icon name="analytics-outline" style="color:#38bdf8;"></ion-icon> Audit Trail Log Details
      </h3>
      <button onclick="document.getElementById('auditDetailsModal').style.display='none'" style="background:transparent;border:none;color:#fff;font-size:20px;cursor:pointer;">&times;</button>
    </div>
    <div style="padding:20px;font-size:14px;color:#334155;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;background:#f8fafc;padding:12px;border-radius:10px;border:1px solid #e2e8f0;">
        <div>
          <span style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700;display:block;">User / Actor</span>
          <strong id="auditModalActor" style="color:#0f172a;">—</strong>
        </div>
        <div>
          <span style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700;display:block;">User IP Address</span>
          <strong id="auditModalIp" style="color:#2563eb;font-family:monospace;">—</strong>
        </div>
        <div>
          <span style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700;display:block;">Action</span>
          <strong id="auditModalAction" style="color:#0f172a;">—</strong>
        </div>
        <div>
          <span style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700;display:block;">Status</span>
          <strong id="auditModalStatus" style="color:#16a34a;">—</strong>
        </div>
        <div style="grid-column:span 2;">
          <span style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700;display:block;">Timestamp</span>
          <span id="auditModalDate" style="color:#475569;">—</span>
        </div>
      </div>
      <div>
        <span style="font-size:11px;text-transform:uppercase;color:#64748b;font-weight:700;display:block;margin-bottom:6px;">Full Activity Context & Metadata</span>
        <div id="auditModalDetails" style="background:#0f172a;color:#f8fafc;padding:12px;border-radius:8px;font-family:monospace;font-size:12px;max-height:160px;overflow-y:auto;white-space:pre-wrap;word-break:break-word;">
        </div>
      </div>
    </div>
    <div style="padding:12px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:right;">
      <button onclick="document.getElementById('auditDetailsModal').style.display='none'" style="padding:8px 18px;background:#334155;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Close</button>
    </div>
  </div>
</div>

<script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
<script src="../../assets/js/osa/audit-trail.js"></script>
  <script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>

  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
