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
$orgId = 0;
$orgRes = $conn->query("SELECT OrgId FROM `event` WHERE EventId = $eventId LIMIT 1");
if ($orgRes && $orow = $orgRes->fetch_assoc()) {
    $orgId = (int)($orow['OrgId'] ?? 0);
}

$chk = $conn->query("SELECT RegistrationId FROM `eventregistration` WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
if ($chk && $chk->num_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Already registered for this event']);
    exit;
}

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
