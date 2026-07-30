<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

$eventId = (int)($_GET['event_id'] ?? 0);
$userId  = (int)($_SESSION['student_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'active' => false]); exit;
}

$row = $conn->query("
    SELECT EventId, EventName, EventStatus, PresenceCheckActive, PresenceCheckTriggeredAt, PresenceCheckDurationSec,
           TIMESTAMPDIFF(SECOND, PresenceCheckTriggeredAt, NOW()) AS elapsed_sec
    FROM event
    WHERE EventId=$eventId LIMIT 1
")->fetch_assoc();

if (!$row || strtolower($row['EventStatus'] ?? '') !== 'ongoing' || !$row['PresenceCheckActive'] || !$row['PresenceCheckTriggeredAt']) {
    echo json_encode(['success' => true, 'active' => false]); exit;
}

$elapsed  = (int)$row['elapsed_sec'];
$duration = (int)$row['PresenceCheckDurationSec'];
$remaining = $duration - $elapsed;

// Auto expire if timer ran out
if ($remaining <= 0) {
    $conn->query("UPDATE event SET PresenceCheckActive=0 WHERE EventId=$eventId");
    echo json_encode(['success' => true, 'active' => false, 'expired' => true]); exit;
}

// Check if this student already responded to this current presence check
$alreadyResponded = false;
if ($userId) {
    $att = $conn->query("
        SELECT LastPresenceCheckAt
        FROM attendance
        WHERE EventId=$eventId AND UserId=$userId AND LastPresenceCheckAt >= '{$row['PresenceCheckTriggeredAt']}'
        LIMIT 1
    ");
    if ($att && $att->num_rows > 0) {
        $alreadyResponded = true;
    }
}

echo json_encode([
    'success'           => true,
    'active'            => true,
    'already_responded' => $alreadyResponded,
    'event_id'          => (int)$row['EventId'],
    'event_name'        => $row['EventName'],
    'triggered_at'      => $row['PresenceCheckTriggeredAt'],
    'duration_sec'      => $duration,
    'remaining_sec'     => $remaining,
]);
