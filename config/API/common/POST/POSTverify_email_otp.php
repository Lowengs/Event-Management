<?php
/**
 * Common API: Verify OTP and commit Email Change
 * Endpoint: /config/API/endpoints/index.php?action=verify_email_change_otp
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../audit.php';

header('Content-Type: application/json');

$isOsa   = !empty($_SESSION['osa_id']);
$isOrg   = !empty($_SESSION['org_id']);
$isAdmin = !empty($_SESSION['admin_id']);

if (!$isOsa && !$isOrg && !$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$otpInput = trim($_POST['otp_code'] ?? $_POST['otp'] ?? '');
if (empty($otpInput)) {
    echo json_encode(['success' => false, 'message' => 'Verification code is required']);
    exit;
}

$pending = $_SESSION['email_change_otp'] ?? null;
if (!$pending || empty($pending['code']) || empty($pending['new_email'])) {
    echo json_encode(['success' => false, 'message' => 'No pending email change request found. Please request a new code.']);
    exit;
}

if (time() > ($pending['expires'] ?? 0)) {
    unset($_SESSION['email_change_otp']);
    echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new one.']);
    exit;
}

if ((string)$pending['code'] !== (string)$otpInput) {
    $_SESSION['email_change_otp']['attempts'] = ($_SESSION['email_change_otp']['attempts'] ?? 0) + 1;
    if ($_SESSION['email_change_otp']['attempts'] >= 5) {
        unset($_SESSION['email_change_otp']);
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please request a new code.']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Incorrect verification code. Please try again.']);
    exit;
}

$newEmail = $pending['new_email'];
$updated = false;

if ($isOsa) {
    $osaId = (int)$_SESSION['osa_id'];
    $stmt = $conn->prepare("UPDATE osa SET Email = ? WHERE OsaId = ?");
    if ($stmt) {
        $stmt->bind_param("si", $newEmail, $osaId);
        $updated = $stmt->execute();
        $stmt->close();
        if ($updated) {
            $_SESSION['osa_email'] = $newEmail;
            logAudit($conn, 'Change Email', 'osa', $osaId, 'success', ['new_email' => $newEmail]);
        }
    }
} elseif ($isOrg) {
    $orgId = (int)$_SESSION['org_id'];
    $stmt = $conn->prepare("UPDATE organization SET email = ? WHERE OrgId = ?");
    if ($stmt) {
        $stmt->bind_param("si", $newEmail, $orgId);
        $updated = $stmt->execute();
        $stmt->close();
        if ($updated) {
            $_SESSION['org_email'] = $newEmail;
            logAudit($conn, 'Change Email', 'organization', $orgId, 'success', ['new_email' => $newEmail]);
        }
    }
} elseif ($isAdmin) {
    $adminId = (int)$_SESSION['admin_id'];
    $stmt = $conn->prepare("UPDATE admin SET Email = ? WHERE AdminId = ?");
    if ($stmt) {
        $stmt->bind_param("si", $newEmail, $adminId);
        $updated = $stmt->execute();
        $stmt->close();
        if ($updated) {
            $_SESSION['admin_email'] = $newEmail;
            logAudit($conn, 'Change Email', 'admin', $adminId, 'success', ['new_email' => $newEmail]);
        }
    }
}

if ($updated) {
    unset($_SESSION['email_change_otp']);
    echo json_encode([
        'success'   => true,
        'message'   => 'Email address has been successfully verified and updated!',
        'new_email' => $newEmail
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Database error while updating email address: ' . $conn->error
    ]);
}
