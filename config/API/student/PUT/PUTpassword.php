<?php
/**
 * Student API: PUT Password
 * Endpoint: /config/API/endpoints/index.php?action=PUTpassword
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

$studentId = (int)($_SESSION['student_id'] ?? 0);
if (!$studentId) { echo json_encode(['success' => false, 'message' => 'Login required']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$currentPass = $input['current_password'] ?? '';
$newPass     = $input['new_password'] ?? '';

if (empty($currentPass) || empty($newPass)) {
    echo json_encode(['success' => false, 'message' => 'Current and new password required']);
    exit;
}

try {
    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
    $emptyMail = '';
    $ps = $conn->prepare("CALL sp_UpdateStudentPassword(?, ?, ?)");
    $ps->bind_param("iss", $studentId, $emptyMail, $newHash);
    $ps->execute();
    $ps->close();
    while ($conn->more_results() && $conn->next_result()) { ; }
    echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
