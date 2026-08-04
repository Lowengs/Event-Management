<?php
/**
 * Student API: PUT Profile
 * Endpoint: /config/API/endpoints/index.php?action=PUTprofile
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId = (int)$_SESSION['student_id'];
$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$firstName  = trim($input['first_name']  ?? '');
$lastName   = trim($input['last_name']   ?? '');
$middleName = trim($input['middle_name'] ?? '');
$phone      = trim($input['phone']       ?? '');
$address    = trim($input['address']     ?? '');

$photo = '';
try {
    $stmt = $conn->prepare("CALL sp_UpdateStudentProfile(?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $userId, $firstName, $lastName, $middleName, $phone, $address, $photo);
    if ($stmt->execute()) {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
