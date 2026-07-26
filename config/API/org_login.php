<?php
/**
 * org_login.php — Authenticates organization account with 3-attempt lockout.
 */
session_start();
require_once '../db.php';
require_once '../audit.php';
require_once '../rate_limit.php';
rateLimit('org_login', 10, 60);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$orgId    = (int)($_POST['org_id']  ?? 0);
$username = trim($_POST['username'] ?? '');
$password = $_POST['password']      ?? '';
$remember = !empty($_POST['remember']);

if ($orgId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please select your organization.']);
    exit;
}
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

// ── 3-Attempt Lockout (per org + username) ────────────────────────
$lockKey     = 'org_fails_'      . md5($orgId . '_' . $username);
$lockTimeKey = 'org_lock_until_' . md5($orgId . '_' . $username);
$maxAttempts = 3;
$lockSeconds = 300;

if (isset($_SESSION[$lockTimeKey]) && time() < $_SESSION[$lockTimeKey]) {
    $remaining = $_SESSION[$lockTimeKey] - time();
    $mins = ceil($remaining / 60);
    logAudit($conn, 'Org Login Blocked', 'organization', $orgId, 'failed', ['username' => $username, 'remaining' => $remaining]);
    echo json_encode(['success' => false, 'locked' => true,
        'message'   => "Too many failed attempts. Please wait {$mins} minute(s).",
        'remaining' => $remaining]);
    exit;
}

// ── Lookup ────────────────────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT OrgId, OrgName, Username, PasswordHash
     FROM `organization`
     WHERE OrgId = ? AND Username = ? AND LOWER(Status) = 'active'
     LIMIT 1"
);
$stmt->bind_param('is', $orgId, $username);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    logAudit($conn, 'Org Login', 'organization', $orgId, 'failed', ['username' => $username, 'reason' => 'Not found / inactive']);
    echo json_encode(['success' => false, 'message' => 'Invalid organization, username, or account is inactive.']);
    exit;
}

$org   = $result->fetch_assoc();
$valid = password_verify($password, $org['PasswordHash']);

if (!$valid) {
    $_SESSION[$lockKey] = ($_SESSION[$lockKey] ?? 0) + 1;
    $attempts     = $_SESSION[$lockKey];
    $attemptsLeft = $maxAttempts - $attempts;

    logAudit($conn, 'Org Login', 'organization', (int)$org['OrgId'], 'failed', ['username' => $username, 'reason' => 'Wrong password', 'attempt' => $attempts]);

    if ($attempts >= $maxAttempts) {
        $_SESSION[$lockTimeKey] = time() + $lockSeconds;
        $_SESSION[$lockKey]     = 0;
        $mins = ceil($lockSeconds / 60);
        echo json_encode(['success' => false, 'locked' => true,
            'message'   => "Account locked after {$maxAttempts} failed attempts. Please wait {$mins} minutes.",
            'remaining' => $lockSeconds]);
    } else {
        echo json_encode(['success' => false,
            'message'       => "Incorrect password. {$attemptsLeft} attempt(s) remaining before lockout.",
            'attempts_left' => $attemptsLeft]);
    }
    exit;
}

// ── Success ───────────────────────────────────────────────────────
unset($_SESSION[$lockKey], $_SESSION[$lockTimeKey]);

$_SESSION['org_id']   = $org['OrgId'];
$_SESSION['org_name'] = $org['OrgName'];
$_SESSION['role']     = 'organization';

// Remember Me — store org_id + username in cookies for 30 days
if ($remember) {
    $expire = time() + (86400 * 30);
    setcookie('org_remember_id',       (string)$orgId, $expire, '/', '', false, true);
    setcookie('org_remember_username', $username,      $expire, '/', '', false, true);
} else {
    setcookie('org_remember_id',       '', time() - 3600, '/');
    setcookie('org_remember_username', '', time() - 3600, '/');
}

logAudit($conn, 'Org Login', 'organization', (int)$org['OrgId'], 'success', ['org_name' => $org['OrgName']]);
$conn->close();

echo json_encode(['success' => true,
    'message'  => 'Welcome, ' . htmlspecialchars($org['OrgName']) . '!',
    'redirect' => '../organization/dashboard_org.php']);
exit;
