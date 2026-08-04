<?php
/**
 * OSA API: GET Events
 * Uses Stored Procedure: sp_GetOSAEvents
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$events = [];
try {
    if ($stmt = $conn->prepare("CALL sp_GetOSAEvents()")) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) $events[] = $r;
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {}

if (empty($events)) {
    $result = $conn->query("SELECT e.*, o.OrgName FROM event e LEFT JOIN organization o ON o.OrgId = e.OrgId ORDER BY e.EventDateTime DESC");
    if ($result) while ($row = $result->fetch_assoc()) $events[] = $row;
}

$hasNoFinanceColumn = ($column = $conn->query("SHOW COLUMNS FROM event LIKE 'NoFinancialReport'")) && $column->num_rows > 0;
if ($hasNoFinanceColumn) {
    foreach ($events as &$event) {
        if (!array_key_exists('NoFinancialReport', $event) && !empty($event['EventId'])) {
            $id = (int)$event['EventId'];
            $flag = $conn->query("SELECT NoFinancialReport FROM event WHERE EventId = $id");
            if ($flag && ($row = $flag->fetch_assoc())) $event['NoFinancialReport'] = $row['NoFinancialReport'];
        }
    }
    unset($event);
}

$stats = [
    'total_events' => count($events),
    'ongoing'      => 0,
    'completed'    => 0,
    'scheduled'    => 0,
    'cancelled'    => 0
];
foreach ($events as $ev) {
    $st = strtolower(trim((string)($ev['EventStatus'] ?? 'scheduled')));
    if ($st === 'ongoing') $stats['ongoing']++;
    elseif ($st === 'completed') $stats['completed']++;
    elseif ($st === 'cancelled') $stats['cancelled']++;
    else $stats['scheduled']++;
}

echo json_encode(['success' => true, 'data' => $events, 'events' => $events, 'stats' => $stats]);
if ($isDirectApiCall) exit;
?>

