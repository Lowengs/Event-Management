<?php
/**
 * OSA API: Forgot / Reset Password Handler
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../mailer.php';

header('Content-Type: application/json');

$action = trim($_POST['action'] ?? 'send_code');
$email  = trim($_POST['email'] ?? '');

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email address is required.']);
    exit;
}

if ($action === 'send_code') {
    // 1. Search OSA table
    $stmt = $conn->prepare("SELECT OsaId, Name, Email, 'osa' AS role FROM osa WHERE LOWER(Email) = LOWER(?) LIMIT 1");
    if (!$stmt) {
        $stmt = $conn->prepare("SELECT OsaId, Name, Email, 'osa' AS role FROM users WHERE LOWER(Email) = LOWER(?) AND Role = 'osa' LIMIT 1");
    }
    $user = null;
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }

    // 2. Search Organization table if not found in OSA
    if (!$user) {
        $orgStmt = $conn->prepare("SELECT OrgId AS OsaId, OrgName AS Name, email AS Email, 'org' AS role FROM organization WHERE LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?) LIMIT 1");
        if ($orgStmt) {
            $orgStmt->bind_param("ss", $email, $email);
            $orgStmt->execute();
            $oRes = $orgStmt->get_result();
            $user = $oRes ? $oRes->fetch_assoc() : null;
            $orgStmt->close();
        }
    }

    if ($user && !empty($user['Email'])) {
        $targetEmail = $user['Email'];
        $code = str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['osa_reset_code']  = $code;
        $_SESSION['osa_reset_time']  = time();
        $_SESSION['osa_reset_email'] = strtolower($targetEmail);
        $_SESSION['osa_reset_role']  = $user['role'];
        $_SESSION['osa_reset_id']    = $user['OsaId'];

        $recipientName = $user['Name'] ?? 'Officer';
        $subject = ($user['role'] === 'org') ? 'Your Organization Account Password Reset OTP' : 'Your OSA Account Password Reset OTP';
        $mailResult = sendOtpEmail($targetEmail, $recipientName, $code, $subject);

        if ($mailResult['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'A 6-digit verification code has been sent to ' . $targetEmail . '. Please check your inbox.',
                'code'    => $code
            ]);
        } else {
            echo json_encode([
                'success'            => true,
                'message'            => 'Reset code generated for ' . $targetEmail . '.',
                'smtp_notice'        => $mailResult['message'],
                'needs_app_password' => $mailResult['needs_app_password'] ?? false,
                'code'               => $code
            ]);
        }
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'No account found with that email address or username.']);
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

    $savedCode  = $_SESSION['osa_reset_code'] ?? '';
    $savedEmail = $_SESSION['osa_reset_email'] ?? '';
    $resetRole  = $_SESSION['osa_reset_role'] ?? 'osa';
    $resetId    = (int)($_SESSION['osa_reset_id'] ?? 0);

    if ($pin !== $savedCode || (strtolower($email) !== strtolower($savedEmail) && !empty($email) && strpos($savedEmail, strtolower($email)) === false)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired 6-digit verification code.']);
        exit;
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);

    if ($resetRole === 'org' && $resetId > 0) {
        $uStmt = $conn->prepare("UPDATE organization SET PasswordHash = ?, password_hash = ? WHERE OrgId = ?");
        if ($uStmt) {
            $uStmt->bind_param("ssi", $hash, $hash, $resetId);
            $uStmt->execute();
            $uStmt->close();
        }
    } else {
        $uStmt = $conn->prepare("UPDATE osa SET PasswordHash = ? WHERE LOWER(Email) = LOWER(?)");
        if ($uStmt) {
            $uStmt->bind_param("ss", $hash, $savedEmail);
            $uStmt->execute();
            $uStmt->close();
        }
    }

    unset($_SESSION['osa_reset_code'], $_SESSION['osa_reset_email'], $_SESSION['osa_reset_role'], $_SESSION['osa_reset_id']);

    echo json_encode(['success' => true, 'message' => 'Password reset successfully! You can now log in.']);
    exit;
}
