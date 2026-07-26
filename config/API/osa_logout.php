<?php
/**
 * osa_logout.php — Destroys OSA session and redirects to login.
 * Use this as the logout href to avoid "Confirm Form Resubmission".
 */
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../audit.php';

$id   = $_SESSION['osa_id']    ?? null;
$name = $_SESSION['osa_name']  ?? '';

if ($id) {
    logAudit($conn, 'OSA Logout', 'osa', (int)$id, 'success', ['name' => $name]);
}

session_unset();
session_destroy();
setcookie('osa_remember', '', time() - 3600, '/'); // Clear remember me cookie if set

// Use POST/Redirect pattern — redirect with no POST data involved
header('Location: ../../app/osa/login.php');
exit;
