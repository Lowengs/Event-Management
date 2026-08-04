<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Organization login required']); exit; }
$orgId = (int)$_SESSION['org_id']; $eventId = (int)($_GET['event_id'] ?? $_GET['EventId'] ?? 0);
if (!$eventId) { echo json_encode(['success'=>false,'message'=>'Event ID is required']); exit; }
$participants=[];
$sql = "SELECT DISTINCT u.UserId, u.student_id AS student_number, u.first_name, u.last_name, u.Email FROM attendance a JOIN `user` u ON u.UserId=a.UserId JOIN event e ON e.EventId=a.EventId WHERE a.EventId=? AND e.OrgId=? AND LOWER(COALESCE(a.AttendanceStatus, 'present'))='present'";
$stmt=$conn->prepare($sql);
if ($stmt) { $stmt->bind_param('ii',$eventId,$orgId); $stmt->execute(); $res=$stmt->get_result(); if($res) while($row=$res->fetch_assoc()) $participants[]=$row; $stmt->close(); }
echo json_encode(['success'=>true,'participants'=>$participants,'count'=>count($participants),'total'=>count($participants)]);
?>
