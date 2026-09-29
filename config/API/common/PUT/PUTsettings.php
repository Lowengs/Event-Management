<?php
/**
 * Common API: PUT Settings
 * Endpoint: /config/API/endpoints/index.php?action=PUTsettings
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$key   = trim($input['setting_key']   ?? '');
$val   = trim($input['setting_value'] ?? '');

if (empty($key)) {
    echo json_encode(['success' => false, 'message' => 'Setting key required']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("sss", $key, $val, $val);
    if ($stmt->execute()) {
        $stmt->close();
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            $actorType = !empty($_SESSION['admin_logged_in']) ? 'admin' : (!empty($_SESSION['osa_id']) ? 'osa' : (!empty($_SESSION['org_id']) ? 'organization' : 'student'));
            $actorId   = (int)($_SESSION['admin_id'] ?? $_SESSION['osa_id'] ?? $_SESSION['org_id'] ?? $_SESSION['student_id'] ?? 0);
            $safeVal = (strpos(strtolower($key), 'password') !== false || strpos(strtolower($key), 'secret') !== false) ? '********' : $val;
            logAudit($conn, 'Update System Setting', $actorType, $actorId ?: null, 'success', [
                'setting_key'   => $key,
                'setting_value' => $safeVal
            ]);
        }
        echo json_encode(['success' => true, 'message' => 'Setting updated']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
