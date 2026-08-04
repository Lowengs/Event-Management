<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']); exit;
}
$eventId = (int)($_POST['EventId'] ?? 0);
$noFinance = !empty($_POST['NoFinancialReport']) ? 1 : 0;
if (!$eventId) { echo json_encode(['success' => false, 'message' => 'Invalid event']); exit; }

$orgId = (int)$_SESSION['org_id'];
$owner = $conn->prepare('SELECT EventId FROM event WHERE EventId = ? AND OrgId = ?');
$owner->bind_param('ii', $eventId, $orgId); $owner->execute();
if (!$owner->get_result()->fetch_assoc()) { echo json_encode(['success' => false, 'message' => 'Event not found']); exit; }

$column = $conn->query("SHOW COLUMNS FROM event LIKE 'NoFinancialReport'");
if (!$column || $column->num_rows === 0) {
    if (!$conn->query('ALTER TABLE event ADD COLUMN NoFinancialReport TINYINT(1) NOT NULL DEFAULT 0')) {
        echo json_encode(['success' => false, 'message' => 'Could not save the financial-report setting']); exit;
    }
}
$stmt = $conn->prepare('UPDATE event SET NoFinancialReport = ? WHERE EventId = ? AND OrgId = ?');
$stmt->bind_param('iii', $noFinance, $eventId, $orgId);
if (!$stmt->execute()) { echo json_encode(['success' => false, 'message' => 'Could not update the event']); exit; }
echo json_encode(['success' => true, 'message' => $noFinance ? 'Marked as no financial involvement' : 'Financial report is required']);
?>
