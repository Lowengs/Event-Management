<?php
/**
 * osa_login.php — Authenticates OSA staff with 3-attempt lockout.
 */
session_start();
require_once '../db.php';
require_once '../audit.php';
require_once '../rate_limit.php';
rateLimit('osa_login', 10, 60);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password']      ?? '';
$remember = !empty($_POST['remember']);

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

// ── 3-Attempt Lockout ────────────────────────────────────────────
$lockKey     = 'osa_fails_'      . md5($email);
$lockTimeKey = 'osa_lock_until_' . md5($email);
$maxAttempts = 3;
$lockSeconds = 300; // 5 min

if (isset($_SESSION[$lockTimeKey]) && time() < $_SESSION[$lockTimeKey]) {
    $remaining = $_SESSION[$lockTimeKey] - time();
    $mins = ceil($remaining / 60);
    logAudit($conn, 'OSA Login Blocked', 'osa', null, 'failed', ['email' => $email, 'reason' => 'Locked out', 'remaining' => $remaining]);
    echo json_encode(['success' => false, 'locked' => true,
        'message'   => "Too many failed attempts. Please wait {$mins} minute(s).",
        'remaining' => $remaining]);
    exit;
}

// ── Lookup ────────────────────────────────────────────────────────
$stmt = $conn->prepare("SELECT OsaId, Name, Email, PasswordHash FROM `osa` WHERE Email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    logAudit($conn, 'OSA Login', 'osa', null, 'failed', ['email' => $email, 'reason' => 'Account not found']);
    echo json_encode(['success' => false, 'message' => 'No OSA account found with that email.']);
    exit;
}

$osa   = $result->fetch_assoc();
$valid = password_verify($password, $osa['PasswordHash']);

if (!$valid) {
    $_SESSION[$lockKey] = ($_SESSION[$lockKey] ?? 0) + 1;
    $attempts     = $_SESSION[$lockKey];
    $attemptsLeft = $maxAttempts - $attempts;

    logAudit($conn, 'OSA Login', 'osa', (int)$osa['OsaId'], 'failed', ['email' => $email, 'reason' => 'Wrong password', 'attempt' => $attempts]);

    if ($attempts >= $maxAttempts) {
        $_SESSION[$lockTimeKey] = time() + $lockSeconds;
        $_SESSION[$lockKey]     = 0;
        $mins = ceil($lockSeconds / 60);
        echo json_encode(['success' => false, 'locked' => true,
            'message'   => "Account locked after {$maxAttempts} failed attempts. Please wait {$mins} minutes.",
            'remaining' => $lockSeconds]);
    } else {
        echo json_encode(['success' => false,
            'message'      => "Incorrect password. {$attemptsLeft} attempt(s) remaining before lockout.",
            'attempts_left' => $attemptsLeft]);
    }
    exit;
}

// ── Success ───────────────────────────────────────────────────────
unset($_SESSION[$lockKey], $_SESSION[$lockTimeKey]);

$_SESSION['osa_id']    = $osa['OsaId'];
$_SESSION['osa_name']  = $osa['Name'];
$_SESSION['osa_email'] = $osa['Email'];
$_SESSION['role']      = 'osa';

// Remember Me — store email in a cookie for 30 days
if ($remember) {
    setcookie('osa_remember_email', $email, time() + (86400 * 30), '/', '', false, true);
} else {
    setcookie('osa_remember_email', '', time() - 3600, '/');
}

logAudit($conn, 'OSA Login', 'osa', (int)$osa['OsaId'], 'success', ['email' => $email]);
$conn->close();

echo json_encode(['success' => true,
    'message'  => 'Welcome, ' . htmlspecialchars($osa['Name']) . '!',
    'redirect' => 'dashboard_final.php']);
exit;
