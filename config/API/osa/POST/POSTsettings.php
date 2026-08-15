<?php
/**
 * OSA API: POST Settings Update
 * Endpoint: /config/API/endpoints/update_osa_settings.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    if ($isDirectApiCall) exit;
    return;
}

$osaId  = (int)$_SESSION['osa_id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'update_profile') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        if (empty($name) || empty($email)) {
            throw new Exception('Name and email are required.');
        }
        $stmt = $conn->prepare('UPDATE osa SET Name = ?, Email = ? WHERE OsaId = ?');
        $stmt->bind_param('ssi', $name, $email, $osaId);
        if ($stmt->execute()) {
            $_SESSION['osa_name']  = $name;
            $_SESSION['osa_email'] = $email;
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Update Settings', 'osa', $osaId, 'success', ['name' => $name, 'email' => $email]);
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            throw new Exception('Failed to update profile.');
        }
        $stmt->close();
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (empty($current) || empty($new) || empty($confirm)) {
            throw new Exception('All password fields are required.');
        }
        if (strlen($new) < 8) {
            throw new Exception('New password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            throw new Exception('New passwords do not match.');
        }
        $verify = $conn->prepare('SELECT PasswordHash FROM osa WHERE OsaId = ?');
        $verify->bind_param('i', $osaId); $verify->execute();
        $row = $verify->get_result()->fetch_assoc() ?: []; $verify->close();
        $currentHash = $row['PasswordHash'] ?? '';
        if (!password_verify($current, $currentHash) && $current !== $currentHash) throw new Exception('Current password is incorrect.');
        $newHash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE osa SET PasswordHash = ? WHERE OsaId = ?');
        $stmt->bind_param('si', $newHash, $osaId);
        if ($stmt->execute()) {
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Change Password', 'osa', $osaId, 'success', ['target' => 'self']);
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            throw new Exception('Failed to update password.');
        }
        $stmt->close();
    } else {
        throw new Exception('Invalid settings action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if ($isDirectApiCall) exit;

