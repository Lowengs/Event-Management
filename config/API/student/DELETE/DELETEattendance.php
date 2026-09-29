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

$evName = '';
$eQ = $conn->query("SELECT EventName FROM event WHERE EventId = $eventId LIMIT 1");
if ($eQ && $er = $eQ->fetch_assoc()) {
    $evName = $er['EventName'];
}

$deleted = false;
try {
    $stmt = $conn->prepare("CALL sp_DeleteAttendance(?, ?)");
    if ($stmt) {
        $stmt->bind_param("ii", $eventId, $userId);
        if ($stmt->execute()) {
            $deleted = true;
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    $deleted = false;
}

if (!$deleted) {
    $stmt2 = $conn->prepare("DELETE FROM attendance WHERE EventId = ? AND UserId = ?");
    if ($stmt2) {
        $stmt2->bind_param("ii", $eventId, $userId);
        $deleted = $stmt2->execute();
        $stmt2->close();
    }
}

if ($deleted) {
    if (file_exists(__DIR__ . '/../../../audit.php')) {
        require_once __DIR__ . '/../../../audit.php';
        logAudit($conn, 'Delete Attendance Record', 'student', $userId, 'success', [
            'EventId'   => $eventId,
            'EventName' => $evName
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Attendance record deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to delete attendance record']);
}
?>
