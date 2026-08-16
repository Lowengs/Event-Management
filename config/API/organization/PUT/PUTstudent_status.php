<?php
/**
 * Organization API: PUT Student Status (Approve / Decline / Reject)
 * Endpoint: /config/API/endpoints/index.php?action=PUTstudent_status
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId = (int)($input['user_id'] ?? $input['UserId'] ?? $input['id'] ?? 0);
$action = trim(strtolower($input['action'] ?? $input['status'] ?? ''));

if (!$userId || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'User ID and action are required']);
    exit;
}

$newStatus  = ($action === 'approve' || $action === 'active') ? 'active' : 'rejected';
$newVStatus = ($action === 'approve' || $action === 'active') ? 'approved' : 'rejected';
$orgId = (int)$_SESSION['org_id'];

try {
    $updated = false;
    // Attempt via stored procedure if available
    if ($stmt = $conn->prepare("CALL sp_UpdateOrgStudentStatus(?, ?, ?, ?)")) {
        $stmt->bind_param("iiss", $userId, $orgId, $newStatus, $newVStatus);
        if ($stmt->execute()) {
            $updated = true;
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }

    // Direct query fallback
    if (!$updated) {
        if ($stmt = $conn->prepare("UPDATE `user` SET status = ?, verification_status = ? WHERE UserId = ? AND OrgId = ?")) {
            $stmt->bind_param("ssii", $newStatus, $newVStatus, $userId, $orgId);
            $updated = $stmt->execute();
            $stmt->close();
        }
    }

    if ($updated) {
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Update Member Status', 'organization', $orgId, 'success', ['UserId' => $userId, 'Status' => $newStatus, 'VerificationStatus' => $newVStatus]);
        }
        echo json_encode(['success' => true, 'message' => 'Student status updated to ' . $newStatus]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update student status: ' . $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
