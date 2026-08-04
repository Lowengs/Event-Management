<?php
/**
 * Organization API: POST Announcement (Create)
 * Endpoint: /config/API/endpoints/index.php?action=POSTannouncement
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orgId    = (int)$_SESSION['org_id'];
$title    = trim($_POST['Title'] ?? $_POST['title'] ?? '');
$body     = trim($_POST['Body'] ?? $_POST['body'] ?? '');
$category = trim($_POST['Category'] ?? $_POST['category'] ?? 'General Notice');
$audience = trim($_POST['Audience'] ?? $_POST['audience'] ?? 'All Members');
$datePosted = trim($_POST['DatePosted'] ?? date('Y-m-d'));
$expiry   = trim($_POST['ExpirationDate'] ?? '');

if (empty($title) || empty($body)) {
    echo json_encode(['success' => false, 'message' => 'Title and announcement text are required']);
    exit;
}

// Insert directly. This avoids a missing/outdated stored procedure silently
// preventing the announcement from being created.
$sql = "INSERT INTO announcement (OrgId, Title, Body, Category, Audience, Status, DatePosted, ExpirationDate) VALUES (?, ?, ?, ?, ?, 'pending', ?, " . (!empty($expiry) ? '?' : 'NULL') . ')';
$ins = $conn->prepare($sql);
if (!$ins) {
    echo json_encode(['success' => false, 'message' => 'Could not prepare announcement: ' . $conn->error]);
    exit;
}
if (!empty($expiry)) {
    $ins->bind_param('issssss', $orgId, $title, $body, $category, $audience, $datePosted, $expiry);
} else {
    $ins->bind_param('isssss', $orgId, $title, $body, $category, $audience, $datePosted);
}
if ($ins->execute()) {
    echo json_encode(['success' => true, 'message' => 'Announcement submitted to OSA for approval']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not create announcement: ' . $ins->error]);
}
$ins->close();
?>
