<?php
/**
 * OSA API: DELETE Announcement
 * Endpoint: /config/API/endpoints/index.php?action=DELETEannouncement
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    exit;
}

$announcementId = (int)($_POST['AnnouncementId'] ?? $_GET['AnnouncementId'] ?? 0);

if (!$announcementId) {
    echo json_encode(['success' => false, 'message' => 'Announcement ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM announcement WHERE AnnouncementId = ?");
    $stmt->bind_param("i", $announcementId);

    if ($stmt->execute()) {
        $stmt->close();
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Delete Announcement', 'osa', $_SESSION['osa_id'] ?? 1, 'success', ['announcement_id' => $announcementId]);
        }
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
