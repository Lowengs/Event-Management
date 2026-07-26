<?php
/** submit_pretest.php */
session_start();
require_once '../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['student_id'])) { echo json_encode(['success'=>false,'message'=>'Login required']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS event_pretest (
    TestId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
    Q1 VARCHAR(10), Q2 VARCHAR(10), Q3 VARCHAR(10), Q4 VARCHAR(10), Q5 VARCHAR(10),
    Score INT DEFAULT 0, SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$userId  = (int)$_SESSION['student_id'];
$eventId = (int)($_POST['EventId'] ?? 0);
if (!$eventId) { echo json_encode(['success'=>false,'message'=>'EventId required']); exit; }

$attendance = $conn->prepare("SELECT AttendanceId FROM attendance WHERE EventId = ? AND UserId = ? AND LOWER(AttendanceStatus) = 'present' LIMIT 1");
$attendance->bind_param('ii', $eventId, $userId);
$attendance->execute();
if (!$attendance->get_result()->fetch_assoc()) {
    $attendance->close();
    echo json_encode(['success'=>false,'message'=>'You must have a recorded attendance for this event before taking the pre-test.']);
    exit;
}
$attendance->close();


// Prevent duplicate
$dup = $conn->query("SELECT TestId FROM event_pretest WHERE EventId=$eventId AND UserId=$userId");
if ($dup && $dup->num_rows > 0) { echo json_encode(['success'=>true,'message'=>'Already submitted','score'=>0]); exit; }

$q1 = trim($_POST['q1'] ?? '');
$q2 = trim($_POST['q2'] ?? '');
$q3 = trim($_POST['q3'] ?? '');
$q4 = trim($_POST['q4'] ?? '');
$q5 = trim($_POST['q5'] ?? '');

// Correct answers (1=a, 2=a, 3=a, 4=c, 5=a based on the dynamic options set in the form)
$answers = ['q1'=>'b','q2'=>'a','q3'=>'a','q4'=>'c','q5'=>'a'];
$score = 0;
if ($q1 === $answers['q1']) $score++;
if ($q2 === $answers['q2']) $score++;
if ($q3 === $answers['q3']) $score++;
if ($q4 === $answers['q4']) $score++;
if ($q5 === $answers['q5']) $score++;

$stmt = $conn->prepare("INSERT INTO event_pretest (EventId, UserId, Q1, Q2, Q3, Q4, Q5, Score) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('iisssssi', $eventId, $userId, $q1, $q2, $q3, $q4, $q5, $score);
$stmt->execute()
    ? print json_encode(['success'=>true,'message'=>'Pre-test submitted','score'=>$score])
    : print json_encode(['success'=>false,'message'=>$conn->error]);
$stmt->close();
