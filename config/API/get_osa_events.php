<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data = ['success' => true, 'stats' => [], 'events' => []];

if ($conn) {
    $data['stats']['total']     = (int)$conn->query("SELECT COUNT(*) FROM event")->fetch_row()[0];
    $data['stats']['upcoming']  = (int)$conn->query("SELECT COUNT(*) FROM event WHERE EventDateTime >= NOW()")->fetch_row()[0];
    $data['stats']['ongoing']   = (int)$conn->query("SELECT COUNT(*) FROM event WHERE EventStatus = 'ongoing'")->fetch_row()[0];
    $data['stats']['completed'] = (int)$conn->query("SELECT COUNT(*) FROM event WHERE EventStatus = 'completed'")->fetch_row()[0];

    $res = $conn->query("SELECT e.*, o.OrgName FROM event e LEFT JOIN organization o ON e.OrgId = o.OrgId ORDER BY e.EventDateTime DESC");
    if ($res) while ($row = $res->fetch_assoc()) $data['events'][] = $row;
}

echo json_encode($data);
