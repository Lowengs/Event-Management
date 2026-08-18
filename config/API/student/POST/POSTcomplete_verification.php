<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');

$studentId = (int)($_SESSION['student_id'] ?? 0);
$eventId = (int)($_POST['event_id'] ?? 0);
$type = $_POST['check_type'] ?? '';
if (!$studentId || !$eventId || !in_array($type, ['presence', 'antispoof'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid verification request']); exit;
}
$conn->query("CREATE TABLE IF NOT EXISTS student_verification_checks (
  VerificationId INT AUTO_INCREMENT PRIMARY KEY, EventId INT NOT NULL, UserId INT NOT NULL,
  CheckType VARCHAR(20) NOT NULL, TriggeredAt DATETIME NOT NULL, CompletedAt DATETIME NOT NULL,
  UNIQUE KEY verification_once (EventId, UserId, CheckType, TriggeredAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$field = $type === 'antispoof' ? 'AntiSpoofTriggeredAt' : 'PresenceCheckTriggeredAt';
$active = $type === 'antispoof' ? 'AntiSpoofActive' : 'PresenceCheckActive';

$event = $conn->query("SELECT EventId, EventName, EventStatus, $field AS triggered_at FROM event WHERE EventId = $eventId LIMIT 1")->fetch_assoc();
if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Event not found']); exit;
}

$triggeredAt = !empty($event['triggered_at']) ? $event['triggered_at'] : date('Y-m-d H:i:s');

// Verify or ensure registration / attendance existence
$reg = $conn->query("SELECT 1 FROM eventregistration WHERE EventId = $eventId AND UserId = $studentId LIMIT 1");
if (!$reg || !$reg->num_rows) {
    // If not in eventregistration, auto-register student for attendance tracking
    $conn->query("INSERT IGNORE INTO eventregistration (EventId, UserId, DateIssued) VALUES ($eventId, $studentId, NOW())");
}

// Insert verification check completion
$stmt = $conn->prepare('INSERT INTO student_verification_checks (EventId, UserId, CheckType, TriggeredAt, CompletedAt) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE CompletedAt = NOW()');
$stmt->bind_param('iiss', $eventId, $studentId, $type, $triggeredAt);
$stmt->execute();
$stmt->close();

// Update or create attendance record
$attCheck = $conn->query("SELECT AttendanceId FROM attendance WHERE EventId = $eventId AND UserId = $studentId LIMIT 1");
if ($attCheck && $attCheck->num_rows > 0) {
    $conn->query("UPDATE attendance SET PresenceChecksPassed = COALESCE(PresenceChecksPassed,0) + 1, LastPresenceCheckAt = NOW() WHERE EventId = $eventId AND UserId = $studentId");
} else {
    $conn->query("INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType, PresenceChecksPassed, LastPresenceCheckAt) VALUES ($eventId, $studentId, 'Online Live Check', 'Present', NOW(), 'Log In', 1, NOW())");
}

echo json_encode(['success' => true, 'message' => 'Verification completed successfully!']);

