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
$updated = false;
try {
    $stmt = $conn->prepare("CALL sp_UpdateStudentProfile(?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("issssss", $userId, $firstName, $lastName, $middleName, $phone, $address, $photo);
        $updated = $stmt->execute();
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {
    $updated = false;
}

if (!$updated) {
    $stmt2 = $conn->prepare("UPDATE `user` SET first_name=?, last_name=?, middle_name=?, phone=?, Address=? WHERE UserId=?");
    if ($stmt2) {
        $stmt2->bind_param("sssssi", $firstName, $lastName, $middleName, $phone, $address, $userId);
        $updated = $stmt2->execute();
        $stmt2->close();
    }
}

if ($updated) {
    if (file_exists(__DIR__ . '/../../../audit.php')) {
        require_once __DIR__ . '/../../../audit.php';
        logAudit($conn, 'Update Profile', 'student', $userId, 'success', [
            'first_name' => $firstName,
            'last_name'  => $lastName
        ]);
    }
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}
?>
