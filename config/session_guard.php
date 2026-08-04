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

// Auto-detect role if $_SESSION['role'] is not explicitly set
if (empty($_SESSION['role'])) {
    if (!empty($_SESSION['osa_id'])) {
        $_SESSION['role'] = 'osa';
    } elseif (!empty($_SESSION['org_id'])) {
        $_SESSION['role'] = 'organization';
    } elseif (!empty($_SESSION['admin_id'])) {
        $_SESSION['role'] = 'admin';
    } elseif (!empty($_SESSION['student_id'])) {
        $_SESSION['role'] = 'student';
    }
}

if (empty($_SESSION['role'])) {
    // Not logged in at all, redirect to login page
    header('Location: ../osa/login.php');
    exit;
}

// Normalize role names
$currentRole = strtolower(trim($_SESSION['role']));
$targetRole  = isset($required_role) ? strtolower(trim($required_role)) : '';

// Map synonyms ('org' -> 'organization')
if ($currentRole === 'org') $currentRole = 'organization';
if ($targetRole === 'org')  $targetRole  = 'organization';

if (!empty($targetRole) && $currentRole !== $targetRole) {
    // Logged in, but wrong role. Redirect based on their actual role
    if ($currentRole === 'admin') {
        header('Location: ../admin/dashboard.php');
    } elseif ($currentRole === 'osa') {
        header('Location: ../osa/dashboard_final.php');
    } elseif ($currentRole === 'organization') {
        header('Location: ../organization/dashboard_org.php');
    } else {
        header('Location: ../index.php');
    }
    exit;
}

// Store current user ID
$current_user_id = null;
if ($currentRole === 'admin')        $current_user_id = $_SESSION['admin_id'] ?? null;
if ($currentRole === 'osa')          $current_user_id = $_SESSION['osa_id'] ?? null;
if ($currentRole === 'organization') $current_user_id = $_SESSION['org_id'] ?? null;
if ($currentRole === 'student')      $current_user_id = $_SESSION['student_id'] ?? $_SESSION['user_id'] ?? null;
?>
