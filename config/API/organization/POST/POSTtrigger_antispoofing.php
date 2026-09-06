<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id']) && empty($_SESSION['osa_id']) && empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization, OSA, or Admin login required']);
    exit;
}

$eventId = (int)($_POST['event_id'] ?? $_POST['EventId'] ?? 0);
$graceMinutes = 0;
if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$check = $conn->prepare("SELECT EventId, OrgId FROM event WHERE EventId = ? LIMIT 1");
$check->bind_param('i', $eventId);
$check->execute();
$ev = $check->get_result()->fetch_assoc();
$check->close();

if (!$ev) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}

$userOrgId = (int)($_SESSION['org_id'] ?? 0);
if (!empty($_SESSION['org_id']) && empty($_SESSION['osa_id']) && empty($_SESSION['admin_id'])) {
    if (!empty($ev['OrgId']) && (int)$ev['OrgId'] !== $userOrgId) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized event for your organization']);
        exit;
    }
}

$stmt = $conn->prepare("UPDATE event
    SET AntiSpoofActive = 1, AntiSpoofTriggeredAt = NOW(), AntiSpoofGraceMinutes = ?
    WHERE EventId = ?");
$stmt->bind_param('ii', $graceMinutes, $eventId);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true, 'message' => 'Anti-spoofing check triggered successfully', 'grace_minutes' => $graceMinutes]);
?>
