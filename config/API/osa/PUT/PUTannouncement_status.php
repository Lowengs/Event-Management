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
// Retrieve title for audit logging
$annTitle = '';
$tRes = $conn->query("SELECT Title FROM announcement WHERE AnnouncementId = $announcementId LIMIT 1");
if ($tRes && $tRow = $tRes->fetch_assoc()) {
    $annTitle = $tRow['Title'];
}

$stmt = $conn->prepare('UPDATE announcement SET Status = ? WHERE AnnouncementId = ?');
if (!$stmt) { echo json_encode(['success'=>false,'message'=>$conn->error]); exit; }
$stmt->bind_param('si', $status, $announcementId);

if ($stmt->execute()) {
    $stmt->close();
    if (file_exists(__DIR__ . '/../../../audit.php')) {
        require_once __DIR__ . '/../../../audit.php';
        $osaId = (int)($_SESSION['osa_id'] ?? $_SESSION['admin_id'] ?? 1);
        $actionTitle = ($status === 'approved') ? 'Approve Announcement' : (($status === 'rejected') ? 'Reject Announcement' : 'Update Announcement');
        logAudit($conn, $actionTitle, 'osa', $osaId, 'success', [
            'AnnouncementId' => $announcementId,
            'Title'          => $annTitle,
            'Status'         => $status
        ]);
    }
    echo json_encode(['success'=>true, 'message'=>'Announcement ' . $status . ' successfully']);
} else {
    echo json_encode(['success'=>false, 'message'=>$stmt->error]);
    $stmt->close();
}
?>
