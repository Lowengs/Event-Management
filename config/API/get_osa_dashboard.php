<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data = ['success' => true, 'stats' => [], 'recent_events' => [], 'notifications' => []];

if ($conn) {
    $data['stats']['total_students']  = (int)$conn->query("SELECT COUNT(*) FROM user WHERE course IS NOT NULL")->fetch_row()[0];
    $data['stats']['active_orgs']     = (int)$conn->query("SELECT COUNT(*) FROM organization WHERE Status = 'active' OR Status IS NULL")->fetch_row()[0];
    $data['stats']['upcoming_events'] = (int)$conn->query("SELECT COUNT(*) FROM event WHERE EventDateTime >= NOW()")->fetch_row()[0];

    $att_count = (int)$conn->query("SELECT COUNT(*) FROM attendance")->fetch_row()[0];
    $reg_count = (int)$conn->query("SELECT COUNT(*) FROM eventregistration")->fetch_row()[0];
    $data['stats']['avg_attendance'] = $reg_count > 0 ? round(($att_count / $reg_count) * 100) . '%' : '0%';

    $r = $conn->query("SELECT e.*, o.OrgName,
        (SELECT COUNT(*) FROM attendance att WHERE att.EventId = e.EventId) as attended_count,
        (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) as reg_count
        FROM event e LEFT JOIN organization o ON e.OrgId = o.OrgId ORDER BY e.EventDateTime DESC LIMIT 3");
    if ($r) while ($row = $r->fetch_assoc()) $data['recent_events'][] = $row;

    $n = $conn->query("SELECT a.*, o.OrgName FROM announcement a LEFT JOIN organization o ON a.OrgId = o.OrgId ORDER BY a.DateCreated DESC LIMIT 5");
    if ($n) while ($row = $n->fetch_assoc()) $data['notifications'][] = $row;
}

echo json_encode($data);
