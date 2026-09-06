<?php
$required_role = 'admin';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

$adminId     = (int)($_SESSION['admin_id'] ?? 0);
$adminRow    = null;

if ($adminId > 0 && isset($conn)) {
    $stmt = $conn->prepare("SELECT AdminId, Name, Email, Role FROM `admin` WHERE AdminId = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $res = $stmt->get_result();
        $adminRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}
if (!$adminRow && !empty($_SESSION['admin_email']) && isset($conn)) {
    $stmt = $conn->prepare("SELECT AdminId, Name, Email, Role FROM `admin` WHERE LOWER(Email) = LOWER(?) LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $_SESSION['admin_email']);
        $stmt->execute();
        $res = $stmt->get_result();
        $adminRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}
if (!$adminRow && isset($conn)) {
    $res = $conn->query("SELECT AdminId, Name, Email, Role FROM `admin` ORDER BY AdminId ASC LIMIT 1");
    if ($res) $adminRow = $res->fetch_assoc();
}

$adminName   = htmlspecialchars($adminRow['Name'] ?? ($_SESSION['admin_name'] ?? 'Administrator'));
$adminEmail  = htmlspecialchars($adminRow['Email'] ?? ($_SESSION['admin_email'] ?? ''));
$adminRole   = htmlspecialchars($adminRow['Role'] ?? 'Super Administrator');
$currentPage = 'settings';
$admin       = ['Name' => $adminName, 'Email' => $adminEmail, 'Role' => $adminRole];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — NAAP Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin/settings.css?v=<?= time() ?>">
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
        <h1>Settings</h1>
        <p>Manage your administrator account password.</p>
    </div>

    <!-- Account Info -->
    <div class="card-panel" style="max-width:600px;">
        <div class="card-panel-header">
            <h2><ion-icon name="person-circle-outline"></ion-icon> Account Information</h2>
        </div>
        <div class="card-panel-body" style="line-height:2;">
            <p><strong>Name:</strong> <?= htmlspecialchars($admin['Name'] ?? '') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($admin['Email'] ?? '') ?></p>
            <p><strong>Role:</strong> <span class="badge badge-purple"><?= htmlspecialchars($admin['Role'] ?? 'Super Administrator') ?></span></p>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card-panel" style="max-width:600px;">
        <div class="card-panel-header">
            <h2><ion-icon name="key-outline"></ion-icon> Change Password</h2>
        </div>
        <div class="card-panel-body">
            <form id="changePasswordForm">
                <div class="form-group">
                    <label for="currentPassword">Current Password</label>
                    <div class="password-input-wrap">
                        <input type="password" id="currentPassword" name="current_password" class="form-control" placeholder="Enter your current password" required>
                        <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('currentPassword', this); return false;" aria-label="Toggle password visibility">
                            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <div class="password-input-wrap">
                        <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="Enter new password (min. 8–12 characters)" required minlength="8">
                        <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('newPassword', this); return false;" aria-label="Toggle password visibility">
                            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                    </div>
                    <!-- Password Strength Meter -->
                    <div class="password-strength-container" id="pwStrengthContainer">
                        <div class="pw-strength-header">
                            <span class="pw-strength-title">Password Strength:</span>
                            <span id="pwStrengthLabel" class="pw-strength-label" style="background:#fee2e2;color:#dc2626;">Too Short</span>
                        </div>
                        <div class="pw-strength-bar-bg">
                            <div id="pwStrengthFill" class="pw-strength-bar-fill" style="width: 0%; background: #ef4444;"></div>
                        </div>
                        <ul class="pw-criteria-list">
                            <li class="pw-criteria-item" id="critLength">
                                <ion-icon name="close-circle-outline"></ion-icon> 8–12+ characters
                            </li>
                            <li class="pw-criteria-item" id="critUpper">
                                <ion-icon name="close-circle-outline"></ion-icon> Uppercase letter
                            </li>
                            <li class="pw-criteria-item" id="critLower">
                                <ion-icon name="close-circle-outline"></ion-icon> Lowercase letter
                            </li>
                            <li class="pw-criteria-item" id="critNumber">
                                <ion-icon name="close-circle-outline"></ion-icon> Number
                            </li>
                            <li class="pw-criteria-item" id="critSpecial">
                                <ion-icon name="close-circle-outline"></ion-icon> Special character
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password</label>
                    <div class="password-input-wrap">
                        <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Re-enter new password" required minlength="8">
                        <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('confirmPassword', this); return false;" aria-label="Toggle password visibility">
                            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                    </div>
                    <div id="pwMatchFeedback" style="font-size:0.75rem;margin-top:5px;display:none;"></div>
                </div>
                <button type="submit" class="btn btn-primary" id="changePwBtn">
                    <ion-icon name="key-outline"></ion-icon> Update Password
                </button>
            </form>
        </div>
    </div>
</main>

<div class="toast-container" id="toastContainer"></div>

<script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
<script src="../../assets/js/admin/settings.js?v=<?= time() ?>"></script>
</body>
</html>
