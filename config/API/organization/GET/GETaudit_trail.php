<?php
/**
 * Organization API: GET Audit Trail
 * Uses Stored Procedure: sp_GetOrgAuditTrail
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
$orgName = $_SESSION['org_name'] ?? 'Organization';

$now  = date('Y-m-d');
$week = date('Y-m-d', strtotime('monday this week'));
$mon  = date('Y-m-01');

$auditTotal  = 0;
$auditToday  = 0;
$auditWeek   = 0;
$auditMonth  = 0;
$log_items   = [];

try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgAuditTrail(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (empty($row['ActorName'])) $row['ActorName'] = $orgName;
                $log_items[] = $row;
                $dt = date('Y-m-d', strtotime($row['Date'] ?? 'now'));
                $auditTotal++;
                if ($dt === $now) $auditToday++;
                if ($dt >= $week) $auditWeek++;
                if ($dt >= $mon) $auditMonth++;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed
}

if (empty($log_items)) {
    $result = $conn->query("
        SELECT * FROM auditlog
        WHERE LOWER(ActorType) IN ('org', 'organization')
          AND (ActorId = $orgId OR UserId = $orgId)
        ORDER BY Date DESC
        LIMIT 500
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (empty($row['ActorName'])) $row['ActorName'] = $orgName;
            $log_items[] = $row;
            $dt = date('Y-m-d', strtotime($row['Date'] ?? 'now'));
            $auditTotal++;
            if ($dt === $now) $auditToday++;
            if ($dt >= $week) $auditWeek++;
            if ($dt >= $mon) $auditMonth++;
        }
    }
}

echo json_encode([
        'success' => true,
        'org_name' => $orgName,
        'stats' => [
            'total' => $auditTotal,
            'today' => $auditToday,
            'week'  => $auditWeek,
            'month' => $auditMonth
        ],
        'logs' => $log_items
    ]);
if ($isDirectApiCall) exit;
?>

