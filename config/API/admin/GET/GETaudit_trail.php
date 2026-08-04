<?php
/**
 * Admin API: GET Admin Audit Trail Data
 * Endpoint: /config/API/endpoints/index.php?action=get_admin_audit_trail
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Admin login required']);
if ($isDirectApiCall) exit;
    return;
}

$filterActor  = trim($_GET['actor']  ?? '');
$filterAction = trim($_GET['audit_action'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterFrom   = trim($_GET['from']   ?? '');
$filterTo     = trim($_GET['to']     ?? '');
$filterQ      = trim($_GET['q']      ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where = [];
$params = [];
$types  = '';

if ($filterActor) {
    $where[] = "ActorType = ?";
    $params[] = $filterActor;
    $types .= 's';
}
if ($filterAction) {
    $where[] = "Action LIKE ?";
    $params[] = "%$filterAction%";
    $types .= 's';
}
if ($filterStatus) {
    $where[] = "Status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}
if ($filterFrom) {
    $where[] = "DATE(`Date`) >= ?";
    $params[] = $filterFrom;
    $types .= 's';
}
if ($filterTo) {
    $where[] = "DATE(`Date`) <= ?";
    $params[] = $filterTo;
    $types .= 's';
}
if ($filterQ) {
    $where[] = "(ActorName LIKE ? OR Action LIKE ? OR Details LIKE ?)";
    $params[] = "%$filterQ%";
    $params[] = "%$filterQ%";
    $params[] = "%$filterQ%";
    $types .= 'sss';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countSql = "SELECT COUNT(*) FROM `auditlog` $whereClause";
$stmtC = $conn->prepare($countSql);
if ($types) $stmtC->bind_param($types, ...$params);
$stmtC->execute();
$total = (int)($stmtC->get_result()->fetch_row()[0] ?? 0);
$stmtC->close();

$dataSql = "SELECT * FROM `auditlog` $whereClause ORDER BY `Date` DESC LIMIT $perPage OFFSET $offset";
$stmtD = $conn->prepare($dataSql);
if ($types) $stmtD->bind_param($types, ...$params);
$stmtD->execute();
$logs = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtD->close();

$actionOptions = [];
$actRes = $conn->query("SELECT DISTINCT Action FROM `auditlog` WHERE Action IS NOT NULL ORDER BY Action ASC");
if ($actRes) while ($a = $actRes->fetch_assoc()) $actionOptions[] = $a['Action'];

echo json_encode([
        'success'        => true,
        'logs'           => $logs,
        'total'          => $total,
        'page'           => $page,
        'per_page'       => $perPage,
        'action_options' => $actionOptions
    ]);
if ($isDirectApiCall) exit;
?>

