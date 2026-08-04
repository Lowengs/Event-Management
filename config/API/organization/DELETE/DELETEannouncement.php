<?php
/**
 * Organization API: DELETE Announcement
 * Endpoint: /config/API/endpoints/index.php?action=DELETEannouncement
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$inputData = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || empty($_POST)) {
    parse_str(file_get_contents('php://input'), $delData);
    if (!empty($delData)) $inputData = array_merge($inputData, $delData);
}

$orgId  = (int)$_SESSION['org_id'];
$annId  = (int)($inputData['AnnouncementId'] ?? $inputData['announcement_id'] ?? $inputData['id'] ?? 0);

if (!$annId) {
    echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
    exit;
}

try {
    $stmt = $conn->prepare("CALL sp_DeleteOrgAnnouncement(?, ?)");
    $stmt->bind_param("ii", $annId, $orgId);
    if ($stmt->execute()) {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { $conn->store_result(); }
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    while ($conn->more_results() && $conn->next_result()) { $conn->store_result(); }
    // Fallback: direct DELETE
    $stmt2 = $conn->prepare("DELETE FROM announcement WHERE AnnouncementId = ? AND OrgId = ?");
    $stmt2->bind_param("ii", $annId, $orgId);
    if ($stmt2->execute()) {
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt2->error]);
    }
    $stmt2->close();
}
?>
