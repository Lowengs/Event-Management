<?php
/**
 * Student API: GET Organizations
 * Uses Stored Procedure: sp_GetActiveOrganizations with fallback query
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}


if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    
}

$orgs = [];
try {
    if ($stmt = $conn->prepare("CALL sp_GetActiveOrganizations()")) {
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
} catch (Exception $e) {
    // proceed
}

// Use a direct query when the stored procedure is unavailable (or returns no
// rows), so the student organizations page does not depend on procedures being
// installed in the database.
if (empty($orgs)) {
    $result = $conn->query("
        SELECT o.*,
               (SELECT COUNT(*) FROM `user` u WHERE u.OrgId = o.OrgId) AS member_count,
               (SELECT COUNT(*)
                  FROM event e
                 WHERE e.OrgId = o.OrgId
                   AND LOWER(e.EventStatus) = 'scheduled') AS event_count
          FROM organization o
         WHERE LOWER(o.Status) = 'active' OR o.Status IS NULL
         ORDER BY o.OrgName ASC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orgs[] = $row;
        }
    }
}

// Always calculate live organization membership from assigned users. Stored
// procedure versions often return event-registration totals as "members".
if ($orgs) {
    $orgs = array_values(array_filter($orgs, function($o) {
        return strtolower(trim((string)($o['Status'] ?? 'active'))) === 'active';
    }));
    $memberCounts = [];
    $membersRes = $conn->query('SELECT OrgId, COUNT(*) total FROM `user` WHERE OrgId IS NOT NULL GROUP BY OrgId');
    while ($membersRes && ($member = $membersRes->fetch_assoc())) $memberCounts[(int)$member['OrgId']] = (int)$member['total'];
    foreach ($orgs as &$org) $org['member_count'] = $memberCounts[(int)($org['OrgId'] ?? 0)] ?? 0;
    unset($org);
}

echo json_encode(['success' => true, 'message' => 'Organizations retrieved', 'data' => $orgs]);
if ($isDirectApiCall) exit;

