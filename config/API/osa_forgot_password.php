<?php
/**
 * osa_forgot_password.php — Sends a reset PIN to the OSA email.
 */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']); exit;
}

$email = trim($_POST['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']); exit;
}

// Check if OSA account exists
$stmt = $conn->prepare("SELECT OsaId, Name FROM osa WHERE Email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    // Don't reveal if email exists — just say "If this email is registered, a code was sent"
    echo json_encode(['success' => true, 'message' => 'If that email is registered, a reset code has been sent.']); exit;
}

$osa = $result->fetch_assoc();

// Generate a 6-digit PIN
$pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiry = date('Y-m-d H:i:s', time() + 900); // 15 minutes

// Store pin in session
$_SESSION['osa_reset_pin']    = password_hash($pin, PASSWORD_BCRYPT);
$_SESSION['osa_reset_email']  = $email;
$_SESSION['osa_reset_expiry'] = $expiry;

// === Attempt to send email via PHP mail() ===
// If mail() is not configured on this server, the PIN is returned in the response for test purposes.
$subject = 'NAAP OSA — Password Reset Code';
$body    = "Hello {$osa['Name']},\n\nYour password reset code is: {$pin}\n\nThis code expires in 15 minutes.\n\nIf you did not request this, please ignore this email.\n\n— NAAP OSA System";
$headers = "From: no-reply@naap.edu.ph\r\nContent-Type: text/plain; charset=UTF-8";

$sent = @mail($email, $subject, $body, $headers);

// Return the PIN in dev mode since mail() may not be configured on local XAMPP
echo json_encode([
    'success' => true,
    'message' => 'Reset code generated. ' . ($sent ? "Check your email." : "Dev mode: code is <strong>{$pin}</strong>"),
    'dev_pin' => $pin // Remove this in production
]);
exit;
