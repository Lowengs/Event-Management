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
        ($eventStatus === 'ongoing') ||
        (in_array($eventStatus, ['scheduled', 'upcoming', 'ongoing', '']) && $now >= $openTime && $now <= $closeTime) ||
        ($eventStatus === 'completed' && $now <= $closeTime);

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

// Serialize check + insert per student/event so two scans arriving at the same
// moment cannot both pass the "already checked in" check (double Time In).
$attLockName = "att_{$eventId}_{$userId}";
$conn->query("SELECT GET_LOCK('$attLockName', 5)");
register_shutdown_function(function () use ($conn, $attLockName) {
    try { @$conn->query("SELECT RELEASE_LOCK('$attLockName')"); } catch (\Throwable $e) {}
});

// Allow separate Log In and Log Out records. Block only exact duplicate log types.
$isLogOut = (strtolower($logType) === 'log out' || strtolower($logType) === 'check out');
$normalizedLogType = $isLogOut ? 'Log Out' : 'Log In';

$existingAttendance = $conn->query("SELECT * FROM attendance WHERE EventId = $eventId AND UserId = $userId");
$hasLogIn = false;
$hasLogOut = false;
$logInTs = 0;
if ($existingAttendance) {
    while ($row = $existingAttendance->fetch_assoc()) {
        $lt = strtolower(trim($row['LogType'] ?? 'log in'));
        if ($lt === 'log in' || $lt === 'check in') {
            $hasLogIn = true;
            $t = strtotime($row['CheckInTime'] ?? '') ?: strtotime($row['Timestamp'] ?? '');
            if ($t && (!$logInTs || $t < $logInTs)) $logInTs = $t;
        }
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

// ── Minimum attendance: 75% of the event duration before Log Out ─────
// Measured from the student's own check-in. Without an end time the event
// is treated as 2 hours long (same default as the attendance window above).
if ($isLogOut && $logInTs) {
    $evStartTs = !empty($erow['EventDateTime']) ? strtotime($erow['EventDateTime']) : 0;
    $evEndTs   = !empty($erow['EndDateTime']) ? strtotime($erow['EndDateTime']) : 0;
    $durationSec = ($evStartTs && $evEndTs && $evEndTs > $evStartTs) ? ($evEndTs - $evStartTs) : 7200;
    $requiredSec = (int)ceil($durationSec * 0.75);
    $elapsedSec  = time() - $logInTs;

    if ($elapsedSec < $requiredSec) {
        $remaining = $requiredSec - $elapsedSec;
        $h = intdiv($remaining, 3600);
        $m = intdiv($remaining % 3600, 60);
        $s = $remaining % 60;
        $wait = $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
        $attendedPct = (int)floor(max(0, $elapsedSec) / $durationSec * 100);
        echo json_encode([
            'success'          => false,
            'code'             => 'min_attendance_not_met',
            'required_percent' => 75,
            'attended_percent' => $attendedPct,
            'remaining_seconds'=> $remaining,
            'message'          => "$studentName cannot check out yet. 75% attendance is required (currently $attendedPct%). Check-out opens in $wait."
        ]);
        exit;
    }
}

// Use the normalized log type
$logType = $normalizedLogType;

try {
    // Try stored procedure first
    $stmt = $conn->prepare("CALL sp_RecordAttendance(?, ?, ?, ?, ?)");
    $stmt->bind_param("iisss", $eventId, $userId, $method, $status, $logType);
    
    $recordedOk = false;
    if ($stmt->execute()) {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { $conn->store_result(); }
        $recordedOk = true;
    } else {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { $conn->store_result(); }
        
        // Fallback: direct insert
        $ins = $conn->prepare("INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType) VALUES (?, ?, ?, ?, NOW(), ?)");
        $ins->bind_param("iisss", $eventId, $userId, $method, $status, $logType);
        if ($ins->execute()) {
            $recordedOk = true;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to record attendance: ' . $ins->error]);
            $ins->close();
            exit;
        }
        $ins->close();
    }

    if ($recordedOk) {
        if (file_exists(__DIR__ . '/../../../../config/audit.php')) {
            require_once __DIR__ . '/../../../../config/audit.php';
            logAudit($conn, 'Attendance Recorded', 'organization', (int)($_SESSION['org_id'] ?? 0), 'success', [
                'EventId'      => $eventId,
                'EventName'    => $erow['EventName'] ?? '',
                'student_id'   => $studentId,
                'student_name' => $studentName,
                'method'       => $method,
                'log_type'     => $logType
            ]);
        }
        echo json_encode([
            'success' => true,
            'message' => "$logType recorded for $studentName"
        ]);
        exit;
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
