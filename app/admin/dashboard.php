<?php
$required_role = 'admin';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

$adminName   = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
$currentPage = 'dashboard';

$_GET['action'] = 'get_admin_dashboard';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$dashApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

$stats          = $dashApiRes['stats']       ?? [];
$totalStudents  = (int)($stats['total_students'] ?? 0);
$totalOsa       = (int)($stats['total_osa']      ?? 0);
$totalOrgs      = (int)($stats['total_orgs']     ?? 0);
$totalAdmins    = (int)($stats['total_admins']   ?? 0);
$todayLogs      = (int)($stats['today_logs']     ?? 0);
$totalEvents    = (int)($stats['total_events']   ?? 0);
$recentLogs     = $dashApiRes['recent_logs'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — NAAP System</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="../../assets/img/philsca.png">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>

<?php include '_admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="page-header">
        <h1>Dashboard</h1>
        <p>Welcome back, <?= $adminName ?>. Here's your system overview.</p>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><ion-icon name="school-outline"></ion-icon></div>
            <div class="stat-info">
                <h3><?= number_format($totalStudents) ?></h3>
                <p>Students</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><ion-icon name="shield-checkmark-outline"></ion-icon></div>
            <div class="stat-info">
                <h3><?= number_format($totalOsa) ?></h3>
                <p>OSA Staff</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><ion-icon name="business-outline"></ion-icon></div>
            <div class="stat-info">
                <h3><?= number_format($totalOrgs) ?></h3>
                <p>Organizations</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><ion-icon name="people-circle-outline"></ion-icon></div>
            <div class="stat-info">
                <h3><?= number_format($totalAdmins) ?></h3>
                <p>Admins</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><ion-icon name="calendar-outline"></ion-icon></div>
            <div class="stat-info">
                <h3><?= number_format($totalEvents) ?></h3>
                <p>Total Events</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><ion-icon name="document-text-outline"></ion-icon></div>
            <div class="stat-info">
                <h3><?= number_format($todayLogs) ?></h3>
                <p>Today's Logs</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card-panel">
        <div class="card-panel-header">
            <h2><ion-icon name="flash-outline"></ion-icon> Quick Actions</h2>
        </div>
        <div class="card-panel-body" style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="users.php" class="btn btn-primary"><ion-icon name="people-outline"></ion-icon> Manage Users</a>
            <a href="audit-trail.php" class="btn btn-ghost"><ion-icon name="document-text-outline"></ion-icon> View Audit Trail</a>
        </div>
    </div>

    <!-- Recent System Logs -->
    <div class="card-panel">
        <div class="card-panel-header">
            <h2><ion-icon name="time-outline"></ion-icon> Recent System Activity</h2>
            <a href="audit-trail.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="card-panel-body table-responsive" style="padding:0;">
            <?php if (empty($recentLogs)): ?>
                <div class="empty-state">
                    <ion-icon name="document-text-outline"></ion-icon>
                    <p>No system activity recorded yet.</p>
                </div>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= date('M j, Y g:i A', strtotime($log['Date'])) ?></td>
                        <td>
                            <span class="badge badge-purple"><?= htmlspecialchars($log['ActorType'] ?? 'system') ?></span>
                            <?= htmlspecialchars($log['ActorName'] ?? '—') ?>
                        </td>
                        <td><?= htmlspecialchars($log['Action'] ?? '') ?></td>
                        <td>
                            <?php
                                $st = strtolower($log['Status'] ?? 'info');
                                $bc = $st === 'success' ? 'badge-success' : ($st === 'failed' ? 'badge-danger' : 'badge-info');
                            ?>
                            <span class="badge <?= $bc ?>"><?= htmlspecialchars($log['Status'] ?? 'info') ?></span>
                        </td>
                        <td style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars(!empty($log['IpAddress']) ? $log['IpAddress'] : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
</html>
