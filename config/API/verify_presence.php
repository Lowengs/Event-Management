<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$userId  = (int)$_SESSION['student_id'];
$eventId = (int)($_POST['event_id'] ?? 0);
$status  = trim($_POST['status'] ?? 'passed'); // 'passed' or 'missed'
$method  = trim($_POST['method'] ?? 'manual'); // 'face' or 'manual'

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']); exit;
}

$now = date('Y-m-d H:i:s');

// Fetch or create attendance record
$att = $conn->query("SELECT AttendanceId, PresenceChecksPassed, PresenceChecksMissed FROM attendance WHERE EventId=$eventId AND UserId=$userId LIMIT 1")->fetch_assoc();

if ($att) {
    if ($status === 'passed') {
        $stmt = $conn->prepare("UPDATE attendance SET PresenceChecksPassed = PresenceChecksPassed + 1, LastPresenceCheckAt=? WHERE AttendanceId=?");
        $stmt->bind_param('si', $now, $att['AttendanceId']);
    } else {
        $stmt = $conn->prepare("UPDATE attendance SET PresenceChecksMissed = PresenceChecksMissed + 1, LastPresenceCheckAt=? WHERE AttendanceId=?");
        $stmt->bind_param('si', $now, $att['AttendanceId']);
    }
    $stmt->execute();
} else {
    // If student hasn't checked in yet, create record
    $passed = ($status === 'passed') ? 1 : 0;
    $missed = ($status === 'missed') ? 1 : 0;
    $stmt = $conn->prepare("INSERT INTO attendance (EventId, UserId, AttendanceStatus, PresenceChecksPassed, PresenceChecksMissed, LastPresenceCheckAt) VALUES (?, ?, 'Present', ?, ?, ?)");
    $stmt->bind_param('iiiis', $eventId, $userId, $passed, $missed, $now);
    $stmt->execute();
}

echo json_encode([
    'success' => true,
    'message' => ($status === 'passed') ? 'Presence verified successfully!' : 'Presence check expired.',
    'status'  => $status,
    'method'  => $method
]);
