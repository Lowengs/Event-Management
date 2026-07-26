<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$out = [];

// Check if certificates table exists
$r = $conn->query("SHOW TABLES LIKE 'certificates'");
$out['certificates_table_exists'] = ($r && $r->num_rows > 0);

// If exists, show all rows for any user
if ($out['certificates_table_exists']) {
    $rows = [];
    $r2 = $conn->query("SELECT * FROM certificates LIMIT 20");
    if ($r2) while ($row = $r2->fetch_assoc()) $rows[] = $row;
    $out['certificates_rows'] = $rows;
    $out['certificates_count'] = count($rows);
}

// Check if certificate_templates table exists
$r3 = $conn->query("SHOW TABLES LIKE 'certificate_templates'");
$out['cert_templates_exists'] = ($r3 && $r3->num_rows > 0);

if ($out['cert_templates_exists']) {
    $tpls = [];
    $r4 = $conn->query("SELECT TemplateId, OrgId, TemplateName, IsDeleted FROM certificate_templates LIMIT 10");
    if ($r4) while ($row = $r4->fetch_assoc()) $tpls[] = $row;
    $out['templates'] = $tpls;
}

// Check attendance rows
$rows2 = [];
$r5 = $conn->query("SELECT * FROM attendance LIMIT 10");
if ($r5) while ($row = $r5->fetch_assoc()) $rows2[] = $row;
$out['attendance_rows'] = $rows2;

// GD check
$out['gd_enabled'] = extension_loaded('gd');

// Session check
$out['session_student_id'] = $_SESSION['student_id'] ?? null;
$out['session_org_id']     = $_SESSION['org_id'] ?? null;

echo json_encode($out, JSON_PRETTY_PRINT);
