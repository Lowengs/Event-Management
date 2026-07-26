<?php
/** org_logout.php — destroy session and redirect */
session_start();
$orgName = $_SESSION['org_name'] ?? 'Organization';
session_unset();
session_destroy();
header('Location: ../../app/index.php');
exit;
