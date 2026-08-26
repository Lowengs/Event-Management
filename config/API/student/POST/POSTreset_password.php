<?php
/**
 * Student API: Reset Password
 * Endpoint: /config/API/endpoints/index.php?action=POSTreset_password
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$newPass     = $_POST['new_password'] ?? $_POST['password'] ?? '';
$confirmPass = $_POST['confirm_password'] ?? $newPass;

if (empty($newPass) || strlen($newPass) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
    exit;
}

if ($newPass !== $confirmPass) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

$isVerified = !empty($_SESSION['student_forgot_verified']);
$email      = $_SESSION['student_forgot_email'] ?? '';
$userId     = (int)($_SESSION['student_forgot_user_id'] ?? 0);

if (!$isVerified || (empty($email) && !$userId)) {
    echo json_encode(['success' => false, 'message' => 'Password reset session expired or unverified. Please request a new code.']);
    exit;
}

try {
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    $updated = false;

    try {
        $stmt = $conn->prepare("CALL sp_UpdateStudentPassword(?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $userId, $email, $hash);
            if ($stmt->execute()) {
                $updated = true;
            }
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    } catch (\Throwable $e) {
        $updated = false;
    }

    if (!$updated) {
        $stmtF = $conn->prepare("UPDATE `user` SET PasswordHash = ? WHERE UserId = ? OR LOWER(Email) = LOWER(?)");
        if ($stmtF) {
            $stmtF->bind_param("sis", $hash, $userId, $email);
            $updated = $stmtF->execute();
            $stmtF->close();
        }
    }

    if ($updated) {
        unset($_SESSION['student_forgot_otp'], $_SESSION['student_forgot_email'], $_SESSION['student_forgot_user_id'], $_SESSION['student_forgot_verified']);
        echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password. Please try again.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
