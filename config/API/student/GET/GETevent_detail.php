<?php
/**
 * Student API: GET Event Detail
 * Uses Stored Procedures: sp_GetEventDetail & sp_GetStudentInfo
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$studentId = (int)($_SESSION['student_id'] ?? 0);
$eventId   = (int)($_GET['event_id'] ?? $_REQUEST['event_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID required']);
if ($isDirectApiCall) exit;
    return;
}

$ev = null;
$isRegistered = false;
$regId = 0;
$preDone = false;
$postDone = false;
$studentData = null;

try {
    if ($stmt = $conn->prepare("CALL sp_GetEventDetail(?)")) {
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $ev = $res->fetch_assoc();
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {}

if (!$ev) {
    $stmt = $conn->prepare("
        SELECT e.*, o.OrgName, o.OrgPicture
        FROM event e
        LEFT JOIN organization o ON o.OrgId = e.OrgId
        WHERE e.EventId = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $ev = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

if ($studentId) {
    try {
        if ($stmtS = $conn->prepare("CALL sp_GetStudentInfo(?, ?)")) {
            $sIdStr = (string)$studentId;
            $stmtS->bind_param("si", $sIdStr, $eventId);
            $stmtS->execute();
            $resS = $stmtS->get_result();
            if ($resS && $sRow = $resS->fetch_assoc()) {
                $studentData = $sRow;
                $isRegistered = !empty($sRow['is_registered']);
            }
            $stmtS->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    } catch (Exception $e) {}

    if (!$studentData) {
        $studentResult = $conn->query("SELECT * FROM `user` WHERE UserId = $studentId LIMIT 1");
        $studentData = $studentResult ? $studentResult->fetch_assoc() : null;
    }

    $registrationResult = $conn->query("SELECT RegistrationId FROM eventregistration WHERE EventId = $eventId AND UserId = $studentId LIMIT 1");
    if ($registrationResult && $registration = $registrationResult->fetch_assoc()) {
        $isRegistered = true;
        $regId = (int)$registration['RegistrationId'];
    }
}

echo json_encode([
        'success'         => (bool)$ev,
        'data'            => $ev,
        'is_registered'   => $isRegistered,
        'registration_id' => $regId,
        'pre_done'        => $preDone,
        'post_done'       => $postDone,
        'student'         => $studentData
    ]);
if ($isDirectApiCall) exit;
?>

