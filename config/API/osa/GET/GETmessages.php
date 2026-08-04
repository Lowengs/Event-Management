<?php
/**
 * OSA API: GET Messages
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../img_helpers.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$orgId = (int)($_GET['org_id'] ?? 0);

// Fetch all organizations to form conversations list
$conversations = [];
$or = $conn->query("SELECT o.OrgId, o.OrgName, o.OrgPicture,
    (SELECT COUNT(*) FROM org_messages m WHERE m.OrgId = o.OrgId AND m.SenderType = 'org' AND m.IsRead = 0) AS unread_count,
    (SELECT Message FROM org_messages m WHERE m.OrgId = o.OrgId ORDER BY m.SentAt DESC LIMIT 1) AS last_message,
    (SELECT Subject FROM org_messages m WHERE m.OrgId = o.OrgId ORDER BY m.SentAt DESC LIMIT 1) AS last_subject,
    (SELECT SentAt FROM org_messages m WHERE m.OrgId = o.OrgId ORDER BY m.SentAt DESC LIMIT 1) AS last_sent_at
    FROM organization o
    ORDER BY o.OrgName ASC");

if ($or) {
    while ($row = $or->fetch_assoc()) {
        $row['OrgPicture'] = imgPathForDepth($row['OrgPicture'] ?? '', 2, '../../assets/img/philsca.png');
        $conversations[] = $row;
    }
}

if (!$orgId && !empty($conversations)) {
    $orgId = (int)$conversations[0]['OrgId'];
}

$selectedOrgName = '';
foreach ($conversations as $c) {
    if ((int)$c['OrgId'] === $orgId) {
        $selectedOrgName = $c['OrgName'];
        break;
    }
}

// Fetch thread for active org
$thread = [];
if ($orgId > 0) {
    $stmt = $conn->prepare("SELECT MessageId, OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt FROM org_messages WHERE OrgId = ? ORDER BY SentAt ASC");
    if ($stmt) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) $thread[] = $r;
        }
        $stmt->close();
    }
    
    // Mark received messages as read
    $up = $conn->prepare("UPDATE org_messages SET IsRead = 1 WHERE OrgId = ? AND SenderType = 'org'");
    if ($up) {
        $up->bind_param("i", $orgId);
        $up->execute();
        $up->close();
    }
}

$totalUnread = 0;
$ur = $conn->query("SELECT COUNT(*) AS cnt FROM org_messages WHERE SenderType = 'org' AND IsRead = 0");
if ($ur && ($r = $ur->fetch_assoc())) $totalUnread = (int)$r['cnt'];

echo json_encode([
    'success'           => true,
    'conversations'     => $conversations,
    'selected_org_id'   => $orgId,
    'selected_org_name' => $selectedOrgName,
    'thread'            => $thread,
    'messages'          => $thread,
    'total_unread'      => $totalUnread
]);
if ($isDirectApiCall) exit;
?>
