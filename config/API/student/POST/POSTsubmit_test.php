<?php
/**
 * Student API: POST Submit Test
 * Endpoint: /config/API/endpoints/index.php?action=POSTsubmit_test
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$studentId    = (int)($_SESSION['student_id'] ?? 0);
$input        = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$assessmentId = (int)($input['assessment_id'] ?? 0);
$eventId      = (int)($input['event_id'] ?? 0);
$type         = ($input['type'] ?? 'pre');
$answers      = $input['answers'] ?? [];
$tabSwitches  = (int)($input['tab_switches'] ?? 0);
$engScore     = (int)($input['engagement_score'] ?? 100);
$flagged      = (int)($input['monitoring_flagged'] ?? 0);

if (!$studentId || !$assessmentId || !$eventId) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Both pre-test and post-test become available once the student has checked
// in. Checking out is used only for completed-attendance statistics.
$checkIn = $conn->prepare("SELECT AttendanceId FROM attendance WHERE EventId = ? AND UserId = ? AND LOWER(COALESCE(LogType, '')) = 'log in' LIMIT 1");
$checkIn->bind_param('ii', $eventId, $studentId);
$checkIn->execute();
if (!$checkIn->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Check in to the event before taking an assessment']);
    exit;
}

try {
    // Get questions with correct answers
    $qStmt = $conn->prepare("SELECT question_id, question_text, correct_answer FROM assessment_questions WHERE assessment_id = ?");
    $qStmt->bind_param("i", $assessmentId);
    $qStmt->execute();
    $qResult = $qStmt->get_result();
    $questions = [];
    while ($q = $qResult->fetch_assoc()) $questions[$q['question_id']] = $q;
    $qStmt->close();

    // Calculate score
    $score = 0;
    $total = count($questions);
    $answerDetails = [];
    foreach ($questions as $qid => $q) {
        $given = $answers[$qid] ?? '';
        $isCorrect = (strtolower(trim($given)) === strtolower(trim($q['correct_answer']))) ? 1 : 0;
        if ($isCorrect) $score++;
        $answerDetails[] = ['question_id' => $qid, 'given' => $given, 'correct' => $isCorrect];
    }

    // Store in pre/post test table
    $typeStr = (strpos(strtolower($type), 'post') !== false) ? 'post' : 'pre';
    if ($typeStr === 'pre') {
        $spStmt = $conn->prepare("CALL sp_SubmitPretest(?, ?, ?, ?, ?, ?)");
    } else {
        $spStmt = $conn->prepare("CALL sp_SubmitPosttest(?, ?, ?, ?, ?, ?)");
    }
    $spStmt->bind_param("iiiiii", $eventId, $studentId, $score, $tabSwitches, $engScore, $flagged);
    $spStmt->execute();
    $spStmt->close();
    while ($conn->more_results() && $conn->next_result()) { ; }

    $insStmt = $conn->prepare("CALL sp_SaveStudentQuestionResponse(?, ?, ?, ?, ?)");
    foreach ($answerDetails as $ad) {
        $insStmt->bind_param("iiiis", $assessmentId, $studentId, $ad['question_id'], $ad['correct'], $ad['given']);
        $insStmt->execute();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
    $insStmt->close();

    echo json_encode([
        'success' => true,
        'score' => $score,
        'total' => $total,
        'assessment_id' => $assessmentId,
        'event_id' => $eventId,
        'type' => $typeStr
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
