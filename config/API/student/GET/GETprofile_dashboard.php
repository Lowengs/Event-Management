<?php
/**
 * Student API: GET Profile Dashboard Data
 * Endpoint: /config/API/endpoints/index.php?action=GETprofile_dashboard
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$studentId = (int)($_SESSION['student_id'] ?? 0);
if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    if ($isDirectApiCall) exit;
    return;
}

$regCount    = 0;
$attCount    = 0;
$preregCount = 0;
$takenTests  = [];
$createdAssessments = [];
$regs  = [];
$certs = [];

$stats = $conn->query("
    SELECT
        (SELECT COUNT(DISTINCT EventId) FROM eventregistration WHERE UserId = $studentId) AS registered,
        (SELECT COUNT(DISTINCT EventId) FROM attendance WHERE UserId = $studentId AND LOWER(COALESCE(LogType, '')) = 'log out') AS attended
");
if ($stats && $row = $stats->fetch_assoc()) {
    $regCount = (int)$row['registered'];
    $attCount = (int)$row['attended'];
    $preregCount = $regCount;
}

$registrations = $conn->query("
    SELECT er.RegistrationId, er.DateIssued, e.*, o.OrgName,
           (SELECT MIN(a.AttendanceId) FROM attendance a WHERE a.EventId = e.EventId AND a.UserId = $studentId) AS AttendanceId,
           (SELECT MAX(a.Timestamp) FROM attendance a WHERE a.EventId = e.EventId AND a.UserId = $studentId AND LOWER(a.LogType) = 'log in') AS login_time,
           (SELECT MAX(a.Timestamp) FROM attendance a WHERE a.EventId = e.EventId AND a.UserId = $studentId AND LOWER(a.LogType) = 'log out') AS logout_time,
           'Registered' AS RegStatus
      FROM eventregistration er
      JOIN event e ON e.EventId = er.EventId
      LEFT JOIN organization o ON o.OrgId = e.OrgId
     WHERE er.UserId = $studentId
     ORDER BY e.EventDateTime DESC
");
if ($registrations) {
    while ($row = $registrations->fetch_assoc()) {
        $regs[] = $row;
    }
}

// Build the per-event test state expected by the registration cards.
$testRows = $conn->query("
    SELECT EventId, 'pretest' AS test_type, Score, SubmittedAt
      FROM event_pretest
     WHERE UserId = $studentId
    UNION ALL
    SELECT EventId, 'posttest' AS test_type, Score, SubmittedAt
      FROM event_posttest
     WHERE UserId = $studentId
");
if ($testRows) {
    while ($row = $testRows->fetch_assoc()) {
        $eventId = (int)$row['EventId'];
        $takenTests[$eventId][$row['test_type']] = $row;
    }
}

$assessmentRows = $conn->query("
    SELECT event_id, type, assessment_id, title
      FROM assessments
     WHERE type IN ('pretest', 'posttest')
       AND status = 'published'
");
if ($assessmentRows) {
    while ($row = $assessmentRows->fetch_assoc()) {
        $eventId = (int)$row['event_id'];
        $createdAssessments[$eventId][$row['type']] = $row;
    }
}

$certificateRows = $conn->query("
    SELECT c.*, e.EventName, e.EventDateTime, o.OrgName
      FROM certificates c
      LEFT JOIN event e ON e.EventId = c.EventId
      LEFT JOIN organization o ON o.OrgId = e.OrgId
     WHERE c.UserId = $studentId
     ORDER BY c.IssuedAt DESC
");
if ($certificateRows) {
    while ($row = $certificateRows->fetch_assoc()) {
        $certs[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'stats' => [
        'registered'    => $regCount,
        'attended'      => $attCount,
        'preregistered' => $preregCount,
        'certificates'  => count($certs)
    ],
    'taken_tests'         => $takenTests,
    'created_assessments' => $createdAssessments,
    'registrations'       => $regs,
    'certificates'        => $certs
]);
if ($isDirectApiCall) exit;
?>

