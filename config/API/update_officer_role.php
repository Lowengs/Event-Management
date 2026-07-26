<?php
/** update_officer_role.php — set/clear officer role for a member */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId  = (int)$_SESSION['org_id'];
$userId = (int)($_POST['UserId'] ?? 0);
$role   = trim($_POST['officer_role'] ?? '');
$isOfficer = $role !== '' ? 1 : 0;

if (!$userId) { echo json_encode(['success'=>false,'message'=>'User ID required']); exit; }

$stmt = $conn->prepare("UPDATE user SET officer_role=?, is_officer=? WHERE UserId=? AND OrgId=?");
$stmt->bind_param('siii', $role, $isOfficer, $userId, $orgId);
$stmt->execute()
    ? print json_encode(['success'=>true,'message'=>'Officer role updated'])
    : print json_encode(['success'=>false,'message'=>$conn->error]);
$stmt->close();
