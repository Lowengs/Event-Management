<?php
/**
 * Admin API: POST Change Password
 * Endpoint: /config/API/endpoints/index.php?action=change_admin_password
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../audit.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json; charset=utf-8');
}

if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Admin login required']);
    if ($isDirectApiCall) exit;
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    if ($isDirectApiCall) exit;
    return;
}

$adminId = (int)($_SESSION['admin_id'] ?? 0);
$currentPassword = trim($_POST['current_password'] ?? '');
$newPassword     = trim($_POST['new_password'] ?? '');
$confirmPassword = trim($_POST['confirm_password'] ?? '');

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
    if ($isDirectApiCall) exit;
    return;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
    if ($isDirectApiCall) exit;
    return;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
    if ($isDirectApiCall) exit;
    return;
}

try {
    // Find admin by adminId or session email
    $admin = null;
    if ($adminId > 0) {
        $stmt = $conn->prepare("SELECT AdminId, Name, Email, PasswordHash FROM `admin` WHERE AdminId = ? LIMIT 1");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$admin && !empty($_SESSION['admin_email'])) {
        $stmt = $conn->prepare("SELECT AdminId, Name, Email, PasswordHash FROM `admin` WHERE LOWER(Email) = LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $_SESSION['admin_email']);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$admin) {
        $q = $conn->query("SELECT AdminId, Name, Email, PasswordHash FROM `admin` ORDER BY AdminId ASC LIMIT 1");
        if ($q) $admin = $q->fetch_assoc();
    }

    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Administrator account not found.']);
        if ($isDirectApiCall) exit;
        return;
    }

    $adminId = (int)$admin['AdminId'];
    $hash = $admin['PasswordHash'] ?? '';

    // Verify current password
    $isValid = false;
    if (!empty($hash)) {
        if (password_verify($currentPassword, $hash) || $currentPassword === $hash) {
            $isValid = true;
        }
    }

    if (!$isValid) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        if ($isDirectApiCall) exit;
        return;
    }

    // Update with new bcrypt hash
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $upStmt = $conn->prepare("UPDATE `admin` SET PasswordHash = ? WHERE AdminId = ?");
    $upStmt->bind_param("si", $newHash, $adminId);
    $upStmt->execute();
    $upStmt->close();

    logAudit($conn, 'Change Password', 'admin', $adminId, 'success', [
        'email' => $admin['Email']
    ], $admin['Name']);

    echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
    if ($isDirectApiCall) exit;

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
?>
