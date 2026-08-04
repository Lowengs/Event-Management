<?php
/**
 * OSA API: POST Announcement (Create)
 * Endpoint: /config/API/endpoints/index.php?action=POSTannouncement
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    exit;
}

$title    = trim($_POST['Title']    ?? $_POST['title'] ?? '');
$body     = trim($_POST['Body']     ?? $_POST['body']  ?? '');
$audience = trim($_POST['Audience'] ?? $_POST['audience'] ?? 'all');
$orgId    = !empty($_POST['OrgId']) ? (int)$_POST['OrgId'] : null;

if (empty($title) || empty($body)) {
    echo json_encode(['success' => false, 'message' => 'Title and content are required']);
    exit;
}

try {
    $sql = !empty($orgId)
        ? "INSERT INTO announcement (OrgId, Title, Body, Audience, Status, DatePosted, CreatedAt) VALUES (?, ?, ?, ?, 'approved', NOW(), NOW())"
        : "INSERT INTO announcement (OrgId, Title, Body, Audience, Status, DatePosted, CreatedAt) VALUES (NULL, ?, ?, ?, 'approved', NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception($conn->error);
    if (!empty($orgId)) $stmt->bind_param('isss', $orgId, $title, $body, $audience);
    else $stmt->bind_param('sss', $title, $body, $audience);

    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Announcement created and published successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
