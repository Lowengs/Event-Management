<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data = ['success' => true, 'stats' => [], 'organizations' => []];

if ($conn) {
    $data['stats']['total']    = (int)$conn->query("SELECT COUNT(*) FROM organization")->fetch_row()[0];
    $data['stats']['active']   = (int)$conn->query("SELECT COUNT(*) FROM organization WHERE Status = 'active' OR Status IS NULL")->fetch_row()[0];
    $data['stats']['pending']  = (int)$conn->query("SELECT COUNT(*) FROM organization WHERE Status = 'pending'")->fetch_row()[0];
    $data['stats']['inactive'] = (int)$conn->query("SELECT COUNT(*) FROM organization WHERE Status = 'inactive'")->fetch_row()[0];

    $res = $conn->query("
        SELECT o.*,
            (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId) AS member_count,
            (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId) AS event_count
        FROM organization o ORDER BY o.OrgName ASC
    ");
    if ($res) while ($row = $res->fetch_assoc()) $data['organizations'][] = $row;
}

echo json_encode($data);
