<?php
/**
 * Organization API: GET Certificates
 * Uses Stored Procedure: sp_GetOrgCertificates & sp_GetOrgEvents
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
$events = [];

try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgEvents(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $events[] = $row;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed
}

// Fall back to the events table if sp_GetOrgEvents returned empty
if (empty($events)) {
    $stmt = $conn->prepare("SELECT * FROM event WHERE OrgId = ? ORDER BY EventDateTime DESC");
    if ($stmt) {
        $stmt->bind_param('i', $orgId);
        if ($stmt->execute() && ($res = $stmt->get_result())) {
            while ($row = $res->fetch_assoc()) $events[] = $row;
        }
        $stmt->close();
    }
}

echo json_encode(['success' => true, 'events' => $events]);
if ($isDirectApiCall) exit;
?>

