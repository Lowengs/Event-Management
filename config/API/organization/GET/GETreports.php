<?php
/**
 * Organization API: GET Reports Data
 * Uses Stored Procedure: sp_GetOrgReports
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$orgId = (int)($_SESSION['org_id'] ?? 0);
$summary = ['totalEvents' => 0, 'totalMembers' => 0, 'totalAttended' => 0, 'attRate' => 0];
$monthly = [];
$eventStats = [];
$byYear = [];

if ($orgId > 0) {
    $summaryResult = $conn->query("SELECT
        (SELECT COUNT(*) FROM event WHERE OrgId = $orgId) AS total_events,
        (SELECT COUNT(*) FROM `user` WHERE OrgId = $orgId) AS total_members,
        (SELECT COUNT(DISTINCT a.UserId) FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = $orgId) AS total_attended,
        (SELECT COUNT(*) FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = $orgId) AS attendance_rows");
    if ($summaryResult && $row = $summaryResult->fetch_assoc()) {
        $summary['totalEvents'] = (int)$row['total_events'];
        $summary['totalMembers'] = (int)$row['total_members'];
        $summary['totalAttended'] = (int)$row['total_attended'];
        $summary['attRate'] = $summary['totalMembers'] > 0 ? round(($summary['totalAttended'] / $summary['totalMembers']) * 100) : 0;
    }

    $eventResult = $conn->query("SELECT e.*,
        (SELECT COUNT(DISTINCT a.UserId) FROM attendance a WHERE a.EventId = e.EventId) AS attended,
        (SELECT COUNT(DISTINCT er.UserId) FROM eventregistration er WHERE er.EventId = e.EventId) AS registered,
        (SELECT AVG(100.0 * p.Score / NULLIF((SELECT COUNT(q.question_id) FROM assessments s JOIN assessment_questions q ON q.assessment_id = s.assessment_id WHERE s.event_id = e.EventId AND LOWER(COALESCE(s.type, s.test_type, '')) LIKE '%pre%'), 0)) FROM event_pretest p WHERE p.EventId = e.EventId) AS pretest_avg,
        (SELECT AVG(100.0 * p.Score / NULLIF((SELECT COUNT(q.question_id) FROM assessments s JOIN assessment_questions q ON q.assessment_id = s.assessment_id WHERE s.event_id = e.EventId AND LOWER(COALESCE(s.type, s.test_type, '')) LIKE '%post%'), 0)) FROM event_posttest p WHERE p.EventId = e.EventId) AS posttest_avg
        FROM event e WHERE e.OrgId = $orgId ORDER BY e.EventDateTime DESC");
    if ($eventResult) while ($row = $eventResult->fetch_assoc()) $eventStats[] = $row;

    $monthResult = $conn->query("SELECT DATE_FORMAT(EventDateTime, '%b') AS mo, MONTH(EventDateTime) AS month_num, COUNT(*) AS cnt FROM event WHERE OrgId = $orgId GROUP BY MONTH(EventDateTime), DATE_FORMAT(EventDateTime, '%b') ORDER BY month_num");
    if ($monthResult) while ($row = $monthResult->fetch_assoc()) $monthly[] = $row;

    $yearResult = $conn->query("SELECT COALESCE(year_level, 'Unknown') AS year_level, COUNT(*) AS cnt FROM `user` WHERE OrgId = $orgId GROUP BY year_level ORDER BY year_level");
    if ($yearResult) while ($row = $yearResult->fetch_assoc()) $byYear[] = $row;
}

echo json_encode(['success' => true, 'summary' => $summary, 'stats' => $summary, 'monthly' => $monthly, 'event_stats' => $eventStats, 'by_year' => $byYear]);
if ($isDirectApiCall) exit;
?>

