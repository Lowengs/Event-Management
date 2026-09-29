<?php
/**
 * OSA API: POST Logout
 * Endpoint: /config/API/endpoints/index.php?action=POSTlogout
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../audit.php';

$osaId   = !empty($_SESSION['osa_id']) ? (int)$_SESSION['osa_id'] : null;
$osaName = $_SESSION['osa_name'] ?? null;
$osaEmail= $_SESSION['osa_email'] ?? null;

if ($conn && $conn instanceof mysqli && function_exists('logAudit') && ($osaId !== null || $osaName !== null)) {
    logAudit(
        $conn,
        'Logout',
        'osa',
        $osaId,
        'success',
        [
            'email'  => $osaEmail,
            'portal' => 'OSA'
        ],
        $osaName ?: 'OSA Administrator'
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
    echo json_encode(['success' => true, 'message' => 'Logged out successfully', 'redirect' => '../../../app/osa/login.php?logout=success']);
    exit;
}

header('Location: ../../../app/osa/login.php?logout=success');
exit;
?>
