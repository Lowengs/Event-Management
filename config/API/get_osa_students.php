<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$data = ['success' => true, 'stats' => [], 'students' => []];

if ($conn) {
    $data['stats']['total']  = (int)$conn->query("SELECT COUNT(*) FROM user WHERE course IS NOT NULL")->fetch_row()[0];
    $data['stats']['ilas']   = (int)$conn->query("SELECT COUNT(*) FROM user WHERE course IN ('BSAVTOUR', 'BSAVCOMM', 'BSAVSEC', 'BSAVSSM')")->fetch_row()[0];
    $data['stats']['ics']    = (int)$conn->query("SELECT COUNT(*) FROM user WHERE course IN ('BSAIS', 'BSAIT')")->fetch_row()[0];
    $data['stats']['inet']   = (int)$conn->query("SELECT COUNT(*) FROM user WHERE course IN ('AAMT', 'AAET', 'BSAMT', 'BSAET', 'BSAERO', 'BSAT')")->fetch_row()[0];

    $res = $conn->query("SELECT u.*, o.OrgName FROM user u LEFT JOIN organization o ON u.OrgId = o.OrgId WHERE u.course IS NOT NULL ORDER BY u.first_name ASC, u.last_name ASC");
    if ($res) while ($row = $res->fetch_assoc()) $data['students'][] = $row;
}

echo json_encode($data);
