<?php
/** send_osa_message.php — OSA replies to an org */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$osaId   = (int)$_SESSION['osa_id'];
$orgId   = (int)($_POST['OrgId'] ?? 0);
$message = trim($_POST['message'] ?? '');
if (!$orgId || !$message) { echo json_encode(['success'=>false,'message'=>'OrgId and message required']); exit; }

$stmt = $conn->prepare("INSERT INTO org_messages (OrgId, SenderType, SenderId, Message, SentAt) VALUES (?, 'osa', ?, ?, NOW())");
$stmt->bind_param('iis',$orgId,$osaId,$message);
$stmt->execute()
    ? print json_encode(['success'=>true,'message'=>'Sent','id'=>$stmt->insert_id,'sent_at'=>date('Y-m-d H:i:s')])
    : print json_encode(['success'=>false,'message'=>$conn->error]);
$stmt->close();
