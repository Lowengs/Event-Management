<?php
/**
 * Organization API: GET Officers
 * Endpoint: /config/API/endpoints/index.php?action=GETofficers
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
$officers = [];

try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgOfficers(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $officers[] = $row;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed to fallback
}

if (empty($officers)) {
    $q = $conn->query("
        SELECT UserId, first_name, last_name, Email, student_id, course, year_level, section, Position AS officer_role, profile_photo, is_officer
        FROM `user`
        WHERE OrgId = $orgId AND (is_officer = 1 OR (Position IS NOT NULL AND Position != ''))
        ORDER BY first_name ASC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $officers[] = $r;
        }
    }
}

echo json_encode(['success' => true, 'data' => $officers]);
if ($isDirectApiCall) exit;

