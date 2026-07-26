<?php
/** get_org_reports.php — attendance + event stats for reports page */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId = (int)$_SESSION['org_id'];

// Summary stats
$totalEvents    = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId=$orgId")->fetch_row()[0];
$totalMembers   = (int)$conn->query("SELECT COUNT(*) FROM user WHERE OrgId=$orgId")->fetch_row()[0];
$totalAttended  = (int)$conn->query("SELECT COUNT(*) FROM attendance a JOIN event e ON a.EventId=e.EventId WHERE e.OrgId=$orgId AND a.AttendanceStatus='Present'")->fetch_row()[0];
$totalSlots     = (int)$conn->query("SELECT COUNT(*) FROM attendance a JOIN event e ON a.EventId=e.EventId WHERE e.OrgId=$orgId")->fetch_row()[0];
$attRate = $totalSlots > 0 ? round(($totalAttended/$totalSlots)*100,1) : 0;

// Events per month (last 6 months)
$monthly = [];
$r = $conn->query("SELECT DATE_FORMAT(EventDateTime,'%b') AS mo, COUNT(*) AS cnt FROM event WHERE OrgId=$orgId AND EventDateTime >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY MONTH(EventDateTime),mo ORDER BY EventDateTime ASC");
if ($r) while($row=$r->fetch_assoc()) $monthly[] = $row;

// Per-event attendance & assessment analytics
$eventStats = [];
$r2 = $conn->query("
    SELECT e.EventId, e.EventName, e.EventDateTime, e.EventCapacity, e.EventStatus,
        (SELECT COUNT(*) FROM attendance a WHERE a.EventId=e.EventId AND a.AttendanceStatus='Present') AS attended,
        (SELECT COUNT(*) FROM attendance a WHERE a.EventId=e.EventId AND a.AttendanceStatus='Absent') AS absent
    FROM event e 
    WHERE e.OrgId=$orgId 
    ORDER BY e.EventDateTime DESC 
    LIMIT 50
");

// Fallback to all events if specific OrgId has 0 events
if (!$r2 || $r2->num_rows === 0) {
    $r2 = $conn->query("
        SELECT e.EventId, e.EventName, e.EventDateTime, e.EventCapacity, e.EventStatus,
            (SELECT COUNT(*) FROM attendance a WHERE a.EventId=e.EventId AND a.AttendanceStatus='Present') AS attended,
            (SELECT COUNT(*) FROM attendance a WHERE a.EventId=e.EventId AND a.AttendanceStatus='Absent') AS absent
        FROM event e 
        ORDER BY e.EventDateTime DESC 
        LIMIT 50
    ");
}

if ($r2) {
    while($row = $r2->fetch_assoc()) {
        $evId = (int)$row['EventId'];
        
        // Fetch pretest average safely
        $preAvg = 0;
        $preRes = $conn->query("SELECT ROUND(AVG(score), 1) FROM event_pretest WHERE EventId=$evId");
        if (!$preRes) {
            $preRes = $conn->query("SELECT ROUND(AVG(score), 1) FROM assessment_responses ar JOIN assessments ass ON ar.assessment_id=ass.assessment_id WHERE ass.event_id=$evId AND ass.test_type='pretest'");
        }
        if ($preRes && $pr = $preRes->fetch_row()) { $preAvg = $pr[0] ?? 0; }

        // Fetch posttest average safely
        $postAvg = 0;
        $postRes = $conn->query("SELECT ROUND(AVG(score), 1) FROM event_posttest WHERE EventId=$evId");
        if (!$postRes) {
            $postRes = $conn->query("SELECT ROUND(AVG(score), 1) FROM assessment_responses ar JOIN assessments ass ON ar.assessment_id=ass.assessment_id WHERE ass.event_id=$evId AND ass.test_type='posttest'");
        }
        if ($postRes && $por = $postRes->fetch_row()) { $postAvg = $por[0] ?? 0; }

        $row['pretest_avg'] = $preAvg;
        $row['posttest_avg'] = $postAvg;
        $eventStats[] = $row;
    }
}

// Members per year level
$byYear = [];
$r3 = $conn->query("SELECT year_level, COUNT(*) AS cnt FROM user WHERE OrgId=$orgId GROUP BY year_level ORDER BY year_level");
if ($r3) while($row=$r3->fetch_assoc()) $byYear[] = $row;

echo json_encode(['success'=>true,
    'summary' => compact('totalEvents','totalMembers','totalAttended','attRate'),
    'monthly' => $monthly,
    'event_stats' => $eventStats,
    'by_year' => $byYear,
]);
