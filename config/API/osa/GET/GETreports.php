<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) header('Content-Type: application/json');

$orgFilter = (int)($_GET['org'] ?? 0);
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$where = ['1=1'];
if ($orgFilter) $where[] = 'e.OrgId = ' . $orgFilter;
if ($status !== '') $where[] = "e.EventStatus = '" . $conn->real_escape_string($status) . "'";
if ($search !== '') { $s = $conn->real_escape_string($search); $where[] = "(e.EventName LIKE '%$s%' OR o.OrgName LIKE '%$s%')"; }
$whereSql = implode(' AND ', $where);

$stats = ['scheduled'=>0,'ongoing'=>0,'completed'=>0,'cancelled'=>0,'delayed'=>0];
$sr = $conn->query("SELECT LOWER(EventStatus) status, COUNT(*) total FROM event GROUP BY LOWER(EventStatus)");
if ($sr) while ($row = $sr->fetch_assoc()) {
    $key = $row['status'];
    if (isset($stats[$key])) $stats[$key] = (int)$row['total'];
    if ($key === 'cancelled') $stats['cancelled'] = (int)$row['total'];
}
$orgs = [];
$or = $conn->query('SELECT OrgId, OrgName FROM organization ORDER BY OrgName');
if ($or) while ($row = $or->fetch_assoc()) $orgs[] = $row;

$eventsByOrg = [];
$hasColumn = ($c = $conn->query("SHOW COLUMNS FROM event LIKE 'NoFinancialReport'")) && $c->num_rows > 0;
$noFinance = $hasColumn ? 'e.NoFinancialReport' : '0';
$events = $conn->query("SELECT e.*, o.OrgName, $noFinance AS no_financial_report,
    (SELECT COUNT(*) FROM eventregistration r WHERE r.EventId=e.EventId) registered,
    (SELECT COUNT(DISTINCT a.UserId) FROM attendance a WHERE a.EventId=e.EventId) attended
    FROM event e LEFT JOIN organization o ON o.OrgId=e.OrgId WHERE $whereSql ORDER BY e.EventDateTime DESC");
if ($events) while ($row = $events->fetch_assoc()) $eventsByOrg[$row['OrgName'] ?? 'Unassigned'][] = $row;

$officersByOrg = [];
$officersRes = $conn->query("SELECT u.OrgId, o.OrgName, CONCAT(u.first_name, ' ', u.last_name, ' (', COALESCE(u.officer_role, u.Position, 'Officer'), ')') AS officer_label FROM user u JOIN organization o ON o.OrgId = u.OrgId WHERE u.is_officer = 1");
if ($officersRes) {
    while ($row = $officersRes->fetch_assoc()) {
        $officersByOrg[$row['OrgName']][] = $row['officer_label'];
    }
}

$docsByEvent = [];
$docs = $conn->query("SELECT d.DocId, d.EventId, d.DocType, d.FilePath, d.Title, d.OrgId, e.EventName FROM org_documents d LEFT JOIN event e ON e.EventId = d.EventId WHERE d.EventId IS NOT NULL AND d.EventId > 0 ORDER BY d.UploadedAt DESC");
if ($docs) {
    while ($doc = $docs->fetch_assoc()) {
        $eventId = (int)($doc['EventId'] ?? 0);
        if ($eventId <= 0) continue;

        $cleanType  = strtolower(preg_replace('/[^a-z]/', '', $doc['DocType'] ?? ''));
        $cleanTitle = strtolower(preg_replace('/[^a-z]/', '', $doc['Title'] ?? ''));

        // Specifically match Post-Activity Report
        $isPost = ($doc['DocType'] === 'PostActivityReport' 
            || strpos($cleanType, 'postactivity') !== false 
            || strpos($cleanTitle, 'postactivity') !== false 
            || (strpos($cleanType, 'post') !== false && strpos($cleanType, 'report') !== false));

        // Specifically match Financial Report
        $isFin  = ($doc['DocType'] === 'FinancialReport' 
            || strpos($cleanType, 'financial') !== false 
            || strpos($cleanTitle, 'financial') !== false 
            || strpos($cleanType, 'budget') !== false);

        if ($isPost && !isset($docsByEvent[$eventId]['postactivityreport'])) {
            $docsByEvent[$eventId]['postactivityreport'] = $doc;
        }
        if ($isFin && !isset($docsByEvent[$eventId]['financialreport'])) {
            $docsByEvent[$eventId]['financialreport'] = $doc;
        }
    }
}
echo json_encode(['success'=>true, 'stats'=>$stats, 'orgs'=>$orgs, 'events_by_org'=>$eventsByOrg, 'officers_by_org'=>$officersByOrg, 'all_docs_by_event'=>$docsByEvent]);
if ($isDirectApiCall) exit;
?>
