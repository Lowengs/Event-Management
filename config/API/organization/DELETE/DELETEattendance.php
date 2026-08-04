<?php
/**
 * Organization API: Delete Attendance Record
 * Endpoint: /config/API/endpoints/index.php?action=DELETEattendance
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'DELETE') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$attendanceId = (int)($_POST['AttendanceId'] ?? $_GET['AttendanceId'] ?? 0);

if (!$attendanceId) {
    echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM attendance WHERE AttendanceId = ?");
    $stmt->bind_param("i", $attendanceId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Attendance record deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
