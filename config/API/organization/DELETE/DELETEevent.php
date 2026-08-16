<?php
/**
 * Organization API: DELETE Event
 * Uses Stored Procedure: sp_DeleteOrgEvent
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$isOsaOrAdmin = !empty($_SESSION['osa_id']) || !empty($_SESSION['admin_id']) || !empty($_SESSION['admin_logged_in']);

if (empty($_SESSION['org_id']) && !$isOsaOrAdmin) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    if ($isDirectApiCall) exit;
    return;
}

$inputData = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || empty($_POST)) {
    parse_str(file_get_contents('php://input'), $delData);
    if (!empty($delData)) $inputData = array_merge($inputData, $delData);
}

$orgId   = (int)($_SESSION['org_id'] ?? 0);
$eventId = (int)($inputData['EventId'] ?? $inputData['id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    if ($isDirectApiCall) exit;
    return;
}

$success = false;
try {
    if ($orgId > 0 && !$isOsaOrAdmin) {
        $owned = $conn->query("SELECT EventId FROM event WHERE EventId = $eventId AND OrgId = $orgId LIMIT 1");
        if (!$owned || $owned->num_rows === 0) {
            throw new RuntimeException('Event not found or does not belong to this organization');
        }
    }

    $conn->begin_transaction();

    // 1. Delete physical document files on disk
    try {
        $docsQuery = $conn->query("SELECT FilePath FROM org_documents WHERE EventId = $eventId");
        if ($docsQuery) {
            while ($drow = $docsQuery->fetch_assoc()) {
                $fp = $drow['FilePath'] ?? '';
                if (!empty($fp)) {
                    $fullPath = __DIR__ . '/../../../../' . ltrim($fp, '/');
                    if (file_exists($fullPath) && is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }
            }
        }
    } catch (Throwable $e) {}

    // 2. Remove all related child records
    $tables = [
        'event_pretest',
        'event_posttest',
        'preposttest',
        'student_verification_checks',
        'attendance',
        'eventregistration',
        'certificates',
        'certificatetemplate',
        'certificate_templates',
        'certificate_backup',
        'event_report',
        'org_documents'
    ];
    foreach ($tables as $table) {
        try { $conn->query("DELETE FROM `$table` WHERE EventId = $eventId"); } catch (Throwable $e) {}
    }

    // 3. Remove assessment questions & attempts
    try {
        $conn->query("DELETE sqr FROM student_question_responses sqr JOIN assessments a ON a.assessment_id = sqr.assessment_id WHERE a.event_id = $eventId");
        $conn->query("DELETE sq FROM assessment_questions sq JOIN assessments a ON a.assessment_id = sq.assessment_id WHERE a.event_id = $eventId");
        $conn->query("DELETE saa FROM student_assessment_attempts saa JOIN assessments a ON a.assessment_id = saa.assessment_id WHERE a.event_id = $eventId");
        $conn->query("DELETE FROM assessments WHERE event_id = $eventId");
    } catch (Throwable $e) {}

    // 4. Delete event
    $stmt = $conn->prepare('DELETE FROM event WHERE EventId = ?' . ($orgId > 0 && !$isOsaOrAdmin ? ' AND OrgId = ?' : ''));
    if ($orgId > 0 && !$isOsaOrAdmin) {
        $stmt->bind_param('ii', $eventId, $orgId);
    } else {
        $stmt->bind_param('i', $eventId);
    }
    $success = $stmt->execute() && ($stmt->affected_rows > 0 || $stmt->errno === 0);
    $stmt->close();
    if ($success) $conn->commit(); else $conn->rollback();
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignore) {}
    $errorMessage = $e->getMessage();
}

if ($success) {
    if (file_exists(__DIR__ . '/../../../audit.php')) {
        require_once __DIR__ . '/../../../audit.php';
        logAudit($conn, 'Delete Event', 'organization', $orgId, 'success', ['event_id' => $eventId]);
    }
    echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => $errorMessage ?? 'Failed to delete event']);
}
if ($isDirectApiCall) exit;
?>

