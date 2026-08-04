<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$eventId = (int)($_POST['event_id'] ?? $_POST['EventId'] ?? 0);
$graceMinutes = 0;
if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event is required']);
    exit;
}

$stmt = $conn->prepare("UPDATE event
    SET AntiSpoofActive = 1, AntiSpoofTriggeredAt = NOW(), AntiSpoofGraceMinutes = ?
    WHERE EventId = ? AND OrgId = ?");
$orgId = (int)$_SESSION['org_id'];
$stmt->bind_param('iii', $graceMinutes, $eventId, $orgId);
$stmt->execute();

if ($stmt->affected_rows < 1) {
    echo json_encode(['success' => false, 'message' => 'Event was not found or anti-spoofing is already active']);
    exit;
}
echo json_encode(['success' => true, 'message' => 'Anti-spoofing activated', 'grace_minutes' => $graceMinutes]);
?>
