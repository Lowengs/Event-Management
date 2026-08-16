<?php
/**
 * Student API: GET Notifications
 * Endpoint: /config/API/endpoints/index.php?action=get_student_notifications
 * Retrieves real-time counts for pending assessments, live attendance, recent certificates, and announcements.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$studentId = (int)($_SESSION['student_id'] ?? 0);
if (!$studentId) {
    echo json_encode([
        'success' => false,
        'message' => 'Login required',
        'data' => [
            'announcements_count' => 0,
            'certificates_count' => 0,
            'pending_tests_count' => 0,
            'online_attendance_count' => 0
        ]
    ]);
    if ($isDirectApiCall) exit;
    return;
}

$annCount = 0;
$certCount = 0;
$pendingTestCount = 0;
$onlineAttCount = 0;

try {
    // 1. Recent Approved Announcements (within last 7 days)
    $annQ = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM announcement a
        WHERE LOWER(TRIM(COALESCE(a.Status, 'approved'))) = 'approved'
          AND COALESCE(a.DatePosted, a.CreatedAt) >= NOW() - INTERVAL 7 DAY
    ");
    if ($annQ && $aRow = $annQ->fetch_assoc()) {
        $annCount = (int)$aRow['cnt'];
    }

    // 2. Recent Certificates issued (within last 7 days)
    $certQ = $conn->query("
        SELECT COUNT(*) AS cnt
        FROM certificates c
        WHERE c.UserId = $studentId
          AND c.IssuedAt >= NOW() - INTERVAL 7 DAY
    ");
    if ($certQ && $cRow = $certQ->fetch_assoc()) {
        $certCount = (int)$cRow['cnt'];
    }

    // 3. Pending assessments for active/ongoing/upcoming registered events
    $testQ = $conn->query("
        SELECT COUNT(DISTINCT a.assessment_id) AS cnt
        FROM assessments a
        JOIN eventregistration er ON er.EventId = a.event_id
        JOIN event e ON e.EventId = a.event_id
        WHERE er.UserId = $studentId
          AND a.status = 'published'
          AND LOWER(TRIM(COALESCE(e.EventStatus, ''))) IN ('ongoing', 'scheduled', 'upcoming', 'active')
          AND (e.EventDateTime >= NOW() - INTERVAL 1 DAY OR LOWER(TRIM(COALESCE(e.EventStatus, ''))) = 'ongoing')
          AND NOT EXISTS (
              SELECT 1 FROM assessment_responses ar 
              WHERE ar.assessment_id = a.assessment_id AND ar.user_id = $studentId
          )
    ");
    if ($testQ && $tRow = $testQ->fetch_assoc()) {
        $pendingTestCount = (int)$tRow['cnt'];
    }

    // 4. Online Attendance - strictly ongoing events needing login/logout
    $attQ = $conn->query("
        SELECT COUNT(DISTINCT e.EventId) AS cnt
        FROM eventregistration er
        JOIN event e ON e.EventId = er.EventId
        WHERE er.UserId = $studentId
          AND LOWER(TRIM(COALESCE(e.EventStatus, ''))) = 'ongoing'
          AND (
              NOT EXISTS (
                  SELECT 1 FROM attendance a 
                  WHERE a.EventId = e.EventId AND a.UserId = $studentId AND LOWER(TRIM(COALESCE(a.LogType, ''))) = 'log in'
              )
              OR (
                  EXISTS (
                      SELECT 1 FROM attendance a 
                      WHERE a.EventId = e.EventId AND a.UserId = $studentId AND LOWER(TRIM(COALESCE(a.LogType, ''))) = 'log in'
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM attendance a 
                      WHERE a.EventId = e.EventId AND a.UserId = $studentId AND LOWER(TRIM(COALESCE(a.LogType, ''))) = 'log out'
                  )
              )
          )
    ");
    if ($attQ && $attRow = $attQ->fetch_assoc()) {
        $onlineAttCount = (int)$attRow['cnt'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'announcements_count'     => $annCount,
            'certificates_count'      => $certCount,
            'pending_tests_count'     => $pendingTestCount,
            'online_attendance_count' => $onlineAttCount
        ]
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => [
            'announcements_count'     => 0,
            'certificates_count'      => 0,
            'pending_tests_count'     => 0,
            'online_attendance_count' => 0
        ]
    ]);
}
if ($isDirectApiCall) exit;
?>
