<?php
/**
 * Student API: GET Test Results
 * Uses Stored Procedures: sp_GetTestResults & sp_GetEventDetail
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$studentId    = (int)($_SESSION['student_id'] ?? 0);
$eventId      = (int)($_GET['event_id'] ?? $_REQUEST['event_id'] ?? 0);
$type         = ($_GET['type'] ?? 'pre') === 'post' ? 'post' : 'pre';
$assessmentId = (int)($_GET['assessment_id'] ?? $_REQUEST['assessment_id'] ?? 0);

if (!$studentId || !$eventId) {
    echo json_encode(['success' => false, 'message' => 'Student ID and Event ID required']);
    if ($isDirectApiCall) exit;
    return;
}

$ev = null;
$score = 0;
$submittedAt = date('Y-m-d H:i:s');
$preResult = null;
$postResult = null;
$totalQuestions = 0;

try {
    if ($stmtE = $conn->prepare("CALL sp_GetEventDetail(?)")) {
        $stmtE->bind_param("i", $eventId);
        $stmtE->execute();
        $resE = $stmtE->get_result();
        if ($resE) $ev = $resE->fetch_assoc();
        $stmtE->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {}

if (!$ev) {
    $eventStmt = $conn->prepare('SELECT e.*, o.OrgName FROM event e LEFT JOIN organization o ON o.OrgId=e.OrgId WHERE e.EventId=? LIMIT 1');
    if ($eventStmt) {
        $eventStmt->bind_param('i', $eventId); 
        $eventStmt->execute();
        $ev = $eventStmt->get_result()->fetch_assoc();
        $eventStmt->close();
    }
}

try {
    if ($stmtT = $conn->prepare("CALL sp_GetTestResults(?, ?)")) {
        $stmtT->bind_param("ii", $eventId, $studentId);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        if ($resT && $rT = $resT->fetch_assoc()) {
            if ($rT['pretest_score'] !== null)  $preResult  = ['Score' => (int)$rT['pretest_score'],  'SubmittedAt' => $rT['pretest_time']];
            if ($rT['posttest_score'] !== null) $postResult = ['Score' => (int)$rT['posttest_score'], 'SubmittedAt' => $rT['posttest_time']];
        }
        $stmtT->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {}

// Fallback 1: event_pretest / event_posttest tables
if ($preResult === null) {
    $preStmt = $conn->prepare('SELECT Score, SubmittedAt FROM event_pretest WHERE EventId = ? AND UserId = ? ORDER BY SubmittedAt DESC LIMIT 1');
    if ($preStmt) {
        $preStmt->bind_param('ii', $eventId, $studentId);
        $preStmt->execute();
        if ($row = $preStmt->get_result()->fetch_assoc()) $preResult = $row;
        $preStmt->close();
    }
}
if ($postResult === null) {
    $postStmt = $conn->prepare('SELECT Score, SubmittedAt FROM event_posttest WHERE EventId = ? AND UserId = ? ORDER BY SubmittedAt DESC LIMIT 1');
    if ($postStmt) {
        $postStmt->bind_param('ii', $eventId, $studentId);
        $postStmt->execute();
        if ($row = $postStmt->get_result()->fetch_assoc()) $postResult = $row;
        $postStmt->close();
    }
}

// Fallback 2: preposttest table
if ($preResult === null) {
    $ppStmt = $conn->prepare("SELECT Score, CompletedAt AS SubmittedAt FROM preposttest WHERE EventId = ? AND StudentId = ? AND LOWER(TestType) = 'pre' ORDER BY CompletedAt DESC LIMIT 1");
    if ($ppStmt) {
        $ppStmt->bind_param('ii', $eventId, $studentId);
        $ppStmt->execute();
        if ($row = $ppStmt->get_result()->fetch_assoc()) $preResult = $row;
        $ppStmt->close();
    }
}
if ($postResult === null) {
    $ppStmt = $conn->prepare("SELECT Score, CompletedAt AS SubmittedAt FROM preposttest WHERE EventId = ? AND StudentId = ? AND LOWER(TestType) = 'post' ORDER BY CompletedAt DESC LIMIT 1");
    if ($ppStmt) {
        $ppStmt->bind_param('ii', $eventId, $studentId);
        $ppStmt->execute();
        if ($row = $ppStmt->get_result()->fetch_assoc()) $postResult = $row;
        $ppStmt->close();
    }
}

// Fallback 3: assessment_responses table
if ($preResult === null) {
    $arStmt = $conn->prepare("SELECT ar.score AS Score, ar.submitted_at AS SubmittedAt FROM assessment_responses ar JOIN assessments a ON a.assessment_id = ar.assessment_id WHERE a.event_id = ? AND ar.user_id = ? AND LOWER(COALESCE(a.type, a.test_type, '')) LIKE '%pre%' ORDER BY ar.submitted_at DESC LIMIT 1");
    if ($arStmt) {
        $arStmt->bind_param('ii', $eventId, $studentId);
        $arStmt->execute();
        if ($row = $arStmt->get_result()->fetch_assoc()) $preResult = $row;
        $arStmt->close();
    }
}
if ($postResult === null) {
    $arStmt = $conn->prepare("SELECT ar.score AS Score, ar.submitted_at AS SubmittedAt FROM assessment_responses ar JOIN assessments a ON a.assessment_id = ar.assessment_id WHERE a.event_id = ? AND ar.user_id = ? AND LOWER(COALESCE(a.type, a.test_type, '')) LIKE '%post%' ORDER BY ar.submitted_at DESC LIMIT 1");
    if ($arStmt) {
        $arStmt->bind_param('ii', $eventId, $studentId);
        $arStmt->execute();
        if ($row = $arStmt->get_result()->fetch_assoc()) $postResult = $row;
        $arStmt->close();
    }
}

// Align hero score with the requested result
$currentResult = $type === 'post' ? $postResult : $preResult;
if ($currentResult) {
    $score = (int)$currentResult['Score'];
    $submittedAt = $currentResult['SubmittedAt'];
}

// Calculate Total Questions
$typeNeedle = $type === 'post' ? 'post' : 'pre';
if ($assessmentId > 0) {
    $totalStmt = $conn->prepare("SELECT COUNT(question_id) AS total FROM assessment_questions WHERE assessment_id = ?");
    if ($totalStmt) {
        $totalStmt->bind_param('i', $assessmentId);
        $totalStmt->execute();
        $totalQuestions = (int)(($totalStmt->get_result()->fetch_assoc()['total'] ?? 0));
        $totalStmt->close();
    }
}

if ($totalQuestions === 0) {
    $totalStmt = $conn->prepare("SELECT COUNT(q.question_id) AS total
        FROM assessments a
        JOIN assessment_questions q ON q.assessment_id = a.assessment_id
        WHERE a.event_id = ? AND (LOWER(COALESCE(a.type, a.test_type, '')) LIKE CONCAT('%', ?, '%'))");
    if ($totalStmt) {
        $totalStmt->bind_param('is', $eventId, $typeNeedle);
        $totalStmt->execute();
        $totalQuestions = (int)(($totalStmt->get_result()->fetch_assoc()['total'] ?? 0));
        $totalStmt->close();
    }
}

if ($totalQuestions === 0) {
    // Fallback: check count from student_question_responses
    $sqrCount = $conn->prepare("SELECT COUNT(*) AS total FROM student_question_responses WHERE student_id = ? AND assessment_id = ?");
    if ($sqrCount && $assessmentId > 0) {
        $sqrCount->bind_param('ii', $studentId, $assessmentId);
        $sqrCount->execute();
        $totalQuestions = (int)(($sqrCount->get_result()->fetch_assoc()['total'] ?? 0));
        $sqrCount->close();
    }
}
if ($totalQuestions === 0) $totalQuestions = 1;

// Attendance check-in / check-out times
$attLogIn = null;
$attLogOut = null;
$attStmt = $conn->prepare("SELECT Timestamp, LogType FROM attendance WHERE EventId = ? AND UserId = ? ORDER BY AttendanceId ASC");
if ($attStmt) {
    $attStmt->bind_param("ii", $eventId, $studentId);
    $attStmt->execute();
    $attRes = $attStmt->get_result();
    while ($attRow = $attRes->fetch_assoc()) {
        $lt = strtolower($attRow['LogType'] ?? '');
        if (strpos($lt, 'in') !== false && !$attLogIn) {
            $attLogIn = ['Timestamp' => $attRow['Timestamp']];
        } elseif (strpos($lt, 'out') !== false) {
            $attLogOut = ['Timestamp' => $attRow['Timestamp']];
        }
    }
    $attStmt->close();
}

echo json_encode([
    'success'      => true,
    'event'        => $ev,
    'score'        => $score,
    'total'        => $totalQuestions,
    'submitted_at' => $submittedAt,
    'pre_result'   => $preResult,
    'post_result'  => $postResult,
    'att_login'    => $attLogIn,
    'att_logout'   => $attLogOut
]);
if ($isDirectApiCall) exit;
?>
