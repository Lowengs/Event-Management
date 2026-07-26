<?php
/** update_org_event_status.php — update just the status of an event */
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$orgId   = (int)$_SESSION['org_id'];
$eventId = (int)($_POST['EventId'] ?? 0);
$status  = trim($_POST['EventStatus'] ?? '');

if (!$eventId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Event ID and Status are required']);
    exit;
}

$allowedStatuses = ['Scheduled', 'Ongoing', 'Completed', 'Cancelled', 'Delayed'];
if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status provided']);
    exit;
}

// Fetch event name for richer audit details
$evRow = $conn->query("SELECT EventName FROM event WHERE EventId=$eventId AND OrgId=$orgId LIMIT 1")->fetch_assoc();
$eventName = $evRow['EventName'] ?? "Event #$eventId";

$stmt = $conn->prepare("UPDATE event SET EventStatus = ? WHERE EventId = ? AND OrgId = ?");
$stmt->bind_param("sii", $status, $eventId, $orgId);

// Map status to a clear, specific audit action label
$actionLabels = [
    'Cancelled'  => 'Event Cancelled',
    'Delayed'    => 'Event Delayed',
    'Scheduled'  => 'Event Rescheduled',
    'Ongoing'    => 'Event Marked Ongoing',
    'Completed'  => 'Event Marked Completed',
];
$auditAction = $actionLabels[$status] ?? "Event Status Changed to $status";

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        logAudit($conn, $auditAction, 'org', $orgId, 'success', [
            'event_name' => $eventName,
            'new_status' => $status,
        ]);
        echo json_encode(['success' => true, 'message' => 'Event status updated to ' . $status]);
    } else {
        // Check if status was already the same
        $check = $conn->query("SELECT EventStatus FROM event WHERE EventId=$eventId AND OrgId=$orgId");
        if ($check && $check->num_rows > 0) {
            $row = $check->fetch_assoc();
            if ($row['EventStatus'] === $status) {
                echo json_encode(['success' => true, 'message' => 'Status was already ' . $status]);
            } else {
                logAudit($conn, $auditAction, 'org', $orgId, 'failed', [
                    'event_name' => $eventName,
                    'reason'     => 'Update affected 0 rows',
                ]);
                echo json_encode(['success' => false, 'message' => 'Failed to update Event status']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Event not found or access denied']);
        }
    }
} else {
    logAudit($conn, $auditAction, 'org', $orgId, 'failed', [
        'event_name' => $eventName,
        'reason'     => $conn->error,
    ]);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();
?>