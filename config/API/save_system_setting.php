<?php
/** save_system_setting.php — Saves a key/value pair to system_settings (OSA only) */
header('Content-Type: application/json');
session_start();
require_once '../db.php';
require_once '../rate_limit.php';
rateLimit('save_setting', 30, 60);


if (empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']); exit;
}

$key   = trim($_POST['key']   ?? '');
$value = trim($_POST['value'] ?? '');

if (!$key) {
    echo json_encode(['success' => false, 'message' => 'Setting key is required']); exit;
}

// Whitelist of allowed keys
$allowed = ['financial_report_required'];
if (!in_array($key, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Unknown setting key']); exit;
}

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    SettingKey VARCHAR(100) NOT NULL PRIMARY KEY,
    SettingValue VARCHAR(500) NOT NULL DEFAULT '',
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$stmt = $conn->prepare("INSERT INTO system_settings (SettingKey, SettingValue)
    VALUES (?, ?) ON DUPLICATE KEY UPDATE SettingValue = VALUES(SettingValue)");
$stmt->bind_param('ss', $key, $value);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Setting saved']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
$stmt->close();
