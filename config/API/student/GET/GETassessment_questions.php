<?php
/**
 * Student API: GET Assessment Questions
 * Endpoint: /config/API/endpoints/index.php?action=GETassessment_questions
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$assessmentId = (int)($_GET['assessment_id'] ?? $_REQUEST['assessment_id'] ?? 0);
$eventId      = (int)($_GET['event_id'] ?? $_REQUEST['event_id'] ?? 0);
$rawType      = strtolower(trim($_GET['type'] ?? $_REQUEST['type'] ?? 'pre'));
$isPost       = (strpos($rawType, 'post') !== false);
$typeSearch   = $isPost ? '%post%' : '%pre%';

if (!$assessmentId && $eventId) {
    // Find assessment by event + type (search both type and test_type)
    $stmt = $conn->prepare("SELECT assessment_id, title, type, instructions, time_limit, status FROM assessments WHERE event_id = ? AND (LOWER(type) LIKE ? OR LOWER(COALESCE(test_type, '')) LIKE ?) ORDER BY assessment_id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("iss", $eventId, $typeSearch, $typeSearch);
        $stmt->execute();
        $aRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($aRow) $assessmentId = (int)$aRow['assessment_id'];
    }
}

if (!$assessmentId) {
    echo json_encode(['success' => false, 'message' => 'Assessment not found']);
    if ($isDirectApiCall) exit;
    exit;
}

try {
    // Get assessment info
    $assessment = null;
    $aStmt = $conn->prepare("SELECT assessment_id, event_id, title, type, instructions, time_limit, status FROM assessments WHERE assessment_id = ?");
    if ($aStmt) {
        $aStmt->bind_param("i", $assessmentId);
        $aStmt->execute();
        $assessment = $aStmt->get_result()->fetch_assoc();
        $aStmt->close();
    }

    $questions = [];

    // 1. Try stored procedure
    try {
        $qStmt = $conn->prepare("CALL sp_GetAssessmentQuestionsDetail(?)");
        if ($qStmt) {
            $qStmt->bind_param("i", $assessmentId);
            $qStmt->execute();
            $qResult = $qStmt->get_result();
            if ($qResult) {
                while ($q = $qResult->fetch_assoc()) {
                    $questions[] = $q;
                }
            }
            $qStmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    } catch (Throwable $eProcedure) {
        // Fallback below
    }

    // 2. Direct query fallback if procedure returned no questions or threw an exception
    if (empty($questions)) {
        $qDirect = $conn->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY question_id ASC");
        if ($qDirect) {
            $qDirect->bind_param("i", $assessmentId);
            $qDirect->execute();
            $res = $qDirect->get_result();
            if ($res) {
                while ($q = $res->fetch_assoc()) {
                    $questions[] = $q;
                }
            }
            $qDirect->close();
        }
    }

    echo json_encode([
        'success' => true,
        'assessment' => $assessment,
        'questions' => $questions,
        'question_count' => count($questions)
    ]);
    if ($isDirectApiCall) exit;
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
