<?php
/** send_org_message.php — org sends a message to OSA */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId   = (int)$_SESSION['org_id'];
$message = trim($_POST['message'] ?? '');
if (!$message) { echo json_encode(['success'=>false,'message'=>'Message is empty']); exit; }

$stmt = $conn->prepare("INSERT INTO org_messages (OrgId, SenderType, SenderId, Message, SentAt) VALUES (?, 'org', ?, ?, NOW())");
$stmt->bind_param('iis', $orgId, $orgId, $message);
$stmt->execute()
    ? print json_encode(['success'=>true,'message'=>'Sent','id'=>$stmt->insert_id,'sent_at'=>date('Y-m-d H:i:s')])
    : print json_encode(['success'=>false,'message'=>$conn->error]);
$stmt->close();
