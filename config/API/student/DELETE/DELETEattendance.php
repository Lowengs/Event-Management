<?php
/**
 * Student API: DELETE Attendance Record
 * Endpoint: /config/API/endpoints/index.php?action=DELETEattendance
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId = (int)$_SESSION['student_id'];
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$eventId = (int)($input['event_id'] ?? $_GET['event_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID required']);
    exit;
}

try {
    $stmt = $conn->prepare("CALL sp_DeleteAttendance(?, ?)");
    $stmt->bind_param("ii", $eventId, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
        echo json_encode(['success' => true, 'message' => 'Attendance record deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete attendance record']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
