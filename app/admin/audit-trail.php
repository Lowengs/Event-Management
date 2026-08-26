<?php
$required_role = 'admin';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

$adminName   = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
$currentPage = 'audit';

$filterQ      = trim($_GET['q'] ?? '');
$filterPeriod = trim($_GET['period'] ?? $_GET['time_filter'] ?? '');
$filterActor  = trim($_GET['actor'] ?? $_GET['user_filter'] ?? '');
$filterCat    = trim($_GET['category'] ?? '');
$filterAction = trim($_GET['audit_action'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterFrom   = trim($_GET['from'] ?? '');
$filterTo     = trim($_GET['to'] ?? '');

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
$offset        = ($page - 1) * $perPage;
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
    <script src="../../assets/js/security.js"></script>
</head>
<body>

<?php include '_admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <h1>System Audit Trail</h1>
            <p>Comprehensive security & activity tracking across Students, OSA, Organizations, and Admins.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <button type="button" class="btn btn-primary" onclick="openExportModal()" style="display:inline-flex;align-items:center;gap:6px;">
                <ion-icon name="download-outline"></ion-icon> Export Logs
            </button>
        </div>
    </div>

    <!-- Filters Section -->
    <form method="GET" class="filter-bar" id="auditFilterForm" style="flex-wrap:wrap;gap:10px;background:var(--bg-surface,#ffffff);padding:16px;border-radius:12px;border:1px solid var(--border-color,#e2e8f0);margin-bottom:20px;">
        <!-- Keyword Search -->
        <input type="text" name="q" class="form-control" placeholder="Search keyword, IP, user…"
               value="<?= htmlspecialchars($filterQ) ?>" style="min-width:180px;flex:1;">

        <!-- 1. Date & Time Filter -->
        <select name="period" id="timePeriodSelect" class="form-control" onchange="toggleCustomDates(this.value)" style="min-width:140px;">
            <option value="">All Time</option>
            <option value="today" <?= $filterPeriod === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="yesterday" <?= $filterPeriod === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
            <option value="this_week" <?= $filterPeriod === 'this_week' ? 'selected' : '' ?>>This Week</option>
            <option value="this_month" <?= $filterPeriod === 'this_month' ? 'selected' : '' ?>>This Month</option>
            <option value="this_year" <?= $filterPeriod === 'this_year' ? 'selected' : '' ?>>This Year</option>
            <option value="custom" <?= ($filterPeriod === 'custom' || (!empty($filterFrom) || !empty($filterTo))) ? 'selected' : '' ?>>Custom Date Range</option>
        </select>

        <!-- Custom Date Range inputs -->
        <div id="customDateWrap" style="display:<?= ($filterPeriod === 'custom' || (!empty($filterFrom) || !empty($filterTo))) ? 'flex' : 'none' ?>;gap:6px;align-items:center;">
            <input type="date" name="from" id="fromDateInput" class="form-control" value="<?= htmlspecialchars($filterFrom) ?>" title="From date" style="width:140px;">
            <span style="color:var(--text-muted);font-size:0.8rem;">to</span>
            <input type="date" name="to" id="toDateInput" class="form-control" value="<?= htmlspecialchars($filterTo) ?>" title="To date" style="width:140px;">
        </div>

        <!-- 2. User Filter -->
        <select name="actor" class="form-control" style="min-width:140px;">
            <option value="">All Users</option>
            <option value="organization" <?= $filterActor === 'organization' ? 'selected' : '' ?>>Organization Admin</option>
            <option value="officer" <?= $filterActor === 'officer' ? 'selected' : '' ?>>Officers</option>
            <option value="osa" <?= $filterActor === 'osa' ? 'selected' : '' ?>>OSA Admin</option>
            <option value="admin" <?= $filterActor === 'admin' ? 'selected' : '' ?>>System Admin</option>
            <option value="student" <?= $filterActor === 'student' ? 'selected' : '' ?>>Student</option>
        </select>

        <!-- 3. Action Category -->
        <select name="category" class="form-control" style="min-width:140px;">
            <option value="">All Categories</option>
            <option value="members" <?= $filterCat === 'members' ? 'selected' : '' ?>>Members</option>
            <option value="events" <?= $filterCat === 'events' ? 'selected' : '' ?>>Events</option>
            <option value="announcements" <?= $filterCat === 'announcements' ? 'selected' : '' ?>>Announcements</option>
            <option value="attendance" <?= $filterCat === 'attendance' ? 'selected' : '' ?>>Attendance</option>
            <option value="documents" <?= $filterCat === 'documents' ? 'selected' : '' ?>>Documents</option>
            <option value="assessments" <?= $filterCat === 'assessments' ? 'selected' : '' ?>>Assessments</option>
            <option value="certificates" <?= $filterCat === 'certificates' ? 'selected' : '' ?>>Certificates</option>
            <option value="officers" <?= $filterCat === 'officers' ? 'selected' : '' ?>>Officers</option>
            <option value="messages" <?= $filterCat === 'messages' ? 'selected' : '' ?>>Messages</option>
            <option value="login" <?= $filterCat === 'login' ? 'selected' : '' ?>>Login / Logout</option>
        </select>

        <!-- 4. Status Filter -->
        <select name="status" class="form-control" style="min-width:120px;">
            <option value="">All Status</option>
            <option value="success" <?= strtolower($filterStatus) === 'success' ? 'selected' : '' ?>>Success</option>
            <option value="failed" <?= strtolower($filterStatus) === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="warning" <?= strtolower($filterStatus) === 'warning' ? 'selected' : '' ?>>Warning</option>
        </select>

        <button type="submit" class="btn btn-primary btn-sm"><ion-icon name="filter-outline"></ion-icon> Filter</button>
        <a href="audit-trail.php" class="btn btn-ghost btn-sm">Clear</a>
    </form>

    <!-- Results Table -->
    <div class="card-panel">
        <div class="card-panel-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h2><ion-icon name="document-text-outline"></ion-icon> Audit Logs (<?= number_format($total) ?> records)</h2>
            <span style="font-size:0.82rem;color:var(--text-muted);">Showing page <?= $page ?> of <?= $totalPages ?></span>
        </div>
        <div class="card-panel-body table-responsive" style="padding:0;">
            <?php if (empty($logs)): ?>
                <div class="empty-state" style="padding:40px;text-align:center;">
                    <ion-icon name="document-text-outline" style="font-size:40px;color:var(--text-muted);"></ion-icon>
                    <p style="margin-top:8px;color:var(--text-muted);">No audit logs found matching your filters.</p>
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
                    <?php foreach ($logs as $i => $log): 
                        $det = json_decode($log['Details'] ?? '', true) ?: [];
                        $ip = !empty($log['IpAddress']) ? $log['IpAddress'] : ($det['ip'] ?? '127.0.0.1');
                        $device = $det['device'] ?? ($ip === '127.0.0.1' ? 'Windows (Desktop)' : 'Client Device');
                        $browser = $det['browser'] ?? 'Browser';
                        $location = $det['location'] ?? ($ip === '127.0.0.1' ? 'Localhost' : 'Philippines');
                    ?>
                    <tr>
                        <td style="color:var(--text-muted);"><?= $offset + $i + 1 ?></td>
                        <td style="white-space:nowrap;font-size:0.82rem;color:var(--text-secondary);"><?= date('M j, Y g:i A', strtotime($log['Date'])) ?></td>
                        <td>
                            <?php
                                $at = strtolower($log['ActorType'] ?? 'system');
                                $atBadge = [
                                    'student'      => 'badge-info',
                                    'osa'          => 'badge-success',
                                    'organization' => 'badge-purple',
                                    'admin'        => 'badge-warning',
                                    'officer'      => 'badge-primary',
                                ][$at] ?? 'badge-info';
                            ?>
                            <span class="badge <?= $atBadge ?>"><?= htmlspecialchars($log['ActorType'] ?? 'system') ?></span>
                        </td>
                        <td style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($log['ActorName'] ?? '—') ?></td>
                        <td style="font-weight:500;"><?= htmlspecialchars($log['Action'] ?? '') ?></td>
                        <td>
                            <?php
                                $st = strtolower($log['Status'] ?? '');
                                $bc = $st === 'success' ? 'badge-success' : ($st === 'failed' ? 'badge-danger' : 'badge-warning');
                            ?>
                            <span class="badge <?= $bc ?>"><?= htmlspecialchars(strtoupper($log['Status'] ?? '—')) ?></span>
                        </td>
                        <td style="max-width:140px;">
                            <button class="btn btn-ghost btn-sm" onclick="showDetails(this)" 
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="display:flex;gap:6px;justify-content:center;margin-top:20px;">
        <?php
            $qp = $_GET;
            unset($qp['page']);
            $qs = http_build_query($qp);
        ?>
        <?php if ($page > 1): ?>
            <a href="audit-trail.php?<?= $qs ?>&page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="audit-trail.php?<?= $qs ?>&page=<?= $p ?>"
               class="btn <?= $p === $page ? 'btn-primary' : 'btn-ghost' ?> btn-sm"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="audit-trail.php?<?= $qs ?>&page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Details Modal -->
<div class="modal-overlay" id="detailsModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.75);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:20px;">
    <div class="modal-box" style="background:#ffffff;border-radius:20px;max-width:540px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;border:1px solid #e2e8f0;font-family:'Inter',sans-serif;">
        <div class="modal-header" style="padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
            <h3 style="margin:0;font-size:1.1rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;">
                <ion-icon name="shield-checkmark-outline" style="color:#2563eb;"></ion-icon> Audit Log Details
            </h3>
            <button class="modal-close" onclick="closeDetailsModal()" style="border:none;background:none;font-size:22px;color:#64748b;cursor:pointer;display:flex;align-items:center;">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div class="modal-body" style="padding:20px 24px;max-height:80vh;overflow-y:auto;">
            <div id="detailsContent"></div>
        </div>
    </div>
</div>

<!-- Export Logs Modal -->
<div class="modal-overlay" id="exportLogsModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.75);backdrop-filter:blur(6px);z-index:99999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#ffffff;border-radius:20px;max-width:480px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,0.3);overflow:hidden;border:1px solid #e2e8f0;font-family:'Inter',sans-serif;">
        <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
            <h3 style="margin:0;font-size:1.1rem;font-weight:800;color:#0f172a;display:flex;align-items:center;gap:8px;">
                <ion-icon name="cloud-download-outline" style="color:#2563eb;"></ion-icon> Export Audit Logs
            </h3>
            <button type="button" onclick="closeExportModal()" style="border:none;background:none;font-size:22px;color:#64748b;cursor:pointer;display:flex;align-items:center;">
                <ion-icon name="close-outline"></ion-icon>
            </button>
        </div>
        <div style="padding:24px;">
            <form id="exportForm" onsubmit="handleExportSubmit(event)">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:0.82rem;font-weight:700;color:#334155;margin-bottom:6px;">Select Time Period *</label>
                    <select id="exportPeriod" class="form-control" onchange="toggleExportCustomDates(this.value)" style="width:100%;">
                        <option value="today">Today / This Day</option>
                        <option value="this_month">This Month</option>
                        <option value="this_year">This Year</option>
                        <option value="custom">Custom Date Range (Select Date)</option>
                        <option value="all">All Time (Complete Log)</option>
                    </select>
                </div>

                <div id="exportCustomDateWrap" style="display:none;margin-bottom:16px;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="display:block;font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:4px;">From Date</label>
                        <input type="date" id="exportFromDate" class="form-control" style="width:100%;">
                    </div>
                    <div>
                        <label style="display:block;font-size:0.78rem;font-weight:600;color:#64748b;margin-bottom:4px;">To Date</label>
                        <input type="date" id="exportToDate" class="form-control" style="width:100%;">
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:0.82rem;font-weight:700;color:#334155;margin-bottom:6px;">Format</label>
                    <div style="display:flex;gap:12px;">
                        <label style="display:flex;align-items:center;gap:6px;font-size:0.88rem;color:#1e293b;cursor:pointer;">
                            <input type="radio" name="exportFormat" value="csv" checked> CSV Spreadsheet (.csv)
                        </label>
                    </div>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="btn btn-ghost" onclick="closeExportModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                        <ion-icon name="download-outline"></ion-icon> Download Export
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCustomDates(val) {
    const wrap = document.getElementById('customDateWrap');
    if (wrap) {
        wrap.style.display = (val === 'custom') ? 'flex' : 'none';
    }
}

function openExportModal() {
    const m = document.getElementById('exportLogsModal');
    if (m) m.style.display = 'flex';
}

function closeExportModal() {
    const m = document.getElementById('exportLogsModal');
    if (m) m.style.display = 'none';
}

function toggleExportCustomDates(val) {
    const wrap = document.getElementById('exportCustomDateWrap');
    if (wrap) {
        wrap.style.display = (val === 'custom') ? 'grid' : 'none';
    }
}

function handleExportSubmit(e) {
    e.preventDefault();
    const period = document.getElementById('exportPeriod').value;
    const from = document.getElementById('exportFromDate').value;
    const to = document.getElementById('exportToDate').value;
    
    // Carry over existing active filters if any
    const filterForm = document.getElementById('auditFilterForm');
    const fd = new FormData(filterForm);
    const params = new URLSearchParams();
    
    params.set('action', 'export_audit_logs');
    params.set('export', 'csv');
    params.set('period', period);
    if (period === 'custom') {
        if (from) params.set('from', from);
        if (to) params.set('to', to);
    }
    
    if (fd.get('q')) params.set('q', fd.get('q'));
    if (fd.get('actor')) params.set('actor', fd.get('actor'));
    if (fd.get('category')) params.set('category', fd.get('category'));
    if (fd.get('status')) params.set('status', fd.get('status'));

    const exportUrl = '../../config/API/endpoints/index.php?' + params.toString();
    window.location.href = exportUrl;
    closeExportModal();
}

function closeDetailsModal() {
    const m = document.getElementById('detailsModal');
    if (m) m.style.display = 'none';
}
</script>

<script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
<script src="../../assets/js/admin/audit-trail.js?v=<?= time() ?>"></script>
</body>
</html>
