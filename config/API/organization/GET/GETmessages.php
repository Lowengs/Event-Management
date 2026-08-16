<?php
/**
 * Organization API: GET Messages
 * Endpoint: /config/API/endpoints/index.php?action=GETmessages
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'messages' => [], 'unread' => 0, 'message' => 'Organization login required']);
    if ($isDirectApiCall) exit;
    return;
}

$orgId = (int)$_SESSION['org_id'];

try {
    $stmt = $conn->prepare("
        SELECT MessageId, OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt
        FROM org_messages
        WHERE OrgId = ?
        ORDER BY SentAt ASC
    ");
    $stmt->bind_param("i", $orgId);
    $stmt->execute();
    $q = $stmt->get_result();

    $messages = [];
    $unread = 0;
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $messages[] = $row;
            if ($row['SenderType'] !== 'org' && (int)$row['IsRead'] === 0) {
                $unread++;
            }
        }
    }

    // Automatically mark all incoming messages from OSA/Students as read when viewing conversation
    $markRead = !isset($_GET['mark_read']) || $_GET['mark_read'] === '1' || $_GET['mark_read'] === 'true';
    if ($markRead && $unread > 0) {
        $conn->query("UPDATE org_messages SET IsRead = 1 WHERE OrgId = $orgId AND LOWER(SenderType) != 'org' AND IsRead = 0");
        $unread = 0;
    }

    echo json_encode([
        'success'  => true,
        'messages' => $messages,
        'unread'   => $unread
    ]);
    if ($isDirectApiCall) exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'messages' => [], 'unread' => 0, 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
?>
