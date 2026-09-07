<?php
/**
 * session_guard.php
 * Included at the top of protected pages.
 * Ensures the user is logged in and has the required role.
 * 
 * Usage:
 * $required_role = 'osa'; // or 'organization', 'student', 'admin'
 * require_once '../../config/session_guard.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/session_helper.php';

// Enforce 40-minute inactivity timeout
checkSessionInactivityTimeout($conn ?? null);

// Normalize target role
$targetRole = isset($required_role) ? strtolower(trim($required_role)) : '';
if ($targetRole === 'org') $targetRole = 'organization';

// Auto-detect role if $_SESSION['role'] is not explicitly set
if (empty($_SESSION['role'])) {
    if (!empty($_SESSION['osa_id'])) {
        $_SESSION['role'] = 'osa';
    } elseif (!empty($_SESSION['org_id'])) {
        $_SESSION['role'] = 'organization';
    } elseif (!empty($_SESSION['admin_id'])) {
        $_SESSION['role'] = 'admin';
        $_SESSION['admin_logged_in'] = true;
    } elseif (!empty($_SESSION['student_id'])) {
        $_SESSION['role'] = 'student';
    }
}
if (!empty($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') === 'admin') {
    $_SESSION['admin_logged_in'] = true;
}

if (empty($_SESSION['role'])) {
    // Not logged in at all, redirect to the specific portal needed for this page
    if ($targetRole === 'admin') {
        header('Location: ../admin/login.php');
    } elseif ($targetRole === 'student') {
        header('Location: ../student/login.php');
    } else {
        header('Location: ../osa/login.php');
    }
    exit;
}

// Normalize current role
$currentRole = strtolower(trim($_SESSION['role']));
if ($currentRole === 'org') $currentRole = 'organization';

if (!empty($targetRole) && $currentRole !== $targetRole) {
    // Logged in, but wrong role. Redirect based on their actual role
    if ($currentRole === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($currentRole === 'osa') {
        header('Location: ../osa/dashboard_final.php');
    } elseif ($currentRole === 'organization') {
        header('Location: ../organization/dashboard_org.php');
    } else {
        header('Location: ../student/profile-dashboard.php');
    }
    exit;
}

// Store current user ID
$current_user_id = null;
if ($currentRole === 'admin')        $current_user_id = $_SESSION['admin_id'] ?? null;
if ($currentRole === 'osa')          $current_user_id = $_SESSION['osa_id'] ?? null;
if ($currentRole === 'organization') $current_user_id = $_SESSION['org_id'] ?? null;
if ($currentRole === 'student')      $current_user_id = $_SESSION['student_id'] ?? $_SESSION['user_id'] ?? null;

// Actively enforce suspension check for currently logged in sessions
if (!empty($currentRole) && !empty($current_user_id)) {
    $statusCheckTable = [
        'student'      => ['user', 'UserId', '../student/login.php'],
        'organization' => ['organization', 'OrgId', '../organization/login_org.php'],
        'osa'          => ['osa', 'OsaId', '../osa/login.php'],
        'admin'        => ['admin', 'AdminId', '../admin/login.php'],
    ];
    if (isset($statusCheckTable[$currentRole])) {
        list($tbl, $col, $loginRedir) = $statusCheckTable[$currentRole];
        if (!isset($conn) || !$conn) {
            @require_once __DIR__ . '/../db.php';
        }
        if (isset($conn) && $conn instanceof mysqli) {
            $chkStmt = $conn->prepare("SELECT Status FROM `$tbl` WHERE `$col` = ? LIMIT 1");
            if ($chkStmt) {
                $chkStmt->bind_param("i", $current_user_id);
                $chkStmt->execute();
                $chkRes = $chkStmt->get_result();
                if ($chkRes && $chkRow = $chkRes->fetch_assoc()) {
                    $accStatus = strtolower($chkRow['Status'] ?? 'active');
                    if ($accStatus === 'suspended' || $accStatus === 'inactive') {
                        $_SESSION = [];
                        if (session_id()) session_destroy();
                        header("Location: {$loginRedir}?error=suspended");
                        exit;
                    }
                }
                $chkStmt->close();
            }
        }
    }
}
?>
