<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

$data = ['success' => true, 'organizations' => []];

if ($conn) {
    $q = "
        SELECT o.*,
            (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId AND u.Role = 'member') AS member_count,
            (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId AND u.Role IN ('admin','officer')) AS officers_count,
            (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId) AS event_count,
            (SELECT u.Name FROM user u WHERE u.OrgId = o.OrgId AND u.Position = 'President' LIMIT 1) AS president_name,
            (SELECT u.Name FROM user u WHERE u.OrgId = o.OrgId AND u.Position = 'Vice President' LIMIT 1) AS vp_name,
            (SELECT GROUP_CONCAT(t.Type SEPARATOR ', ') FROM orgtype t WHERE t.OrgId = o.OrgId) AS org_type
        FROM organization o
        WHERE o.Status = 'Active' OR o.Status IS NULL
        ORDER BY o.OrgName ASC
    ";
    $r = $conn->query($q);
    if ($r) while ($row = $r->fetch_assoc()) $data['organizations'][] = $row;
}

echo json_encode($data);
