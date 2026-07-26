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

$title = trim($_POST['Title'] ?? '');
$body = trim($_POST['Body'] ?? '');
$audience = trim($_POST['Audience'] ?? 'all_org');
$orgId = isset($_POST['OrgId']) && $_POST['OrgId'] !== '' ? (int)$_POST['OrgId'] : null;

if ($title === '' || $body === '') {
    echo json_encode(['success' => false, 'message' => 'Title and content are required']);
    exit;
}

$allowedAudiences = ['by_org', 'all_org', 'students', 'all'];
if (!in_array($audience, $allowedAudiences, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid audience selection']);
    exit;
}

if ($audience !== 'by_org') {
    $orgId = null;
}

if ($audience === 'by_org' && !$orgId) {
    echo json_encode(['success' => false, 'message' => 'Please choose an organization']);
    exit;
}

$status = 'approved';
$category = 'General Notice';
$datePosted = date('Y-m-d');
$expirationDate = null;

if ($orgId === null) {
    $stmt = $conn->prepare("INSERT INTO announcement (OrgId, Title, Body, Category, Audience, Status, DatePosted, ExpirationDate) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssssss', $title, $body, $category, $audience, $status, $datePosted, $expirationDate);
} else {
    $stmt = $conn->prepare("INSERT INTO announcement (OrgId, Title, Body, Category, Audience, Status, DatePosted, ExpirationDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssssss', $orgId, $title, $body, $category, $audience, $status, $datePosted, $expirationDate);
}

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

if ($stmt->execute()) {
    logAudit($conn, 'Create Announcement', 'osa', $_SESSION['osa_id'], 'success', [
        'title' => $title,
        'audience' => $audience,
        'org_id' => $orgId,
    ]);
    echo json_encode(['success' => true, 'message' => 'Announcement created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error ?: $conn->error]);
}

$stmt->close();