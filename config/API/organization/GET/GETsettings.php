<?php
/**
 * Organization API: GET Settings
 * Endpoint: /config/API/endpoints/index.php?action=get_org_settings
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
if ($isDirectApiCall) exit;
    return;
}

$orgId = (int)$_SESSION['org_id'];
$orgData = null;

try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgSettings(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            $orgData = $res->fetch_assoc();
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {
    // proceed to fallback
}

if (!$orgData) {
    $stmt2 = $conn->prepare("SELECT OrgId, OrgName, Description, OrgPicture, OrgBanner, username AS Username, email AS Email, Status, Adviser, DateRegistered FROM organization WHERE OrgId = ? LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("i", $orgId);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2) {
            $orgData = $res2->fetch_assoc();
        }
        $stmt2->close();
    }
}

if (!empty($orgData)) {
    if (!empty($orgData['OrgName'])) $_SESSION['org_name'] = $orgData['OrgName'];
    if (!empty($orgData['OrgPicture'])) $_SESSION['org_logo'] = $orgData['OrgPicture'];
}

echo json_encode(['success' => true, 'data' => $orgData]);
if ($isDirectApiCall) exit;
?>

