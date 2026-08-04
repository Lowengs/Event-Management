<?php
/**
 * Organization API: GET Attendance Events
 * Endpoint: /config/API/endpoints/index.php?action=GETattendance
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
    // proceed to fallback
}

if (empty($events)) {
    $q = $conn->query("SELECT EventId, EventName, EventDateTime, EndDateTime, EventStatus, EventMode FROM event WHERE OrgId = $orgId ORDER BY EventDateTime DESC LIMIT 50");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $events[] = $r;
        }
    }
}

echo json_encode(['success' => true, 'data' => $events]);
if ($isDirectApiCall) exit;

