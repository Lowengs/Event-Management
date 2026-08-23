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
    $isOfficer = ($role === '' || $role === '0') ? 0 : 1;
    $position = ($role === '' || $role === '0') ? null : $role;
    $officerRole = ($role === '' || $role === '0') ? null : $role;

    if ($isOfficer === 1) {
        // Enforce: only one person per position/role in the organization
        $checkStmt = $conn->prepare("
            SELECT UserId, CONCAT(first_name, ' ', last_name) AS OfficerName 
            FROM `user` 
            WHERE OrgId = ? 
              AND UserId != ? 
              AND (is_officer = 1 OR (Position IS NOT NULL AND Position != ''))
              AND (LOWER(TRIM(officer_role)) = LOWER(TRIM(?)) OR LOWER(TRIM(Position)) = LOWER(TRIM(?)))
            LIMIT 1
        ");
        $checkStmt->bind_param("iiss", $orgId, $userId, $role, $role);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();
        if ($checkRes && $checkRes->num_rows > 0) {
            $holder = $checkRes->fetch_assoc();
            $holderName = trim($holder['OfficerName']) ?: 'another officer';
            $checkStmt->close();
            echo json_encode([
                'success' => false, 
                'message' => "The position '$role' is already assigned to $holderName. Only one person can hold this position."
            ]);
            exit;
        }
        $checkStmt->close();
    }

    if ($isOfficer === 0) {
        $stmt = $conn->prepare("UPDATE `user` SET officer_role = NULL, Position = NULL, is_officer = 0 WHERE UserId = ? AND OrgId = ?");
        $stmt->bind_param("ii", $userId, $orgId);
    } else {
        $stmt = $conn->prepare("UPDATE `user` SET OrgId = ?, officer_role = ?, Position = ?, is_officer = ? WHERE UserId = ?");
        $stmt->bind_param("issii", $orgId, $officerRole, $position, $isOfficer, $userId);
    }

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
    echo json_encode(['success' => true, 'message' => empty($role) ? 'Officer removed successfully' : 'Officer role assigned successfully']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
