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

$studentId = (int)($_SESSION['student_id'] ?? 0);
$eventId   = (int)($_GET['event_id'] ?? $_REQUEST['event_id'] ?? 0);
$type      = ($_GET['type'] ?? 'pre') === 'post' ? 'post' : 'pre';

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
} catch (Exception $e) {}

if (!$ev) {
    $eventStmt = $conn->prepare('SELECT e.*, o.OrgName FROM event e LEFT JOIN organization o ON o.OrgId=e.OrgId WHERE e.EventId=? LIMIT 1');
    $eventStmt->bind_param('i', $eventId); $eventStmt->execute();
    $ev = $eventStmt->get_result()->fetch_assoc();
}

try {
    if ($stmtT = $conn->prepare("CALL sp_GetTestResults(?, ?)")) {
        $stmtT->bind_param("ii", $eventId, $studentId);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        if ($resT && $rT = $resT->fetch_assoc()) {
            if ($rT['pretest_score'] !== null)  $preResult  = ['Score' => (int)$rT['pretest_score'],  'SubmittedAt' => $rT['pretest_time']];
            if ($rT['posttest_score'] !== null) $postResult = ['Score' => (int)$rT['posttest_score'], 'SubmittedAt' => $rT['posttest_time']];
            $curRes = ($type === 'pre') ? $preResult : $postResult;
            if ($curRes) {
                $score = (int)$curRes['Score'];
                $submittedAt = $curRes['SubmittedAt'];
            }
        }
        $stmtT->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {}

// Some installations use the event_pretest/event_posttest tables directly
// instead of returning rows through sp_GetTestResults. Fall back to those
// saved submission records so completed tests never display as "Not Taken".
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

// Keep the hero score aligned with the requested result after a fallback.
$currentResult = $type === 'post' ? $postResult : $preResult;
if ($currentResult) {
    $score = (int)$currentResult['Score'];
    $submittedAt = $currentResult['SubmittedAt'];
}

$typeNeedle = $type === 'post' ? 'post' : 'pre';
$totalStmt = $conn->prepare("SELECT COUNT(q.question_id) AS total
    FROM assessments a
    JOIN assessment_questions q ON q.assessment_id = a.assessment_id
    WHERE a.event_id = ? AND LOWER(COALESCE(a.type, a.test_type, '')) LIKE CONCAT('%', ?, '%')");
if ($totalStmt) {
    $totalStmt->bind_param('is', $eventId, $typeNeedle);
    $totalStmt->execute();
    $totalQuestions = (int)(($totalStmt->get_result()->fetch_assoc()['total'] ?? 0));
    $totalStmt->close();
}

echo json_encode([
        'success'      => true,
        'event'        => $ev,
        'score'        => $score,
        'total'        => $totalQuestions,
        'submitted_at' => $submittedAt,
        'pre_result'   => $preResult,
        'post_result'  => $postResult
    ]);
if ($isDirectApiCall) exit;
?>

