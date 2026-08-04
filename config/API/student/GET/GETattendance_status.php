<?php
/**
 * Student API: GET Attendance Status
 * Endpoint: /config/API/endpoints/index.php?action=GETattendance_status
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}




$studentId = (int)($_SESSION['student_id'] ?? 0);
$eventId   = (int)($_GET['event_id'] ?? $_REQUEST['event_id'] ?? 0);

if (!$studentId || !$eventId) {
    echo json_encode(['success' => false, 'message' => 'Student ID and Event ID required']);
if ($isDirectApiCall) exit;
    exit;
}

try {
    $att = $conn->query("SELECT AttendanceId FROM attendance WHERE EventId = $eventId AND UserId = $studentId LIMIT 1");
    $hasAttendance = ($att && $att->num_rows > 0);

    echo json_encode(['success' => true, 'has_attendance' => $hasAttendance]);
if ($isDirectApiCall) exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
if ($isDirectApiCall) exit;
}
?>

