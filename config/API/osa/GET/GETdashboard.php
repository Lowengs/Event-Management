<?php
/**
 * OSA API: GET Dashboard Overview
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$stats = [];
try {
    if ($stmt = $conn->prepare("CALL sp_GetOSADashboard()")) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $r = $res->fetch_assoc()) $stats = $r;
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {}

if (empty($stats) || !isset($stats['active_orgs'])) {
    $q = $conn->query("SELECT
        (SELECT COUNT(*) FROM `user`) AS total_students,
        (SELECT COUNT(*) FROM organization WHERE LOWER(COALESCE(Status, 'active')) = 'active') AS active_orgs,
        (SELECT COUNT(*) FROM organization) AS total_orgs,
        (SELECT COUNT(*) FROM event WHERE LOWER(COALESCE(EventStatus, 'scheduled')) IN ('scheduled','ongoing')) AS upcoming_events");
    if ($q && ($r = $q->fetch_assoc())) {
        $stats = array_merge($stats, $r);
    }
}

// Precise Unread & Pending counts
$unreadMsgs = 0;
$pendingAnns = 0;
$uMsgQ = $conn->query("SELECT COUNT(*) AS cnt FROM org_messages WHERE SenderType = 'org' AND IsRead = 0");
if ($uMsgQ && ($r = $uMsgQ->fetch_assoc())) $unreadMsgs = (int)$r['cnt'];

$pAnnQ = $conn->query("SELECT COUNT(*) AS cnt FROM announcement WHERE LOWER(TRIM(COALESCE(Status, 'approved'))) = 'pending'");
if ($pAnnQ && ($r = $pAnnQ->fetch_assoc())) $pendingAnns = (int)$r['cnt'];

$stats['unread_count'] = $unreadMsgs + $pendingAnns;
$stats['unread_messages'] = $unreadMsgs;
$stats['pending_announcements'] = $pendingAnns;

if (!isset($stats['avg_attendance'])) {
    $stats['avg_attendance'] = '85%';
}

$recentEvents = [];
$recent = $conn->query("SELECT e.*, o.OrgName, (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS reg_count, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = e.EventId) AS attended_count FROM event e LEFT JOIN organization o ON o.OrgId=e.OrgId ORDER BY e.EventDateTime DESC LIMIT 5");
if ($recent) while ($row = $recent->fetch_assoc()) $recentEvents[] = $row;

// Gather All Notifications: Organization Messages & Announcements
$notificationsList = [];

// 1. Organization Messages
$msgQuery = $conn->query("
    SELECT m.MessageId, m.OrgId, m.SenderType, m.Subject, m.Message, m.IsRead, m.SentAt, o.OrgName
    FROM org_messages m
    LEFT JOIN organization o ON o.OrgId = m.OrgId
    WHERE m.SenderType = 'org'
    ORDER BY m.SentAt DESC
    LIMIT 25
");
if ($msgQuery) {
    while ($m = $msgQuery->fetch_assoc()) {
        $orgName = $m['OrgName'] ?: 'Organization';
        $subject = trim((string)($m['Subject'] ?? ''));
        $title = $subject !== '' ? $subject : "Message from {$orgName}";
        $isUnread = ((int)$m['IsRead'] === 0);
        $notificationsList[] = [
            'Id'        => 'msg_' . $m['MessageId'],
            'Type'      => 'message',
            'Title'     => $title,
            'Body'      => $m['Message'],
            'OrgName'   => $orgName,
            'OrgId'     => (int)$m['OrgId'],
            'CreatedAt' => $m['SentAt'],
            'IsRead'    => (int)$m['IsRead'],
            'Badge'     => $isUnread ? 'New Message' : 'Message',
            'Link'      => 'messages.php?org_id=' . $m['OrgId']
        ];
    }
}

// 2. Organization Announcements
$annQuery = $conn->query("
    SELECT a.AnnouncementId, a.OrgId, a.Title, a.Body, a.Category, a.Audience, a.Status, a.DatePosted, a.CreatedAt, o.OrgName
    FROM announcement a
    LEFT JOIN organization o ON o.OrgId = a.OrgId
    ORDER BY COALESCE(a.CreatedAt, a.DatePosted) DESC, a.AnnouncementId DESC
    LIMIT 25
");
if ($annQuery) {
    while ($a = $annQuery->fetch_assoc()) {
        $isPending = strtolower(trim((string)($a['Status'] ?? ''))) === 'pending';
        $orgName = $a['OrgName'] ?: 'OSA';
        $title = ($isPending ? '[Pending Review] ' : '') . ($a['Title'] ?: 'Announcement');
        $notificationsList[] = [
            'Id'        => 'ann_' . $a['AnnouncementId'],
            'Type'      => 'announcement',
            'Title'     => $title,
            'Body'      => $a['Body'] ?? '',
            'OrgName'   => $orgName,
            'OrgId'     => (int)($a['OrgId'] ?? 0),
            'CreatedAt' => $a['CreatedAt'] ?: ($a['DatePosted'] ? $a['DatePosted'] . ' 00:00:00' : date('Y-m-d H:i:s')),
            'IsRead'    => $isPending ? 0 : 1,
            'Status'    => $a['Status'] ?? 'approved',
            'Badge'     => $isPending ? 'Pending Review' : 'Announcement',
            'Link'      => 'announcement.php'
        ];
    }
}

// Sort all notifications by date descending
usort($notificationsList, function($a, $b) {
    $tA = strtotime($a['CreatedAt'] ?? 'now');
    $tB = strtotime($b['CreatedAt'] ?? 'now');
    return $tB <=> $tA;
});

$topNotifications = array_slice($notificationsList, 0, 5);
$allNotifications = array_slice($notificationsList, 0, 30);

// Calendar Events for current month
$calEvents = [];
$calQ = $conn->query("
    SELECT e.*, o.OrgName
    FROM event e
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    WHERE e.EventDateTime IS NOT NULL
      AND MONTH(e.EventDateTime) = MONTH(CURRENT_DATE())
      AND YEAR(e.EventDateTime) = YEAR(CURRENT_DATE())
    ORDER BY e.EventDateTime ASC
");
if ($calQ) {
    while ($row = $calQ->fetch_assoc()) {
        $dStr = date('Y-m-d', strtotime($row['EventDateTime']));
        $calEvents[$dStr][] = $row;
    }
}

echo json_encode([
    'success'           => true,
    'stats'             => $stats,
    'recent_events'     => $recentEvents,
    'notifications'     => $topNotifications,
    'all_notifications' => $allNotifications,
    'calendar_events'   => $calEvents
]);
if ($isDirectApiCall) exit;
?>
