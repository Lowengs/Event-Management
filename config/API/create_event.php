<?php
/**
 * create_event.php — OSA creates a new event (supports multipart/form-data for picture upload)
 */
session_start();
require_once '../db.php';
require_once '../audit.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

// ── Collect & sanitize fields ────────────────────────────────────
$orgId       = isset($_POST['org_id']) && $_POST['org_id'] !== '' ? (int)$_POST['org_id'] : null;
$eventName   = trim($_POST['event_name']     ?? '');
$description = trim($_POST['description']    ?? '');
$speaker     = trim($_POST['speaker']        ?? '');
$capacity    = isset($_POST['capacity']) && $_POST['capacity'] !== '' ? (int)$_POST['capacity'] : null;
$place       = trim($_POST['place']          ?? '');
$location    = trim($_POST['location']       ?? '');
$startDt     = trim($_POST['event_datetime'] ?? '');
$endDt       = !empty($_POST['end_datetime']) ? trim($_POST['end_datetime']) : null;
$status      = in_array($_POST['status'] ?? '', ['Scheduled','Ongoing','Completed','Cancelled'])
               ? $_POST['status'] : 'Scheduled';

if (empty($eventName)) {
    echo json_encode(['success' => false, 'message' => 'Event name is required.']); exit;
}
if (empty($startDt)) {
    echo json_encode(['success' => false, 'message' => 'Event start date & time is required.']); exit;
}

// ── Handle picture upload ────────────────────────────────────────
$picturePath = null;
if (!empty($_FILES['event_picture']['name']) && $_FILES['event_picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'events' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $ext     = strtolower(pathinfo($_FILES['event_picture']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid image type. Use JPG, PNG, GIF, or WEBP.']); exit;
    }
    if ($_FILES['event_picture']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Image must be under 5 MB.']); exit;
    }

    $filename    = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($_FILES['event_picture']['tmp_name'], $destination)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']); exit;
    }
    $picturePath = 'assets/uploads/events/' . $filename;
}

// ── Insert event ─────────────────────────────────────────────────
// Columns: OrgId(i) EventName(s) EventDescription(s) EventSpeaker(s) EventCapacity(i)
//          EventPicture(s) EventPlace(s) EventLocation(s) EventDateTime(s) EndDateTime(s) EventStatus(s)
// Type string: i s s s i s s s s s s  = "isssssssss" with i for capacity = "isssissssss"
$sql = "INSERT INTO event
            (OrgId, EventName, EventDescription, EventSpeaker, EventCapacity, EventPicture,
             EventPlace, EventLocation, EventDateTime, EndDateTime, EventStatus)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
// OrgId=i, EventName=s, EventDescription=s, EventSpeaker=s, EventCapacity=i, EventPicture=s,
// EventPlace=s, EventLocation=s, EventDateTime=s, EndDateTime=s, EventStatus=s
$types = 'isssissssss';
$stmt->bind_param($types,
    $orgId,       // i
    $eventName,   // s
    $description, // s
    $speaker,     // s
    $capacity,    // i
    $picturePath, // s
    $place,       // s
    $location,    // s
    $startDt,     // s
    $endDt,       // s
    $status       // s
);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]); exit;
}
$newId = $conn->insert_id;
$stmt->close();

logAudit($conn, 'Create Event', 'event', $newId, 'success', [
    'event_name' => $eventName,
    'osa_id'     => $_SESSION['osa_id'],
]);

echo json_encode([
    'success'  => true,
    'message'  => "Event \"{$eventName}\" created successfully.",
    'event_id' => $newId,
]);
