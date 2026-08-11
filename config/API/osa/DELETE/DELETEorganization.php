<?php
/**
 * OSA API: DELETE Organization
 * Endpoint: /config/API/endpoints/index.php?action=DELETEorganization
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'OSA login required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$orgId = (int)($input['org_id'] ?? $_GET['org_id'] ?? 0);

if (!$orgId) {
    echo json_encode(['success' => false, 'message' => 'Organization ID required']);
    exit;
}

try {
    // Cascade cleanup foreign key dependencies to prevent constraint errors
    $conn->query("UPDATE `user` SET OrgId = NULL WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `announcement` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `org_documents` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `org_messages` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `certificates` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `certificatetemplate` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `certificate_templates` WHERE OrgId = $orgId");
    $conn->query("DELETE a FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE er FROM eventregistration er JOIN event e ON e.EventId = er.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE ass FROM assessments ass JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $orgId");
    $conn->query("DELETE FROM `event` WHERE OrgId = $orgId");

    $stmt = $conn->prepare("DELETE FROM organization WHERE OrgId = ?");
    $stmt->bind_param("i", $orgId);
    if ($stmt->execute()) {
        $stmt->close();
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            $osaId = (int)($_SESSION['osa_id'] ?? $_SESSION['admin_id'] ?? 1);
            logAudit($conn, 'Delete Organization', 'osa', $osaId, 'success', ['OrgId' => $orgId]);
        }
        echo json_encode(['success' => true, 'message' => 'Organization deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
