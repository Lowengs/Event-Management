<?php
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) || $_SESSION['role'] !== 'osa') { 
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); 
    exit; 
}

$id = (int)$_POST['AnnouncementId'];
$st = $_POST['Status'];

if (!in_array($st, ['approved', 'rejected'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid status']); 
    exit;
}

$stmt = $conn->prepare("UPDATE announcement SET Status=? WHERE AnnouncementId=?");
$stmt->bind_param('si', $st, $id);

if ($stmt->execute()) {
    logAudit($conn, "OSA $st Announcement", 'osa', $_SESSION['osa_id'], 'success', ['id'=>$id]);
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'message'=>$conn->error]);
}
$stmt->close();