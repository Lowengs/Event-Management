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

$status = !empty($input['failed']) ? 'failed' : 'success';
$userId = null;
$actorName = null;

if (!empty($studentId)) {
    $stmt = $conn->prepare("SELECT UserId, CONCAT(first_name, ' ', last_name) AS full_name FROM `user` WHERE student_id = ? OR UserId = ? LIMIT 1");
    if ($stmt) {
        $sidInt = (int)$studentId;
        $stmt->bind_param("si", $studentId, $sidInt);
        $stmt->execute();
        if ($row = $stmt->get_result()->fetch_assoc()) {
            $userId = (int)$row['UserId'];
            $actorName = $row['full_name'];
        }
        $stmt->close();
    }
}

if (file_exists(__DIR__ . '/../../../audit.php')) {
    require_once __DIR__ . '/../../../audit.php';
    logAudit($conn, 'Face Recognition Attempt', 'student', $userId, $status, [
        'student_id'   => $studentId,
        'student_name' => $actorName,
        'status'       => $status
    ], $actorName);
}

echo json_encode(['success' => true, 'message' => 'Face verification completed']);
?>
