<?php
session_start();
require_once '../db.php';
require_once '../audit.php';
require_once '../rate_limit.php';
rateLimit('student_login', 10, 60);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] === '1';

// ── Basic validation ──────────────────────────────────────────────
if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// ── 3-Attempt Lockout (per email, tracked in session) ─────────────
$lockKey      = 'login_fails_' . md5($email);
$lockTimeKey  = 'login_lock_until_' . md5($email);
$maxAttempts  = 3;
$lockDuration = 300; // 5 minutes in seconds

// Check if currently locked out
if (isset($_SESSION[$lockTimeKey]) && time() < $_SESSION[$lockTimeKey]) {
    $remaining = $_SESSION[$lockTimeKey] - time();
    $mins = ceil($remaining / 60);
    logAudit($conn, 'Student Login Blocked', 'student', null, 'failed', [
        'email'  => $email,
        'reason' => 'Account locked out',
        'remaining_seconds' => $remaining
    ]);
    echo json_encode([
        'success'  => false,
        'locked'   => true,
        'message'  => "Too many failed attempts. Please wait {$mins} minute(s) before trying again.",
        'remaining' => $remaining
    ]);
    exit;
}

// ── Query student ─────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT UserId, first_name, last_name, Email, PasswordHash, status, profile_photo FROM `user` WHERE Email = ? AND Role = 'student' LIMIT 1");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logAudit($conn, 'Student Login', 'student', null, 'failed', ['email' => $email, 'reason' => 'Account not found']);
    echo json_encode(['success' => false, 'message' => 'No account found with that email address.']);
    exit;
}

$student = $result->fetch_assoc();

// ── Account status check ──────────────────────────────────────────
if (isset($student['status']) && strtolower($student['status']) !== 'active') {
    logAudit($conn, 'Student Login', 'student', (int)$student['UserId'], 'failed', ['email' => $email, 'reason' => 'Account status: ' . $student['status']]);
    echo json_encode(['success' => false, 'message' => 'Your account is pending approval or has been disabled.']);
    exit;
}

// ── Password verification ──────────────────────────────────────────
$passwordValid = password_verify($password, $student['PasswordHash']);

if (!$passwordValid) {
    // Increment failure counter
    $_SESSION[$lockKey] = ($_SESSION[$lockKey] ?? 0) + 1;
    $attempts    = $_SESSION[$lockKey];
    $attemptsLeft = $maxAttempts - $attempts;

    logAudit($conn, 'Student Login', 'student', (int)$student['UserId'], 'failed', [
        'email'   => $email,
        'reason'  => 'Wrong password',
        'attempt' => $attempts
    ]);

    if ($attempts >= $maxAttempts) {
        // Lock the account
        $_SESSION[$lockTimeKey] = time() + $lockDuration;
        $_SESSION[$lockKey]     = 0; // reset counter
        $mins = ceil($lockDuration / 60);
        echo json_encode([
            'success'  => false,
            'locked'   => true,
            'message'  => "Account locked after {$maxAttempts} failed attempts. Please wait {$mins} minutes.",
            'remaining' => $lockDuration
        ]);
    } else {
        echo json_encode([
            'success'      => false,
            'message'      => "Incorrect password. {$attemptsLeft} attempt(s) remaining before lockout.",
            'attempts_left' => $attemptsLeft
        ]);
    }
    exit;
}

// ── Success: reset failure count ──────────────────────────────────
unset($_SESSION[$lockKey], $_SESSION[$lockTimeKey]);

// ── Session ───────────────────────────────────────────────────────
$_SESSION['student_id']    = $student['UserId'];
$_SESSION['student_name']  = $student['first_name'] . ' ' . $student['last_name'];
$_SESSION['student_email'] = $student['Email'];
$_SESSION['student_photo'] = $student['profile_photo'] ?? '';
$_SESSION['role']          = 'student';

// ── Remember me cookie (30 days) ──────────────────────────────────
if ($remember) {
    $token = bin2hex(random_bytes(32));
    $expire = time() + (86400 * 30);
    setcookie('student_remember',       $token, $expire, '/', '', false, true);
    setcookie('student_remember_email', $email, $expire, '/', '', false, true);
} else {
    // Clear cookies if they previously remembered but unchecked now
    setcookie('student_remember',       '', time() - 3600, '/');
    setcookie('student_remember_email', '', time() - 3600, '/');
}

$stmt->close();

// ── Audit: successful login ───────────────────────────────────────
logAudit($conn, 'Student Login', 'student', (int)$student['UserId'], 'success', ['email' => $email]);

$conn->close();

echo json_encode([
    'success'  => true,
    'message'  => 'Login successful! Redirecting…',
    'redirect' => 'profile-dashboard.php'
]);
exit;
