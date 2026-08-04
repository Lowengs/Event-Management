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
        echo json_encode(['success' => true, 'message' => 'Setting updated']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
