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
$event = $conn->query("SELECT $field AS triggered_at FROM event WHERE EventId = $eventId AND $active = 1 LIMIT 1")->fetch_assoc();
if (!$event || empty($event['triggered_at'])) { echo json_encode(['success' => false, 'message' => 'This verification request is no longer active']); exit; }

$reg = $conn->query("SELECT 1 FROM eventregistration WHERE EventId = $eventId AND UserId = $studentId LIMIT 1");
if (!$reg || !$reg->num_rows) { echo json_encode(['success' => false, 'message' => 'You are not registered for this event']); exit; }
$stmt = $conn->prepare('INSERT IGNORE INTO student_verification_checks (EventId, UserId, CheckType, TriggeredAt, CompletedAt) VALUES (?, ?, ?, ?, NOW())');
$stmt->bind_param('iiss', $eventId, $studentId, $type, $event['triggered_at']);
$stmt->execute();
$conn->query("UPDATE attendance SET PresenceChecksPassed = COALESCE(PresenceChecksPassed,0) + 1, LastPresenceCheckAt = NOW() WHERE EventId = $eventId AND UserId = $studentId AND LOWER(COALESCE(LogType,'')) = 'log in'");
echo json_encode(['success' => true, 'message' => 'Verification completed']);
