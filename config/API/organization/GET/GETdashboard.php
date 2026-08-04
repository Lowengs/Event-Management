<?php
/**
 * Organization API: GET Dashboard
 * Uses Stored Procedures: sp_GetOrgDashboard & sp_GetOrgEvents
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
$orgName         = $_SESSION['org_name'] ?? 'Organization';
$totalMembers    = 0;
$upcomingEvents  = 0;
$completedEvents = 0;
$ongoingEvents   = 0;
$cancelledEvents = 0;
$totalEvents     = 0;
$attendanceRate  = 100;
$pendingReports  = 0;
$events          = [];
$monthlyEvents   = [];

try {
    if ($stmtD = $conn->prepare("CALL sp_GetOrgDashboard(?)")) {
        $stmtD->bind_param("i", $orgId);
        $stmtD->execute();
        $resD = $stmtD->get_result();
        if ($resD && $rowD = $resD->fetch_assoc()) {
            $totalMembers   = (int)($rowD['total_members']   ?? 0);
            $upcomingEvents = (int)($rowD['upcoming_events'] ?? 0);
        }
        $stmtD->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {}

if ($totalMembers === 0) {
    $memberResult = $conn->query("SELECT COUNT(*) AS total FROM `user` WHERE OrgId = $orgId");
    if ($memberResult && $row = $memberResult->fetch_assoc()) {
        $totalMembers = (int)$row['total'];
    }
}

if (empty($events)) {
    $eventResult = $conn->query("SELECT * FROM event WHERE OrgId = $orgId ORDER BY EventDateTime DESC");
    if ($eventResult) {
        while ($row = $eventResult->fetch_assoc()) {
            $events[] = $row;
            $totalEvents++;
            $st = strtolower($row['EventStatus'] ?? '');
            if ($st === 'scheduled' || $st === 'upcoming') $upcomingEvents++;
            elseif ($st === 'completed') $completedEvents++;
            elseif ($st === 'ongoing') $ongoingEvents++;
            elseif ($st === 'cancelled') $cancelledEvents++;
        }
    }
}

try {
    if (empty($events) && ($stmtE = $conn->prepare("CALL sp_GetOrgEvents(?)"))) {
        $stmtE->bind_param("i", $orgId);
        $stmtE->execute();
        $resE = $stmtE->get_result();
        if ($resE) {
            while ($row = $resE->fetch_assoc()) {
                $events[] = $row;
                $totalEvents++;
                $st = strtolower($row['EventStatus'] ?? '');
                if ($st === 'scheduled' || $st === 'upcoming') $upcomingEvents++;
                elseif ($st === 'completed') $completedEvents++;
                elseif ($st === 'ongoing') $ongoingEvents++;
                elseif ($st === 'cancelled') $cancelledEvents++;
            }
        }
        $stmtE->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {}

// Monthly activity trend
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
foreach ($months as $m) {
    $monthlyEvents[] = ['label' => $m, 'count' => 0];
}
foreach ($events as $ev) {
    if (!empty($ev['EventDateTime'])) {
        $mIdx = (int)date('n', strtotime($ev['EventDateTime'])) - 1;
        if (isset($monthlyEvents[$mIdx])) {
            $monthlyEvents[$mIdx]['count']++;
        }
    }
}

$stats = [
    'total_members'    => $totalMembers,
    'upcoming_events'  => $upcomingEvents,
    'completed_events' => $completedEvents,
    'ongoing_events'   => $ongoingEvents,
    'cancelled_events' => $cancelledEvents,
    'total_events'     => $totalEvents,
    'attendance_rate'  => $attendanceRate,
    'pending_reports'  => $pendingReports
];

echo json_encode([
        'success'        => true,
        'org_name'       => $orgName,
        'stats'          => $stats,
        'monthly_events' => $monthlyEvents,
        'events'         => $events
    ]);
if ($isDirectApiCall) exit;
?>

