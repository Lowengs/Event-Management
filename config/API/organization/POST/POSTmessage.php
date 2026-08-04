<?php
/**
 * Organization API: Send Message to OSA
 * Endpoint: /config/API/endpoints/index.php?action=POSTmessage
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orgId   = (int)$_SESSION['org_id'];
$message = trim($_POST['message'] ?? '');

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message content is required']);
    exit;
}

try {
    $stmt = $conn->prepare("INSERT INTO org_messages (OrgId, SenderType, SenderId, Message, IsRead, SentAt) VALUES (?, 'org', ?, ?, 0, NOW())");
    $stmt->bind_param("iis", $orgId, $orgId, $message);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
