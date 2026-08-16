<?php
/**
 * Organization API: Delete Attendance Record
 * Endpoint: /config/API/endpoints/index.php?action=DELETEattendance
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true) ?: [];

$attendanceId = (int)(
    $_POST['AttendanceId'] ??
    $_POST['attendance_id'] ??
    $_GET['AttendanceId'] ??
    $_GET['attendance_id'] ??
    $json['AttendanceId'] ??
    $json['attendance_id'] ??
    0
);

if (!$attendanceId) {
    echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM attendance WHERE AttendanceId = ?");
    if ($stmt) {
        $stmt->bind_param("i", $attendanceId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Attendance record deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error preparing deletion']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
