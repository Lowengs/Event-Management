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
$evCheck = $conn->query("SELECT EventId, OrgId, EventName, EventDateTime, EndDateTime, EventStatus FROM event WHERE EventId = $eventId LIMIT 1");
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

// Look up the user to get the UserId & OrgId
$escaped = $conn->real_escape_string($studentId);
$userResult = $conn->query("
    SELECT UserId, first_name, last_name, student_id, OrgId 
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

// Auto-register student if scanning for attendance so event metrics remain accurate
$regCheck = $conn->query("SELECT 1 FROM eventregistration WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
if (!$regCheck || $regCheck->num_rows === 0) {
    $evOrgRes = $conn->query("SELECT OrgId FROM event WHERE EventId = $eventId LIMIT 1");
    $evOrgId = ($evOrgRes && ($evOrgRow = $evOrgRes->fetch_assoc())) ? (int)$evOrgRow['OrgId'] : null;
    if ($evOrgId && $conn->query("SELECT 1 FROM organization WHERE OrgId = $evOrgId LIMIT 1")->num_rows === 0) {
        $evOrgId = null;
    }
    if ($evOrgId) {
        $conn->query("INSERT INTO eventregistration (EventId, UserId, OrgId, DateIssued) VALUES ($eventId, $userId, $evOrgId, CURDATE())");
    } else {
        $conn->query("INSERT INTO eventregistration (EventId, UserId, DateIssued) VALUES ($eventId, $userId, CURDATE())");
    }
}

// Allow separate Log In and Log Out records. Block only exact duplicate log types.
$isLogOut = (strtolower($logType) === 'log out' || strtolower($logType) === 'check out');
$normalizedLogType = $isLogOut ? 'Log Out' : 'Log In';

$existingAttendance = $conn->query("SELECT LogType FROM attendance WHERE EventId = $eventId AND UserId = $userId");
$hasLogIn = false;
$hasLogOut = false;
if ($existingAttendance) {
    while ($row = $existingAttendance->fetch_assoc()) {
        $lt = strtolower(trim($row['LogType'] ?? 'log in'));
        if ($lt === 'log in' || $lt === 'check in') $hasLogIn = true;
        if ($lt === 'log out' || $lt === 'check out') $hasLogOut = true;
    }
}

if ($hasLogIn && $hasLogOut) {
    echo json_encode([
        'success' => false,
        'message' => "$studentName has already completed both Check-In and Check-Out for this event."
    ]);
    exit;
}

if (!$isLogOut && $hasLogIn) {
    echo json_encode([
        'success' => false,
        'message' => "$studentName has already checked in for this event."
    ]);
    exit;
}

if ($isLogOut && $hasLogOut) {
    echo json_encode([
        'success' => false,
        'message' => "$studentName has already checked out for this event."
    ]);
    exit;
}

if ($isLogOut && !$hasLogIn) {
    echo json_encode([
        'success' => false,
        'message' => "$studentName must check in before checking out."
    ]);
    exit;
}

// Use the normalized log type
$logType = $normalizedLogType;

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
