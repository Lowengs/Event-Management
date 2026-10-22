<?php
/**
 * Student API: PUT Profile
 * Endpoint: /config/API/endpoints/index.php?action=PUTprofile
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json; charset=utf-8');
}

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    if ($isDirectApiCall) exit;
    return;
}

$userId = (int)$_SESSION['student_id'];

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    parse_str($raw, $input);
}
if (!empty($_POST)) {
    $input = array_merge($input ?: [], $_POST);
}

$firstName  = trim($input['first_name']  ?? $input['FirstName'] ?? '');
$lastName   = trim($input['last_name']   ?? $input['LastName'] ?? '');
$middleName = trim($input['middle_name'] ?? $input['MiddleName'] ?? '');
$phone      = trim($input['phone']       ?? $input['Phone'] ?? $input['contact'] ?? '');
$address    = trim($input['address']     ?? $input['Address'] ?? '');

// Preserve existing user fields if omitted
$curStmt = $conn->prepare("SELECT first_name, last_name, middle_name, phone, Address FROM `user` WHERE UserId = ? LIMIT 1");
if ($curStmt) {
    $curStmt->bind_param("i", $userId);
    $curStmt->execute();
    $cur = $curStmt->get_result()->fetch_assoc();
    $curStmt->close();
    if ($cur) {
        if ($firstName === '')  $firstName  = $cur['first_name'] ?? '';
        if ($lastName === '')   $lastName   = $cur['last_name'] ?? '';
        if ($middleName === '') $middleName = $cur['middle_name'] ?? '';
        if ($phone === '')      $phone      = $cur['phone'] ?? '';
        if ($address === '')    $address    = $cur['Address'] ?? '';
    }
}

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
        ], trim("$firstName $lastName"));
    }
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}
if ($isDirectApiCall) exit;
?>
