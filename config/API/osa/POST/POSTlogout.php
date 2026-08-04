<?php
/**
 * OSA API: POST Logout
 * Endpoint: /config/API/endpoints/index.php?action=POSTlogout
 */
if (session_status() === PHP_SESSION_NONE) session_start();
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
