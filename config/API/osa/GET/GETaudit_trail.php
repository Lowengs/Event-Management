<?php
/**
 * OSA API: GET Audit Trail
 * Uses Stored Procedure: sp_GetOSAAuditTrail
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$logs = [];
try {
    if ($stmt = $conn->prepare("CALL sp_GetOSAAuditTrail()")) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) $logs[] = $r;
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {}

if (empty($logs)) {
    $result = $conn->query("SELECT * FROM auditlog WHERE LOWER(ActorType) = 'osa' ORDER BY Date DESC LIMIT 500");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (empty($row['ActorName'])) {
                $id = (int)($row['ActorId'] ?? $row['UserId'] ?? 0);
                if ($id > 0) {
                    $nameQ = $conn->query("SELECT Name FROM osa WHERE OsaId = $id LIMIT 1");
                    if ($nameQ && ($nr = $nameQ->fetch_assoc())) $row['ActorName'] = $nr['Name'];
                }
            }
            $logs[] = $row;
        }
    }
} else {
    $logs = array_values(array_filter($logs, function($l) {
        return strtolower($l['ActorType'] ?? '') === 'osa';
    }));
}

foreach ($logs as &$logItem) {
    if (!empty($logItem['Action'])) {
        $logItem['Action'] = preg_replace('/^OSA\s+/i', '', trim($logItem['Action']));
    }
}
unset($logItem);

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$stats = ['today' => 0, 'week' => 0, 'success' => 0, 'failed' => 0];
$actionTypes = [];
$users = [];
foreach ($logs as $log) {
    $date = substr((string)($log['Date'] ?? ''), 0, 10);
    if ($date === $today) $stats['today']++;
    if ($date >= $weekStart) $stats['week']++;
    if (strtolower($log['Status'] ?? '') === 'success') $stats['success']++;
    if (strtolower($log['Status'] ?? '') === 'failed') $stats['failed']++;
    if (!empty($log['Action'])) $actionTypes[$log['Action']] = $log['Action'];
    if (!empty($log['ActorName'])) $users[$log['ActorName']] = $log['ActorName'];
}

$search = strtolower(trim($_GET['search'] ?? ''));
$actionFilter = trim($_GET['action_filter'] ?? '');
$userFilter = trim($_GET['user'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$filtered = array_values(array_filter($logs, function ($log) use ($search, $actionFilter, $userFilter, $dateFilter) {
    $haystack = strtolower(implode(' ', [$log['ActorName'] ?? '', $log['Action'] ?? '', $log['Details'] ?? '']));
    return (!$search || str_contains($haystack, $search))
        && (!$actionFilter || ($log['Action'] ?? '') === $actionFilter)
        && (!$userFilter || ($log['ActorName'] ?? '') === $userFilter)
        && (!$dateFilter || substr((string)($log['Date'] ?? ''), 0, 10) === $dateFilter);
}));
$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalRows = count($filtered);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$pageLogs = array_slice($filtered, ($page - 1) * $perPage, $perPage);

echo json_encode([
    'success' => true,
    'data' => $pageLogs,
    'logs' => $pageLogs,
    'stats' => $stats,
    'action_types' => array_values($actionTypes),
    'users' => array_values($users),
    'pagination' => ['current_page' => $page, 'total_pages' => $totalPages, 'total_rows' => $totalRows]
]);
if ($isDirectApiCall) exit;
?>

