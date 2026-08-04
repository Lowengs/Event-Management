<?php
/**
 * OSA API: Forgot / Reset Password Handler
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

$action = trim($_POST['action'] ?? 'send_code');
$email  = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email address is required.']);
    exit;
}

if ($action === 'send_code') {
    $stmt = $conn->prepare("SELECT OsaId, Name FROM osa WHERE Email = ? LIMIT 1");
    if (!$stmt) {
        $stmt = $conn->prepare("SELECT OsaId, Name FROM users WHERE Email = ? AND Role = 'osa' LIMIT 1");
    }
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($user) {
            $code = str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['osa_reset_code']  = $code;
            $_SESSION['osa_reset_email'] = $email;

            echo json_encode([
                'success' => true,
                'message' => 'Reset code sent! (Dev verification code: ' . $code . ')',
                'code'    => $code
            ]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'message' => 'No OSA account found with that email.']);
    exit;
}

if ($action === 'reset_password') {
    $pin     = trim($_POST['pin'] ?? '');
    $newPass = trim($_POST['new_password'] ?? '');

    if (empty($pin) || empty($newPass)) {
        echo json_encode(['success' => false, 'message' => 'Code and new password are required.']);
        exit;
    }

    if (strlen($newPass) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
        exit;
    }

    $savedCode = $_SESSION['osa_reset_code'] ?? '';
    $savedEmail = $_SESSION['osa_reset_email'] ?? '';

    if ($pin !== $savedCode || strtolower($email) !== strtolower($savedEmail)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired 6-digit verification code.']);
        exit;
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);

    $updated = false;
    $uStmt = $conn->prepare("UPDATE osa SET PasswordHash = ? WHERE Email = ?");
    if ($uStmt) {
        $uStmt->bind_param("ss", $hash, $email);
        $uStmt->execute();
        if ($uStmt->affected_rows > 0) $updated = true;
        $uStmt->close();
    }

    if (!$updated) {
        $uStmt2 = $conn->prepare("UPDATE users SET PasswordHash = ? WHERE Email = ?");
        if ($uStmt2) {
            $uStmt2->bind_param("ss", $hash, $email);
            $uStmt2->execute();
            if ($uStmt2->affected_rows > 0) $updated = true;
            $uStmt2->close();
        }
    }

    unset($_SESSION['osa_reset_code'], $_SESSION['osa_reset_email']);

    echo json_encode(['success' => true, 'message' => 'Password reset successfully! You can now log in.']);
    exit;
}
