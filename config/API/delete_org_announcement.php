<?php
/** delete_org_announcement.php */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId = (int)$_SESSION['org_id'];
$id    = (int)($_POST['AnnouncementId'] ?? 0);
if (!$id) { echo json_encode(['success'=>false,'message'=>'ID required']); exit; }

$check = $conn->prepare("SELECT OrgId FROM announcement WHERE AnnouncementId=?");
$check->bind_param('i', $id);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$check->close();

if (!$row) {
    echo json_encode(['success'=>false,'message'=>'Announcement not found']);
    exit;
}

if ((int)$row['OrgId'] !== $orgId) {
    echo json_encode(['success'=>false,'message'=>'You can only delete announcements created by your organization']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM announcement WHERE AnnouncementId=? AND OrgId=?");
$stmt->bind_param('ii',$id,$orgId);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(['success'=>true,'message'=>'Deleted']);
} else {
    echo json_encode(['success'=>false,'message'=>'Delete failed']);
}
$stmt->close();
