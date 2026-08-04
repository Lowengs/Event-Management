<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) { echo json_encode(['success'=>false,'message'=>'OSA administrator login required']); exit; }
$orgId = (int)($_POST['org_id'] ?? $_POST['to_org_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? $_POST['body'] ?? '');
if (!$orgId || $message === '') { echo json_encode(['success'=>false,'message'=>'Organization and message are required']); exit; }
$osaId = (int)($_SESSION['osa_id'] ?? 0);
$stmt = $conn->prepare("INSERT INTO org_messages (OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt) VALUES (?, 'osa', ?, ?, ?, 0, NOW())");
if (!$stmt) { echo json_encode(['success'=>false,'message'=>$conn->error]); exit; }
$stmt->bind_param('iiss', $orgId, $osaId, $subject, $message);
echo json_encode($stmt->execute() ? ['success'=>true,'message'=>'Message sent successfully'] : ['success'=>false,'message'=>$stmt->error]);
$stmt->close();
?>
