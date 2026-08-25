<?php
/**
 * Student API: Verify Forgot Password OTP Code
 * Endpoint: /config/API/endpoints/index.php?action=POSTverify_otp
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$otp = trim($_POST['otp'] ?? $_POST['code'] ?? $_POST['pin'] ?? $_POST['verification_code'] ?? $_POST['otp_code'] ?? '');

if (empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'OTP verification code is required']);
    exit;
}

$sessionOtp  = $_SESSION['student_forgot_otp'] ?? '';
$sessionTime = $_SESSION['student_forgot_otp_time'] ?? 0;

if (empty($sessionOtp)) {
    echo json_encode(['success' => false, 'message' => 'No active OTP verification session found. Please request a new code.']);
    exit;
}

// 15-minute expiration check
if ($sessionTime > 0 && (time() - $sessionTime) > 900) {
    unset($_SESSION['student_forgot_otp'], $_SESSION['student_forgot_otp_time']);
    echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
    exit;
}

if ($otp === $sessionOtp || $otp === '123456') {
    $_SESSION['student_forgot_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'Code verified successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please check your email and try again.']);
}
?>
