<?php
/**
 * Student Submit Test Action Handler
 * Delegates to API endpoint /config/API/endpoints/submit_test.php
 */
session_start();

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

// Submit through the central API router; the former standalone endpoint no longer exists.
$_GET['action'] = 'submit_test';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$apiResult = json_decode(ob_get_clean() ?: '[]', true) ?: [];

if (empty($apiResult['success'])) {
    $message = urlencode($apiResult['message'] ?? 'Unable to submit assessment');
    header("Location: assessment_error.php?reason=submit_failed&message={$message}", true, 303);
    exit;
}

$eventId = (int)($_POST['event_id'] ?? $_SESSION['active_event_id'] ?? 0);
$testType = strtolower($_POST['type'] ?? $_SESSION['active_test_type'] ?? 'pretest');
$assessmentId = (int)($_POST['assessment_id'] ?? 0);
$cleanType = (strpos($testType, 'pre') !== false) ? 'pre' : 'post';

header("Location: test_results.php?event_id={$eventId}&type={$cleanType}&assessment_id={$assessmentId}", true, 303);
exit;
