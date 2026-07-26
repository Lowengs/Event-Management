<?php
session_start();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!isset($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing user ID']);
    exit;
}

$userId = (int)$data['user_id'];

// Verify the user belongs to this org
$verify = $conn->prepare("SELECT status FROM user WHERE UserId = ? AND OrgId = ?");
$verify->bind_param("ii", $userId, $orgId);
$verify->execute();
$res = $verify->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found in your organization']);
    exit;
}

try {
    $conn->query("DELETE FROM face_data WHERE UserId = $userId");
    $conn->query("DELETE FROM assessment_answers WHERE student_id = $userId");
    $conn->query("DELETE FROM event_posttest WHERE UserId = $userId");
    $conn->query("DELETE FROM event_pretest WHERE UserId = $userId");
    $conn->query("DELETE FROM eventregistration WHERE UserId = $userId");
    $conn->query("DELETE FROM attendance WHERE UserId = $userId");
    $conn->query("DELETE FROM certificate WHERE UserId = $userId");
    $conn->query("DELETE FROM message WHERE SenderId = $userId OR ReceiverId = $userId");
    $conn->query("UPDATE auditlog SET UserId = NULL WHERE UserId = $userId");

    $stmt = $conn->prepare("DELETE FROM user WHERE UserId = ? AND OrgId = ?");
    $stmt->bind_param("ii", $userId, $orgId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Member deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error deleting member']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Could not delete member. Database constraints exist: ' . $e->getMessage()]);
}
