<?php
/** get_student_certificates.php — returns all certificates for logged-in student */
session_start();
require_once '../db.php';
header('Content-Type: application/json');
error_reporting(E_ERROR);
ini_set('display_errors', 0);

if (empty($_SESSION['student_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$userId = (int)$_SESSION['student_id'];

$r = $conn->query("
    SELECT 
        c.CertId, c.CertCode, c.IssuedAt,
        c.GeneratedImage,
        e.EventId, e.EventName, e.EventDateTime, e.EventLocation,
        o.OrgName, o.OrgPicture,
        t.TemplateName, t.TemplateImage, t.FieldConfig
    FROM certificates c
    JOIN certificate_templates t ON t.TemplateId = c.TemplateId
    JOIN event e ON e.EventId = c.EventId
    JOIN organization o ON o.OrgId = e.OrgId
    WHERE c.UserId = $userId
    ORDER BY c.IssuedAt DESC
");

$certs = [];
if ($r) while ($row = $r->fetch_assoc()) {
    $row['FieldConfig'] = json_decode($row['FieldConfig'] ?? '[]', true);
    $certs[] = $row;
}

echo json_encode(['success'=>true,'certificates'=>$certs]);
