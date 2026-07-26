<?php
/** create_org_announcement.php */
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId    = (int)$_SESSION['org_id'];
$title    = trim($_POST['Title'] ?? '');
$body     = trim($_POST['Body'] ?? '');
$category = trim($_POST['Category'] ?? 'General Notice');
$audience = trim($_POST['Audience'] ?? 'All Members');
$datePst  = !empty($_POST['DatePosted']) ? $_POST['DatePosted'] : date('Y-m-d');
$dateExp  = !empty($_POST['ExpirationDate']) ? $_POST['ExpirationDate'] : null;
$status   = 'pending'; // always pending OSA approval first

if (!$title || !$body) { echo json_encode(['success'=>false,'message'=>'Title and body required']); exit; }

$stmt = $conn->prepare("INSERT INTO announcement (OrgId,Title,Body,Category,Audience,Status,DatePosted,ExpirationDate) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('isssssss', $orgId, $title, $body, $category, $audience, $status, $datePst, $dateExp);
if ($stmt->execute()) {
    logAudit($conn,'Create Announcement','org',$orgId,'success',['title'=>$title]);
    echo json_encode(['success'=>true,'message'=>'Announcement submitted for OSA approval','id'=>$stmt->insert_id]);
} else {
    echo json_encode(['success'=>false,'message'=>$conn->error]);
}
$stmt->close();
