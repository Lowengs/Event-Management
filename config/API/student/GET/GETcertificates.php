<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
if (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT) header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId = (int)$_SESSION['student_id'];
$sql = "SELECT c.CertId AS CertificateId, c.CertCode, c.GeneratedImage, c.CertificateURL, c.IssuedAt,
               e.EventName, e.EventDateTime, e.EventLocation,
               o.OrgName, t.TemplateName, t.TemplateImage, t.FieldConfig
        FROM certificates c
        LEFT JOIN event e ON e.EventId = c.EventId
        LEFT JOIN organization o ON o.OrgId = COALESCE(c.OrgId, e.OrgId)
        LEFT JOIN certificate_templates t ON t.TemplateId = c.TemplateId
        WHERE c.UserId = ? ORDER BY c.IssuedAt DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$certs = [];
while ($result && ($row = $result->fetch_assoc())) $certs[] = $row;

echo json_encode(['success' => true, 'message' => 'Certificates retrieved successfully', 'data' => $certs]);
?>
