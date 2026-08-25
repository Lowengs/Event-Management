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
                <input type="password" id="adminPassword" class="form-control" placeholder="Enter your password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary" id="loginBtn">
                <ion-icon name="log-in-outline"></ion-icon> Sign In
            </button>
        </form>

        <div style="text-align:center;margin-top:24px;">
            <a href="../osa/index.php" style="color:#1e40af;text-decoration:none;font-weight:600;font-size:0.88rem;display:inline-flex;align-items:center;justify-content:center;gap:6px;">← Back to OSA / Organization Login</a>
        </div>
    </div>

    <script src="../../assets/js/admin/login.js"></script>
</body>
</html>
