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

// Attendance is a single event record. A prior Log In or Log Out both mean
// the student has already been marked for this event.
$existingAttendance = $conn->query("SELECT LogType FROM attendance WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
if ($existingAttendance && $existingAttendance->num_rows > 0) {
    $existing = $existingAttendance->fetch_assoc();
    echo json_encode([
        'success' => false,
        'message' => 'Attendance has already been recorded for this event' . (!empty($existing['LogType']) ? ' (' . $existing['LogType'] . ')' : '') . '.'
    ]);
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

$logType = trim($_POST['LogType'] ?? $_POST['log_type'] ?? 'Log In');

try {
    $stmt = $conn->prepare("CALL sp_RecordAttendance(?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iisss", $eventId, $userId, $method, $status, $logType);
        if ($stmt->execute()) {
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
            echo json_encode([
                'success' => true,
                'message' => 'Attendance recorded successfully'
            ]);
            exit;
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // fallback direct insert below
}

try {
    $ins = $conn->prepare("INSERT INTO `attendance` (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType) VALUES (?, ?, ?, ?, NOW(), ?)");
    if ($ins) {
        $ins->bind_param("iisss", $eventId, $userId, $method, $status, $logType);
        if ($ins->execute()) {
            $ins->close();
            echo json_encode([
                'success' => true,
                'message' => 'Attendance recorded successfully'
            ]);
            exit;
        }
        $ins->close();
    }
} catch (Exception $e2) {
    echo json_encode(['success' => false, 'message' => $e2->getMessage()]);
}
?>
