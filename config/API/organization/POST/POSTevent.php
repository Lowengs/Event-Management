<?php
/**
 * Organization API: POST Event (Create)
 * Endpoint: /config/API/endpoints/index.php?action=POSTevent
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

$orgId      = (int)$_SESSION['org_id'];
$name       = trim($_POST['EventName']        ?? $_POST['name'] ?? '');
$desc       = trim($_POST['EventDescription'] ?? $_POST['description'] ?? '');
$date       = trim($_POST['EventDate']        ?? $_POST['EventDateTime'] ?? $_POST['date'] ?? '');
$timeStart  = trim($_POST['EventTimeStart']   ?? '');
if ($timeStart && strpos($date, ' ') === false) {
    $date .= ' ' . $timeStart;
}
$endDate    = trim($_POST['EndDateTime']      ?? $_POST['end_date'] ?? null);
$place      = trim($_POST['EventLocation']    ?? $_POST['EventPlace'] ?? $_POST['location'] ?? '');
$eventType  = trim($_POST['EventType']        ?? $_POST['event_type'] ?? 'General');
$mode       = trim($_POST['EventMode']        ?? $_POST['mode'] ?? 'On-site');
$speaker    = trim($_POST['EventSpeaker']     ?? $_POST['GuestSpeaker'] ?? $_POST['speaker'] ?? '');
$capacity   = (int)($_POST['EventCapacity']   ?? $_POST['capacity'] ?? 0);
$picture    = trim($_POST['EventPicture']     ?? $_POST['picture'] ?? '');
$attEnabled = 1;
$attMethod  = 'Face & QR';

// Handle Image Upload
$fileKey = !empty($_FILES['EventPicture']) ? 'EventPicture' : (!empty($_FILES['poster']) ? 'poster' : (!empty($_FILES['picture']) ? 'picture' : ''));
if ($fileKey && !empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
    $dir = __DIR__ . '/../../../../assets/uploads/events/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
        echo json_encode(['success' => false, 'message' => 'Event posters must be PNG or JPG images only']);
        if ($isDirectApiCall) exit;
        return;
    }
    $fn  = 'event_' . time() . '_' . rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dir . $fn)) {
        $picture = 'assets/uploads/events/' . $fn;
    }
}

if (empty($name) || empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Event name and date/time are required']);
    if ($isDirectApiCall) exit;
    return;
}

$success = false;

// Try Stored Procedure
try {
    if ($stmt = $conn->prepare("CALL sp_CreateEvent(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
        $stmt->bind_param("isssssssis", $orgId, $name, $desc, $date, $endDate, $place, $mode, $speaker, $capacity, $picture);
        if ($stmt->execute()) {
            $success = true;
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed to fallback
}

// Fallback direct SQL insert
if (!$success) {
    $stmt = $conn->prepare("
        INSERT INTO event (OrgId, EventName, EventDescription, EventDateTime, EndDateTime, EventLocation, EventMode, EventSpeaker, EventCapacity, EventPicture, EventStatus, AttendanceEnabled, AttendanceMethod)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?)
    ");
    if ($stmt) {
        $stmt->bind_param("isssssssisis", $orgId, $name, $desc, $date, $endDate, $place, $mode, $speaker, $capacity, $picture, $attEnabled, $attMethod);
        if ($stmt->execute()) {
            $success = true;
        }
        $stmt->close();
    }
}

if ($success) {
    // The legacy procedure does not persist these fields, so keep the event
    // card data consistent regardless of which create path was used.
    $sync = $conn->prepare('UPDATE event SET EventType = ?, EventPlace = ?, EventLocation = ? WHERE OrgId = ? AND EventName = ? AND EventDateTime = ? ORDER BY EventId DESC LIMIT 1');
    if ($sync) {
        $sync->bind_param('sssiss', $eventType, $place, $place, $orgId, $name, $date);
        $sync->execute();
        $sync->close();
    }
    echo json_encode(['success' => true, 'message' => 'Event created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error ?: 'Failed to create event']);
}
if ($isDirectApiCall) exit;
?>

