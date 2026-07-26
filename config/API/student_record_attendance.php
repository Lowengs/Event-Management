<?php
/**
 * student_record_attendance.php
 * Student-side self-attendance recording for ONLINE events only.
 * POST: EventId, Method (face|qr), Descriptor (JSON, for face) or StudentId (for QR)
 */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success'=>false,'message'=>'Not logged in']); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid request']); exit;
}

$userId  = (int)$_SESSION['student_id'];
$eventId = (int)($_POST['EventId'] ?? 0);
$method  = trim($_POST['Method'] ?? 'qr_self');

if (!$eventId) {
    echo json_encode(['success'=>false,'message'=>'Event ID required']); exit;
}

// Validate event: must be online + ongoing
$evRow = $conn->query("SELECT EventId, EventName, EventStatus, EventType FROM event WHERE EventId=$eventId LIMIT 1")->fetch_assoc();
if (!$evRow) {
    echo json_encode(['success'=>false,'message'=>'Event not found']); exit;
}
if (strtolower($evRow['EventType'] ?? '') !== 'online') {
    echo json_encode(['success'=>false,'message'=>'Self-attendance is only for online events']); exit;
}
if (strtolower(trim($evRow['EventStatus'])) !== 'ongoing') {
    echo json_encode(['success'=>false,'message'=>'Event is not currently ongoing']); exit;
}

// Face recognition: match the submitted descriptor against stored face
if ($method === 'face') {
    $descriptorJson = trim($_POST['descriptor'] ?? '');
    if (!$descriptorJson) {
        echo json_encode(['success'=>false,'message'=>'Face descriptor required']); exit;
    }
    $targetDesc = json_decode($descriptorJson, true);
    if (!is_array($targetDesc) || count($targetDesc) !== 128) {
        echo json_encode(['success'=>false,'message'=>'Malformed face descriptor']); exit;
    }

    // Get THIS student's stored face descriptor
    $faceRow = $conn->query("SELECT FaceEmbedding FROM face_data WHERE UserId=$userId LIMIT 1")->fetch_assoc();
    if (!$faceRow || empty($faceRow['FaceEmbedding'])) {
        echo json_encode(['success'=>false,'message'=>'No face registered. Please register your face in your profile first.']); exit;
    }

    $storedDesc = json_decode($faceRow['FaceEmbedding'], true);
    if (!is_array($storedDesc) || count($storedDesc) < 128) {
        echo json_encode(['success'=>false,'message'=>'Stored face data is invalid']); exit;
    }

    // Euclidean distance
    $sum = 0;
    for ($i = 0; $i < 128; $i++) {
        $d = ($targetDesc[$i] ?? 0) - ($storedDesc[$i] ?? 0);
        $sum += $d * $d;
    }
    $dist = sqrt($sum);

    if ($dist > 0.5) {
        echo json_encode(['success'=>false,'message'=>'Face does not match your registered face. Distance: ' . round($dist,3)]); exit;
    }
}

// QR self method: verify the QR payload belongs to THIS student
if ($method === 'qr_self') {
    $qrData = trim($_POST['QrData'] ?? '');
    if ($qrData) {
        $parsed = json_decode($qrData, true);
        if (is_array($parsed) && isset($parsed['user_id'])) {
            if ((int)$parsed['user_id'] !== $userId) {
                echo json_encode(['success'=>false,'message'=>'QR code does not belong to your account']); exit;
            }
        }
    }
}

// Duplicate check
$dup = $conn->query("SELECT AttendanceId FROM attendance WHERE EventId=$eventId AND UserId=$userId LIMIT 1");
if ($dup && $dup->num_rows > 0) {
    echo json_encode(['success'=>false,'message'=>'You have already recorded attendance for this event']); exit;
}

// Insert attendance
$stmt = $conn->prepare("INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus) VALUES (?, ?, ?, 'present')");
$stmt->bind_param('iis', $eventId, $userId, $method);
if ($stmt->execute()) {
    echo json_encode(['success'=>true,'message'=>'Attendance recorded successfully for: ' . htmlspecialchars($evRow['EventName'])]);
} else {
    echo json_encode(['success'=>false,'message'=>'Database error: ' . $conn->error]);
}
$stmt->close();
