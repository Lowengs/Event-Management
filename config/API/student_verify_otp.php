<?php
session_start();
require_once '../db.php';
require_once '../audit.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = trim($_POST['action'] ?? '');

// ══════════════════════════════════════════════════════════════
//  ACTION 1: verify_otp  – check the 6-digit code
// ══════════════════════════════════════════════════════════════
if ($action === 'verify_otp') {
    $email = $_SESSION['reset_email'] ?? '';
    $otp   = trim($_POST['otp'] ?? '');

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }

    if (strlen($otp) !== 6 || !ctype_digit($otp)) {
        echo json_encode(['success' => false, 'message' => 'Please enter the 6-digit code.']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT token FROM password_reset_tokens
         WHERE email = ? AND expires_at > NOW() LIMIT 1"
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Code expired. Please request a new one.']);
        exit;
    }

    $row = $result->fetch_assoc();
    if ($row['token'] !== $otp) {
        logAudit($conn, 'Password Reset OTP Verify', 'student', null, 'failed', ['email' => $email, 'reason' => 'Incorrect OTP']);
        echo json_encode(['success' => false, 'message' => 'Incorrect code. Please try again.']);
        exit;
    }

    $_SESSION['otp_verified'] = true;
    logAudit($conn, 'Password Reset OTP Verified', 'student', null, 'success', ['email' => $email]);
    echo json_encode(['success' => true, 'message' => 'Code verified. Please set a new password.']);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  ACTION 2: reset_password  – set new password
// ══════════════════════════════════════════════════════════════
if ($action === 'reset_password') {
    $email           = $_SESSION['reset_email']  ?? '';
    $otpVerified     = $_SESSION['otp_verified'] ?? false;
    $newPassword     = $_POST['new_password']     ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($email) || !$otpVerified) {
        echo json_encode(['success' => false, 'message' => 'Session invalid. Please start the reset process again.']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("UPDATE students SET password = ? WHERE email = ?");
    $stmt->bind_param('ss', $hashed, $email);
    $stmt->execute();
    $stmt->close();

    // Clean up OTP record
    $clean = $conn->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
    $clean->bind_param('s', $email);
    $clean->execute();
    $clean->close();

    // ── Audit: password successfully reset ───────────────────────
    logAudit($conn, 'Password Reset Completed', 'student', null, 'success', ['email' => $email]);

    $conn->close();

    // Clear reset-related session data
    unset($_SESSION['reset_email'], $_SESSION['otp_verified']);

    echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
exit;
?>
