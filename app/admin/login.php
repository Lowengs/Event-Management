<?php
session_start();

// Redirect logged-in users to their respective dashboards
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
} elseif (!empty($_SESSION['osa_id'])) {
    header('Location: ../osa/dashboard_final.php');
    exit;
} elseif (!empty($_SESSION['org_id'])) {
    header('Location: ../organization/dashboard_org.php');
    exit;
} elseif (!empty($_SESSION['student_id'])) {
    header('Location: ../student/profile-dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP – Administrator Login</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="../../assets/img/philsca.png">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        .admin-login-card .btn-primary, #loginBtn {
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            padding: 12px 14px !important;
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            background: #1e40af !important;
            border: 1px solid #1e40af !important;
            color: #ffffff !important;
            border-radius: 10px !important;
            box-shadow: none !important;
            cursor: pointer !important;
            transition: background 0.2s ease !important;
            margin-top: 10px !important;
        }
        .admin-login-card .btn-primary:hover, #loginBtn:hover {
            background: #2563eb !important;
            border-color: #2563eb !important;
            box-shadow: none !important;
        }
    </style>
<script src="../../assets/js/security.js"></script>
</head>
<body class="admin-login-body">

    <div class="admin-login-card">
        <img src="../../assets/img/philsca.png" alt="NAAP" class="login-logo">
        <h1>Administrator Login</h1>
        <p class="subtitle">Sign in to the NAAP System Admin Panel</p>

        <div class="login-error-msg" id="loginError"></div>

        <form id="adminLoginForm" novalidate>
            <div class="form-group">
                <label for="adminEmail">Email Address</label>
                <input type="email" id="adminEmail" class="form-control" placeholder="admin@naap.edu.ph" autocomplete="email" required>
            </div>
            <div class="form-group">
                <label for="adminPassword">Password</label>
                <div class="password-input-wrap">
                    <input type="password" id="adminPassword" class="form-control" placeholder="Enter your password" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('adminPassword', this); return false;" aria-label="Toggle password visibility">
                        <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" id="loginBtn">
                <ion-icon name="log-in-outline"></ion-icon> Sign In
            </button>
        </form>

        <div style="text-align:center;margin-top:24px;">
            <a href="../osa/index.php" style="color:#1e40af;text-decoration:none;font-weight:600;font-size:0.88rem;display:inline-flex;align-items:center;justify-content:center;gap:6px;">← Back to OSA / Organization Login</a>
        </div>
    </div>

    <div id="adminToast" role="alert" aria-live="polite" style="display:none;position:fixed;top:24px;right:24px;z-index:99999;"></div>

    <script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        if (btn) {
            const openSvg = btn.querySelector('.eye-open');
            const closedSvg = btn.querySelector('.eye-closed');
            if (openSvg && closedSvg) {
                openSvg.style.display = isPassword ? 'none' : 'block';
                closedSvg.style.display = isPassword ? 'block' : 'none';
            }
        }
    }
    </script>
    <script src="../../assets/js/admin/login.js?v=<?= time() ?>"></script>
</body>
</html>
