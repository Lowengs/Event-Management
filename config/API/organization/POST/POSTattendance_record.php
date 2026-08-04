<?php
/**
 * Organization API: Record Attendance (QR, Face, Manual Scanner)
 * Endpoint: /config/API/endpoints/index.php?action=POSTattendance_record
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$eventId     = (int)($_POST['EventId'] ?? 0);
$studentId   = trim($_POST['StudentId'] ?? '');
$studentName = trim($_POST['StudentName'] ?? '');
$method      = trim($_POST['Method'] ?? 'manual');
$logType     = trim($_POST['LogType'] ?? 'Log In');
$status      = 'present';

// Parse JSON QR payload if StudentId is a JSON string
if (!empty($studentId) && $studentId[0] === '{') {
    $qrPayload = json_decode($studentId, true);
    if ($qrPayload && isset($qrPayload['type']) && $qrPayload['type'] === 'student_qr') {
        $studentId = $qrPayload['student_id'] ?? $qrPayload['user_id'] ?? $studentId;
    }
}

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}
if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
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

// Look up the user to get the UserId
$escaped = $conn->real_escape_string($studentId);
$userResult = $conn->query("
    SELECT UserId, first_name, last_name, student_id 
    FROM `user` 
    WHERE student_id = '$escaped' 
       OR UserId = '" . intval($studentId) . "'
       OR Email = '$escaped'
    LIMIT 1
");
$userRow = $userResult ? $userResult->fetch_assoc() : null;

if (!$userRow) {
    echo json_encode(['success' => false, 'message' => 'Student not found in database']);
    exit;
}

$userId = (int)$userRow['UserId'];
$actualStudentName = trim($userRow['first_name'] . ' ' . $userRow['last_name']);
if (empty($studentName)) $studentName = $actualStudentName;

// Check if student is registered/pre-registered for this event
if (empty($_POST['force']) && empty($_POST['bypass_registration'])) {
    $regCheck = $conn->query("SELECT 1 FROM eventregistration WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
    if (!$regCheck || $regCheck->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => "Attendance cannot be recorded. $studentName is not registered or pre-registered for this event."
        ]);
        exit;
    }
}

// Do not create duplicate attendance rows. A student may be marked only once
// per event, regardless of whether the original scan used Log In or Log Out.
$existingAttendance = $conn->query("SELECT LogType FROM attendance WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
if ($existingAttendance && $existingAttendance->num_rows > 0) {
    $existing = $existingAttendance->fetch_assoc();
    echo json_encode([
        'success' => false,
        'message' => "$studentName already has an attendance record for this event" . (!empty($existing['LogType']) ? " ({$existing['LogType']})" : '') . '.'
    ]);
    exit;
}

try {
    // Try stored procedure first
    $stmt = $conn->prepare("CALL sp_RecordAttendance(?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $eventId, $userId, $method, $status, $logType);
    
    if ($stmt->execute()) {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { $conn->store_result(); }
        echo json_encode([
            'success' => true,
            'message' => "$logType recorded for $studentName"
        ]);
    } else {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { $conn->store_result(); }
        
        // Fallback: direct insert
        $ins = $conn->prepare("INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType) VALUES (?, ?, ?, ?, NOW(), ?)");
        $ins->bind_param("iisss", $eventId, $userId, $method, $status, $logType);
        if ($ins->execute()) {
            echo json_encode([
                'success' => true,
                'message' => "$logType recorded for $studentName"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to record attendance: ' . $ins->error]);
        }
        $ins->close();
    }
} catch (Exception $e) {
    try {
        $ins = $conn->prepare("INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType) VALUES (?, ?, ?, ?, NOW(), ?)");
        $ins->bind_param("iisss", $eventId, $userId, $method, $status, $logType);
        if ($ins->execute()) {
            echo json_encode([
                'success' => true,
                'message' => "$logType recorded for $studentName"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $ins->error]);
        }
        $ins->close();
    } catch (Exception $e2) {
        echo json_encode(['success' => false, 'message' => $e2->getMessage()]);
    }
}
?>
