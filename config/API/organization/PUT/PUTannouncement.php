<?php
/**
 * Organization API: PUT Announcement
 * Endpoint: /config/API/endpoints/index.php?action=PUTannouncement
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json; charset=utf-8');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    if ($isDirectApiCall) exit;
    return;
}

$orgId = (int)$_SESSION['org_id'];

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    parse_str($raw, $input);
}
if (!empty($_POST)) {
    $input = array_merge($input ?: [], $_POST);
}

$announcementId = (int)($input['AnnouncementId'] ?? $input['announcement_id'] ?? $input['id'] ?? 0);
$title          = trim($input['Title'] ?? $input['title'] ?? '');
$body           = trim($input['Body'] ?? $input['body']  ?? '');
$category       = trim($input['Category'] ?? $input['category'] ?? '');
$audience       = trim($input['Audience'] ?? $input['audience'] ?? '');
$datePosted     = trim($input['DatePosted'] ?? $input['date_posted'] ?? '');
$expiry         = trim($input['ExpirationDate'] ?? $input['expiration_date'] ?? $input['expiry'] ?? '');

if (!$announcementId || empty($title) || empty($body)) {
    echo json_encode(['success' => false, 'message' => 'Announcement ID, title, and body are required']);
    if ($isDirectApiCall) exit;
    return;
}

try {
    // When an announcement is edited by an organization, revert status to 'pending' for OSA re-approval
    $sql = "UPDATE announcement SET Title = ?, Body = ?, Status = 'pending'";
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
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
        } elseif (file_exists(__DIR__ . '/../../../../config/audit.php')) {
            require_once __DIR__ . '/../../../../config/audit.php';
        }
        if (function_exists('logAudit')) {
            logAudit($conn, 'Update Announcement', 'organization', $orgId, 'success', [
                'AnnouncementId' => $announcementId,
                'Title'          => $title,
                'Status'         => 'pending',
                'module'         => 'Announcements',
                'description'    => "Announcement '$title' was edited and resubmitted to OSA for approval"
            ]);
        }
        echo json_encode(['success' => true, 'message' => 'Announcement updated and resubmitted to OSA for approval']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
if ($isDirectApiCall) exit;
?>
