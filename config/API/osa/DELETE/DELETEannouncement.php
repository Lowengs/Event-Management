<?php
/**
 * OSA API: DELETE Announcement
 * Endpoint: /config/API/endpoints/index.php?action=DELETEannouncement
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in']) && ($_SESSION['role'] ?? '') !== 'osa' && ($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    exit;
}

$inputData = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || empty($_POST)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $json = json_decode($rawInput, true);
        if (is_array($json)) {
            $inputData = array_merge($inputData, $json);
        } else {
            parse_str($rawInput, $parsed);
            if (is_array($parsed)) {
                $inputData = array_merge($inputData, $parsed);
            }
        }
    }
}

$announcementId = (int)($inputData['AnnouncementId'] ?? $inputData['announcement_id'] ?? $inputData['id'] ?? $_GET['AnnouncementId'] ?? $_GET['id'] ?? 0);

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
