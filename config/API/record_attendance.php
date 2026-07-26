<?php
/** record_attendance.php — record a single attendance entry (Log In or Log Out via QR or Face) */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

// Self-healing migration for LogType
try {
    $conn->query("ALTER TABLE attendance ADD COLUMN LogType VARCHAR(20) DEFAULT 'Log In'");
} catch (Exception $e) {}

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$eventId     = (int)($_POST['EventId'] ?? 0);
$studentId   = trim($_POST['StudentId'] ?? '');
$studentName = trim($_POST['StudentName'] ?? '');
$method      = trim($_POST['Method'] ?? 'manual');
$logType     = trim($_POST['LogType'] ?? 'Log In');

if (!in_array($logType, ['Log In', 'Log Out'])) {
    $logType = 'Log In';
}

if (!$eventId) { echo json_encode(['success'=>false,'message'=>'Event required']); exit; }

$eventCheck = $conn->prepare("SELECT EventStatus FROM event WHERE EventId=? AND OrgId=?");
$eventCheck->bind_param('ii', $eventId, $_SESSION['org_id']);
$eventCheck->execute();
$eventRow = $eventCheck->get_result()->fetch_assoc();
$eventCheck->close();

if (!$eventRow) {
    echo json_encode(['success'=>false,'message'=>'Event not found or access denied']);
    exit;
}

if (strtolower(trim($eventRow['EventStatus'] ?? '')) !== 'ongoing') {
    echo json_encode(['success'=>false,'message'=>'Change the event status to Ongoing before recording attendance.']);
    exit;
}

// Find user by student_id
$userId = null;
if ($studentId) {
    $decodedStudent = json_decode($studentId, true);
    if (is_array($decodedStudent)) {
        $studentId = trim((string)($decodedStudent['student_id'] ?? $decodedStudent['studentId'] ?? ''));
        if ($studentId === '' && !empty($decodedStudent['user_id'])) {
            $studentId = (string)(int)$decodedStudent['user_id'];
        }
        if (!$studentName && !empty($decodedStudent['name'])) {
            $studentName = trim((string)$decodedStudent['name']);
        }
    }

    if (strpos($studentId, 'ID:') === 0) {
        $studentId = trim(substr($studentId, 3));
    }
    $r = $conn->query("SELECT UserId, first_name, last_name FROM user WHERE student_id='".addslashes($studentId)."' LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $u = $r->fetch_assoc();
        $userId = $u['UserId'];
        if (!$studentName) $studentName = trim($u['first_name'].' '.$u['last_name']);
    } else {
        echo json_encode(['success'=>false,'message'=>'Student not found.']); exit;
    }
}

if (!$userId) {
    echo json_encode(['success'=>false,'message'=>'User ID could not be resolved.']); exit;
}

// Check existing attendance records
$checkIn = $conn->query("SELECT AttendanceId FROM attendance WHERE EventId=$eventId AND UserId=$userId AND (LogType='Log In' OR LogType IS NULL) LIMIT 1");
$hasLoggedIn = ($checkIn && $checkIn->num_rows > 0);

$checkOut = $conn->query("SELECT AttendanceId FROM attendance WHERE EventId=$eventId AND UserId=$userId AND LogType='Log Out' LIMIT 1");
$hasLoggedOut = ($checkOut && $checkOut->num_rows > 0);

if ($hasLoggedIn && $hasLoggedOut) {
    echo json_encode(['success'=>false, 'message'=>($studentName ?: "Student") . " has already completed Log In and Log Out for this event"]); exit;
}

if ($logType === 'Log In') {
    if ($hasLoggedIn && !$hasLoggedOut) {
        // Automatically switch next mode to Log Out since student already logged in
        $logType = 'Log Out';
    }
} else if ($logType === 'Log Out') {
    if (!$hasLoggedIn) {
        echo json_encode(['success'=>false, 'message'=>($studentName ?: "Student") . " must Log In first before Logging Out"]); exit;
    }
    if ($hasLoggedOut) {
        echo json_encode(['success'=>false, 'message'=>($studentName ?: "Student") . " has already logged out"]); exit;
    }
}

$stmt = $conn->prepare("INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus, LogType) VALUES (?, ?, ?, 'present', ?)");
if ($stmt) {
    $stmt->bind_param('iiss', $eventId, $userId, $method, $logType);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true, 'message'=>"$logType recorded for " . ($studentName ?: "Student"), 'student_name'=>$studentName, 'log_type'=>$logType]);
    } else {
        echo json_encode(['success'=>false, 'message'=>$conn->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success'=>false, 'message'=>$conn->error]);
}
