<?php
session_start();
$_SESSION['student_id'] = 47;

// include the exact code from get_student_certificates.php
require '../db.php';

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
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $row['FieldConfig'] = json_decode($row['FieldConfig'] ?? '[]', true);
        $certs[] = $row;
    }
}

$output = ['success'=>true,'certificates'=>$certs];
echo json_encode($output, JSON_PRETTY_PRINT);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "\nJSON Error: " . json_last_error_msg();
}
