<?php
/**
 * config/session_helper.php
 * Centralized session helper & inactivity timeout enforcement.
 * Enforces a strict 40-minute (2400 seconds) inactivity timeout.
 */

if (!defined('SESSION_INACTIVITY_TIMEOUT')) {
    define('SESSION_INACTIVITY_TIMEOUT', 2400); // 40 minutes in seconds
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * Checks if there is an authenticated user session in the current request.
 */
function isUserLoggedIn(): bool {
    return !empty($_SESSION['student_id'])
        || !empty($_SESSION['osa_id'])
        || !empty($_SESSION['org_id'])
        || !empty($_SESSION['admin_id'])
        || !empty($_SESSION['role']);
}

/**
 * Returns the normalized role of the current authenticated user.
 */
function getCurrentUserRole(): string {
    if (!empty($_SESSION['role'])) {
        $role = strtolower(trim($_SESSION['role']));
        if ($role === 'org') return 'organization';
        return $role;
    }
    if (!empty($_SESSION['admin_id'])) return 'admin';
    if (!empty($_SESSION['osa_id']))   return 'osa';
    if (!empty($_SESSION['org_id']))   return 'organization';
    if (!empty($_SESSION['student_id'])) return 'student';
    return '';
}

/**
 * Validates the session inactivity timeout.
 * If more than 40 minutes (2400s) have passed since the last recorded activity,
 * the session is destroyed and the user is redirected / an API error is returned.
 */
function checkSessionInactivityTimeout(?mysqli $conn = null): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (!isUserLoggedIn()) {
        return;
    }

    $now = time();
    $lastActivity = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : $now;

    // Check if session has exceeded the 40-minute inactivity window
    if (isset($_SESSION['last_activity']) && ($now - $lastActivity) > SESSION_INACTIVITY_TIMEOUT) {
        $role = getCurrentUserRole();
        
        // Optional audit logging if connection is present
        if ($conn && $conn instanceof mysqli && function_exists('logAudit')) {
            $userId = $_SESSION['admin_id'] ?? $_SESSION['osa_id'] ?? $_SESSION['org_id'] ?? $_SESSION['student_id'] ?? null;
            try {
                logAudit($conn, 'Session Timeout', $role ?: 'user', $userId ? (int)$userId : null, 'success', [
                    'reason' => '40 minutes inactivity timeout',
                    'last_activity' => date('Y-m-d H:i:s', $lastActivity),
                    'timeout_seconds' => SESSION_INACTIVITY_TIMEOUT
                ]);
            } catch (\Throwable $e) {}
        }

        // Wipe session contents
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();

        // Determine if request is API/JSON or HTML page
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $isApi = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT)
              || stripos($uri, '/API/') !== false
              || stripos($uri, '/api/') !== false
              || (stripos(basename($scriptFile), 'index.php') !== false && stripos($scriptFile, 'endpoints') !== false)
              || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if ($isApi) {
            if (!headers_sent()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success'         => false,
                'session_active'  => false,
                'session_expired' => true,
                'message'         => 'You were logged out for being inactive'
            ]);
            exit;
        }

        // Browser navigation: calculate relative path to appropriate login page
        $redirectUrl = getLoginRedirectUrlForRole($role);
        if (!headers_sent()) {
            header('Location: ' . $redirectUrl);
        } else {
            echo "<script>window.location.href = '" . htmlspecialchars($redirectUrl, ENT_QUOTES) . "';</script>";
        }
        exit;
    }

    // Refresh last activity timestamp
    $_SESSION['last_activity'] = $now;
}

/**
 * Helper to compute the appropriate login redirect path with session_expired query.
 */
function getLoginRedirectUrlForRole(string $role): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $inAdmin = stripos($script, '/app/admin/') !== false;
    $inOsa   = stripos($script, '/app/osa/') !== false;
    $inOrg   = stripos($script, '/app/organization/') !== false;
    $inStud  = stripos($script, '/app/student/') !== false;
    $inApp   = stripos($script, '/app/') !== false && !$inAdmin && !$inOsa && !$inOrg && !$inStud;

    if ($role === 'admin') {
        if ($inAdmin) return 'login.php?session_expired=1';
        if ($inOsa || $inOrg || $inStud) return '../admin/login.php?session_expired=1';
        if ($inApp) return 'admin/login.php?session_expired=1';
        return 'app/admin/login.php?session_expired=1';
    }

    if ($role === 'student') {
        if ($inStud) return 'login.php?session_expired=1';
        if ($inAdmin || $inOsa || $inOrg) return '../student/login.php?session_expired=1';
        if ($inApp) return 'student/login.php?session_expired=1';
        return 'app/student/login.php?session_expired=1';
    }

    // OSA & Organization share the OSA login page
    if ($inOsa) return 'login.php?session_expired=1';
    if ($inOrg || $inAdmin || $inStud) return '../osa/login.php?session_expired=1';
    if ($inApp) return 'osa/login.php?session_expired=1';
    return 'app/osa/login.php?session_expired=1';
}
