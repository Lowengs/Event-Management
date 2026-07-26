<?php
/** event_register.php — register a student for an event */
session_start();
require_once '../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['student_id'])) { echo json_encode(['success'=>false,'message'=>'Login required']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

// Ensure tables exist
// Ensure pretest table exists (eventregistration likely exists)
$conn->query("CREATE TABLE IF NOT EXISTS event_pretest (
    TestId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
    Q1 VARCHAR(10), Q2 VARCHAR(10), Q3 VARCHAR(10), Q4 VARCHAR(10), Q5 VARCHAR(10),
    Score INT DEFAULT 0, SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$userId  = (int)$_SESSION['student_id'];
$eventId = (int)($_POST['EventId'] ?? 0);
if (!$eventId) { echo json_encode(['success'=>false,'message'=>'EventId required']); exit; }

// Check duplicate
$dup = $conn->query("SELECT RegistrationId FROM eventregistration WHERE EventId=$eventId AND UserId=$userId");
if ($dup && $dup->num_rows > 0) { echo json_encode(['success'=>true,'message'=>'Already registered']); exit; }

// Check event exists
$ev = $conn->query("SELECT EventId, EventName FROM event WHERE EventId=$eventId")->fetch_assoc();
if (!$ev) { echo json_encode(['success'=>false,'message'=>'Event not found']); exit; }

$stmt = $conn->prepare("INSERT INTO eventregistration (EventId, UserId, DateIssued) VALUES (?,?,NOW())");
$stmt->bind_param('ii', $eventId, $userId);
$stmt->execute()
    ? print json_encode(['success'=>true,'message'=>'Successfully registered for '.$ev['EventName'],'reg_id'=>$stmt->insert_id])
    : print json_encode(['success'=>false,'message'=>$conn->error]);
$stmt->close();
