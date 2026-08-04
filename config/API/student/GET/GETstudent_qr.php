<?php
/**
 * Student API: GET Student QR Data
 * Uses Stored Procedure: sp_GetStudentInfo
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

$student = null;
try {
    if ($stmt = $conn->prepare("CALL sp_GetStudentInfo(?, 0)")) {
        $sStr = (string)$studentId;
        $stmt->bind_param("s", $sStr);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) $student = $res->fetch_assoc();
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {}

echo json_encode(['success' => true, 'data' => $student]);
if ($isDirectApiCall) exit;
?>

