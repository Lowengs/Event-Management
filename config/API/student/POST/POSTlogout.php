<?php
/**
 * Student API: POST Logout
 * Endpoint: /config/API/endpoints/index.php?action=POSTlogout
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../audit.php';

$studentId   = !empty($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : (!empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
$studentName = $_SESSION['student_name'] ?? $_SESSION['user_name'] ?? null;
$studentEmail= $_SESSION['student_email'] ?? null;

if ($conn && $conn instanceof mysqli && function_exists('logAudit') && ($studentId !== null || $studentName !== null)) {
    logAudit(
        $conn,
        'Logout',
        'student',
        $studentId,
        'success',
        [
            'email'  => $studentEmail,
            'portal' => 'Student'
        ],
        $studentName ?: 'Student'
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
    echo json_encode(['success' => true, 'message' => 'Logged out successfully', 'redirect' => '../../../app/index.php?logout=success']);
    exit;
}

header('Location: ../../../app/index.php?logout=success');
exit;
?>
