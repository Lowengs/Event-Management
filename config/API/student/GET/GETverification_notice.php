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

// Automatically clear verification flags on completed or cancelled events
$conn->query("UPDATE event SET AntiSpoofActive = 0, PresenceCheckActive = 0 WHERE LOWER(COALESCE(EventStatus, '')) IN ('completed', 'cancelled', 'archived') AND (AntiSpoofActive = 1 OR PresenceCheckActive = 1)");

$sql = "
    SELECT EventId, EventName, check_type, triggered_at FROM (
        -- Anti-Spoofing checks
        SELECT e.EventId, e.EventName, 'antispoof' AS check_type, e.AntiSpoofTriggeredAt AS triggered_at
          FROM event e
         WHERE e.AntiSpoofActive = 1
           AND e.AntiSpoofTriggeredAt IS NOT NULL
           AND LOWER(COALESCE(e.EventStatus, '')) NOT IN ('completed', 'cancelled', 'archived')
           AND (
               EXISTS (SELECT 1 FROM eventregistration er WHERE er.EventId = e.EventId AND er.UserId = ?)
               OR EXISTS (SELECT 1 FROM attendance a WHERE a.EventId = e.EventId AND a.UserId = ?)
           )
           AND NOT EXISTS (
               SELECT 1 FROM student_verification_checks svc
                WHERE svc.EventId = e.EventId AND svc.UserId = ?
                  AND (LOWER(svc.CheckType) = 'antispoof' OR LOWER(svc.CheckType) = 'anti-spoof')
                  AND svc.TriggeredAt = e.AntiSpoofTriggeredAt
           )
        UNION ALL
        -- Continuous Monitoring / Presence checks
        SELECT e.EventId, e.EventName, 'presence' AS check_type, e.PresenceCheckTriggeredAt AS triggered_at
          FROM event e
         WHERE e.PresenceCheckActive = 1
           AND e.PresenceCheckTriggeredAt IS NOT NULL
           AND LOWER(COALESCE(e.EventStatus, '')) NOT IN ('completed', 'cancelled', 'archived')
           AND (
               EXISTS (SELECT 1 FROM eventregistration er WHERE er.EventId = e.EventId AND er.UserId = ?)
               OR EXISTS (SELECT 1 FROM attendance a WHERE a.EventId = e.EventId AND a.UserId = ?)
           )
           AND NOT EXISTS (
               SELECT 1 FROM student_verification_checks svc
                WHERE svc.EventId = e.EventId AND svc.UserId = ?
                  AND (LOWER(svc.CheckType) = 'presence' OR LOWER(svc.CheckType) = 'continuous')
                  AND svc.TriggeredAt = e.PresenceCheckTriggeredAt
           )
    ) pending_checks
    ORDER BY triggered_at DESC
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iiiiii', $studentId, $studentId, $studentId, $studentId, $studentId, $studentId);
$stmt->execute();
$notice = $stmt->get_result()->fetch_assoc();
echo json_encode(['success' => true, 'notice' => $notice ?: null]);

