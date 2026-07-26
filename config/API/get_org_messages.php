<?php
/** get_org_messages.php — chat messages between org and OSA */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId = (int)$_SESSION['org_id'];

// Mark osa messages as read (osa sent → org is reading them)
$conn->query("UPDATE org_messages SET IsRead=1 WHERE OrgId=$orgId AND SenderType='osa'");

$messages = [];
$r = $conn->query("SELECT MessageId AS MsgId, OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt 
                   FROM org_messages 
                   WHERE OrgId=$orgId 
                   ORDER BY SentAt ASC LIMIT 100");
if ($r) {
    while($row=$r->fetch_assoc()) $messages[] = $row;
}

$unread = (int)($conn->query("SELECT COUNT(*) FROM org_messages WHERE OrgId=$orgId AND SenderType='osa' AND IsRead=0")->fetch_row()[0] ?? 0);

echo json_encode(['success'=>true,'messages'=>$messages,'unread'=>$unread]);
