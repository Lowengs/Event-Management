<?php
/**
 * Student API: GET Profile
 * Uses Stored Procedure: sp_GetStudentProfile
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
if ($isDirectApiCall) exit;
    return;
}

$userId = (int)$_SESSION['student_id'];
$profile = null;

try {
    if ($stmt = $conn->prepare("CALL sp_GetStudentProfile(?)")) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $profile = $res->fetch_assoc();
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed
}

if (!$profile) {
    $stmt = $conn->prepare("
        SELECT u.*, o.OrgName, o.OrgPicture
        FROM `user` u
        LEFT JOIN organization o ON o.OrgId = u.OrgId
        WHERE u.UserId = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

// Stored procedures in older databases return PascalCase fields while the
// student pages use the current snake_case names. Normalize once at the API.
if (is_array($profile)) {
    $profile['first_name'] = $profile['first_name'] ?? $profile['FirstName'] ?? $profile['Firstname'] ?? '';
    $profile['last_name'] = $profile['last_name'] ?? $profile['LastName'] ?? $profile['Lastname'] ?? '';
    $profile['profile_photo'] = $profile['profile_photo'] ?? $profile['ProfilePhoto'] ?? $profile['ProfilePicture'] ?? '';
}

echo json_encode([
        'success' => true,
        'message' => 'Profile retrieved successfully',
        'data'    => $profile
    ]);
if ($isDirectApiCall) exit;
?>

