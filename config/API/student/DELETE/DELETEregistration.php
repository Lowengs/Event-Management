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
            logAudit($conn, 'Cancel Registration', 'student', $userId, 'success', ['event_id' => $eventId, 'registration_id' => $regId]);
        }
        echo json_encode(['success' => true, 'message' => 'Event registration cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to cancel registration: ' . $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
