<?php
/**
 * Organization API: Update Officer Role
 * Endpoint: /config/API/endpoints/index.php?action=PUTofficer_role
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$userId = (int)($_POST['UserId'] ?? $_POST['user_id'] ?? 0);
$role   = trim($_POST['officer_role'] ?? $_POST['role'] ?? '');

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

try {
    $isOfficer = $role === '' ? 0 : 1;
    $position = $role === '' ? null : $role;
    $stmt = $conn->prepare("UPDATE `user` SET OrgId = ?, officer_role = ?, Position = ?, is_officer = ? WHERE UserId = ?");
    $stmt->bind_param("issii", $orgId, $role, $position, $isOfficer, $userId);
    if (!$stmt->execute()) {
        $error = $stmt->error ?: 'Officer record could not be updated';
        $stmt->close();
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    $stmt->close();
    if (file_exists(__DIR__ . '/../../../audit.php')) {
        require_once __DIR__ . '/../../../audit.php';
        $actionMsg = empty($role) ? 'Remove Officer Role' : 'Update Officer Role';
        logAudit($conn, $actionMsg, 'organization', $orgId, 'success', ['UserId' => $userId, 'Role' => $role]);
    }
    echo json_encode(['success' => true, 'message' => empty($role) ? 'Officer role removed' : 'Officer role assigned successfully']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
