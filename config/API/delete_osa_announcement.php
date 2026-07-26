<?php
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) || ($_SESSION['role'] ?? '') !== 'osa') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = (int)($_POST['AnnouncementId'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
    exit;
}

$check = $conn->prepare("SELECT AnnouncementId FROM announcement WHERE AnnouncementId=?");
$check->bind_param('i', $id);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if (!$exists) {
    echo json_encode(['success' => false, 'message' => 'Announcement not found']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM announcement WHERE AnnouncementId=?");
$stmt->bind_param('i', $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    logAudit($conn, 'Delete Announcement', 'osa', $_SESSION['osa_id'], 'success', ['announcement_id' => $id]);
    echo json_encode(['success' => true, 'message' => 'Announcement deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}

$stmt->close();