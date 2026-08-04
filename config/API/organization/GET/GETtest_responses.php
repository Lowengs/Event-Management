<?php
/**
 * Org API: GET Assessment Test Responses
 * Endpoint: /config/API/endpoints/index.php?action=get_org_test_responses
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
$assessmentId = (int)($_GET['assessment_id'] ?? 0);

if ($assessmentId === 0) {
    echo json_encode(['success' => false, 'message' => 'Assessment ID required']);
if ($isDirectApiCall) exit;
    return;
}

$stmt = $conn->prepare("
    SELECT a.*, e.EventName, e.EventDateTime,
           (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.assessment_id) as q_count
    FROM assessments a
    JOIN event e ON a.event_id = e.EventId
    WHERE a.assessment_id = ? AND e.OrgId = ?
");
$stmt->bind_param('ii', $assessmentId, $orgId);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$assessment) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found']);
if ($isDirectApiCall) exit;
    return;
}

$eventId = $assessment['event_id'];
$type = strtolower($assessment['type']);
$responses = [];
try {
    $stmtR = $conn->prepare("CALL sp_GetTestResponses(?, ?)");
    $stmtR->bind_param("is", $eventId, $type);
    $stmtR->execute();
    $resR = $stmtR->get_result();
    if ($resR) {
        while ($row = $resR->fetch_assoc()) $responses[] = $row;
    }
    $stmtR->close();
    while ($conn->more_results() && $conn->next_result()) { ; }
} catch (Exception $e) {
    // fallback
}

if (empty($responses)) {
    $testTable = $type === 'posttest' ? 'event_posttest' : 'event_pretest';
    $result = $conn->query("SELECT t.*, u.first_name, u.last_name, u.student_id FROM `$testTable` t LEFT JOIN `user` u ON u.UserId = t.UserId WHERE t.EventId = $eventId ORDER BY t.SubmittedAt DESC");
    if ($result) while ($row = $result->fetch_assoc()) $responses[] = $row;
}

$questionsList = [];
try {
    $stmtQ = $conn->prepare("CALL sp_GetAssessmentQuestions(?)");
    $stmtQ->bind_param("i", $assessmentId);
    $stmtQ->execute();
    $resQ = $stmtQ->get_result();
    if ($resQ) {
        while ($row = $resQ->fetch_assoc()) $questionsList[] = $row;
    }
    $stmtQ->close();
    while ($conn->more_results() && $conn->next_result()) { ; }
} catch (Exception $e) {
    // fallback
}

if (empty($questionsList)) {
    $result = $conn->query("SELECT aq.*, 0 AS total_answered, 0 AS total_correct FROM assessment_questions aq WHERE aq.assessment_id = $assessmentId ORDER BY aq.question_id ASC");
    if ($result) while ($row = $result->fetch_assoc()) $questionsList[] = $row;
}

echo json_encode([
        'success' => true,
        'assessment' => $assessment,
        'responses'  => $responses,
        'questions'  => $questionsList
    ]);
if ($isDirectApiCall) exit;
?>

