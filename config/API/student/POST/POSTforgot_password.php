<?php
/**
 * Student API: Send Forgot Password Verification Code
 * Endpoint: /config/API/endpoints/index.php?action=POSTforgot_password
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../mailer.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email address is required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT UserId, first_name, last_name FROM `user` WHERE LOWER(Email) = LOWER(?) LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $q = $stmt->get_result();
    $user = $q ? $q->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        echo json_encode([
            'success' => false, 
            'not_registered' => true,
            'message' => 'No student account found with that email address.'
        ]);
        exit;
    }

    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Student';

    // Generate 6-digit OTP code
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $_SESSION['student_forgot_otp']      = $otp;
    $_SESSION['student_forgot_otp_time'] = time();
    $_SESSION['student_forgot_email']    = strtolower($email);
    $_SESSION['student_forgot_user_id']  = $user['UserId'];
    $_SESSION['student_forgot_verified'] = false;

    // Send automated email via SMTP
    $mailResult = sendOtpEmail($email, $fullName, $otp, 'Your Student Portal Password Reset OTP');

    if ($mailResult['success']) {
        echo json_encode([
            'success' => true,
            'message' => "Verification code sent to $email. Please check your inbox or spam folder.",
            'code'    => $otp
        ]);
    } else {
        // Fallback for local testing if SMTP App Password is required
        echo json_encode([
            'success'            => true,
            'message'            => "Verification code generated for $email.",
            'smtp_notice'        => $mailResult['message'],
            'needs_app_password' => $mailResult['needs_app_password'] ?? false,
            'code'               => $otp
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
