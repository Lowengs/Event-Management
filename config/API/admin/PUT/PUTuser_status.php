<?php
/**
 * Admin API: PUT User Status
 * Endpoint: /config/API/endpoints/index.php?action=PUTuser_status
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Admin login required']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId = (int)($input['user_id'] ?? 0);
$userTab = trim($input['user_tab'] ?? 'students');
$status = trim($input['status']  ?? 'active');

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    $targets = [
        'students' => ['user', 'UserId'],
        'organizations' => ['organization', 'OrgId'],
        'osa' => ['osa', 'OsaId'],
        'admins' => ['admin', 'AdminId'],
    ];
    if (!isset($targets[$userTab])) $userTab = 'students';
    [$table, $idColumn] = $targets[$userTab];
    $stmt = $conn->prepare("UPDATE `$table` SET Status = ? WHERE `$idColumn` = ?");
    if (!$stmt) throw new RuntimeException($conn->error);
    $stmt->bind_param("si", $status, $userId);
    if ($stmt->execute()) {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
        echo json_encode(['success' => true, 'message' => 'User status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
