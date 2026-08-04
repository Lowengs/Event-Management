<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$eventId = (int)($_POST['event_id'] ?? $_POST['EventId'] ?? 0);
$duration = 0;
if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event is required']);
    exit;
}

$stmt = $conn->prepare("UPDATE event
    SET PresenceCheckActive = 1, PresenceCheckTriggeredAt = NOW(), PresenceCheckDurationSec = ?
    WHERE EventId = ? AND OrgId = ?");
$orgId = (int)$_SESSION['org_id'];
$stmt->bind_param('iii', $duration, $eventId, $orgId);
$stmt->execute();

if ($stmt->affected_rows < 1) {
    echo json_encode(['success' => false, 'message' => 'Event was not found or the same presence check is already active']);
    exit;
}
echo json_encode(['success' => true, 'message' => 'Presence check started', 'duration_sec' => $duration]);
?>
