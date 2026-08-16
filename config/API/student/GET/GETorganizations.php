<?php
/**
 * Student API: GET Organizations
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$orgs = [];
$result = $conn->query("
    SELECT o.*,
           (SELECT COUNT(*) FROM `user` u WHERE u.OrgId = o.OrgId) AS member_count,
           (SELECT COUNT(*)
              FROM event e
             WHERE e.OrgId = o.OrgId
               AND LOWER(COALESCE(e.EventStatus, 'scheduled')) NOT IN ('cancelled', 'archived')) AS event_count
      FROM organization o
     WHERE LOWER(COALESCE(o.Status, 'active')) = 'active'
     ORDER BY o.OrgName ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orgs[] = $row;
    }
}

echo json_encode(['success' => true, 'message' => 'Organizations retrieved', 'data' => $orgs]);
if ($isDirectApiCall) exit;
?>
