<?php
/** update_org_password.php */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId      = (int)$_SESSION['org_id'];
$current    = $_POST['current_password'] ?? '';
$newPass    = $_POST['new_password'] ?? '';
$confirm    = $_POST['confirm_password'] ?? '';

if (!$current || !$newPass || !$confirm) { echo json_encode(['success'=>false,'message'=>'All fields required']); exit; }
if ($newPass !== $confirm) { echo json_encode(['success'=>false,'message'=>'Passwords do not match']); exit; }
if (strlen($newPass) < 8) { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters']); exit; }

$row = $conn->query("SELECT Password FROM organization WHERE OrgId=$orgId")->fetch_assoc();
if (!$row || !password_verify($current, $row['Password'])) {
    echo json_encode(['success'=>false,'message'=>'Current password is incorrect']); exit;
}

$hash = password_hash($newPass, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE organization SET Password=? WHERE OrgId=?");
$stmt->bind_param('si', $hash, $orgId);
$stmt->execute()
    ? print json_encode(['success'=>true,'message'=>'Password updated successfully'])
    : print json_encode(['success'=>false,'message'=>$conn->error]);
$stmt->close();
