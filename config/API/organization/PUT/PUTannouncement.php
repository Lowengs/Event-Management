<?php
/**
 * Organization API: PUT Announcement
 * Endpoint: /config/API/endpoints/index.php?action=PUTannouncement
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$announcementId = (int)($input['AnnouncementId'] ?? $input['announcement_id'] ?? 0);
$title          = trim($input['Title'] ?? $input['title'] ?? '');
$body           = trim($input['Body'] ?? $input['body']  ?? '');
$category       = trim($input['Category'] ?? $input['category'] ?? '');
$audience       = trim($input['Audience'] ?? $input['audience'] ?? '');
$datePosted     = trim($input['DatePosted'] ?? '');
$expiry         = trim($input['ExpirationDate'] ?? '');

if (!$announcementId || empty($title) || empty($body)) {
    echo json_encode(['success' => false, 'message' => 'Announcement ID, title, and body are required']);
    exit;
}

try {
    $sql = "UPDATE announcement SET Title = ?, Body = ?";
    $types = "ss";
    $params = [$title, $body];
    
    if (!empty($category)) { $sql .= ", Category = ?"; $types .= "s"; $params[] = $category; }
    if (!empty($audience)) { $sql .= ", Audience = ?"; $types .= "s"; $params[] = $audience; }
    if (!empty($datePosted)) { $sql .= ", DatePosted = ?"; $types .= "s"; $params[] = $datePosted; }
    if (!empty($expiry)) { $sql .= ", ExpirationDate = ?"; $types .= "s"; $params[] = $expiry; }
    
    $sql .= " WHERE AnnouncementId = ? AND OrgId = ?";
    $types .= "ii";
    $params[] = $announcementId;
    $params[] = $orgId;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Announcement updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
