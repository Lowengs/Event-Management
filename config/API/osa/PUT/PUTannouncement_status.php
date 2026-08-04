<?php
/**
 * OSA API: PUT Announcement Status (Approve / Reject)
 * Endpoint: /config/API/endpoints/index.php?action=PUTannouncement_status
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$announcementId = (int)($input['AnnouncementId'] ?? $_GET['AnnouncementId'] ?? 0);
$status         = strtolower(trim($input['Status'] ?? $_GET['Status'] ?? ''));

if (!$announcementId || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Announcement ID and Status are required']);
    exit;
}

if (!in_array($status, ['approved', 'rejected', 'pending', 'draft'], true)) { echo json_encode(['success'=>false,'message'=>'Invalid announcement status']); exit; }
$stmt = $conn->prepare('UPDATE announcement SET Status = ? WHERE AnnouncementId = ?');
if (!$stmt) { echo json_encode(['success'=>false,'message'=>$conn->error]); exit; }
$stmt->bind_param('si', $status, $announcementId);
echo json_encode($stmt->execute() ? ['success'=>true,'message'=>'Announcement ' . $status . ' successfully'] : ['success'=>false,'message'=>$stmt->error]);
$stmt->close();
?>
