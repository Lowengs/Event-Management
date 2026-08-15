<?php
/**
 * Admin API: Reset User Password
 * Endpoint: /config/API/endpoints/index.php?action=reset_user_password
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId   = (int)($input['user_id'] ?? 0);
$userTab  = trim($input['user_tab'] ?? 'students');
$password = trim($input['password'] ?? '');

if ($userId <= 0) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    if ($isDirectApiCall) exit;
    return;
}

if (empty($password) || strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters.']);
    if ($isDirectApiCall) exit;
    return;
}

try {
    $newHash = password_hash($password, PASSWORD_BCRYPT);
    $adminId = (int)($_SESSION['admin_id'] ?? 1);
    $targetName = '';

    switch ($userTab) {
        case 'osa':
            $stmt = $conn->prepare("UPDATE `osa` SET PasswordHash = ? WHERE OsaId = ?");
            $stmt->bind_param("si", $newHash, $userId);
            $stmt->execute();
            $stmt->close();
            
            $q = $conn->query("SELECT Name FROM `osa` WHERE OsaId = $userId LIMIT 1");
            if ($q && $r = $q->fetch_assoc()) $targetName = $r['Name'];
            break;

        case 'organizations':
            $stmt = $conn->prepare("UPDATE `organization` SET PasswordHash = ? WHERE OrgId = ?");
            $stmt->bind_param("si", $newHash, $userId);
            $stmt->execute();
            $stmt->close();

            // Also sync officer user password for this org
            $stmtOff = $conn->prepare("UPDATE `user` SET PasswordHash = ? WHERE OrgId = ?");
            if ($stmtOff) {
                $stmtOff->bind_param("si", $newHash, $userId);
                $stmtOff->execute();
                $stmtOff->close();
            }

            $q = $conn->query("SELECT OrgName FROM `organization` WHERE OrgId = $userId LIMIT 1");
            if ($q && $r = $q->fetch_assoc()) $targetName = $r['OrgName'];
            break;

        case 'admins':
            $stmt = $conn->prepare("UPDATE `admin` SET PasswordHash = ? WHERE AdminId = ?");
            $stmt->bind_param("si", $newHash, $userId);
            $stmt->execute();
            $stmt->close();

            $q = $conn->query("SELECT Name FROM `admin` WHERE AdminId = $userId LIMIT 1");
            if ($q && $r = $q->fetch_assoc()) $targetName = $r['Name'];
            break;

        case 'students':
        default:
            $stmt = $conn->prepare("UPDATE `user` SET PasswordHash = ? WHERE UserId = ?");
            $stmt->bind_param("si", $newHash, $userId);
            $stmt->execute();
            $stmt->close();

            $q = $conn->query("SELECT CONCAT(first_name, ' ', last_name) AS n FROM `user` WHERE UserId = $userId LIMIT 1");
            if ($q && $r = $q->fetch_assoc()) $targetName = $r['n'];
            break;
    }

    logAudit($conn, 'Reset User Password', 'admin', $adminId, 'success', [
        'target_tab'  => $userTab,
        'target_id'   => $userId,
        'target_name' => $targetName
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Password has been reset successfully.'
    ]);
    if ($isDirectApiCall) exit;

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to reset password: ' . $e->getMessage()
    ]);
    if ($isDirectApiCall) exit;
}
?>
