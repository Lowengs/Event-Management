<?php
/**
 * Student API: POST Event Register
 * Uses Stored Procedure: sp_RegisterStudentEvent
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['student_id'] ?? $_SESSION['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$input   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$eventId = (int)($input['EventId'] ?? $input['event_id'] ?? $input['id'] ?? $_POST['EventId'] ?? $_POST['event_id'] ?? $_GET['EventId'] ?? $_GET['event_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID required']);
    exit;
}

// Check event existence, capacity, and status
$evRes = $conn->query("SELECT e.EventId, e.EventName, e.OrgId, e.EventStatus, e.EventCapacity, e.EventType, o.OrgName 
                       FROM `event` e 
                       LEFT JOIN `organization` o ON o.OrgId = e.OrgId 
                       WHERE e.EventId = $eventId LIMIT 1");
if (!$evRes || $evRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}
$evRow = $evRes->fetch_assoc();
$orgId = !empty($evRow['OrgId']) ? (int)$evRow['OrgId'] : null;
$evStatus = strtolower(trim($evRow['EventStatus'] ?? ''));
$eventName = $evRow['EventName'] ?? 'Event';
$orgName = $evRow['OrgName'] ?? 'this organization';

if ($evStatus === 'completed') {
    echo json_encode(['success' => false, 'message' => 'Registration is closed. This event has already completed.']);
    exit;
}
if ($evStatus === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'Registration is unavailable. This event was cancelled.']);
    exit;
}

// Validate event type if restricted to organization members only
$evType = strtolower(trim($evRow['EventType'] ?? 'general'));
if (($evType === 'members' || $evType === 'members only' || $evType === 'exclusive') && $orgId) {
    $uCheck = $conn->query("SELECT OrgId FROM `user` WHERE UserId = $userId LIMIT 1");
    $uRow = $uCheck ? $uCheck->fetch_assoc() : null;
    $userOrgId = (int)($uRow['OrgId'] ?? 0);
    if ($userOrgId !== $orgId) {
        echo json_encode(['success' => false, 'message' => "This event is exclusively open to members of $orgName."]);
        exit;
    }
}

// Check capacity
$capacity = (int)($evRow['EventCapacity'] ?? 0);
if ($capacity > 0) {
    $cRes = $conn->query("SELECT COUNT(*) AS total FROM `eventregistration` WHERE EventId = $eventId");
    $cRow = $cRes ? $cRes->fetch_assoc() : null;
    $registeredCount = (int)($cRow['total'] ?? 0);
    if ($registeredCount >= $capacity) {
        echo json_encode(['success' => false, 'message' => 'Event is already full. No more registration slots available.']);
        exit;
    }
}

// Check if already registered
$chk = $conn->query("SELECT RegistrationId FROM `eventregistration` WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
if ($chk && $chk->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Already registered for this event', 'already_registered' => true]);
    exit;
}

// Ensure OrgId is a valid foreign key in organization table
$validOrgId = null;
if ($orgId) {
    $orgChk = $conn->query("SELECT OrgId FROM `organization` WHERE OrgId = $orgId LIMIT 1");
    if ($orgChk && $orgChk->num_rows > 0) {
        $validOrgId = $orgId;
    }
}

$registered = false;
try {
    $stmt = $conn->prepare("CALL sp_RegisterStudentEvent(?, ?)");
    if ($stmt) {
        $stmt->bind_param("ii", $eventId, $userId);
        if ($stmt->execute()) {
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
            $registered = true;
        } else {
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    }
} catch (Throwable $e) {
    // Fallback to direct query
}

if (!$registered) {
    if ($validOrgId !== null) {
        $ins = $conn->prepare("INSERT INTO `eventregistration` (UserId, EventId, OrgId, DateIssued) VALUES (?, ?, ?, CURDATE())");
        if ($ins) {
            $ins->bind_param("iii", $userId, $eventId, $validOrgId);
            if ($ins->execute()) {
                $registered = true;
            }
            $ins->close();
        }
    } else {
        $ins = $conn->prepare("INSERT INTO `eventregistration` (UserId, EventId, DateIssued) VALUES (?, ?, CURDATE())");
        if ($ins) {
            $ins->bind_param("ii", $userId, $eventId);
            if ($ins->execute()) {
                $registered = true;
            }
            $ins->close();
        }
    }
}

if ($registered) {
    // Record audit log if available
    if (file_exists(__DIR__ . '/../../../audit.php')) {
        require_once __DIR__ . '/../../../audit.php';
        logAudit($conn, 'Event Registration', 'student', $userId, 'success', [
            'EventId'   => $eventId,
            'EventName' => $eventName,
            'OrgId'     => $validOrgId
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Event registration successful']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Failed to register for event']);
?>
