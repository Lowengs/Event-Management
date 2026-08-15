<?php
/**
 * Organization API: DELETE Certificate Template
 * Endpoint: /config/API/endpoints/index.php?action=delete_certificate_template
 */
if (!isset($_SESSION['org_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$templateId = (int)($_POST['template_id'] ?? $_GET['template_id'] ?? 0);

if (!$templateId) {
    echo json_encode(['success' => false, 'message' => 'Missing Template ID']);
    exit;
}

// Check template existence and ownership
$stmt = $conn->prepare("SELECT TemplateId, Name, BackgroundUrl FROM certificate_templates WHERE TemplateId = ? AND OrgId = ? LIMIT 1");
$stmt->bind_param("ii", $templateId, $orgId);
$stmt->execute();
$tpl = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tpl) {
    echo json_encode(['success' => false, 'message' => 'Certificate template not found or unauthorized']);
    exit;
}

// Delete or mark as deleted
$delStmt = $conn->prepare("DELETE FROM certificate_templates WHERE TemplateId = ? AND OrgId = ?");
$delStmt->bind_param("ii", $templateId, $orgId);
$success = $delStmt->execute();
$delStmt->close();

if ($success) {
    require_once __DIR__ . '/../../audit.php';
    if (function_exists('logAudit')) {
        logAudit($conn, 'Delete Certificate Template', 'organization', $orgId, 'success', [
            'TemplateId' => $templateId,
            'Name' => $tpl['Name']
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Certificate template deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error deleting template: ' . $conn->error]);
}
exit;
