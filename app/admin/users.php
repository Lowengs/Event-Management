<?php
$required_role = 'admin';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

$adminName   = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
$currentPage = 'users';

$activeTab    = $_GET['tab'] ?? 'students';
if (!in_array($activeTab, ['students', 'osa', 'organizations'], true)) {
    $activeTab = 'students';
}
$search       = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$verifFilter  = trim($_GET['verif_status'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$_GET['action']       = 'get_admin_users';
$_GET['tab']          = $activeTab;
$_GET['q']            = $search;
$_GET['status']       = $statusFilter;
$_GET['verif_status'] = $verifFilter;
$_GET['page']         = $page;
$_GET['per_page']     = $perPage;

ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$uApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];

$users      = $uApiRes['users'] ?? [];
$total      = (int)($uApiRes['total'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$queryBase = [
    'tab'          => $activeTab,
    'q'            => $search ?: null,
    'status'       => $statusFilter ?: null,
    'verif_status' => $verifFilter ?: null,
];
$queryBase = array_filter($queryBase);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management — NAAP Admin</title>
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
        <h1>User Management</h1>
        <p>Manage and verify all user accounts across the NAAP system.</p>
    </div>

    <!-- Tabs -->
    <div class="tab-nav">
        <button class="tab-btn <?= $activeTab === 'students' ? 'active' : '' ?>" onclick="switchTab('students')">
            <ion-icon name="school-outline"></ion-icon> Students
        </button>
        <button class="tab-btn <?= $activeTab === 'organizations' ? 'active' : '' ?>" onclick="switchTab('organizations')">
            <ion-icon name="business-outline"></ion-icon> Organizations
        </button>
        <button class="tab-btn <?= $activeTab === 'osa' ? 'active' : '' ?>" onclick="switchTab('osa')">
            <ion-icon name="shield-checkmark-outline"></ion-icon> OSA Staff
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar" style="background:#ffffff;padding:16px 20px;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.03);">
        <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            
            <!-- Search Keyword -->
            <div style="flex:1;min-width:220px;max-width:340px;">
                <input type="text" name="q" class="form-control" 
                       placeholder="<?= $activeTab === 'organizations' ? 'Search org name, email, adviser...' : ($activeTab === 'osa' ? 'Search staff name, email...' : 'Search student name, ID, email...') ?>"
                       value="<?= htmlspecialchars($search) ?>" style="width:100%;height:40px;">
            </div>

            <?php if ($activeTab === 'students'): ?>
            <!-- Student AI Verification Status Filter -->
            <div style="min-width:170px;">
                <select name="verif_status" class="form-control" style="height:40px;">
                    <option value="">All Verification Status</option>
                    <option value="verified" <?= $verifFilter === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="pending" <?= $verifFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="rejected" <?= $verifFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <?php endif; ?>

            <!-- Account Status Filter -->
            <div style="min-width:150px;">
                <select name="status" class="form-control" style="height:40px;">
                    <option value="">All Account Statuses</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <?php if ($activeTab === 'students'): ?>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <?php endif; ?>
                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm" style="height:40px;padding:0 18px;display:inline-flex;align-items:center;gap:6px;">
                <ion-icon name="filter-outline"></ion-icon> Filter
            </button>

            <?php if ($search || $statusFilter || $verifFilter): ?>
                <a href="users.php?tab=<?= $activeTab ?>" class="btn btn-ghost btn-sm" style="height:40px;display:inline-flex;align-items:center;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Results -->
    <div class="card-panel">
        <div class="card-panel-header" style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid #e2e8f0;">
            <h2 style="font-size:1.15rem;font-weight:700;color:#0f172a;margin:0;display:flex;align-items:center;gap:8px;">
                <ion-icon name="<?= $activeTab === 'organizations' ? 'business-outline' : ($activeTab === 'osa' ? 'shield-checkmark-outline' : 'people-outline') ?>" style="color:#2563eb;"></ion-icon>
                <?= ucfirst($activeTab) ?> Accounts
                <span class="badge badge-purple" style="font-size:12px;margin-left:6px;"><?= number_format($total) ?> Total</span>
            </h2>
        </div>
        <div class="card-panel-body table-responsive" style="padding:0;">
            <?php if (empty($users)): ?>
                <div class="empty-state" style="padding:40px 20px;text-align:center;color:#64748b;">
                    <ion-icon name="search-outline" style="font-size:36px;color:#94a3b8;margin-bottom:8px;"></ion-icon>
                    <p style="margin:0;font-weight:600;">No users found matching your selected filters.</p>
                </div>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if ($activeTab === 'students'): ?>
                            <th>Student Info</th>
                            <th>Email</th>
                           
                            <th>AI Verification</th>
                            <th>Status</th>
                        <?php elseif ($activeTab === 'organizations'): ?>
                            <th>Organization</th>
                            <th>Username / Email</th>
                            <th>Total Events</th>
                            <th>Status</th>
                        <?php else: ?>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td style="color:#475569;font-weight:600;"><?= $offset + $i + 1 ?></td>

                        <?php if ($activeTab === 'students'): ?>
                            <!-- Student Info: Name, Student ID, Course & Section -->
                            <td>
                                <div style="font-weight:700;color:#0f172a;font-size:14px;"><?= htmlspecialchars($u['name']) ?></div>
                                <div style="font-size:12px;color:#475569;font-weight:600;margin-top:2px;">
                                    <span><?= htmlspecialchars($u['student_id'] ?? 'No ID') ?></span>
                                    <?php if (!empty($u['course'])): ?>
                                        <span style="color:#94a3b8;">&bull;</span>
                                        <span style="color:#2563eb;"><?= htmlspecialchars($u['course']) ?><?= !empty($u['year_level']) ? ' ' . htmlspecialchars($u['year_level']) : '' ?><?= !empty($u['section']) ? '-' . htmlspecialchars($u['section']) : '' ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="color:#0f172a;font-weight:500;"><?= htmlspecialchars($u['email'] ?? $u['extra'] ?? '—') ?></td>
                            
                            <td>
                                <?php
                                    $vs = strtolower($u['verification_status'] ?? 'pending');
                                    if ($vs === 'approved' || $vs === 'ai_verified') {
                                        $scoreDisp = (!empty($u['ai_verification_score']) ? (int)$u['ai_verification_score'] : 100) . '%';
                                        echo '<span class="badge badge-success" style="display:inline-flex;align-items:center;gap:4px;"><ion-icon name="checkmark-circle-outline"></ion-icon> AI Verified (' . $scoreDisp . ')</span>';
                                    } elseif ($vs === 'rejected') {
                                        echo '<span class="badge badge-danger" style="display:inline-flex;align-items:center;gap:4px;"><ion-icon name="close-circle-outline"></ion-icon> Rejected</span>';
                                    } else {
                                        echo '<span class="badge badge-warning" style="display:inline-flex;align-items:center;gap:4px;"><ion-icon name="time-outline"></ion-icon> Pending Review</span>';
                                    }
                                ?>
                            </td>

                        <?php elseif ($activeTab === 'organizations'): ?>
                            <!-- Organization Name & Adviser -->
                            <td>
                                <div style="font-weight:700;color:#0f172a;font-size:14px;"><?= htmlspecialchars($u['name'] ?? $u['OrgName']) ?></div>
                                <?php if (!empty($u['Adviser'])): ?>
                                    <div style="font-size:12px;color:#64748b;font-weight:500;">Adviser: <?= htmlspecialchars($u['Adviser']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="color:#0f172a;font-weight:500;"><?= htmlspecialchars($u['email'] ?? $u['username'] ?? '—') ?></td>
                            <td>
                                <span class="badge" style="background:#eff6ff;color:#2563eb;font-weight:700;font-size:12.5px;padding:4px 12px;border-radius:12px;"><?= (int)($u['total_events'] ?? 0) ?> Events</span>
                            </td>

                        <?php else: ?>
                            <!-- OSA Staff -->
                            <td style="font-weight:700;color:#0f172a;font-size:14px;"><?= htmlspecialchars($u['name']) ?></td>
                            <td style="color:#0f172a;font-weight:500;"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                            <td><span class="badge badge-purple"><?= htmlspecialchars($u['role']) ?></span></td>
                        <?php endif; ?>

                        <td>
                            <?php
                                $st = strtolower($u['status'] ?? 'active');
                                $bc = $st === 'active' ? 'badge-success' : ($st === 'inactive' || $st === 'suspended' ? 'badge-danger' : 'badge-warning');
                            ?>
                            <span class="badge <?= $bc ?>"><?= htmlspecialchars($u['status'] ?? 'active') ?></span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-ghost btn-sm" title="View Account Details" onclick="viewUserAccount(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <ion-icon name="eye-outline"></ion-icon> 
                                </button>
                                <button class="btn btn-primary btn-sm" title="Reset Password" onclick="openResetPasswordModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <ion-icon name="key-outline"></ion-icon> 
                                </button>
                                <?php if ($st === 'suspended' || $st === 'inactive'): ?>
                                <button class="btn btn-success btn-sm" title="Activate account" onclick="updateUserStatus(<?= (int)$u['id'] ?>, '<?= $activeTab ?>', 'active')">
                                    <ion-icon name="checkmark-circle-outline"></ion-icon> 
                                </button>
                                <?php else: ?>
                                <button class="btn btn-ghost btn-sm" title="Suspend account" onclick="updateUserStatus(<?= (int)$u['id'] ?>, '<?= $activeTab ?>', 'suspended')">
                                    <ion-icon name="ban-outline"></ion-icon> 
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" title="Delete account" onclick="deleteUserAccount(<?= (int)$u['id'] ?>, '<?= $activeTab === 'organizations' ? 'organization' : ($activeTab === 'osa' ? 'osa' : 'student') ?>')">
                                    <ion-icon name="trash-outline"></ion-icon> 
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination & Summary Bar -->
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;margin-top:22px;padding:4px 2px;">
        <div style="font-size:0.88rem;color:#64748b;font-weight:600;">
            Showing <strong style="color:#0f172a;"><?= $total > 0 ? $offset + 1 : 0 ?></strong> to <strong style="color:#0f172a;"><?= min($offset + $perPage, $total) ?></strong> of <strong style="color:#2563eb;"><?= number_format($total) ?></strong> <?= htmlspecialchars($activeTab) ?> accounts
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination" style="display:inline-flex;gap:6px;align-items:center;margin:0;">
            <?php if ($page > 1): ?>
                <a href="users.php?<?= http_build_query(array_merge($queryBase, ['page' => 1])) ?>" title="First Page" style="font-weight:700;">« First</a>
                <a href="users.php?<?= http_build_query(array_merge($queryBase, ['page' => $page - 1])) ?>" title="Previous Page">‹ Prev</a>
            <?php endif; ?>

            <?php 
                $startP = max(1, $page - 2);
                $endP   = min($totalPages, $page + 2);
                if ($startP > 1) {
                    echo '<span style="color:#94a3b8;padding:0 4px;">…</span>';
                }
                for ($p = $startP; $p <= $endP; $p++): 
            ?>
                <a href="users.php?<?= http_build_query(array_merge($queryBase, ['page' => $p])) ?>"
                   class="<?= $p === $page ? 'active' : '' ?>" style="min-width:36px;text-align:center;"><?= $p ?></a>
            <?php endfor; ?>
            <?php if ($endP < $totalPages): ?>
                <span style="color:#94a3b8;padding:0 4px;">…</span>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a href="users.php?<?= http_build_query(array_merge($queryBase, ['page' => $page + 1])) ?>" title="Next Page">Next ›</a>
                <a href="users.php?<?= http_build_query(array_merge($queryBase, ['page' => $totalPages])) ?>" title="Last Page" style="font-weight:700;">Last »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- View User Account Modal -->
<div class="modal-overlay" id="viewUserModal">
    <div class="modal-box" style="max-width:620px;">
        <div class="modal-header">
            <h3><ion-icon name="person-circle-outline" style="vertical-align:middle;margin-right:6px;color:var(--accent);"></ion-icon> Account Details</h3>
            <button class="modal-close" onclick="closeViewUserModal()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body" id="viewUserBody" style="line-height:1.6;padding:22px 24px;">
            <!-- Dynamic content -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" onclick="closeViewUserModal()">Close</button>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetPasswordModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><ion-icon name="key-outline" style="vertical-align:middle;margin-right:6px;color:var(--accent);"></ion-icon> Reset User Password</h3>
            <button class="modal-close" onclick="closeResetPasswordModal()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <form id="resetPasswordForm">
            <div class="modal-body">
                <input type="hidden" id="resetUserId" name="user_id">
                <input type="hidden" id="resetUserTab" name="user_tab" value="<?= $activeTab ?>">

                <p style="font-size:0.85rem;color:var(--text-secondary);margin-bottom:16px;" id="resetUserHeading">
                    Resetting password for user account.
                </p>

                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <div class="password-input-wrap">
                        <input type="password" id="newPassword" name="password" class="form-control" placeholder="Enter new password (min. 6 chars)" required minlength="6">
                        <button type="button" class="pw-toggle-btn" data-target="newPassword" aria-label="Toggle password visibility">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeResetPasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="confirmResetBtn">
                    <ion-icon name="key-outline"></ion-icon> Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
<script src="../../assets/js/admin/users.js?v=<?= time() ?>"></script>
</body>
</html>
