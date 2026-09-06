<?php
/**
 * Common API: Send OTP for Email Change Verification
 * Endpoint: /config/API/endpoints/index.php?action=send_email_change_otp
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../mailer.php';

header('Content-Type: application/json');

$isOsa   = !empty($_SESSION['osa_id']);
$isOrg   = !empty($_SESSION['org_id']);
$isAdmin = !empty($_SESSION['admin_id']);

if (!$isOsa && !$isOrg && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in to update email']);
    exit;
}

$newEmail = trim($_POST['new_email'] ?? $_POST['email'] ?? '');
if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

// Check current email to make sure it's actually changing
$currentEmail = '';
$recipientName = 'User';
if ($isOsa) {
    $currentEmail = $_SESSION['osa_email'] ?? '';
    $recipientName = $_SESSION['osa_name'] ?? 'OSA Administrator';
} elseif ($isOrg) {
    $currentEmail = $_SESSION['org_email'] ?? '';
    $recipientName = $_SESSION['org_name'] ?? 'Organization';
} elseif ($isAdmin) {
    $currentEmail = $_SESSION['admin_email'] ?? '';
    $recipientName = $_SESSION['admin_name'] ?? 'Administrator';
}

if (strtolower($newEmail) === strtolower($currentEmail)) {
    echo json_encode(['success' => false, 'message' => 'New email is identical to your current email']);
    exit;
}

// Check if email is already taken by another account
$chk = $conn->prepare("SELECT 1 FROM `user` WHERE LOWER(Email) = LOWER(?) UNION SELECT 1 FROM `osa` WHERE LOWER(Email) = LOWER(?) UNION SELECT 1 FROM `organization` WHERE LOWER(email) = LOWER(?)");
if ($chk) {
    $chk->bind_param("sss", $newEmail, $newEmail, $newEmail);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        echo json_encode(['success' => false, 'message' => 'This email address is already in use by another account']);
        exit;
    }
    $chk->close();
}

$otpCode = (string)random_int(100000, 999999);
$_SESSION['email_change_otp'] = [
    'new_email' => $newEmail,
    'code'      => $otpCode,
    'expires'   => time() + 600, // 10 minutes
    'attempts'  => 0
];

$mailRes = sendOtpEmail($newEmail, $recipientName, $otpCode, 'NAAP Email Verification Code: ' . $otpCode);
if ($mailRes['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'A 6-digit verification code has been sent to ' . htmlspecialchars($newEmail)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send verification email: ' . ($mailRes['message'] ?? 'Mailer error')
    ]);
}
