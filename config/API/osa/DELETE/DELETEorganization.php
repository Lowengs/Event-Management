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
    $stmt = $conn->prepare("DELETE FROM organization WHERE OrgId = ?");
    $stmt->bind_param("i", $orgId);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Organization deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
