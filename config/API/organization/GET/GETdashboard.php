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

// Calculate Organization Attendance Rate & Participation Rate
$attCalc = $conn->query("
    SELECT 
        COUNT(DISTINCT a.UserId) AS total_attended,
        COALESCE(SUM(a.PresenceChecksPassed), 0) AS total_passed,
        COALESCE(SUM(a.PresenceChecksMissed), 0) AS total_missed
    FROM attendance a
    JOIN event e ON e.EventId = a.EventId
    WHERE e.OrgId = $orgId
");
$attData = $attCalc ? $attCalc->fetch_assoc() : null;

$regCalc = $conn->query("
    SELECT COUNT(DISTINCT er.UserId) AS total_registered
    FROM eventregistration er
    JOIN event e ON e.EventId = er.EventId
    WHERE e.OrgId = $orgId
");
$regData = $regCalc ? $regCalc->fetch_assoc() : null;

$totalReg = (int)($regData['total_registered'] ?? 0);
$totalAtt = (int)($attData['total_attended'] ?? 0);
if ($totalReg > 0) {
    $attendanceRate = (int)round(($totalAtt / $totalReg) * 100);
} else {
    $attendanceRate = 100;
}

$passedChecks = (int)($attData['total_passed'] ?? 0);
$missedChecks = (int)($attData['total_missed'] ?? 0);
$totalChecks = $passedChecks + $missedChecks;
$participationRate = $totalChecks > 0 ? (int)round(($passedChecks / $totalChecks) * 100) : 100;

// Today's Attendance strictly for this Organization's events on current date (CURDATE)
$orgAttTodayQ = $conn->query("
    SELECT 
        SUM(CASE WHEN LOWER(TRIM(COALESCE(a.AttendanceStatus, ''))) = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN LOWER(TRIM(COALESCE(a.AttendanceStatus, ''))) = 'absent' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN LOWER(TRIM(COALESCE(a.AttendanceStatus, ''))) = 'late' THEN 1 ELSE 0 END) AS late_count,
        COUNT(*) AS total_count
    FROM attendance a
    JOIN event e ON e.EventId = a.EventId
    WHERE e.OrgId = $orgId AND DATE(a.Timestamp) = CURDATE()
");
$orgAttToday = $orgAttTodayQ ? $orgAttTodayQ->fetch_assoc() : null;
$orgPresentToday = (int)($orgAttToday['present_count'] ?? 0);
$orgAbsentToday  = (int)($orgAttToday['absent_count'] ?? 0);
$orgLateToday    = (int)($orgAttToday['late_count'] ?? 0);
$orgTotalToday   = $orgPresentToday + $orgAbsentToday + $orgLateToday;

$orgAttRateToday = ($orgTotalToday > 0) ? round(($orgPresentToday / $orgTotalToday) * 100) : 0;

$stats = [
    'total_members'         => $totalMembers,
    'upcoming_events'       => $upcomingEvents,
    'completed_events'      => $completedEvents,
    'ongoing_events'        => $ongoingEvents,
    'cancelled_events'      => $cancelledEvents,
    'total_events'          => $totalEvents,
    'attendance_rate'       => $attendanceRate,
    'participation_rate'    => $participationRate,
    'pending_reports'       => $pendingReports,
    'today_present'         => $orgPresentToday,
    'today_absent'          => $orgAbsentToday,
    'today_late'            => $orgLateToday,
    'today_attendance_rate' => $orgAttRateToday . '%'
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

