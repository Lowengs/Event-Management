<?php
/**
 * osa_reset_password.php — Verifies PIN and resets the OSA password.
 */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']); exit;
}

$pin         = trim($_POST['pin']         ?? '');
$newPassword = trim($_POST['new_password'] ?? '');
$confPassword = trim($_POST['confirm_password'] ?? '');

// Validate inputs
if (empty($pin) || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'PIN and new password are required.']); exit;
}
if ($newPassword !== $confPassword) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']); exit;
}
if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']); exit;
}

// Check session data
if (empty($_SESSION['osa_reset_email']) || empty($_SESSION['osa_reset_pin']) || empty($_SESSION['osa_reset_expiry'])) {
    echo json_encode(['success' => false, 'message' => 'No active reset session. Please request a new code.']); exit;
}

// Check expiry
if (strtotime($_SESSION['osa_reset_expiry']) < time()) {
    unset($_SESSION['osa_reset_pin'], $_SESSION['osa_reset_email'], $_SESSION['osa_reset_expiry']);
    echo json_encode(['success' => false, 'message' => 'Reset code has expired. Please request a new one.']); exit;
}

// Verify PIN
if (!password_verify($pin, $_SESSION['osa_reset_pin'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid reset code. Please check and try again.']); exit;
}

// Update password
$email    = $_SESSION['osa_reset_email'];
$newHash  = password_hash($newPassword, PASSWORD_BCRYPT);
$stmt     = $conn->prepare("UPDATE osa SET PasswordHash = ? WHERE Email = ?");
$stmt->bind_param('ss', $newHash, $email);
$ok = $stmt->execute();
$stmt->close();

// Clear session
unset($_SESSION['osa_reset_pin'], $_SESSION['osa_reset_email'], $_SESSION['osa_reset_expiry']);

if ($ok && $conn->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
}
exit;
