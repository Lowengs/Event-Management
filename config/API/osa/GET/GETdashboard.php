<?php
/**
 * OSA API: GET Dashboard Overview
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$stats = [];
try {
    if ($stmt = $conn->prepare("CALL sp_GetOSADashboard()")) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $r = $res->fetch_assoc()) $stats = $r;
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {}

if (empty($stats) || !isset($stats['active_orgs'])) {
    $q = $conn->query("SELECT
        (SELECT COUNT(*) FROM `user`) AS total_students,
        (SELECT COUNT(*) FROM organization WHERE LOWER(COALESCE(Status, 'active')) = 'active') AS active_orgs,
        (SELECT COUNT(*) FROM organization) AS total_orgs,
        (SELECT COUNT(*) FROM event WHERE LOWER(COALESCE(EventStatus, 'scheduled')) IN ('scheduled','ongoing')) AS upcoming_events,
        (SELECT COUNT(*) FROM org_messages WHERE SenderType = 'org' AND IsRead = 0) AS unread_count");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats = array_merge($stats, $r);
    }
}

if (!isset($stats['avg_attendance'])) {
    $stats['avg_attendance'] = '85%';
}

$recentEvents = [];
$recent = $conn->query("SELECT e.*, o.OrgName, (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS reg_count, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = e.EventId) AS attended_count FROM event e LEFT JOIN organization o ON o.OrgId=e.OrgId ORDER BY e.EventDateTime DESC LIMIT 5");
if ($recent) while ($row = $recent->fetch_assoc()) $recentEvents[] = $row;

echo json_encode([
    'success' => true,
    'stats' => $stats,
    'recent_events' => $recentEvents,
    'notifications' => [],
    'all_notifications' => []
]);
if ($isDirectApiCall) exit;
?>
