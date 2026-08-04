<?php
/**
 * Admin API: GET Dashboard Data
 * Endpoint: /config/API/endpoints/index.php?action=get_admin_dashboard
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

$totalStudents = 0; $totalOsa = 0; $totalOrgs = 0; $totalAdmins = 0; $todayLogs = 0; $totalEvents = 0;
try {
    $stmt = $conn->prepare("CALL sp_GetAdminDashboard()");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $totalStudents = (int)($row['total_students'] ?? 0);
        $totalOsa      = (int)($row['total_osa']      ?? 0);
        $totalOrgs     = (int)($row['total_orgs']     ?? 0);
        $totalAdmins   = (int)($row['total_admins']   ?? 0);
        $todayLogs     = (int)($row['today_logs']     ?? 0);
        $totalEvents   = (int)($row['total_events']   ?? 0);
    }
    $stmt->close();
    while ($conn->more_results() && $conn->next_result()) { ; }
} catch (Exception $e) {
    // fallback
}

$recentLogs = [];
$r = $conn->query("SELECT AuditId, UserId, ActorType, ActorId, ActorName, Action, Details, Status, IpAddress, Date FROM `auditlog` ORDER BY `Date` DESC LIMIT 10");
if ($r) while ($row = $r->fetch_assoc()) $recentLogs[] = $row;

echo json_encode([
        'success' => true,
        'stats' => [
            'total_students' => $totalStudents,
            'total_osa'      => $totalOsa,
            'total_orgs'     => $totalOrgs,
            'total_admins'   => $totalAdmins,
            'today_logs'     => $todayLogs,
            'total_events'   => $totalEvents
        ],
        'recent_logs' => $recentLogs
    ]);
if ($isDirectApiCall) exit;
?>

