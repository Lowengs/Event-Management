<?php
/**
 * Admin API: POST Logout
 * Endpoint: /config/API/endpoints/index.php?action=POSTlogout
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../audit.php';

$adminId   = !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
$adminName = $_SESSION['admin_name'] ?? null;
$adminEmail= $_SESSION['admin_email'] ?? null;

if ($conn && $conn instanceof mysqli && function_exists('logAudit') && ($adminId !== null || $adminName !== null)) {
    logAudit(
        $conn,
        'Logout',
        'admin',
        $adminId,
        'success',
        [
            'email'  => $adminEmail,
            'portal' => 'Admin'
        ],
        $adminName ?: 'System Administrator'
    );
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Logged out successfully', 'redirect' => '../../../app/admin/login.php?logout=success']);
    exit;
}

header('Location: ../../../app/admin/login.php?logout=success');
exit;
?>
