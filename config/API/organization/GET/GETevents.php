<?php
/**
 * Organization API: GET Events
 * Endpoint: /config/API/endpoints/index.php?action=GETevents
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
$noFinanceColumn = false;
$columnCheck = $conn->query("SHOW COLUMNS FROM event LIKE 'NoFinancialReport'");
if ($columnCheck && $columnCheck->num_rows > 0) $noFinanceColumn = true;

try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgEvents(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed to fallback query
}

if (empty($events)) {
    $q = $conn->query("
        SELECT e.*, o.OrgName
        FROM event e
        LEFT JOIN organization o ON o.OrgId = e.OrgId
        WHERE e.OrgId = $orgId
        ORDER BY e.EventDateTime DESC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $events[] = $r;
        }
    }
}

// Ensure the events view can distinguish events with pre/post assessments
// even when the stored procedure does not return these computed fields.
foreach ($events as &$event) {
    $eventId = (int)($event['EventId'] ?? 0);
    if (!$eventId) continue;
    $assessmentResult = $conn->query("SELECT LOWER(type) AS type, COUNT(*) AS total FROM assessments WHERE event_id = $eventId GROUP BY LOWER(type)");
    $pretest = 0;
    $posttest = 0;
    if ($assessmentResult) {
        while ($assessment = $assessmentResult->fetch_assoc()) {
            if ($assessment['type'] === 'pretest') $pretest = (int)$assessment['total'];
            if ($assessment['type'] === 'posttest') $posttest = (int)$assessment['total'];
        }
    }
    $event['has_pretest'] = $pretest;
    $event['has_posttest'] = $posttest;
    $event['has_assessment'] = ($pretest || $posttest) ? 1 : 0;

    $postReport = 0;
    $financialReport = 0;
    $postReportTitle = '';
    $financialReportTitle = '';
    $docs = $conn->query("SELECT DocId, Title, DocType, FilePath FROM org_documents WHERE EventId = $eventId");
    if ($docs) {
        while ($doc = $docs->fetch_assoc()) {
            $dt = strtolower($doc['DocType'] ?? '');
            $tt = strtolower($doc['Title'] ?? '');
            if (strpos($dt, 'post') !== false || strpos($tt, 'post') !== false) {
                $postReport = 1;
                $postReportTitle = $doc['Title'];
            }
            if (strpos($dt, 'finan') !== false || strpos($tt, 'finan') !== false || strpos($dt, 'budget') !== false) {
                $financialReport = 1;
                $financialReportTitle = $doc['Title'];
            }
        }
    }
    $event['post_report_uploaded'] = $postReport;
    $event['post_report_title'] = $postReportTitle;
    $event['financial_report_uploaded'] = $financialReport;
    $event['financial_report_title'] = $financialReportTitle;
    if ($noFinanceColumn && !array_key_exists('NoFinancialReport', $event)) {
        $flag = $conn->query("SELECT NoFinancialReport FROM event WHERE EventId = $eventId");
        if ($flag && ($flagRow = $flag->fetch_assoc())) $event['NoFinancialReport'] = $flagRow['NoFinancialReport'];
    }
    $event['no_financial_report'] = $noFinanceColumn ? (int)($event['NoFinancialReport'] ?? 0) : 0;
}
unset($event);

// Calculate stats breakdown
$total = count($events);
$upcoming = 0;
$ongoing = 0;
$completed = 0;
foreach ($events as $ev) {
    $st = strtolower(trim($ev['EventStatus'] ?? 'scheduled'));
    if ($st === 'completed') {
        $completed++;
    } elseif ($st === 'ongoing') {
        $ongoing++;
    } else {
        $upcoming++;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Organization events retrieved successfully',
    'stats'   => [
        'total'     => $total,
        'upcoming'  => $upcoming,
        'ongoing'   => $ongoing,
        'completed' => $completed
    ],
    'events'  => $events,
    'data'    => $events
]);
if ($isDirectApiCall) exit;

