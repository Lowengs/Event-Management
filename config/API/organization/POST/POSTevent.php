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
$date       = trim($_POST['EventDateTime']    ?? $_POST['date'] ?? '');
$endDate    = !empty($_POST['EndDateTime'])   ? trim($_POST['EndDateTime']) : null;
$eventDate  = trim($_POST['EventDate'] ?? (!empty($date) ? explode(' ', $date)[0] : ''));

// Combine date and time if submitted as separate fields
if (empty($date) && !empty($eventDate)) {
    $timeStart = trim($_POST['EventTimeStart'] ?? $_POST['time_start'] ?? '00:00');
    $date = $eventDate . ' ' . (strlen($timeStart) === 5 ? $timeStart . ':00' : $timeStart);
}
$timeEnd = trim($_POST['EventTimeEnd'] ?? $_POST['time_end'] ?? $_POST['endTime'] ?? '');
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
$place      = trim($_POST['EventLocation']    ?? $_POST['EventPlace'] ?? $_POST['location'] ?? '');
$eventType  = trim($_POST['EventType']        ?? $_POST['event_type'] ?? 'General');
$mode       = trim($_POST['EventMode']        ?? $_POST['mode'] ?? 'On-site');
if ($mode === 'Online' && empty($place)) {
    $place = 'Online (Zoom / MS Teams)';
}
$speaker    = trim($_POST['EventSpeaker']     ?? $_POST['GuestSpeaker'] ?? $_POST['speaker'] ?? '');
$capacity   = (int)($_POST['EventCapacity']   ?? $_POST['capacity'] ?? 0);
$picture    = trim($_POST['EventPicture']     ?? $_POST['picture'] ?? '');
$attEnabled = 1;
$attMethod  = 'Face & QR';

// Handle Image Upload (Event Banner / Poster)
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

// Direct SQL insert without Audience
$stmt = $conn->prepare("
    INSERT INTO event (OrgId, EventName, EventDescription, EventDateTime, EndDateTime, EventLocation, EventPlace, EventMode, EventSpeaker, EventCapacity, EventPicture, EventStatus, AttendanceEnabled, AttendanceMethod)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Scheduled', ?, ?)
");
if ($stmt) {
    $stmt->bind_param("issssssssisisi", $orgId, $name, $desc, $date, $endDate, $place, $place, $mode, $speaker, $capacity, $picture, $attEnabled, $attMethod);
    if ($stmt->execute()) {
        $success = true;
    }
    $stmt->close();
}

if ($success) {
    // Fetch newly created EventId
    $createdEventId = (int)($conn->insert_id ?? 0);
    if ($createdEventId <= 0) {
        $getEvStmt = $conn->prepare('SELECT EventId FROM event WHERE OrgId = ? AND EventName = ? ORDER BY EventId DESC LIMIT 1');
        if ($getEvStmt) {
            $getEvStmt->bind_param('is', $orgId, $name);
            $getEvStmt->execute();
            $res = $getEvStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $createdEventId = (int)$row['EventId'];
            }
            $getEvStmt->close();
        }
    }

    if ($createdEventId) {
        $sync = $conn->prepare('UPDATE event SET EventType = ?, EventPlace = ?, EventLocation = ? WHERE EventId = ?');
        if ($sync) {
            $sync->bind_param('sssi', $eventType, $place, $place, $createdEventId);
            $sync->execute();
            $sync->close();
        }

        // Audit Trail Logging
        if (file_exists(__DIR__ . '/../../../../config/audit.php')) {
            require_once __DIR__ . '/../../../../config/audit.php';
        }
        if (function_exists('logAudit')) {
            logAudit(
                $conn,
                'Create Event',
                'organization',
                $orgId,
                'success',
                [
                    'EventId'   => $createdEventId,
                    'EventName' => $name,
                    'EventType' => $eventType,
                    'EventDate' => $date
                ]
            );
        }

        // Save Uploaded Documents (Proposal, Program Flow, Supporting Docs, Financial Report) to org_documents
        $docDir = __DIR__ . '/../../../../assets/uploads/documents/';
        if (!is_dir($docDir)) mkdir($docDir, 0755, true);

        $fileMappings = [
            'EventProposal'    => ['title' => 'Project Proposal / OPlan', 'type' => 'EventProposal'],
            'oplanFile'        => ['title' => 'Project Proposal / OPlan', 'type' => 'EventProposal'],
            'EventProgramFlow' => ['title' => 'Program Flow',            'type' => 'EventProgramFlow'],
            'programFlowFile'  => ['title' => 'Program Flow',            'type' => 'EventProgramFlow'],
            'EventOther'       => ['title' => 'Supporting Document',      'type' => 'Supporting Document'],
            'otherDocs'        => ['title' => 'Supporting Document',      'type' => 'Supporting Document'],
            'supportingFiles'  => ['title' => 'Supporting Document',      'type' => 'Supporting Document'],
            'FinancialReport'  => ['title' => 'Financial Report',        'type' => 'FinancialReport'],
            'evFinReport'      => ['title' => 'Financial Report',        'type' => 'FinancialReport'],
        ];

        foreach ($fileMappings as $fKey => $info) {
            if (empty($_FILES[$fKey])) continue;

            $names = is_array($_FILES[$fKey]['name']) ? $_FILES[$fKey]['name'] : [$_FILES[$fKey]['name']];
            $tmpNames = is_array($_FILES[$fKey]['tmp_name']) ? $_FILES[$fKey]['tmp_name'] : [$_FILES[$fKey]['tmp_name']];
            $errors = is_array($_FILES[$fKey]['error']) ? $_FILES[$fKey]['error'] : [$_FILES[$fKey]['error']];
            $sizes = is_array($_FILES[$fKey]['size']) ? $_FILES[$fKey]['size'] : [$_FILES[$fKey]['size']];

            for ($i = 0; $i < count($names); $i++) {
                if (empty($names[$i]) || (isset($errors[$i]) && $errors[$i] !== UPLOAD_ERR_OK)) continue;

                $origName = $names[$i];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $cleanEventName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
                $cleanDocType = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['type']);
                $docFn = $cleanEventName . '_' . $cleanDocType . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $targetPath = $docDir . $docFn;

                $saved = false;
                if (is_uploaded_file($tmpNames[$i])) {
                    $saved = move_uploaded_file($tmpNames[$i], $targetPath);
                } else if (file_exists($tmpNames[$i])) {
                    $saved = copy($tmpNames[$i], $targetPath);
                }

                if ($saved) {
                    $docRelPath = 'assets/uploads/documents/' . $docFn;
                    $docTitle = $name . ' - ' . $info['title'];
                    if (count($names) > 1) {
                        $docTitle .= ' (' . ($i + 1) . ')';
                    }
                    $docCat = $info['type'];
                    $fileSizeStr = isset($sizes[$i]) && $sizes[$i] > 0 ? round($sizes[$i] / 1024, 1) . ' KB' : '0 KB';

                    $insDoc = $conn->prepare("
                        INSERT INTO org_documents 
                        (OrgId, EventId, Title, DocType, Description, FilePath, FileSize, UploadedAt) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    if ($insDoc) {
                        $insDoc->bind_param("iisssss", $orgId, $createdEventId, $docTitle, $docCat, $info['title'], $docRelPath, $fileSizeStr);
                        $insDoc->execute();
                        $insDoc->close();
                    }
                }
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Event and documents created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error ?: 'Failed to create event']);
}
if ($isDirectApiCall) exit;
?>
