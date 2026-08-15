<?php
/**
 * Organization API: GET Attendance Log for an Event
 * Endpoint: /config/API/endpoints/index.php?action=get_attendance_log
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json; charset=utf-8');
}

$eventId = (int)($_GET['EventId'] ?? $_GET['event_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'attendance' => [], 'message' => 'Event ID required']);
    if ($isDirectApiCall) exit;
    return;
}

try {
    $stmt = $conn->prepare("
        SELECT a.AttendanceId, a.EventId, a.UserId, a.ScanType AS Method, 
               a.AttendanceStatus, a.Timestamp AS ScannedAt, a.LogType,
               u.first_name, u.last_name, u.student_id,
               CONCAT(u.first_name, ' ', u.last_name) AS StudentName,
               COALESCE(NULLIF(u.student_id, ''), '—') AS StudentId
        FROM attendance a
        LEFT JOIN `user` u ON u.UserId = a.UserId
        WHERE a.EventId = ?
        ORDER BY a.Timestamp DESC
    ");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attendance = [];
    while ($row = $result->fetch_assoc()) {
        $attendance[] = $row;
    }
    $stmt->close();
    
    echo json_encode([
        'success'    => true,
        'attendance' => $attendance,
        'total'      => count($attendance)
    ]);
    if ($isDirectApiCall) exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'attendance' => [], 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
?>
