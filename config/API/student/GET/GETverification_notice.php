<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');

$studentId = (int)($_SESSION['student_id'] ?? 0);
if (!$studentId) { echo json_encode(['success' => false, 'message' => 'Login required']); exit; }

// A completion is stored per triggered check, so a completed student is not
// repeatedly notified while the organization leaves the event flag enabled.
$conn->query("CREATE TABLE IF NOT EXISTS student_verification_checks (
  VerificationId INT AUTO_INCREMENT PRIMARY KEY,
  EventId INT NOT NULL, UserId INT NOT NULL, CheckType VARCHAR(20) NOT NULL,
  TriggeredAt DATETIME NOT NULL, CompletedAt DATETIME NOT NULL,
  UNIQUE KEY verification_once (EventId, UserId, CheckType, TriggeredAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$sql = "SELECT e.EventId, e.EventName,
  CASE WHEN e.AntiSpoofActive = 1 THEN 'antispoof' ELSE 'presence' END AS check_type,
  CASE WHEN e.AntiSpoofActive = 1 THEN e.AntiSpoofTriggeredAt ELSE e.PresenceCheckTriggeredAt END AS triggered_at
  FROM event e
  JOIN eventregistration er ON er.EventId = e.EventId AND er.UserId = ?
  JOIN attendance a ON a.EventId = e.EventId AND a.UserId = er.UserId AND LOWER(COALESCE(a.LogType,'')) = 'log in'
  WHERE (e.AntiSpoofActive = 1 OR e.PresenceCheckActive = 1)
    AND NOT EXISTS (SELECT 1 FROM student_verification_checks svc
      WHERE svc.EventId = e.EventId AND svc.UserId = ?
      AND svc.CheckType = CASE WHEN e.AntiSpoofActive = 1 THEN 'antispoof' ELSE 'presence' END
      AND svc.TriggeredAt = CASE WHEN e.AntiSpoofActive = 1 THEN e.AntiSpoofTriggeredAt ELSE e.PresenceCheckTriggeredAt END)
  ORDER BY triggered_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $studentId, $studentId);
$stmt->execute();
$notice = $stmt->get_result()->fetch_assoc();
echo json_encode(['success' => true, 'notice' => $notice ?: null]);
