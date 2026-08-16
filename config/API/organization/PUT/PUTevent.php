<?php
/**
 * Organization/OSA API: PUT Event (Update Details & Status)
 * Endpoint: /config/API/endpoints/index.php?action=PUTevent
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['org_id']) && empty($_SESSION['osa_id']) && empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    if ($isDirectApiCall) exit;
    return;
}

$inputData = $_POST;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'PUT' || empty($_POST)) {
    parse_str(file_get_contents('php://input'), $putData);
    if (!empty($putData)) $inputData = array_merge($inputData, $putData);
}

$orgId      = (int)($_SESSION['org_id'] ?? 0);
$eventId    = (int)($inputData['EventId']         ?? $inputData['id'] ?? $inputData['event_id'] ?? 0);
$name       = trim($inputData['EventName']        ?? $inputData['name'] ?? '');
$desc       = trim($inputData['EventDescription'] ?? $inputData['description'] ?? '');
$date       = trim($inputData['EventDateTime']    ?? $inputData['date'] ?? '');
$endDate    = !empty($inputData['EndDateTime'])   ? trim($inputData['EndDateTime']) : null;
$eventDate  = trim($inputData['EventDate'] ?? (!empty($date) ? explode(' ', $date)[0] : ''));

// Combine date and time if submitted as separate fields
if (empty($date) && !empty($eventDate)) {
    $timeStart = trim($inputData['EventTimeStart'] ?? $inputData['time_start'] ?? '00:00');
    $date = $eventDate . ' ' . (strlen($timeStart) === 5 ? $timeStart . ':00' : $timeStart);
}
$timeEnd = trim($inputData['EventTimeEnd'] ?? $inputData['time_end'] ?? $inputData['endTime'] ?? '');
if (empty($endDate) && !empty($eventDate) && !empty($timeEnd)) {
    $endDate = $eventDate . ' ' . (strlen($timeEnd) === 5 ? $timeEnd . ':00' : $timeEnd);
}
if (empty($endDate) && !empty($date) && !empty($timeEnd)) {
    $datePart = explode(' ', $date)[0];
    $endDate = $datePart . ' ' . (strlen($timeEnd) === 5 ? $timeEnd . ':00' : $timeEnd);
}
if (empty($endDate) && !empty($date)) {
    $startTs = strtotime($date);
    if ($startTs) {
        $endDate = date('Y-m-d H:i:s', $startTs + 7200);
    }
}

$place      = trim($inputData['EventLocation']    ?? $inputData['EventPlace'] ?? $inputData['location'] ?? '');
$mode       = trim($inputData['EventMode']        ?? $inputData['mode'] ?? 'On-site');
$audience   = strtolower(trim($inputData['Audience'] ?? $inputData['audience'] ?? ''));
if ($audience !== 'members' && $audience !== 'all') {
    $audience = '';
}
$speaker    = trim($inputData['EventSpeaker']     ?? $inputData['GuestSpeaker'] ?? $inputData['speaker'] ?? '');
$capacity   = (int)($inputData['EventCapacity']   ?? $inputData['capacity'] ?? 0);
$picture    = trim($inputData['EventPicture']     ?? $inputData['picture'] ?? '');
$status     = trim($inputData['EventStatus']      ?? $inputData['status'] ?? 'Scheduled');

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    if ($isDirectApiCall) exit;
    return;
}

// Handle Image Upload if provided
$fileKey = !empty($_FILES['EventPicture']) ? 'EventPicture' : (!empty($_FILES['poster']) ? 'poster' : (!empty($_FILES['picture']) ? 'picture' : ''));
if ($fileKey && !empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
    $dir = __DIR__ . '/../../../../assets/uploads/events/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    $fn  = 'event_' . time() . '_' . rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dir . $fn)) {
        $picture = 'assets/uploads/events/' . $fn;
    }
}

$isStatusOnly = empty($inputData['EventName']) && (isset($inputData['EventStatus']) || isset($inputData['status']));
$success = false;

if ($isStatusOnly) {
    // Fast path: update status only
    $curEv = $conn->query("SELECT EventName FROM event WHERE EventId = $eventId LIMIT 1");
    if ($curEv && $crow = $curEv->fetch_assoc()) {
        $name = $crow['EventName'];
    }
    $statusSql = "UPDATE event SET EventStatus = ? WHERE EventId = ?" . ($orgId > 0 && empty($_SESSION['osa_id']) && empty($_SESSION['admin_id']) ? " AND OrgId = $orgId" : "");
    $stmtStatus = $conn->prepare($statusSql);
    if ($stmtStatus) {
        $stmtStatus->bind_param("si", $status, $eventId);
        if ($stmtStatus->execute()) {
            $success = true;
        }
        $stmtStatus->close();
    }
} else {
    // Full event details update
    if (empty($name) || empty($date)) {
        $curEv = $conn->query("SELECT EventName, EventDescription, EventDateTime, EndDateTime, EventLocation, EventMode, EventSpeaker, EventCapacity, EventPicture FROM event WHERE EventId = $eventId LIMIT 1");
        if ($curEv && $crow = $curEv->fetch_assoc()) {
            if (empty($name))    $name    = $crow['EventName'];
            if (empty($desc))    $desc    = $crow['EventDescription'];
            if (empty($date))    $date    = $crow['EventDateTime'];
            if (empty($endDate)) $endDate = $crow['EndDateTime'];
            if (empty($place))   $place   = $crow['EventLocation'];
            if (empty($mode))    $mode    = $crow['EventMode'];
            if (empty($speaker)) $speaker = $crow['EventSpeaker'];
            if (!$capacity)      $capacity= (int)$crow['EventCapacity'];
        }
    }

    // 1. Try Direct SQL Update
    $sql = "UPDATE event 
            SET EventName = ?, EventDescription = ?, EventDateTime = ?, EndDateTime = ?, 
                EventLocation = ?, EventMode = ?, EventSpeaker = ?, EventCapacity = ?, 
                EventStatus = ?, EventPicture = IF(? != '', ?, EventPicture)
            WHERE EventId = ?";
    if ($orgId > 0 && empty($_SESSION['osa_id']) && empty($_SESSION['admin_id'])) {
        $sql .= " AND OrgId = $orgId";
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sssssssisssi", $name, $desc, $date, $endDate, $place, $mode, $speaker, $capacity, $status, $picture, $picture, $eventId);
        if ($stmt->execute()) {
            $success = true;
        }
        $stmt->close();
    }

    // 2. Fallback to Stored Procedure if direct SQL failed
    if (!$success && $orgId > 0) {
        try {
            if ($sp = $conn->prepare("CALL sp_UpdateEvent(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")) {
                $sp->bind_param("iisssssssiss", $eventId, $orgId, $name, $desc, $date, $endDate, $place, $mode, $speaker, $capacity, $picture, $status);
                if ($sp->execute()) {
                    $success = true;
                }
                $sp->close();
                while ($conn->more_results() && $conn->next_result()) { ; }
            }
        } catch (Exception $e) {
            // proceed
        }
    }
}

if ($success) {
    if (in_array(strtolower($status), ['completed', 'cancelled', 'archived'])) {
        $conn->query("UPDATE event SET AntiSpoofActive = 0, PresenceCheckActive = 0 WHERE EventId = $eventId");
    }
    if (file_exists(__DIR__ . '/../../../../config/audit.php')) {
        require_once __DIR__ . '/../../../../config/audit.php';
    }
    if (function_exists('logAudit')) {
        logAudit(
            $conn,
            'Update Event',
            !empty($_SESSION['osa_id']) ? 'osa' : 'organization',
            !empty($_SESSION['osa_id']) ? (int)$_SESSION['osa_id'] : $orgId,
            'success',
            ['EventId' => $eventId, 'EventName' => $name, 'EventStatus' => $status]
        );
    }
    echo json_encode(['success' => true, 'message' => "Event updated successfully"]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error ?: 'Failed to update event details']);
}
if ($isDirectApiCall) exit;
?>

