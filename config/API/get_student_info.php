<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$studentId = trim($_GET['StudentId'] ?? '');
if (!$studentId) { echo json_encode(['success'=>false,'message'=>'No ID provided']); exit; }

$decodedStudent = json_decode($studentId, true);
if (is_array($decodedStudent)) {
    $studentId = trim((string)($decodedStudent['student_id'] ?? $decodedStudent['studentId'] ?? ''));
    if ($studentId === '' && !empty($decodedStudent['user_id'])) {
        $studentId = (string)(int)$decodedStudent['user_id'];
    }
}

$eventId = (int)($_GET['EventId'] ?? 0);

$r = $conn->query("SELECT UserId, first_name, last_name, student_id, course, year_level, section, profile_photo FROM user WHERE student_id='".addslashes($studentId)."' OR UserId='".addslashes($studentId)."' LIMIT 1");
if ($r && $r->num_rows > 0) {
    $u = $r->fetch_assoc();
    
    $profileSrc = '../../assets/img/default-avatar.png';
    if (!empty($u['profile_photo']) && file_exists('../../' . $u['profile_photo'])) {
        $profileSrc = '../../' . $u['profile_photo'];
    }

    $userId = (int)($u['UserId'] ?? 0);
    $hasLoggedIn = false;
    $hasLoggedOut = false;

    if ($eventId > 0 && $userId > 0) {
        $cIn = $conn->query("SELECT AttendanceId FROM attendance WHERE EventId=$eventId AND UserId=$userId AND (LogType='Log In' OR LogType IS NULL) LIMIT 1");
        $hasLoggedIn = ($cIn && $cIn->num_rows > 0);

        $cOut = $conn->query("SELECT AttendanceId FROM attendance WHERE EventId=$eventId AND UserId=$userId AND LogType='Log Out' LIMIT 1");
        $hasLoggedOut = ($cOut && $cOut->num_rows > 0);
    }

    $autoLogType = ($hasLoggedIn && !$hasLoggedOut) ? 'Log Out' : 'Log In';
    $alreadyCompleted = ($hasLoggedIn && $hasLoggedOut);

    echo json_encode([
        'success' => true,
        'student' => [
            'user_id' => $userId,
            'student_id' => $u['student_id'],
            'name' => trim($u['first_name'].' '.$u['last_name']),
            'course' => $u['course'] ?? '',
            'year_level' => $u['year_level'] ?? '',
            'section' => $u['section'] ?? '',
            'profile_photo' => $u['profile_photo'] ? ('../../' . $u['profile_photo']) : '../../assets/img/default-avatar.png',
            'has_logged_in' => $hasLoggedIn,
            'has_logged_out' => $hasLoggedOut,
            'auto_log_type' => $autoLogType,
            'already_completed' => $alreadyCompleted
        ]
    ]);
} else {
    echo json_encode(['success'=>false,'message'=>'Student not found.']);
}
