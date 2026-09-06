<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id']) && empty($_SESSION['osa_id']) && empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization, OSA, or Admin login required']);
    exit;
}

$eventId = (int)($_POST['event_id'] ?? $_POST['EventId'] ?? 0);
$checkType = trim($_POST['check_type'] ?? 'all'); // 'antispoof', 'presence', or 'all'

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

if ($checkType === 'antispoof') {
    $stmt = $conn->prepare("UPDATE event SET AntiSpoofActive = 0 WHERE EventId = ?");
    $stmt->bind_param('i', $eventId);
} elseif ($checkType === 'presence') {
    $stmt = $conn->prepare("UPDATE event SET PresenceCheckActive = 0 WHERE EventId = ?");
    $stmt->bind_param('i', $eventId);
} else {
    $stmt = $conn->prepare("UPDATE event SET AntiSpoofActive = 0, PresenceCheckActive = 0 WHERE EventId = ?");
    $stmt->bind_param('i', $eventId);
}

if ($stmt) {
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Verification checks stopped successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error stopping checks']);
}
?>
