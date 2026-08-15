<?php
$required_role = 'admin';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

$adminName   = htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator');
$currentPage = 'users';

$activeTab = $_GET['tab'] ?? 'students';
if (!in_array($activeTab, ['students', 'osa', 'organizations'], true)) {
    $activeTab = 'students';
}
$search    = trim($_GET['q'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;
$offset    = ($page - 1) * $perPage;

$_GET['action']   = 'get_admin_users';
$_GET['tab']      = $activeTab;
$_GET['q']        = $search;
$_GET['page']     = $page;
$_GET['per_page'] = $perPage;

ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$uApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];

$users      = $uApiRes['users'] ?? [];
$total      = (int)($uApiRes['total'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
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
</head>
<body>

<?php include '_admin_sidebar.php'; ?>

<main class="admin-main">
    <div class="page-header">
        <h1>User Management</h1>
        <p>Manage all user accounts across the NAAP system.</p>
    </div>

    <!-- Tabs -->
    <div class="tab-nav">
        <button class="tab-btn <?= $activeTab === 'students' ? 'active' : '' ?>" onclick="switchTab('students')">
            <ion-icon name="school-outline"></ion-icon> Students
        </button>
        <button class="tab-btn <?= $activeTab === 'osa' ? 'active' : '' ?>" onclick="switchTab('osa')">
            <ion-icon name="shield-checkmark-outline"></ion-icon> OSA Staff
        </button>
        <button class="tab-btn <?= $activeTab === 'organizations' ? 'active' : '' ?>" onclick="switchTab('organizations')">
            <ion-icon name="business-outline"></ion-icon> Organizations
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <input type="text" name="q" class="form-control" placeholder="Search by name or email..."
                   value="<?= htmlspecialchars($search) ?>" style="max-width:300px;">
            <button type="submit" class="btn btn-primary btn-sm"><ion-icon name="search-outline"></ion-icon> Search</button>
            <?php if ($search): ?>
                <a href="users.php?tab=<?= $activeTab ?>" class="btn btn-ghost btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Results -->
    <div class="card-panel">
        <div class="card-panel-header">
            <h2><ion-icon name="people-outline"></ion-icon> <?= ucfirst($activeTab) ?> (<?= number_format($total) ?>)</h2>
        </div>
        <div class="card-panel-body table-responsive" style="padding:0;">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <ion-icon name="search-outline"></ion-icon>
                    <p>No users found<?= $search ? ' matching "'.htmlspecialchars($search).'"' : '' ?>.</p>
                </div>
            <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th><?= $activeTab === 'organizations' ? 'Username' : 'Email' ?></th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td style="font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u[$activeTab === 'organizations' ? 'extra' : 'email'] ?? '') ?></td>
                        <td><span class="badge badge-purple"><?= htmlspecialchars($u['role']) ?></span></td>
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
                                    <ion-icon name="eye-outline"></ion-icon> View Account
                                </button>
                                <button class="btn btn-primary btn-sm" title="Reset Password" onclick="openResetPasswordModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <ion-icon name="key-outline"></ion-icon> Reset Password
                                </button>
                                <?php if ($st === 'suspended' || $st === 'inactive'): ?>
                                <button class="btn btn-success btn-sm" title="Activate account" onclick="updateUserStatus(<?= (int)$u['id'] ?>, '<?= $activeTab ?>', 'active')">
                                    <ion-icon name="checkmark-circle-outline"></ion-icon> Activate
                                </button>
                                <?php else: ?>
                                <button class="btn btn-ghost btn-sm" title="Suspend account" onclick="updateUserStatus(<?= (int)$u['id'] ?>, '<?= $activeTab ?>', 'suspended')">
                                    <ion-icon name="ban-outline"></ion-icon> Suspend
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" title="Delete account" onclick="deleteUserAccount(<?= (int)$u['id'] ?>, '<?= $activeTab === 'organizations' ? 'organization' : ($activeTab === 'osa' ? 'osa' : 'student') ?>')">
                                    <ion-icon name="trash-outline"></ion-icon> Delete
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="users.php?tab=<?= $activeTab ?>&q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
            <a href="users.php?tab=<?= $activeTab ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>"
               class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a href="users.php?tab=<?= $activeTab ?>&q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- View User Account Modal -->
<div class="modal-overlay" id="viewUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><ion-icon name="person-circle-outline" style="vertical-align:middle;margin-right:6px;color:var(--accent);"></ion-icon> Account Details</h3>
            <button class="modal-close" onclick="closeViewUserModal()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body" id="viewUserBody" style="line-height:1.8;">
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
                    <input type="password" id="newPassword" name="password" class="form-control" placeholder="Enter new password (min. 6 chars)" required minlength="6">
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
