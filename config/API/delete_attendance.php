<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$attendanceId = (int)($_POST['AttendanceId'] ?? 0);
if (!$attendanceId) { echo json_encode(['success'=>false,'message'=>'ID required']); exit; }

$conn->query("DELETE FROM attendance WHERE AttendanceId = $attendanceId LIMIT 1");
if ($conn->affected_rows > 0) {
    echo json_encode(['success'=>true,'message'=>'Attendance testing record deleted']);
} else {
    echo json_encode(['success'=>false,'message'=>'Failed to delete or not found']);
}
