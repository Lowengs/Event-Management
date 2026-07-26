<?php
/** get_org_events.php — events for the logged-in org */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId   = (int)$_SESSION['org_id'];
$orgName = $_SESSION['org_name'] ?? '';
$now     = date('Y-m-d H:i:s');
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

// Auto-set Ongoing when event start time has passed and end time is in future/unset
$conn->query("UPDATE event SET EventStatus = 'Ongoing' WHERE OrgId=$orgId AND EventStatus = 'Scheduled' AND EventDateTime <= '$now' AND (EndDateTime > '$now' OR EndDateTime IS NULL OR EndDateTime = '0000-00-00 00:00:00')");

// Auto-set Completed when event end time has passed
$conn->query("UPDATE event SET EventStatus = 'Completed' WHERE OrgId=$orgId AND EventStatus IN ('Scheduled', 'Ongoing') AND EndDateTime <= '$now' AND EndDateTime != '0000-00-00 00:00:00' AND EndDateTime IS NOT NULL");

$total     = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId=$orgId")->fetch_row()[0];
$upcoming  = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId=$orgId AND EventStatus='Scheduled' AND EventDateTime > '$now'")->fetch_row()[0];
$ongoing   = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId=$orgId AND EventStatus='Ongoing'")->fetch_row()[0];
$completed = (int)$conn->query("SELECT COUNT(*) FROM event WHERE OrgId=$orgId AND EventStatus='Completed'")->fetch_row()[0];

$events = [];
$r = $conn->query("
    SELECT e.*, o.OrgName,
        (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS pre_registered_count
    FROM event e 
    LEFT JOIN organization o ON e.OrgId=o.OrgId 
    WHERE e.OrgId=$orgId 
    ORDER BY e.EventDateTime DESC 
    LIMIT 200
");
if ($r) while($row = $r->fetch_assoc()) $events[] = $row;

echo json_encode([
    'success' => true,
    'org_id'  => $orgId,
    'org_name'=> $orgName,
    'stats'   => compact('total','upcoming','ongoing','completed'),
    'events'  => $events
]);
