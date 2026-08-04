<?php
/**
 * Common / Student API: GET Organization Details
 * Endpoint: /config/API/endpoints/index.php?action=get_organization_detail
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}


if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    
}

$orgId = (int)($_GET['org_id'] ?? $_GET['OrgId'] ?? 0);

if (!$orgId) {
    echo json_encode(['success' => false, 'message' => 'Organization ID is required.']);
if ($isDirectApiCall) exit;
    exit;
}

$org = null;

try {
    if ($stmt = $conn->prepare("CALL sp_GetOrganizationDetails(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $org = $res->fetch_assoc();
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed to fallback
}

if (!$org) {
    $stmt2 = $conn->prepare("
        SELECT o.*,
            (SELECT COUNT(*) FROM `user` u WHERE u.OrgId = o.OrgId) AS member_count,
            (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId AND LOWER(e.EventStatus) = 'scheduled') AS event_count
        FROM organization o
        WHERE o.OrgId = ?
        LIMIT 1
    ");
    if ($stmt2) {
        $stmt2->bind_param("i", $orgId);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2) {
            $org = $res2->fetch_assoc();
        }
        $stmt2->close();
    }
}

if ($org) {
    // Keep modal data accurate even when the stored procedure returned a
    // registration total rather than the organization's assigned users.
    $memberStmt = $conn->prepare('SELECT COUNT(*) total FROM `user` WHERE OrgId = ?');
    $memberStmt->bind_param('i', $orgId); $memberStmt->execute();
    $org['member_count'] = (int)($memberStmt->get_result()->fetch_assoc()['total'] ?? 0);
    echo json_encode(['success' => true, 'data' => $org]);
if ($isDirectApiCall) exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Organization not found.']);
if ($isDirectApiCall) exit;
}

