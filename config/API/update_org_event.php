<?php
/** update_org_event.php — edit an existing event */
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid method']); exit; }

$orgId    = (int)$_SESSION['org_id'];
$eventId  = (int)($_POST['EventId'] ?? 0);
if (!$eventId) { echo json_encode(['success'=>false,'message'=>'Event ID required']); exit; }

// Verify ownership and fetch all existing values to preserve them
$check = $conn->query("SELECT * FROM event WHERE EventId=$eventId AND OrgId=$orgId");
if (!$check || $check->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Event not found']); exit; }
$existing = $check->fetch_assoc();

$name        = trim($_POST['EventName']        ?? $existing['EventName']        ?? '');
$desc        = trim($_POST['EventDescription'] ?? $existing['EventDescription'] ?? '');
$speaker     = trim($_POST['EventSpeaker']     ?? $existing['EventSpeaker']     ?? '');
$capacity    = isset($_POST['EventCapacity'])  ? (int)$_POST['EventCapacity']  : (int)($existing['EventCapacity'] ?? 0);
$location    = trim($_POST['EventLocation']    ?? $existing['EventLocation']    ?? '');
$place       = trim($_POST['EventPlace']       ?? $existing['EventPlace']       ?? '');
$datetimeStr = trim($_POST['EventDateTime']    ?? $existing['EventDateTime']    ?? '');
$endDatetime = trim($_POST['EndDateTime']      ?? $existing['EndDateTime']      ?? '');
$status      = trim($_POST['EventStatus']      ?? $existing['EventStatus']      ?? 'Scheduled');
$eventType   = trim($_POST['EventType']        ?? $existing['EventType']        ?? '');
$eventMode   = trim($_POST['EventMode']        ?? $existing['EventMode']        ?? '');
$attMethod   = trim($_POST['AttendanceMethod'] ?? $existing['AttendanceMethod'] ?? '');

$picturePath = $existing['EventPicture'];
if (!empty($_FILES['EventPicture']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['EventPicture']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowed)) {
        $uploadDir = __DIR__ . '/../../assets/uploads/events/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = 'event_' . time() . '_' . $orgId . '.' . $ext;
        if (move_uploaded_file($_FILES['EventPicture']['tmp_name'], $uploadDir . $filename)) {
            $picturePath = 'assets/uploads/events/' . $filename;
        }
    }
}

$stmt = $conn->prepare(
    "UPDATE event
     SET EventName=?, EventDescription=?, EventSpeaker=?, EventCapacity=?,
         EventLocation=?, EventPlace=?, EventDateTime=?, EndDateTime=?,
         EventStatus=?, EventPicture=?, EventType=?, EventMode=?, AttendanceMethod=?
     WHERE EventId=? AND OrgId=?"
);
// Types:  name  desc  speaker  capacity  location  place  datetime  endDatetime  status  picture  type  mode  method  eventId  orgId
$stmt->bind_param(
    'sssisssssssssii',
    $name, $desc, $speaker, $capacity,
    $location, $place, $datetimeStr, $endDatetime,
    $status, $picturePath, $eventType, $eventMode, $attMethod,
    $eventId, $orgId
);

if ($stmt->execute()) {
    // Save uploaded documents
    $docsDir = __DIR__ . '/../../assets/uploads/events/docs/';
    if (!is_dir($docsDir)) mkdir($docsDir, 0755, true);

    $docTypes = ['EventProposal', 'EventProgramFlow', 'FinancialReport'];
    foreach ($docTypes as $dType) {
        if (!empty($_FILES[$dType]['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES[$dType]['name'], PATHINFO_EXTENSION));
            $allowedDocs = $dType === 'FinancialReport'
                ? ['pdf','doc','docx','xlsx','xls']
                : ['pdf','doc','docx'];
            if (in_array($ext, $allowedDocs)) {
                $fname = strtolower($dType) . '_' . time() . '_' . $eventId . '.' . $ext;
                if (move_uploaded_file($_FILES[$dType]['tmp_name'], $docsDir . $fname)) {
                    $p = 'assets/uploads/events/docs/' . $fname;
                    $origName = $_FILES[$dType]['name'];
                    $fileSize = filesize($docsDir . $fname);
                    $fSizeStr = round($fileSize / 1024) . 'KB';
                    $stmtDoc = $conn->prepare("INSERT INTO org_documents (OrgId, EventId, Title, DocType, FilePath, FileSize) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmtDoc) {
                        $stmtDoc->bind_param("iissss", $orgId, $eventId, $origName, $dType, $p, $fSizeStr);
                        $stmtDoc->execute();
                        $stmtDoc->close();
                    }
                }
            }
        }
    }

    if (function_exists('logAudit')) {
        logAudit($conn, 'Update Event', 'org', $orgId, 'success', [
            'event_name'  => $name,
            'date_time'   => $datetimeStr,
            'location'    => $place ?: $location,
            'new_status'  => $status,
        ]);
    }
    echo json_encode(['success'=>true,'message'=>'Event updated successfully']);
} else {
    logAudit($conn, 'Update Event', 'org', $orgId, 'failed', [
        'event_name' => $name,
        'reason'     => $conn->error,
    ]);
    echo json_encode(['success'=>false,'message'=>'DB error: '.$conn->error]);
}
$stmt->close();
