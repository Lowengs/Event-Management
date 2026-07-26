<?php
session_start();
require_once '../db.php';
require_once '../audit.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

// ── Validation ────────────────────────────────────────────────────
if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email address is required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// ── Check student exists ──────────────────────────────────────────
$stmt = $conn->prepare("SELECT UserId, first_name FROM `user` WHERE Email = ? AND Role = 'student' LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logAudit($conn, 'Password Reset Requested', 'student', null, 'failed', ['email' => $email, 'reason' => 'Email not registered']);
    echo json_encode([
        'success' => false,
        'message' => 'No student account found with that email. Please register first.',
        'not_registered' => true
    ]);
    exit;
}

$student = $result->fetch_assoc();
$stmt->close();

// ── Generate 6-digit OTP ──────────────────────────────────────────
$otp     = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// ── Persist OTP (upsert) ──────────────────────────────────────────
// Requires table: password_reset_tokens (email, token, expires_at)
$stmt2 = $conn->prepare(
    "INSERT INTO password_reset_tokens (email, token, expires_at)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)"
);
if (!$stmt2) {
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}
$stmt2->bind_param('sss', $email, $otp, $expires);
$stmt2->execute();
$stmt2->close();
$conn->close();

// ── Send email via PHP mail() ─────────────────────────────────────
$firstName = htmlspecialchars($student['first_name']);
$to        = $email;
$subject   = 'Your Password Reset Code';
$body      = "Hello {$firstName},\n\n"
           . "Your password reset code is:\n\n"
           . "  {$otp}\n\n"
           . "This code expires in 15 minutes. If you did not request a reset, you can ignore this email.\n\n"
           . "– Student Portal";

$headers   = "From: no-reply@studentportal.edu\r\n"
           . "Reply-To: no-reply@studentportal.edu\r\n"
           . "X-Mailer: PHP/" . phpversion();

if (mail($to, $subject, $body, $headers)) {
    // Store email in session so OTP step knows which account to reset
    $_SESSION['reset_email'] = $email;
    logAudit($conn, 'Password Reset OTP Sent', 'student', (int)$student['UserId'], 'success', ['email' => $email]);
    echo json_encode(['success' => true, 'message' => 'A 6-digit code has been sent to your email.']);
} else {
    logAudit($conn, 'Password Reset OTP Failed', 'student', (int)$student['UserId'], 'failed', ['email' => $email, 'reason' => 'mail() failed']);
    echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again later.']);
}
exit;
?>
