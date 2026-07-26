<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

$data = ['success' => true, 'events' => []];
$studentId = !empty($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0;

if ($conn) {
    $q = "
        SELECT e.*, o.OrgName,
            (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS registered_count
        FROM event e
        LEFT JOIN organization o ON e.OrgId = o.OrgId
        WHERE e.EventStatus NOT IN ('Cancelled')
        ORDER BY e.EventDateTime ASC
    ";
    $r = $conn->query($q);
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            // Check if logged-in student is registered
            $row['is_registered'] = false;
            $row['pre_test_done'] = false;
            if ($studentId) {
                $eid = (int)$row['EventId'];
                $rCheck = $conn->query("SELECT RegistrationId FROM eventregistration WHERE EventId=$eid AND UserId=$studentId");
                $row['is_registered'] = ($rCheck && $rCheck->num_rows > 0);
                $pCheck = $conn->query("SELECT TestId FROM event_pretest WHERE EventId=$eid AND UserId=$studentId");
                $row['pre_test_done'] = ($pCheck && $pCheck->num_rows > 0);
            }
            $data['events'][] = $row;
        }
    }
}

echo json_encode($data);
