<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$orgId   = (int)$_SESSION['org_id'];
$eventId = (int)($_POST['event_id'] ?? 0);
$grace   = (int)($_POST['grace_minutes'] ?? 15);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Invalid event']); exit;
}

// Verify event belongs to this org
$ev = $conn->query("SELECT EventId, EventName, EventMode FROM event WHERE EventId=$eventId AND OrgId=$orgId LIMIT 1")->fetch_assoc();
if (!$ev) {
    echo json_encode(['success' => false, 'message' => 'Event not found or not yours']); exit;
}

// Set trigger directly on event row
$now = date('Y-m-d H:i:s');
$stmt = $conn->prepare("UPDATE event SET AntiSpoofActive=1, AntiSpoofTriggeredAt=?, AntiSpoofGraceMinutes=? WHERE EventId=?");
$stmt->bind_param('sii', $now, $grace, $eventId);
$stmt->execute();

echo json_encode(['success' => true, 'message' => 'Anti-spoofing activated', 'event' => $ev['EventName']]);
