<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
if (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT) header('Content-Type: application/json');
if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success'=>false,'message'=>'OSA administrator login required']); exit;
}
$items = [];
$res = $conn->query('SELECT a.*, o.OrgName FROM announcement a LEFT JOIN organization o ON o.OrgId=a.OrgId ORDER BY a.DatePosted DESC, a.AnnouncementId DESC');
if ($res) while ($row = $res->fetch_assoc()) $items[] = $row;
echo json_encode(['success'=>true,'data'=>$items,'announcements'=>$items]);
?>
