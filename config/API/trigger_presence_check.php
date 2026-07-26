<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$orgId    = (int)$_SESSION['org_id'];
$eventId  = (int)($_POST['event_id'] ?? 0);
$duration = (int)($_POST['duration_sec'] ?? 90);
$action   = trim($_POST['action'] ?? 'trigger'); // 'trigger' or 'stop'

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']); exit;
}

// Verify event ownership
$ev = $conn->query("SELECT EventId, EventName FROM event WHERE EventId=$eventId AND OrgId=$orgId LIMIT 1")->fetch_assoc();
if (!$ev) {
    echo json_encode(['success' => false, 'message' => 'Event not found or not owned by your organization']); exit;
}

if ($action === 'stop') {
    $conn->query("UPDATE event SET PresenceCheckActive=0 WHERE EventId=$eventId");
    echo json_encode(['success' => true, 'message' => 'Presence check stopped', 'event' => $ev['EventName']]);
    exit;
}

$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("UPDATE event SET PresenceCheckActive=1, PresenceCheckTriggeredAt=?, PresenceCheckDurationSec=? WHERE EventId=?");
$stmt->bind_param('sii', $now, $duration, $eventId);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Presence check triggered! Online students have ' . $duration . ' seconds to verify presence.',
    'event'   => $ev['EventName'],
    'duration_sec' => $duration
]);
