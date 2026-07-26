<?php
/** get_attendance_log.php — returns attendance records for a specific event */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId   = (int)$_SESSION['org_id'];
$eventId = (int)($_GET['EventId'] ?? 0);

if (!$eventId) { echo json_encode(['success'=>false,'message'=>'EventId required']); exit; }

// Verify the event belongs to this org
$check = $conn->query("SELECT EventId FROM event WHERE EventId=$eventId AND OrgId=$orgId");
if (!$check || $check->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Event not found']); exit; }

$attendance = [];
$r = $conn->query("
    SELECT a.*, e.EventName, u.first_name, u.last_name, u.student_id 
    FROM attendance a 
    LEFT JOIN event e ON a.EventId=e.EventId 
    LEFT JOIN user u ON a.UserId = u.UserId
    WHERE a.EventId=$eventId 
    ORDER BY a.Timestamp ASC
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $row['StudentName'] = trim(($row['first_name']??''). ' ' . ($row['last_name']??''));
        $row['StudentId'] = $row['student_id'] ?? '—';
        $row['ScannedAt'] = $row['Timestamp'] ?? '';
        $row['Method'] = $row['ScanType'] ?? 'manual';
        $row['LogType'] = $row['LogType'] ?? 'Log In';
        $attendance[] = $row;
    }
}

echo json_encode(['success'=>true,'attendance'=>$attendance,'total'=>count($attendance)]);
