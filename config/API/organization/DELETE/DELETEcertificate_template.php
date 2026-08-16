<?php
/**
 * Organization API: DELETE Certificate Template
 * Endpoint: /config/API/endpoints/index.php?action=delete_certificate_template
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$templateId = (int)($_POST['TemplateId'] ?? $_POST['template_id'] ?? $_POST['id'] ?? $_GET['TemplateId'] ?? $_GET['template_id'] ?? $_GET['id'] ?? 0);

if (!$templateId) {
    echo json_encode(['success' => false, 'message' => 'Missing Template ID']);
    exit;
}

// Check template existence and ownership
$stmt = $conn->prepare("SELECT TemplateId, TemplateName, TemplateImage FROM certificate_templates WHERE TemplateId = ? AND OrgId = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ii", $templateId, $orgId);
$stmt->execute();
$tpl = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tpl) {
    echo json_encode(['success' => false, 'message' => 'Certificate template not found or unauthorized']);
    exit;
}

// Delete from certificate_templates
$delStmt = $conn->prepare("DELETE FROM certificate_templates WHERE TemplateId = ? AND OrgId = ?");
if (!$delStmt) {
    echo json_encode(['success' => false, 'message' => 'Database delete prepare error: ' . $conn->error]);
    exit;
}

$delStmt->bind_param("ii", $templateId, $orgId);
$success = $delStmt->execute();
$delStmt->close();

if ($success) {
    if (file_exists(__DIR__ . '/../../../audit.php')) {
        require_once __DIR__ . '/../../../audit.php';
        if (function_exists('logAudit')) {
            logAudit($conn, 'Delete Certificate Template', 'organization', $orgId, 'success', [
                'TemplateId' => $templateId,
                'TemplateName' => $tpl['TemplateName'] ?? ''
            ]);
        }
    }
    echo json_encode(['success' => true, 'message' => 'Certificate template deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error deleting template: ' . $conn->error]);
}
exit;
