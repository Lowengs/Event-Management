<?php
/**
 * Student API: DELETE / Cancel Event Registration
 * Endpoint: /config/API/endpoints/index.php?action=cancel_registration
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

$userId = (int)($_SESSION['student_id'] ?? $_SESSION['user_id'] ?? 0);
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Student login required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$eventId = (int)($input['event_id'] ?? $input['EventId'] ?? $_GET['event_id'] ?? 0);
$regId   = (int)($input['registration_id'] ?? $input['RegistrationId'] ?? $_GET['registration_id'] ?? 0);

if (!$eventId && !$regId) {
    echo json_encode(['success' => false, 'message' => 'Event ID or Registration ID required']);
    exit;
}

try {
    $evName = '';
    $effectiveEventId = $eventId;
    if (!$effectiveEventId && $regId > 0) {
        $rQ = $conn->query("SELECT EventId FROM eventregistration WHERE RegistrationId = $regId LIMIT 1");
        if ($rQ && $rRow = $rQ->fetch_assoc()) $effectiveEventId = (int)$rRow['EventId'];
    }
    if ($effectiveEventId > 0) {
        $eQ = $conn->query("SELECT EventName FROM event WHERE EventId = $effectiveEventId LIMIT 1");
        if ($eQ && $eRow = $eQ->fetch_assoc()) $evName = $eRow['EventName'];
    }

    $stuIdNum = '';
    $stuName = '';
    $uRes = $conn->query("SELECT student_id, first_name, last_name FROM `user` WHERE UserId = $userId LIMIT 1");
    if ($uRes && $uRow = $uRes->fetch_assoc()) {
        $stuIdNum = $uRow['student_id'] ?? '';
        $stuName  = trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? ''));
    }

    if ($regId > 0) {
        $stmt = $conn->prepare("DELETE FROM eventregistration WHERE RegistrationId = ? AND UserId = ?");
        $stmt->bind_param("ii", $regId, $userId);
    } else {
        $stmt = $conn->prepare("DELETE FROM eventregistration WHERE EventId = ? AND UserId = ?");
        $stmt->bind_param("ii", $eventId, $userId);
    }

    if ($stmt->execute()) {
        $stmt->close();
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Cancel Registration', 'student', $userId, 'success', [
                'EventId'         => $effectiveEventId,
                'EventName'       => $evName,
                'registration_id' => $regId,
                'student_id'      => $stuIdNum,
                'student_name'    => $stuName
            ]);
        }
        echo json_encode(['success' => true, 'message' => 'Event registration cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel registration: ' . $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
