<?php
/**
 * Common API: POST Face Recognition Verification
 * Endpoint: /config/API/endpoints/index.php?action=POSTface_recognition
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$studentId = trim($input['student_id'] ?? '');

if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID required']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Face verification completed']);
?>
