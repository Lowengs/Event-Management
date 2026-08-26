<?php
/**
 * OSA API: GET Audit Trail & CSV Export
 * Endpoint: /config/API/endpoints/index.php?action=get_osa_audit_trail
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));

if (empty($_SESSION['osa_logged_in']) && empty($_SESSION['admin_logged_in'])) {
    if ($isDirectApiCall) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'OSA login required']);
        exit;
    }
    return;
}

$timeFilter   = trim($_GET['time_filter'] ?? $_GET['period'] ?? '');
$filterActor  = trim($_GET['actor'] ?? $_GET['user_filter'] ?? $_GET['user'] ?? '');
$filterAction = trim($_GET['action_filter'] ?? $_GET['audit_action'] ?? '');
$filterCat    = trim($_GET['category'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterFrom   = trim($_GET['from'] ?? '');
$filterTo     = trim($_GET['to'] ?? '');
$filterDate   = trim($_GET['date'] ?? '');
$filterQ      = trim($_GET['search'] ?? $_GET['q'] ?? '');
$isExport     = !empty($_GET['export']) && strtolower($_GET['export']) === 'csv';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where = [];
$params = [];
$types  = '';

// 1. Date & Time Filter
if ($timeFilter === 'today') {
    $where[] = "DATE(`Date`) = CURDATE()";
} elseif ($timeFilter === 'yesterday') {
    $where[] = "DATE(`Date`) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
} elseif ($timeFilter === 'this_week') {
    $where[] = "YEARWEEK(`Date`, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($timeFilter === 'this_month') {
    $where[] = "YEAR(`Date`) = YEAR(CURDATE()) AND MONTH(`Date`) = MONTH(CURDATE())";
} elseif ($timeFilter === 'this_year') {
    $where[] = "YEAR(`Date`) = YEAR(CURDATE())";
} elseif ($timeFilter === 'custom' || (!empty($filterFrom) || !empty($filterTo))) {
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
} elseif (!empty($filterDate)) {
    // Handle YYYY-MM-DD or DD/MM/YYYY
    $parsedDate = date('Y-m-d', strtotime(str_replace('/', '-', $filterDate)));
    if ($parsedDate && $parsedDate !== '1970-01-01') {
        $where[] = "DATE(`Date`) = ?";
        $params[] = $parsedDate;
        $types .= 's';
    }
}

// 2. User / Actor Filter
if ($filterActor && $filterActor !== 'all') {
    if ($filterActor === 'officer') {
        $where[] = "(ActorType = 'officer' OR (ActorType = 'student' AND Details LIKE '%officer%'))";
    } else {
        $where[] = "ActorType = ?";
        $params[] = $filterActor;
        $types .= 's';
    }
}

// 3. Specific Action Filter
if ($filterAction && $filterAction !== 'all') {
    $where[] = "Action LIKE ?";
    $params[] = "%$filterAction%";
    $types .= 's';
}

// 4. Action Category Filter
if ($filterCat && $filterCat !== 'all') {
    switch (strtolower($filterCat)) {
        case 'members':
            $where[] = "(Action LIKE '%Member%' OR Action LIKE '%Student%' OR Action LIKE '%Account%' OR Action LIKE '%Profile%' OR Action LIKE '%Register%' OR Action LIKE '%User%')";
            break;
        case 'events':
            $where[] = "(Action LIKE '%Event%' OR Action LIKE '%Proposal%' OR Action LIKE '%Activity%')";
            break;
        case 'announcements':
            $where[] = "(Action LIKE '%Announcement%')";
            break;
        case 'attendance':
            $where[] = "(Action LIKE '%Attendance%' OR Action LIKE '%Scan%' OR Action LIKE '%Spoof%' OR Action LIKE '%Liveness%' OR Action LIKE '%Check-in%')";
            break;
        case 'documents':
            $where[] = "(Action LIKE '%Document%' OR Action LIKE '%Doc%' OR Action LIKE '%Upload%' OR Action LIKE '%Report%')";
            break;
        case 'assessments':
            $where[] = "(Action LIKE '%Assessment%' OR Action LIKE '%Evaluation%' OR Action LIKE '%Pre-test%' OR Action LIKE '%Post-test%' OR Action LIKE '%Survey%')";
            break;
        case 'certificates':
            $where[] = "(Action LIKE '%Certificate%' OR Action LIKE '%Cert%')";
            break;
        case 'officers':
            $where[] = "(Action LIKE '%Officer%' OR Action LIKE '%Role%')";
            break;
        case 'messages':
            $where[] = "(Action LIKE '%Message%' OR Action LIKE '%Chat%' OR Action LIKE '%Inquiry%')";
            break;
        case 'login':
        case 'login_logout':
            $where[] = "(Action LIKE '%Login%' OR Action LIKE '%Logout%' OR Action LIKE '%Password%' OR Action LIKE '%Session%' OR Action LIKE '%OTP%')";
            break;
    }
}

// 5. Status Filter
if ($filterStatus && $filterStatus !== 'all') {
    $where[] = "LOWER(Status) = ?";
    $params[] = strtolower($filterStatus);
    $types .= 's';
}

// 6. Search Keyword
if ($filterQ) {
    $where[] = "(ActorName LIKE ? OR Action LIKE ? OR Details LIKE ? OR IpAddress LIKE ?)";
    $params[] = "%$filterQ%";
    $params[] = "%$filterQ%";
    $params[] = "%$filterQ%";
    $params[] = "%$filterQ%";
    $types .= 'ssss';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Stats
$stats = ['today' => 0, 'week' => 0, 'success' => 0, 'failed' => 0];
$sRes = $conn->query("SELECT 
    SUM(CASE WHEN DATE(`Date`) = CURDATE() THEN 1 ELSE 0 END) AS today_cnt,
    SUM(CASE WHEN YEARWEEK(`Date`, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS week_cnt,
    SUM(CASE WHEN LOWER(`Status`) = 'success' THEN 1 ELSE 0 END) AS success_cnt,
    SUM(CASE WHEN LOWER(`Status`) = 'failed' THEN 1 ELSE 0 END) AS failed_cnt
FROM `auditlog`");
if ($sRes && ($sRow = $sRes->fetch_assoc())) {
    $stats['today']   = (int)($sRow['today_cnt'] ?? 0);
    $stats['week']    = (int)($sRow['week_cnt'] ?? 0);
    $stats['success'] = (int)($sRow['success_cnt'] ?? 0);
    $stats['failed']  = (int)($sRow['failed_cnt'] ?? 0);
}

// CSV Export Handler
if ($isExport) {
    $exportSql = "SELECT * FROM `auditlog` $whereClause ORDER BY `Date` DESC";
    $stmtE = $conn->prepare($exportSql);
    if ($types) $stmtE->bind_param($types, ...$params);
    $stmtE->execute();
    $exportLogs = $stmtE->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtE->close();

    $filename = 'osa_audit_logs_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Log ID',
        'Date & Time',
        'Actor Type',
        'Actor Name',
        'Action',
        'Status',
        'IP Address',
        'Location',
        'Device / OS',
        'Browser',
        'Details'
    ]);

    foreach ($exportLogs as $row) {
        $det = json_decode($row['Details'] ?? '', true) ?: [];
        $ip = !empty($row['IpAddress']) ? $row['IpAddress'] : ($det['ip'] ?? '127.0.0.1');
        $device = $det['device'] ?? ($ip === '127.0.0.1' ? 'Windows (Desktop)' : 'Client Device');
        $browser = $det['browser'] ?? 'Browser';
        $location = $det['location'] ?? ($ip === '127.0.0.1' ? 'Localhost' : 'Philippines');
        $detailsText = !empty($row['Details']) ? (is_array($det) ? json_encode($det, JSON_UNESCAPED_SLASHES) : $row['Details']) : '—';

        fputcsv($output, [
            $row['LogId'] ?? '',
            $row['Date'] ?? '',
            strtoupper($row['ActorType'] ?? 'SYSTEM'),
            $row['ActorName'] ?? '—',
            $row['Action'] ?? '—',
            strtoupper($row['Status'] ?? 'SUCCESS'),
            $ip,
            $location,
            $device,
            $browser,
            $detailsText
        ]);
    }
    fclose($output);
    exit;
}

$countSql = "SELECT COUNT(*) FROM `auditlog` $whereClause";
$stmtC = $conn->prepare($countSql);
if ($types) $stmtC->bind_param($types, ...$params);
$stmtC->execute();
$totalRows = (int)($stmtC->get_result()->fetch_row()[0] ?? 0);
$stmtC->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$dataSql = "SELECT * FROM `auditlog` $whereClause ORDER BY `Date` DESC LIMIT $perPage OFFSET $offset";
$stmtD = $conn->prepare($dataSql);
if ($types) $stmtD->bind_param($types, ...$params);
$stmtD->execute();
$logs = $stmtD->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtD->close();

$actionTypes = [];
$users = [];
$actRes = $conn->query("SELECT DISTINCT Action FROM `auditlog` WHERE Action IS NOT NULL ORDER BY Action ASC");
if ($actRes) while ($a = $actRes->fetch_assoc()) $actionTypes[] = $a['Action'];

$userRes = $conn->query("SELECT DISTINCT ActorName FROM `auditlog` WHERE ActorName IS NOT NULL ORDER BY ActorName ASC");
if ($userRes) while ($u = $userRes->fetch_assoc()) $users[] = $u['ActorName'];

if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

echo json_encode([
    'success'      => true,
    'data'         => $logs,
    'logs'         => $logs,
    'stats'        => $stats,
    'action_types' => $actionTypes,
    'users'        => $users,
    'pagination'   => ['current_page' => $page, 'total_pages' => $totalPages, 'total_rows' => $totalRows]
]);
if ($isDirectApiCall) exit;
?>
