<?php
/** delete_org_event.php */
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId   = (int)$_SESSION['org_id'];
$eventId = (int)($_POST['EventId'] ?? 0);
if (!$eventId) { echo json_encode(['success'=>false,'message'=>'Event ID required']); exit; }

mysqli_begin_transaction($conn);

try {
    $check = $conn->prepare("SELECT EventId FROM event WHERE EventId=? AND OrgId=?");
    $check->bind_param('ii', $eventId, $orgId);
    $check->execute();
    $eventRow = $check->get_result();

    if (!$eventRow || $eventRow->num_rows === 0) {
        $check->close();
        mysqli_rollback($conn);
        echo json_encode(['success'=>false,'message'=>'Event not found or access denied']);
        exit;
    }
    $check->close();

    $deleteTables = [
        'attendance',
        'certificate',
        'event_posttest',
        'event_pretest',
        'eventregistration',
    ];

    foreach ($deleteTables as $table) {
        $stmt = $conn->prepare("DELETE FROM {$table} WHERE EventId=?");
        $stmt->bind_param('i', $eventId);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error ?: 'Failed to delete related records from ' . $table);
        }
        $stmt->close();
    }

    $stmt = $conn->prepare("DELETE FROM event WHERE EventId=? AND OrgId=?");
    $stmt->bind_param('ii', $eventId, $orgId);
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        throw new Exception($stmt->error ?: 'Failed to delete event');
    }
    $stmt->close();

    mysqli_commit($conn);
    logAudit($conn,'Delete Event','org',$orgId,'success',['event_id'=>$eventId]);
    echo json_encode(['success'=>true,'message'=>'Event deleted']);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['success'=>false,'message'=>'Could not delete event: ' . $e->getMessage()]);
}
