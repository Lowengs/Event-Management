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
                $st = strtolower($row['EventStatus'] ?? '');
                $dt = !empty($row['EventDateTime']) ? strtotime($row['EventDateTime']) : 0;
                if ($st === 'completed' || ($dt > 0 && $dt <= time())) {
                    $events[] = $row;
                }
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed
}

// Older installs do not include sp_GetOrgEvents. Fall back to the events
// table so certificate creation is not blocked by an empty event selector.
if (empty($events)) {
    $stmt = $conn->prepare("SELECT * FROM event WHERE OrgId = ? AND (LOWER(COALESCE(EventStatus, '')) = 'completed' OR EventDateTime <= NOW()) ORDER BY EventDateTime DESC");
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

