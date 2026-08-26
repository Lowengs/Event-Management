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
<script src="../../assets/js/security.js"></script>
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
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): 
                        $det = json_decode($log['Details'] ?? '', true) ?: [];
                        $ip = !empty($log['IpAddress']) ? $log['IpAddress'] : ($det['ip'] ?? '127.0.0.1');
                        $device = $det['device'] ?? ($ip === '127.0.0.1' ? 'Windows (Desktop)' : 'Client Device');
                        $browser = $det['browser'] ?? 'Browser';
                        $location = $det['location'] ?? ($ip === '127.0.0.1' ? 'Localhost' : 'Philippines');
                    ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:0.82rem;color:var(--text-secondary);"><?= date('M j, Y g:i A', strtotime($log['Date'])) ?></td>
                        <td>
                            <span class="badge badge-purple"><?= htmlspecialchars($log['ActorType'] ?? 'system') ?></span>
                            <span style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($log['ActorName'] ?? '—') ?></span>
                        </td>
                        <td style="font-weight:500;"><?= htmlspecialchars($log['Action'] ?? '') ?></td>
                        <td>
                            <?php
                                $st = strtolower($log['Status'] ?? 'info');
                                $bc = $st === 'success' ? 'badge-success' : ($st === 'failed' ? 'badge-danger' : 'badge-info');
                            ?>
                            <span class="badge <?= $bc ?>"><?= htmlspecialchars(strtoupper($log['Status'] ?? 'info')) ?></span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="showDashboardLogDetails(this)"
                                data-actor="<?= htmlspecialchars($log['ActorName'] ?? '—') ?>"
                                data-actortype="<?= htmlspecialchars($log['ActorType'] ?? 'system') ?>"
                                data-action="<?= htmlspecialchars($log['Action'] ?? '—') ?>"
                                data-status="<?= htmlspecialchars($log['Status'] ?? 'success') ?>"
                                data-date="<?= date('M j, Y g:i A', strtotime($log['Date'])) ?>"
                                data-ip="<?= htmlspecialchars($ip) ?>"
                                data-device="<?= htmlspecialchars($device) ?>"
                                data-browser="<?= htmlspecialchars($browser) ?>"
                                data-location="<?= htmlspecialchars($location) ?>"
                                data-details="<?= htmlspecialchars(!empty($log['Details']) ? $log['Details'] : '') ?>">
                                <ion-icon name="eye-outline"></ion-icon> Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Dashboard Log Details Modal -->
<div class="modal-overlay" id="dashboardLogModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.75);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#ffffff;border-radius:20px;max-width:540px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;font-family:'Inter',sans-serif;border:1px solid #e2e8f0;">
        <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;border-radius:10px;background:rgba(37,99,235,0.1);color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:20px;">
                    <ion-icon name="shield-checkmark-outline"></ion-icon>
                </div>
                <div>
                    <h3 style="margin:0;font-size:1.1rem;font-weight:800;color:#0f172a;">Audit Log Details</h3>
                    <p style="margin:0;font-size:0.78rem;color:#64748b;">Device, IP & activity metadata</p>
                </div>
            </div>
            <button type="button" onclick="closeDashboardLogModal()" style="border:none;background:none;font-size:22px;color:#64748b;cursor:pointer;">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div style="padding:24px;max-height:75vh;overflow-y:auto;" id="dashboardLogBody">
            <!-- Rendered by JS -->
        </div>
        <div style="padding:14px 24px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;">
            <button type="button" onclick="closeDashboardLogModal()" class="btn btn-primary btn-sm">Close</button>
        </div>
    </div>
</div>

<script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
<script src="../../assets/js/admin/dashboard.js?v=<?= time() ?>"></script>
</body>
</html>
