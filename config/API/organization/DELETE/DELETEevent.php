<?php
/**
 * Organization API: DELETE Event
 * Uses Stored Procedure: sp_DeleteOrgEvent
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    if ($isDirectApiCall) exit;
    return;
}

$inputData = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || empty($_POST)) {
    parse_str(file_get_contents('php://input'), $delData);
    if (!empty($delData)) $inputData = array_merge($inputData, $delData);
}

$orgId   = (int)$_SESSION['org_id'];
$eventId = (int)($inputData['EventId'] ?? $inputData['id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    if ($isDirectApiCall) exit;
    return;
}

$success = false;
try {
    $owned = $conn->query("SELECT EventId FROM event WHERE EventId = $eventId AND OrgId = $orgId LIMIT 1");
    if (!$owned || $owned->num_rows === 0) {
        throw new RuntimeException('Event not found or does not belong to this organization');
    }

    $conn->begin_transaction();
    // Remove records that reference the event before deleting it. Assessments
    // cascade their questions/answers where foreign keys are installed.
    foreach (['event_pretest', 'event_posttest', 'attendance', 'eventregistration', 'certificates'] as $table) {
        $conn->query("DELETE FROM `$table` WHERE EventId = $eventId");
    }
    $conn->query("DELETE FROM assessments WHERE event_id = $eventId");
    $stmt = $conn->prepare('DELETE FROM event WHERE EventId = ? AND OrgId = ?');
    $stmt->bind_param('ii', $eventId, $orgId);
    $success = $stmt->execute() && $stmt->affected_rows > 0;
    $stmt->close();
    if ($success) $conn->commit(); else $conn->rollback();
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignore) {}
    $errorMessage = $e->getMessage();
}

if ($success) {
    if (file_exists(__DIR__ . '/../../../audit.php')) {
        require_once __DIR__ . '/../../../audit.php';
        logAudit($conn, 'Delete Event', 'organization', $orgId, 'success', ['event_id' => $eventId]);
    }
    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => $errorMessage ?? 'Failed to delete event']);
}
if ($isDirectApiCall) exit;
?>

