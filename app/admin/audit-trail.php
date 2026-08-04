<?php
$required_role = 'admin';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

$adminName   = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
$currentPage = 'audit';

$_GET['action'] = 'get_admin_audit_trail';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$atApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

$logs          = $atApiRes['logs']           ?? [];
$total         = (int)($atApiRes['total']    ?? 0);
$page          = (int)($atApiRes['page']     ?? 1);
$perPage       = (int)($atApiRes['per_page'] ?? 25);
$totalPages    = max(1, (int)ceil($total / $perPage));
$actionOptions = $atApiRes['action_options'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Trail — NAAP Admin</title>
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
        <h1>System Audit Trail</h1>
        <p>Complete log of all system activity across Students, OSA, Organizations, and Admins.</p>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-bar" style="flex-wrap:wrap;">
        <input type="text" name="q" class="form-control" placeholder="Search keyword…"
               value="<?= htmlspecialchars($filterQ) ?>" style="max-width:200px;">

        <select name="actor" class="form-control" style="max-width:160px;">
            <option value="">All Actors</option>
            <option value="student" <?= $filterActor === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="osa" <?= $filterActor === 'osa' ? 'selected' : '' ?>>OSA</option>
            <option value="organization" <?= $filterActor === 'organization' ? 'selected' : '' ?>>Organization</option>
            <option value="admin" <?= $filterActor === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>

        <select name="audit_action" class="form-control" style="max-width:200px;">
            <option value="">All Actions</option>
            <?php foreach ($actionOptions as $act): ?>
                <option value="<?= htmlspecialchars($act) ?>" <?= $filterAction === $act ? 'selected' : '' ?>><?= htmlspecialchars($act) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="form-control" style="max-width:140px;">
            <option value="">All Status</option>
            <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Success</option>
            <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
        </select>

        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filterFrom) ?>" title="From date" style="max-width:160px;">
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filterTo) ?>" title="To date" style="max-width:160px;">

        <button type="submit" class="btn btn-primary btn-sm"><ion-icon name="filter-outline"></ion-icon> Filter</button>
        <a href="audit-trail.php" class="btn btn-ghost btn-sm">Clear</a>
    </form>

    <!-- Results -->
    <div class="card-panel">
        <div class="card-panel-header">
            <h2><ion-icon name="document-text-outline"></ion-icon> Audit Logs (<?= number_format($total) ?>)</h2>
        </div>
        <div class="card-panel-body" style="padding:0;overflow-x:auto;">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <ion-icon name="document-text-outline"></ion-icon>
                    <p>No audit logs found matching your filters.</p>
                </div>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date & Time</th>
                        <th>User Type</th>
                        <th>Username</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td style="color:var(--text-muted);"><?= $offset + $i + 1 ?></td>
                        <td style="white-space:nowrap;"><?= date('M j, Y g:i A', strtotime($log['Date'])) ?></td>
                        <td>
                            <?php
                                $at = strtolower($log['ActorType'] ?? 'system');
                                $atBadge = [
                                    'student'      => 'badge-info',
                                    'osa'          => 'badge-success',
                                    'organization' => 'badge-purple',
                                    'admin'        => 'badge-warning',
                                ][$at] ?? 'badge-info';
                            ?>
                            <span class="badge <?= $atBadge ?>"><?= htmlspecialchars($log['ActorType'] ?? 'system') ?></span>
                        </td>
                        <td style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($log['ActorName'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($log['Action'] ?? '') ?></td>
                        <td>
                            <?php
                                $st = strtolower($log['Status'] ?? '');
                                $bc = $st === 'success' ? 'badge-success' : ($st === 'failed' ? 'badge-danger' : 'badge-info');
                            ?>
                            <span class="badge <?= $bc ?>"><?= htmlspecialchars($log['Status'] ?? '—') ?></span>
                        </td>
                        <td style="max-width:200px;">
                            <button class="btn btn-ghost btn-sm" onclick="showDetails(this)" 
                                data-actor="<?= htmlspecialchars($log['ActorName'] ?? '—') ?>"
                                data-action="<?= htmlspecialchars($log['Action'] ?? '—') ?>"
                                data-status="<?= htmlspecialchars($log['Status'] ?? 'success') ?>"
                                data-date="<?= date('M j, Y g:i A', strtotime($log['Date'])) ?>"
                                data-ip="<?= htmlspecialchars(!empty($log['IpAddress']) ? $log['IpAddress'] : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')) ?>"
                                data-details="<?= htmlspecialchars(!empty($log['Details']) ? $log['Details'] : 'No additional metadata logged') ?>">
                                <ion-icon name="eye-outline"></ion-icon> View Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
            $qp = $_GET;
            unset($qp['page']);
            $qs = http_build_query($qp);
        ?>
        <?php if ($page > 1): ?>
            <a href="audit-trail.php?<?= $qs ?>&page=<?= $page - 1 ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="audit-trail.php?<?= $qs ?>&page=<?= $p ?>"
               class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="audit-trail.php?<?= $qs ?>&page=<?= $page + 1 ?>">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Details Modal -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Log Details</h3>
            <button class="modal-close" onclick="document.getElementById('detailsModal').classList.remove('open')"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <pre id="detailsContent" style="font-size:0.82rem;color:var(--text-secondary);white-space:pre-wrap;word-break:break-all;background:rgba(0,0,0,.2);padding:14px;border-radius:8px;max-height:400px;overflow-y:auto;"></pre>
        </div>
    </div>
</div>

<script src="../../assets/js/admin/audit-trail.js"></script>
</body>
</html>
