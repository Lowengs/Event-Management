<?php
/**
 * Student API: GET Info & Stats
 * Used by attendance scanner to look up student info.
 * Uses Stored Procedure: sp_GetStudentInfo
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$studentId = trim($_GET['StudentId'] ?? '');
$eventId   = (int)($_GET['EventId']   ?? 0);

// Parse JSON QR payload if StudentId is a JSON string
if (!empty($studentId) && $studentId[0] === '{') {
    $qrPayload = json_decode($studentId, true);
    if ($qrPayload && isset($qrPayload['type']) && $qrPayload['type'] === 'student_qr') {
        $studentId = $qrPayload['student_id'] ?? $qrPayload['user_id'] ?? $studentId;
    }
}

if (empty($studentId)) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    if ($isDirectApiCall) exit;
    exit;
}

try {
    $student = null;
    
    // Try stored procedure first
    $stmt = $conn->prepare("CALL sp_GetStudentInfo(?, ?)");
    $stmt->bind_param("si", $studentId, $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    while ($conn->more_results() && $conn->next_result()) { $conn->store_result(); }

    // Always resolve the scanned identifier directly. Older stored procedures
    // may return a different user than the face matcher label.
    {
        $escaped = $conn->real_escape_string($studentId);
        $q = $conn->query("
            SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id, 
                   u.course, u.year_level, u.section, u.profile_photo, u.phone
            FROM `user` u 
            WHERE u.student_id = '$escaped' 
               OR u.UserId = '" . intval($studentId) . "'
               OR u.Email = '$escaped'
            LIMIT 1
        ");
        $directStudent = $q ? $q->fetch_assoc() : null;
        if ($directStudent) $student = $directStudent;
    }

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        if ($isDirectApiCall) exit;
        exit;
    }

    // Determine login/logout status directly from attendance records. This is
    // reliable whether the student data came from the stored procedure or its
    // fallback query.
    $attendanceState = null;
    $studentUserId = (int)($student['UserId'] ?? 0);
    if ($studentUserId && $eventId) {
        $attendanceState = $conn->query("
            SELECT
                SUM(LOWER(COALESCE(LogType, 'log in')) = 'log in') AS logged_in,
                SUM(LOWER(COALESCE(LogType, 'log in')) = 'log out') AS logged_out
            FROM attendance
            WHERE EventId = $eventId AND UserId = $studentUserId
        ");
    }
    $attendanceRow = $attendanceState ? $attendanceState->fetch_assoc() : [];
    $hasLoggedIn = (int)($attendanceRow['logged_in'] ?? 0) > 0;
    $hasLoggedOut = (int)($attendanceRow['logged_out'] ?? 0) > 0;
    $alreadyCompleted = $hasLoggedIn && $hasLoggedOut;

    // Auto determine log type
    $autoLogType = 'Log In';
    if ($hasLoggedIn && !$hasLoggedOut) {
        $autoLogType = 'Log Out';
    }

    // Build profile photo URL
    $profilePhoto = '';
    if (!empty($student['profile_photo'])) {
        $profilePhoto = '../../' . $student['profile_photo'];
    }

    echo json_encode([
        'success' => true,
        'student' => [
            'user_id'       => $student['UserId'],
            'student_id'    => $student['student_id'] ?? $student['UserId'],
            'name'          => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')),
            'first_name'    => $student['first_name'] ?? '',
            'last_name'     => $student['last_name'] ?? '',
            'email'         => $student['Email'] ?? '',
            'course'        => $student['course'] ?? '',
            'year_level'    => $student['year_level'] ?? '',
            'section'       => $student['section'] ?? '',
            'profile_photo' => $profilePhoto,
            'is_registered'     => (bool)($student['is_registered'] ?? false),
            'has_attended'      => (bool)($student['has_attended'] ?? false),
            'has_logged_in'     => $hasLoggedIn,
            'has_logged_out'    => $hasLoggedOut,
            'already_completed' => $alreadyCompleted,
            'auto_log_type'     => $autoLogType
        ],
        'data' => [
            'student'       => $student,
            'is_registered' => (bool)($student['is_registered'] ?? false),
            'has_attended'  => (bool)($student['has_attended'] ?? false)
        ]
    ]);
    if ($isDirectApiCall) exit;
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
?>
