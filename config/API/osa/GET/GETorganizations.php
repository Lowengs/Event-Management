<?php
/**
 * OSA API: GET Organizations
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$allowPublicAccess = defined('ALLOW_PUBLIC_ORG_LIST') && ALLOW_PUBLIC_ORG_LIST;
if (!$allowPublicAccess && empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in']) && empty($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    if ($isDirectApiCall) exit;
    return;
}

$orgs = [];
try {
    $stmt = $conn->prepare("CALL sp_GetOSAOrganizations()");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orgs[] = $row;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {
    $orgs = [];
}

if (empty($orgs)) {
    $res = $conn->query("SELECT o.*,
        (SELECT COUNT(*) FROM `user` u WHERE u.OrgId = o.OrgId) AS members_count,
        (SELECT COUNT(*) FROM `user` u WHERE u.OrgId = o.OrgId AND u.officer_role IS NOT NULL AND u.officer_role != '') AS officers_count,
        (SELECT CONCAT(u.first_name,' ',u.last_name) FROM `user` u WHERE u.OrgId = o.OrgId AND LOWER(u.officer_role) LIKE '%president%' AND LOWER(u.officer_role) NOT LIKE '%vice%' LIMIT 1) AS president_name,
        (SELECT CONCAT(u.first_name,' ',u.last_name) FROM `user` u WHERE u.OrgId = o.OrgId AND LOWER(u.officer_role) LIKE '%vice%president%' LIMIT 1) AS vp_name,
        (SELECT GROUP_CONCAT(t.Type SEPARATOR ', ') FROM orgtype t WHERE t.OrgId = o.OrgId) AS org_type
        FROM organization o ORDER BY o.OrgName ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $orgs[] = $row;
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Organizations retrieved successfully',
    'data'    => $orgs,
    'orgs'    => $orgs
]);
if ($isDirectApiCall) exit;
?>
