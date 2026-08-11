<?php
/**
 * Student API: POST Submit Test
 * Endpoint: /config/API/endpoints/index.php?action=submit_test
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
$assessmentId = (int)($input['assessment_id'] ?? $_POST['assessment_id'] ?? 0);
$eventId      = (int)($input['event_id'] ?? $_POST['event_id'] ?? 0);
$type         = ($input['type'] ?? $_POST['type'] ?? 'pre');
$answers      = $input['answers'] ?? [];
$tabSwitches  = (int)($input['tab_switches'] ?? $_POST['tab_switches'] ?? 0);
$engScore     = (int)($input['engagement_score'] ?? $_POST['engagement_score'] ?? 100);
$flagged      = (int)($input['monitoring_flagged'] ?? $_POST['monitoring_flagged'] ?? 0);

// Extract form answers if submitted via standard HTML POST form
if (empty($answers) && is_array($_POST)) {
    foreach ($_POST as $k => $v) {
        if (strpos($k, 'answer_') === 0) {
            $qid = (int)substr($k, 7);
            if ($qid > 0) $answers[$qid] = trim($v);
        }
    }
}
if (empty($answers) && is_array($_REQUEST)) {
    foreach ($_REQUEST as $k => $v) {
        if (strpos($k, 'answer_') === 0) {
            $qid = (int)substr($k, 7);
            if ($qid > 0) $answers[$qid] = trim($v);
        }
    }
}

if (!$studentId || !$eventId) {
    echo json_encode(['success' => false, 'message' => 'Missing required student or event ID']);
    exit;
}

// Ensure assessment ID is found if missing
if (!$assessmentId) {
    $typeNeedle = (strpos(strtolower($type), 'post') !== false) ? '%post%' : '%pre%';
    $findA = $conn->prepare("SELECT assessment_id FROM assessments WHERE event_id = ? AND (LOWER(type) LIKE ? OR LOWER(COALESCE(test_type, '')) LIKE ?) ORDER BY assessment_id DESC LIMIT 1");
    if ($findA) {
        $findA->bind_param("iss", $eventId, $typeNeedle, $typeNeedle);
        $findA->execute();
        $rowA = $findA->get_result()->fetch_assoc();
        if ($rowA) $assessmentId = (int)$rowA['assessment_id'];
        $findA->close();
    }
}

try {
    // Get questions with correct answers
    $questions = [];
    if ($assessmentId > 0) {
        $qStmt = $conn->prepare("SELECT question_id, question_text, correct_answer FROM assessment_questions WHERE assessment_id = ?");
        if ($qStmt) {
            $qStmt->bind_param("i", $assessmentId);
            $qStmt->execute();
            $qResult = $qStmt->get_result();
            while ($q = $qResult->fetch_assoc()) $questions[$q['question_id']] = $q;
            $qStmt->close();
        }
    }

    // Calculate score
    $score = 0;
    $total = count($questions);
    $answerDetails = [];

    if ($total > 0) {
        foreach ($questions as $qid => $q) {
            $given = $answers[$qid] ?? '';
            $isCorrect = (strtolower(trim($given)) === strtolower(trim($q['correct_answer']))) ? 1 : 0;
            if ($isCorrect) $score++;
            $answerDetails[] = ['question_id' => $qid, 'given' => $given, 'correct' => $isCorrect];
        }
    } else {
        // Fallback score if questions array not populated
        $score = (int)($input['score'] ?? $_POST['score'] ?? 0);
        $total = (int)($input['total'] ?? $_POST['total'] ?? 1);
    }

    $typeStr = (strpos(strtolower($type), 'post') !== false) ? 'post' : 'pre';

    // 1. Try Stored Procedure Execution
    try {
        if ($typeStr === 'pre') {
            $spStmt = $conn->prepare("CALL sp_SubmitPretest(?, ?, ?, ?, ?, ?)");
        } else {
            $spStmt = $conn->prepare("CALL sp_SubmitPosttest(?, ?, ?, ?, ?, ?)");
        }
        if ($spStmt) {
            $spStmt->bind_param("iiiiii", $eventId, $studentId, $score, $tabSwitches, $engScore, $flagged);
            $spStmt->execute();
            $spStmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    } catch (Throwable $eSp) {
        // Procedure fallback below
    }

    // 2. Direct SQL Execution into event_pretest / event_posttest
    if ($typeStr === 'pre') {
        $fStmt = $conn->prepare("INSERT INTO event_pretest (EventId, UserId, Score, tab_switches, engagement_score, monitoring_flagged, SubmittedAt) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE Score = ?, tab_switches = ?, engagement_score = ?, monitoring_flagged = ?, SubmittedAt = NOW()");
        if ($fStmt) {
            $fStmt->bind_param("iiiiiiiiii", $eventId, $studentId, $score, $tabSwitches, $engScore, $flagged, $score, $tabSwitches, $engScore, $flagged);
            $fStmt->execute();
            $fStmt->close();
        }
    } else {
        $fStmt = $conn->prepare("INSERT INTO event_posttest (EventId, UserId, Score, tab_switches, engagement_score, monitoring_flagged, SubmittedAt) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE Score = ?, tab_switches = ?, engagement_score = ?, monitoring_flagged = ?, SubmittedAt = NOW()");
        if ($fStmt) {
            $fStmt->bind_param("iiiiiiiiii", $eventId, $studentId, $score, $tabSwitches, $engScore, $flagged, $score, $tabSwitches, $engScore, $flagged);
            $fStmt->execute();
            $fStmt->close();
        }
    }

    // 3. Direct SQL Execution into preposttest table
    $ppStmt = $conn->prepare("INSERT INTO preposttest (EventId, StudentId, TestType, Score, CompletedAt) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE Score = ?, CompletedAt = NOW()");
    if ($ppStmt) {
        $ppStmt->bind_param("iisis", $eventId, $studentId, $typeStr, $score, $score);
        $ppStmt->execute();
        $ppStmt->close();
    }

    // 4. Direct SQL Execution into assessment_responses table
    if ($assessmentId > 0) {
        $answersJson = json_encode($answerDetails);
        $arStmt = $conn->prepare("INSERT INTO assessment_responses (assessment_id, user_id, score, total_points, answers_json, submitted_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE score = ?, total_points = ?, answers_json = ?, submitted_at = NOW()");
        if ($arStmt) {
            $arStmt->bind_param("iiiissis", $assessmentId, $studentId, $score, $total, $answersJson, $score, $total, $answersJson);
            $arStmt->execute();
            $arStmt->close();
        }
    }

    // 5. Store question responses in student_question_responses
    if ($assessmentId > 0 && !empty($answerDetails)) {
        foreach ($answerDetails as $ad) {
            try {
                $insStmt = $conn->prepare("CALL sp_SaveStudentQuestionResponse(?, ?, ?, ?, ?)");
                if ($insStmt) {
                    $insStmt->bind_param("iiiis", $assessmentId, $studentId, $ad['question_id'], $ad['correct'], $ad['given']);
                    $insStmt->execute();
                    $insStmt->close();
                    while ($conn->more_results() && $conn->next_result()) { ; }
                }
            } catch (Throwable $eQ) {
                $sqr = $conn->prepare("DELETE FROM student_question_responses WHERE assessment_id = ? AND student_id = ? AND question_id = ?");
                if ($sqr) { $sqr->bind_param("iii", $assessmentId, $studentId, $ad['question_id']); $sqr->execute(); $sqr->close(); }
                $sqrIn = $conn->prepare("INSERT INTO student_question_responses (assessment_id, student_id, question_id, is_correct, given_answer) VALUES (?, ?, ?, ?, ?)");
                if ($sqrIn) { $sqrIn->bind_param("iiiis", $assessmentId, $studentId, $ad['question_id'], $ad['correct'], $ad['given']); $sqrIn->execute(); $sqrIn->close(); }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'score' => $score,
        'total' => $total,
        'assessment_id' => $assessmentId,
        'event_id' => $eventId,
        'type' => $typeStr
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
