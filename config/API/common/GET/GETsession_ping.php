<?php
/**
 * Common API: GET Session Ping / Keepalive Heartbeat
 * Route: /config/API/endpoints/index.php?action=session_ping
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../../config/session_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isUserLoggedIn()) {
    echo json_encode([
        'success'         => false,
        'session_active'  => false,
        'session_expired' => true,
        'message'         => 'No active user session'
    ]);
    exit;
}

$now = time();
$lastActivity = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : $now;
$timeout = defined('SESSION_INACTIVITY_TIMEOUT') ? SESSION_INACTIVITY_TIMEOUT : 2400;

if (($now - $lastActivity) > $timeout) {
    // Session expired
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();

    http_response_code(401);
    echo json_encode([
        'success'         => false,
        'session_active'  => false,
        'session_expired' => true,
        'message'         => 'You were logged out for being inactive'
    ]);
    exit;
}

// Session is active: refresh last_activity
$_SESSION['last_activity'] = $now;

echo json_encode([
    'success'           => true,
    'session_active'    => true,
    'session_expired'   => false,
    'timeout_seconds'   => $timeout,
    'remaining_seconds' => $timeout,
    'last_activity'     => $now
]);
exit;
