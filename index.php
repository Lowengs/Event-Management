<?php
/**
 * Root index.php
 * Redirects visitors accessing http://localhost/Project/ directly to the main application portal.
 */
session_start();

if (!empty($_SESSION['osa_id'])) {
    header('Location: app/osa/dashboard_final.php');
    exit;
} elseif (!empty($_SESSION['org_id'])) {
    header('Location: app/organization/dashboard_org.php');
    exit;
} elseif (!empty($_SESSION['admin_id'])) {
    header('Location: app/admin/dashboard.php');
    exit;
}

// Determine target location
$target = 'app/index.php';
if (!empty($_GET)) {
    $target .= '?' . http_build_query($_GET);
}

header('Location: ' . $target, true, 302);
exit;
