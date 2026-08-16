<?php
/**
 * Organization API: PUT Mark Messages as Read
 * Endpoint: /config/API/endpoints/index.php?action=mark_org_messages_read
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    if ($isDirectApiCall) exit;
    return;
}

$orgId = (int)$_SESSION['org_id'];

try {
    $stmt = $conn->prepare("UPDATE org_messages SET IsRead = 1 WHERE OrgId = ? AND LOWER(SenderType) != 'org' AND IsRead = 0");
    $stmt->bind_param("i", $orgId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'All incoming messages marked as read',
        'affected' => $affected
    ]);
    if ($isDirectApiCall) exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
?>
