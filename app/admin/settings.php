<?php
$required_role = 'admin';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

$adminName   = htmlspecialchars($_SESSION['admin_name']  ?? 'Administrator');
$adminEmail  = htmlspecialchars($_SESSION['admin_email'] ?? 'admin@philsca.edu.ph');
$currentPage = 'settings';
$adminId     = (int)($_SESSION['admin_id'] ?? 1);
$admin       = ['Name' => $adminName, 'Email' => $adminEmail];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — NAAP Admin</title>
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
            <p><strong>Role:</strong> <span class="badge badge-purple">Super Administrator</span></p>
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
                    <input type="password" id="currentPassword" name="current_password" class="form-control" placeholder="Enter your current password" required>
                </div>
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" name="new_password" class="form-control" placeholder="Enter new password (min. 6 characters)" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirm New Password</label>
                    <input type="password" id="confirmPassword" name="confirm_password" class="form-control" placeholder="Re-enter new password" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary" id="changePwBtn">
                    <ion-icon name="key-outline"></ion-icon> Update Password
                </button>
            </form>
        </div>
    </div>
</main>

<div class="toast-container" id="toastContainer"></div>

<script src="../../assets/js/admin/settings.js"></script>
</body>
</html>
