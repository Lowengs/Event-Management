<?php
/** delete_certificate_template.php — POST: TemplateId */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId      = (int)$_SESSION['org_id'];
$templateId = (int)($_POST['TemplateId'] ?? 0);

if (!$templateId) { echo json_encode(['success'=>false,'message'=>'Template ID required']); exit; }

$stmt = $conn->prepare("UPDATE certificate_templates SET IsDeleted=1 WHERE TemplateId=? AND OrgId=?");
$stmt->bind_param('ii', $templateId, $orgId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success'=>true,'message'=>'Template deleted']);
} else {
    echo json_encode(['success'=>false,'message'=>'Template not found or unauthorized']);
}
