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
    $detailsJson = json_encode(['Manually Approved by Organization Officer']);
    if (!$updated) {
        $scoreVal = ($newStatus === 'active' || $newVStatus === 'approved') ? 100 : 0;
        if ($stmt = $conn->prepare("UPDATE `user` SET status = ?, verification_status = ?, ai_verification_score = ?, ai_verification_details = ? WHERE UserId = ? AND OrgId = ?")) {
            $stmt->bind_param("ssisii", $newStatus, $newVStatus, $scoreVal, $detailsJson, $userId, $orgId);
            $updated = $stmt->execute();
            $stmt->close();
        }
    } else {
        if ($newStatus === 'active' || $newVStatus === 'approved') {
            $uStmt = $conn->prepare("UPDATE `user` SET ai_verification_score = 100, ai_verification_details = ? WHERE UserId = ?");
            if ($uStmt) {
                $uStmt->bind_param("si", $detailsJson, $userId);
                $uStmt->execute();
                $uStmt->close();
            }
        }
    }

    if ($updated) {
        if ($newStatus === 'active' || $newVStatus === 'approved') {
            $qStu = $conn->query("SELECT Email, first_name, last_name FROM `user` WHERE UserId = $userId LIMIT 1");
            if ($qStu && $stuRow = $qStu->fetch_assoc()) {
                if (!empty($stuRow['Email'])) {
                    require_once __DIR__ . '/../../../mailer.php';
                    $orgNameQ = $conn->query("SELECT OrgName FROM organization WHERE OrgId = $orgId LIMIT 1");
                    $orgNameStr = ($orgNameQ && $orow = $orgNameQ->fetch_assoc()) ? $orow['OrgName'] : 'Student Organization';
                    @sendRegistrationApprovedEmail($stuRow['Email'], trim($stuRow['first_name'] . ' ' . $stuRow['last_name']), $orgNameStr . ' Officers');
                }
            }
        }

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
