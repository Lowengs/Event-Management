<?php
/** submit_posttest.php */
session_start();
require_once '../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['student_id'])) { echo json_encode(['success'=>false,'message'=>'Login required']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$userId  = (int)$_SESSION['student_id'];
$eventId = (int)($_POST['EventId'] ?? 0);
if (!$eventId) { echo json_encode(['success'=>false,'message'=>'EventId required']); exit; }

$dup = $conn->query("SELECT TestId FROM event_posttest WHERE EventId=$eventId AND UserId=$userId");
if ($dup && $dup->num_rows > 0) { echo json_encode(['success'=>true,'message'=>'Already submitted','score'=>0]); exit; }

$q1 = trim($_POST['q1'] ?? '');
$q2 = trim($_POST['q2'] ?? '');
$q3 = trim($_POST['q3'] ?? '');
$q4 = trim($_POST['q4'] ?? '');
$q5 = trim($_POST['q5'] ?? '');

// Post-test is feedback — all answers score equally, just record them
$score = 5; // Completion score for feedback survey

$stmt = $conn->prepare("INSERT INTO event_posttest (EventId, UserId, Q1, Q2, Q3, Q4, Q5, Score) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('iisssssi', $eventId, $userId, $q1, $q2, $q3, $q4, $q5, $score);
$stmt->execute()
    ? print json_encode(['success'=>true,'message'=>'Post-test submitted','score'=>$score])
    : print json_encode(['success'=>false,'message'=>$conn->error]);
$stmt->close();
