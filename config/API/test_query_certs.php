<?php
require '../db.php';
$userId = 47; // Test for this user
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
if (!$r) {
    echo "Query Error: " . $conn->error;
} else {
    $certs = [];
    while ($row = $r->fetch_assoc()) {
        $certs[] = $row;
    }
    print_r($certs);
}
