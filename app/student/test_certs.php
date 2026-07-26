<?php
session_start();
require_once '../../config/db.php';

echo "<h1>Session check</h1>";
echo "Student ID: " . ($_SESSION['student_id'] ?? 'Not set') . "<br>";

echo "<h1>API Response</h1>";
$userId = (int)($_SESSION['student_id'] ?? 0);
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
        $certs[] = $row;
    }
}
echo "<pre>";
print_r($certs);
echo "</pre>";
