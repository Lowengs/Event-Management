<?php
/**
 * session_guard.php
 * Included at the top of protected pages.
 * Ensures the user is logged in and has the required role.
 * 
 * Usage:
 * $required_role = 'osa'; // or 'organization', 'student'
 * require_once '../../config/session_guard.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    // Not logged in at all, redirect to the general login or index
    header('Location: ../index.php');
    exit;
}

if (isset($required_role) && $_SESSION['role'] !== $required_role) {
    // Logged in, but wrong role. Redirect based on their actual role
    if ($_SESSION['role'] === 'osa') {
        header('Location: ../osa/dashboard_final.php');
    } elseif ($_SESSION['role'] === 'organization') {
        header('Location: ../organization/dashboard_org.php');
    } else {
        // Assume student
        header('Location: ../index.php');
    }
    exit;
}

// Optionally, you can also store the user's ID in a standard variable for easy access:
$current_user_id = null;
if ($_SESSION['role'] === 'osa') $current_user_id = $_SESSION['osa_id'] ?? null;
if ($_SESSION['role'] === 'organization') $current_user_id = $_SESSION['org_id'] ?? null;
if ($_SESSION['role'] === 'student') $current_user_id = $_SESSION['user_id'] ?? null;
?>
