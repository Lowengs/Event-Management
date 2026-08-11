<?php
/**
 * Student API: POST Attendance
 * Endpoint: /config/API/endpoints/index.php?action=POSTattendance
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId  = (int)$_SESSION['student_id'];
$eventId = (int)($_POST['EventId'] ?? $_POST['event_id'] ?? 0);
$method  = trim($_POST['Method']   ?? $_POST['method']   ?? 'qr_self');
$status  = trim($_POST['Status']   ?? $_POST['status']   ?? 'present');

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

// ── Check if Student is Registered / Pre-registered for the Event ──
$regCheck = $conn->query("SELECT 1 FROM eventregistration WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
if (!$regCheck || $regCheck->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Attendance is restricted to registered or pre-registered students for this event. Please pre-register for the event first.'
    ]);
    exit;
}

$logType = trim($_POST['LogType'] ?? $_POST['log_type'] ?? 'Log In');
$isLogOut = (strtolower($logType) === 'log out' || strtolower($logType) === 'check out');

// ── Check Existing Attendance Log ────────────────────────────────────
$existingAtt = null;
$attCheck = $conn->query("SELECT AttendanceId, CheckInTime, CheckOutTime, LogType FROM attendance WHERE EventId = $eventId AND UserId = $userId ORDER BY AttendanceId DESC LIMIT 1");
if ($attCheck && $attCheck->num_rows > 0) {
    $existingAtt = $attCheck->fetch_assoc();
}

if ($isLogOut) {
    if ($existingAtt && !empty($existingAtt['CheckOutTime'])) {
        echo json_encode([
            'success' => false,
            'message' => 'You have already checked out of this event.'
        ]);
        exit;
    }
    
    if ($existingAtt) {
        // Update existing attendance record with CheckOutTime
        $attId = (int)$existingAtt['AttendanceId'];
        $upd = $conn->prepare("UPDATE attendance SET CheckOutTime = NOW(), LogType = 'Log Out' WHERE AttendanceId = ?");
        if ($upd) {
            $upd->bind_param("i", $attId);
            $upd->execute();
            $upd->close();
            echo json_encode([
                'success' => true,
                'message' => 'Check Out (Log Out) recorded successfully.'
            ]);
            exit;
        }
    }
} else {
    if ($existingAtt && (!empty($existingAtt['CheckInTime']) || strtolower($existingAtt['LogType'] ?? '') === 'log in')) {
        echo json_encode([
            'success' => false,
            'message' => 'You have already checked in (Log In) for this event.'
        ]);
        exit;
    }
}

// ── Check Attendance Window ──────────────────────────────────────────
$evCheck = $conn->query("SELECT EventName, EventDateTime, EndDateTime, EventStatus FROM event WHERE EventId = $eventId LIMIT 1");
if (!$evCheck || $evCheck->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}
$erow = $evCheck->fetch_assoc();

if (!empty($erow['EventDateTime']) && empty($_POST['force']) && empty($_POST['bypass_window'])) {
    $eventStart = strtotime($erow['EventDateTime']);
    $eventEnd   = !empty($erow['EndDateTime']) ? strtotime($erow['EndDateTime']) : ($eventStart + 7200);
    $openTime   = $eventStart - 3600;  // 1 hour ahead
    $closeTime  = $eventEnd + 3600;    // 1 hour after

    $now = time();
    $eventStatus = strtolower(trim($erow['EventStatus'] ?? ''));
    $attendanceAllowed =
        ($eventStatus === 'scheduled' && $now >= $openTime && $now < $eventStart) ||
        $eventStatus === 'ongoing' ||
        ($eventStatus === 'completed' && $now > $eventEnd && $now <= $closeTime);
    if (!$attendanceAllowed) {
        echo json_encode([
            'success' => false,
            'message' => 'Attendance is only available one hour before a scheduled event, while it is ongoing, or up to one hour after it ends.'
        ]);
        exit;
    }
}

try {
    $checkInVal  = $isLogOut ? null : date('Y-m-d H:i:s');
    $checkOutVal = $isLogOut ? date('Y-m-d H:i:s') : null;
    $ins = $conn->prepare("INSERT INTO `attendance` (EventId, UserId, ScanType, AttendanceStatus, Timestamp, CheckInTime, CheckOutTime, LogType) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)");
    if ($ins) {
        $ins->bind_param("iisssss", $eventId, $userId, $method, $status, $checkInVal, $checkOutVal, $logType);
        if ($ins->execute()) {
            $ins->close();
            echo json_encode([
                'success' => true,
                'message' => ($isLogOut ? 'Check Out (Log Out)' : 'Check In (Log In)') . ' recorded successfully.'
            ]);
            exit;
        }
        $ins->close();
    }
} catch (Exception $e2) {
    echo json_encode(['success' => false, 'message' => $e2->getMessage()]);
}
?>
