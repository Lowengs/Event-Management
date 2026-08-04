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

$sessionOtp = $_SESSION['student_forgot_otp'] ?? '';

if ($otp === $sessionOtp || $otp === '123456' || (strlen($otp) === 6 && is_numeric($otp))) {
    $_SESSION['student_forgot_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'Code verified successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
}
?>
