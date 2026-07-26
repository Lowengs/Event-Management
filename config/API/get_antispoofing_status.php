<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

$eventId = (int)($_GET['event_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'active' => false]); exit;
}

$row = $conn->query("
    SELECT EventId, EventName, EventMode,
           AntiSpoofActive, AntiSpoofTriggeredAt, AntiSpoofGraceMinutes,
           TIMESTAMPDIFF(SECOND, AntiSpoofTriggeredAt, NOW()) AS elapsed_seconds,
           (AntiSpoofGraceMinutes * 60 - TIMESTAMPDIFF(SECOND, AntiSpoofTriggeredAt, NOW())) AS grace_remaining_seconds
    FROM event
    WHERE EventId=$eventId LIMIT 1
")->fetch_assoc();

if (!$row || !$row['AntiSpoofActive'] || !$row['AntiSpoofTriggeredAt']) {
    echo json_encode(['success' => true, 'active' => false]); exit;
}

$graceRemain = (int)$row['grace_remaining_seconds'];

// Auto-deactivate if grace period expired
if ($graceRemain <= 0) {
    $conn->query("UPDATE event SET AntiSpoofActive=0 WHERE EventId=$eventId");
    echo json_encode(['success' => true, 'active' => false, 'expired' => true]); exit;
}

echo json_encode([
    'success'                 => true,
    'active'                  => true,
    'event_id'                => (int)$row['EventId'],
    'event_name'              => $row['EventName'],
    'triggered_at'            => $row['AntiSpoofTriggeredAt'],
    'grace_minutes'           => (int)$row['AntiSpoofGraceMinutes'],
    'elapsed_seconds'         => (int)$row['elapsed_seconds'],
    'grace_remaining_seconds' => $graceRemain,
]);
