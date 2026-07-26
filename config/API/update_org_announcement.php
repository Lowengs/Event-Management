<?php
/** update_org_announcement.php */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId = (int)$_SESSION['org_id'];
$id    = (int)($_POST['AnnouncementId'] ?? 0);
$title = trim($_POST['Title'] ?? '');
$body  = trim($_POST['Body'] ?? '');
$cat   = trim($_POST['Category'] ?? '');
$aud   = trim($_POST['Audience'] ?? '');
$datePst = !empty($_POST['DatePosted']) ? $_POST['DatePosted'] : date('Y-m-d');
$dateExp = !empty($_POST['ExpirationDate']) ? $_POST['ExpirationDate'] : null;

if (!$id) { echo json_encode(['success'=>false,'message'=>'ID required']); exit; }

$stmt = $conn->prepare("UPDATE announcement SET Title=?,Body=?,Category=?,Audience=?,Status='pending',DatePosted=?,ExpirationDate=? WHERE AnnouncementId=? AND OrgId=?");
$stmt->bind_param('ssssssii', $title,$body,$cat,$aud,$datePst,$dateExp,$id,$orgId);
if ($stmt->execute()) {
    echo json_encode(['success'=>true,'message'=>'Updated']);
} else {
    echo json_encode(['success'=>false,'message'=>$conn->error]);
}
$stmt->close();
