<?php
/** get_osa_org_messages.php — Admin reads messages from orgs */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$orgId = (int)($_GET['org_id'] ?? 0);

if ($orgId > 0) {
    // Return specific thread
    $conn->query("UPDATE org_messages SET IsRead=1 WHERE OrgId=$orgId AND SenderType='org'");
    $r = $conn->query("SELECT MessageId AS MsgId, OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt 
                       FROM org_messages 
                       WHERE OrgId=$orgId 
                       ORDER BY SentAt ASC");
    $messages = [];
    if ($r) while ($row = $r->fetch_assoc()) $messages[] = $row;
    echo json_encode(['success'=>true,'messages'=>$messages]);
} else {
    // Return summary of all conversations
    $r = $conn->query("SELECT o.OrgId, o.OrgName, 
                         (SELECT Message FROM org_messages WHERE OrgId=o.OrgId ORDER BY SentAt DESC LIMIT 1) AS last_message,
                         (SELECT SentAt FROM org_messages WHERE OrgId=o.OrgId ORDER BY SentAt DESC LIMIT 1) AS last_time,
                         (SELECT COUNT(*) FROM org_messages WHERE OrgId=o.OrgId AND SenderType='org' AND IsRead=0) AS unread
                       FROM organization o
                       WHERE EXISTS (SELECT 1 FROM org_messages WHERE OrgId=o.OrgId)
                       ORDER BY last_time DESC");
    $conv = [];
    if ($r) while ($row = $r->fetch_assoc()) $conv[] = $row;
    echo json_encode(['success'=>true,'conversations'=>$conv]);
}
