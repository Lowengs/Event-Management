<?php
/** get_org_officers.php — members with officer_role set */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId = (int)$_SESSION['org_id'];

$officers = [];
$r = $conn->query("SELECT UserId,student_id,first_name,last_name,Email,course,year_level,section,status,officer_role FROM user WHERE OrgId=$orgId AND is_officer=1 ORDER BY officer_role,last_name");
if ($r) while($row=$r->fetch_assoc()) $officers[] = $row;

echo json_encode(['success'=>true,'officers'=>$officers,'total'=>count($officers)]);
