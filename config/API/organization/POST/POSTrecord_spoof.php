<?php
/**
 * Organization API: Record Spoofing Attempt
 * Endpoint: /config/API/endpoints/index.php?action=record_spoof_attempt
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../audit.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$eventId = (int)($input['event_id'] ?? $input['EventId'] ?? 0);
$spoofType = trim($input['spoof_type'] ?? 'Static Photo / Phone Screen');
$details = trim($input['details'] ?? 'Camera blocked static picture / phone screen presentation. Real live human face required.');

$orgId = (int)($_SESSION['org_id'] ?? 0);
$studentId = (int)($_SESSION['student_id'] ?? 0);
$actorType = !empty($orgId) ? 'organization' : (!empty($studentId) ? 'student' : 'system');
$actorId = !empty($orgId) ? $orgId : (!empty($studentId) ? $studentId : 1);

try {
    logAudit($conn, 'Anti-Spoofing Detection Blocked', $actorType, $actorId, 'blocked', [
        'event_id'   => $eventId,
        'spoof_type' => $spoofType,
        'details'    => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Spoofing incident logged and blocked for security.'
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
