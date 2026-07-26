<?php
/**
 * get_event_participants.php
 * Returns pre-test and post-test participation data for a given event.
 * Accessible by both org (org_id session) and OSA (osa_id session).
 */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

$isOrg = !empty($_SESSION['org_id']);
$isOsa = !empty($_SESSION['osa_id']);

if (!$isOrg && !$isOsa) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'event_id required']); exit;
}

// Verify org owns this event (if org session)
if ($isOrg) {
    $orgId = (int)$_SESSION['org_id'];
    $check = $conn->query("SELECT EventId FROM event WHERE EventId=$eventId AND OrgId=$orgId");
    if (!$check || $check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Event not found for your organization']); exit;
    }
}

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS event_pretest (
    TestId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
    Q1 VARCHAR(10), Q2 VARCHAR(10), Q3 VARCHAR(10), Q4 VARCHAR(10), Q5 VARCHAR(10),
    Score INT DEFAULT 0, SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$conn->query("CREATE TABLE IF NOT EXISTS event_posttest (
    TestId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
    Q1 VARCHAR(10), Q2 VARCHAR(10), Q3 VARCHAR(10), Q4 VARCHAR(10), Q5 VARCHAR(10),
    Score INT DEFAULT 0, SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

// Stats
$totalReg  = (int)$conn->query("SELECT COUNT(*) FROM eventregistration WHERE EventId=$eventId")->fetch_row()[0];
$preDone   = (int)$conn->query("SELECT COUNT(*) FROM event_pretest  WHERE EventId=$eventId")->fetch_row()[0];
$postDone  = (int)$conn->query("SELECT COUNT(*) FROM event_posttest WHERE EventId=$eventId")->fetch_row()[0];
$avgPre    = 0;
$avgPost   = 0;

$r = $conn->query("SELECT AVG(Score) avg FROM event_pretest  WHERE EventId=$eventId");
if ($r) $avgPre  = round((float)$r->fetch_assoc()['avg'], 1);
$r = $conn->query("SELECT AVG(Score) avg FROM event_posttest WHERE EventId=$eventId");
if ($r) $avgPost = round((float)$r->fetch_assoc()['avg'], 1);

$filter = $_GET['filter'] ?? '';
$whereClause = "er.EventId = $eventId";

if ($filter === 'present') {
    $whereClause .= " AND u.UserId IN (SELECT UserId FROM attendance WHERE EventId = $eventId AND AttendanceStatus = 'present')";
}

// Participant list (join pretest + posttest + registration + user)
$participants = [];
$q = $conn->query("
    SELECT
        u.UserId, u.first_name, u.last_name, u.student_id, u.course, u.year_level,
        pt.Score  AS PreScore,  pt.SubmittedAt  AS PreDate,
        ps.Score  AS PostScore, ps.SubmittedAt  AS PostDate,
        er.DateIssued AS RegDate
    FROM eventregistration er
    JOIN user u ON u.UserId = er.UserId
    LEFT JOIN event_pretest  pt ON pt.EventId = er.EventId AND pt.UserId = er.UserId
    LEFT JOIN event_posttest ps ON ps.EventId = er.EventId AND ps.UserId = er.UserId
    WHERE $whereClause
    ORDER BY u.last_name, u.first_name
");
if ($q) {
    while ($row = $q->fetch_assoc()) {
        $participants[] = [
            'UserId'    => $row['UserId'],
            'Name'      => trim($row['first_name'] . ' ' . $row['last_name']),
            'StudentId' => $row['student_id'] ?: '—',
            'Course'    => $row['course'] ?: '—',
            'Year'      => $row['year_level'] ?: '—',
            'RegDate'   => $row['RegDate']  ? date('M j, Y', strtotime($row['RegDate']))  : '—',
            'PreScore'  => $row['PreScore']  !== null ? (int)$row['PreScore']  : null,
            'PostScore' => $row['PostScore'] !== null ? (int)$row['PostScore'] : null,
            'PreDate'   => $row['PreDate']  ? date('M j g:i A', strtotime($row['PreDate']))  : null,
            'PostDate'  => $row['PostDate'] ? date('M j g:i A', strtotime($row['PostDate'])) : null,
        ];
    }
}

echo json_encode([
    'success'      => true,
    'event_id'     => $eventId,
    'stats'        => [
        'registered' => $totalReg,
        'pre_done'   => $preDone,
        'post_done'  => $postDone,
        'avg_pre'    => $avgPre,
        'avg_post'   => $avgPost,
    ],
    'participants' => $participants,
]);
