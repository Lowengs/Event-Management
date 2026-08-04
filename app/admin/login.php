<?php
session_start();
// Already logged in as admin — redirect to dashboard
if (!empty($_SESSION['admin_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP – Administrator Login</title>
    <link rel="stylesheet" href="../../assets/css/admin/admin.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="../../assets/img/philsca.png">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
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

        <p style="text-align:center;margin-top:24px;font-size:0.75rem;color:var(--text-muted);">
            <a href="../osa/login.php" style="color:var(--accent);text-decoration:none;">← Back to OSA / Organization Login</a>
        </p>
    </div>

    <script src="../../assets/js/admin/login.js"></script>
</body>
</html>
