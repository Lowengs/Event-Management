<?php
/**
 * Student API: POST Event Register
 * Uses Stored Procedure: sp_RegisterStudentEvent
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId  = (int)$_SESSION['student_id'];
$eventId = (int)($_POST['EventId'] ?? $_POST['event_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID required']);
    exit;
}

// Check event existence, status, and target audience
$evRes = $conn->query("SELECT e.OrgId, e.EventStatus, e.Audience, o.OrgName FROM `event` e LEFT JOIN `organization` o ON o.OrgId = e.OrgId WHERE e.EventId = $eventId LIMIT 1");
if (!$evRes || $evRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}
$evRow = $evRes->fetch_assoc();
$orgId = (int)($evRow['OrgId'] ?? 0);
$evStatus = strtolower(trim($evRow['EventStatus'] ?? ''));
$audience = strtolower(trim($evRow['Audience'] ?? 'all'));
$orgName = $evRow['OrgName'] ?? 'this organization';

if ($evStatus === 'completed') {
    echo json_encode(['success' => false, 'message' => 'Registration is closed. This event has already completed.']);
    exit;
}
if ($evStatus === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'Registration is unavailable. This event was cancelled.']);
    exit;
}

// Check Members Only restriction
if ($audience === 'members') {
    $uRes = $conn->query("SELECT OrgId FROM `user` WHERE UserId = $userId LIMIT 1");
    $uRow = $uRes ? $uRes->fetch_assoc() : [];
    $userOrgId = (int)($uRow['OrgId'] ?? 0);
    if ($userOrgId !== $orgId) {
        echo json_encode([
            'success' => false,
            'message' => "Registration restricted: This event is exclusive to registered members of {$orgName}."
        ]);
        exit;
    }
}

// Check if already registered
$chk = $conn->query("SELECT RegistrationId FROM `eventregistration` WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
if ($chk && $chk->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Already registered for this event']);
    exit;
}

try {
    $stmt = $conn->prepare("CALL sp_RegisterStudentEvent(?, ?)");
    if ($stmt) {
        $stmt->bind_param("ii", $eventId, $userId);
        if ($stmt->execute()) {
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
            echo json_encode(['success' => true, 'message' => 'Event registration successful']);
            exit;
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // fallback to direct query below
}

// Fallback direct SQL insert
$ins = $conn->prepare("INSERT INTO `eventregistration` (UserId, EventId, OrgId, DateIssued) VALUES (?, ?, ?, CURDATE())");
if ($ins) {
    $ins->bind_param("iii", $userId, $eventId, $orgId);
    if ($ins->execute()) {
        $ins->close();
        echo json_encode(['success' => true, 'message' => 'Event registration successful']);
        exit;
    }
    $ins->close();
}

echo json_encode(['success' => false, 'message' => 'Failed to register for event']);
?>
