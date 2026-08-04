<?php
/**
 * Student API: POST Pre-test
 * Endpoint: /config/API/endpoints/index.php?action=submit_pretest
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId  = (int)$_SESSION['student_id'];
$eventId = (int)($_POST['EventId'] ?? $_POST['event_id'] ?? 0);
$score   = (int)($_POST['Score']   ?? $_POST['score']   ?? 0);
$tabSwitches = (int)($_POST['tab_switches'] ?? 0);
$engagementScore = (int)($_POST['engagement_score'] ?? 100);
$monitoringFlagged = (int)($_POST['monitoring_flagged'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

try {
    $stmt = $conn->prepare("CALL sp_SubmitPretest(?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiii", $eventId, $userId, $score, $tabSwitches, $engagementScore, $monitoringFlagged);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Pre-test submitted successfully',
            'data' => ['event_id' => $eventId, 'score' => $score]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
