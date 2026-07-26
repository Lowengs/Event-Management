<?php
/**
 * get_org_dashboard.php — Returns dashboard stats for the logged-in org
 */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
$orgId = (int)$_SESSION['org_id'];
$now   = date('Y-m-d H:i:s');

// Org info
$orgRow = $conn->query("SELECT OrgName, OrgPicture, OrgBanner, Description FROM organization WHERE OrgId = $orgId")->fetch_assoc();

// Stats
$totalMembers   = (int)$conn->query("SELECT COUNT(*) FROM user WHERE OrgId = $orgId")->fetch_row()[0];
$upcomingEvents = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId = $orgId AND EventDateTime > '$now'")->fetch_row()[0];
$totalEvents    = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId = $orgId")->fetch_row()[0];
$completedEvents = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId = $orgId AND LOWER(EventStatus) = 'completed'")->fetch_row()[0];
$ongoingEvents   = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId = $orgId AND LOWER(EventStatus) = 'ongoing'")->fetch_row()[0];
$cancelledEvents = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId = $orgId AND LOWER(EventStatus) = 'cancelled'")->fetch_row()[0];
$pendingAnn     = (int)$conn->query("SELECT COUNT(*) FROM user WHERE OrgId = $orgId AND LOWER(status) = 'pending'")->fetch_row()[0];

// Attendance rate (Total Attended Events / Total Registered for Events)
$attended = (int)$conn->query("SELECT COUNT(*) FROM attendance a JOIN event e ON a.EventId=e.EventId WHERE e.OrgId=$orgId AND a.AttendanceStatus='present'")->fetch_row()[0];
$total    = (int)$conn->query("SELECT COUNT(*) FROM eventregistration er JOIN event e ON er.EventId=e.EventId WHERE e.OrgId=$orgId")->fetch_row()[0];

if ($total > 0) {
    $attRate = round(($attended / $total) * 100);
    if ($attRate > 100) $attRate = 100;
} else if ($attended > 0) {
    $attRate = 100;
} else {
    $attRate = 0;
}

// Recent 5 events
$events = [];
$r = $conn->query("SELECT EventId, EventName, EventDateTime, EventStatus, EventLocation FROM event WHERE OrgId=$orgId ORDER BY EventDateTime DESC LIMIT 5");
if ($r) while($row = $r->fetch_assoc()) $events[] = $row;

$monthlyEvents = [];
$monthSql = "
    SELECT DATE_FORMAT(EventDateTime, '%Y-%m') AS month_key,
           DATE_FORMAT(EventDateTime, '%b %Y') AS label,
           COUNT(*) AS count
    FROM event
    WHERE OrgId = $orgId
      AND EventDateTime >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY month_key, label
    ORDER BY month_key ASC
";
$monthResult = $conn->query($monthSql);
if ($monthResult) {
    while ($row = $monthResult->fetch_assoc()) {
        $monthlyEvents[] = [
            'label' => $row['label'],
            'count' => (int)$row['count'],
        ];
    }
}

if (!$monthlyEvents) {
    for ($i = 5; $i >= 0; $i--) {
        $monthlyEvents[] = [
            'label' => date('M Y', strtotime("-{$i} months")),
            'count' => 0,
        ];
    }
}

echo json_encode([
    'success'  => true,
    'org_name' => $orgRow['OrgName'] ?? '',
    'org_pic'  => $orgRow['OrgPicture'] ?? '',
    'org_banner'=> $orgRow['OrgBanner'] ?? '',
    'stats'    => [
        'total_members'   => $totalMembers,
        'upcoming_events' => $upcomingEvents,
        'total_events'    => $totalEvents,
        'pending_reports' => $pendingAnn,
        'attendance_rate' => $attRate,
        'completed_events' => $completedEvents,
        'ongoing_events' => $ongoingEvents,
        'cancelled_events' => $cancelledEvents,
    ],
    'events' => $events,
    'monthly_events' => $monthlyEvents,
]);
