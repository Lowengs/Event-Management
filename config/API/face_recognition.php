<?php
/**
 * face_recognition.php — Search matched faces from event attendees
 */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

// Show all errors to easily catch bugs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$eventId = (int)($_POST['EventId'] ?? 0);
$descriptorJson = trim($_POST['descriptor'] ?? '');

if (!$eventId || empty($descriptorJson)) {
    echo json_encode(['success' => false, 'message' => 'EventId and face descriptor are required']);
    exit;
}

$targetDescriptor = json_decode($descriptorJson, true);
if (!is_array($targetDescriptor) || count($targetDescriptor) !== 128) {
    echo json_encode(['success' => false, 'message' => 'Malformed face descriptor']);
    exit;
}

// Fetch users with registered faces
// The face data is stored in the face_data table, joined with the user table
$q = "SELECT u.UserId, u.student_id, u.first_name, u.last_name, fd.FaceEmbedding 
      FROM user u 
      JOIN face_data fd ON u.UserId = fd.UserId 
      WHERE fd.FaceEmbedding IS NOT NULL";
$res = $conn->query($q);

$bestMatch = null;
$bestDistance = 0.5; // Threshold for face-api.js euclidean distance (0.5 is usually good for resnet)

// Function to compute Euclidean distance
function euclideanDistance($arr1, $arr2) {
    if (!$arr1 || !$arr2) return 999;
    $sum = 0;
    for ($i = 0; $i < 128; $i++) {
        $diff = ($arr1[$i] ?? 0) - ($arr2[$i] ?? 0);
        $sum += $diff * $diff;
    }
    return sqrt($sum);
}

if ($res) {
    while ($row = $res->fetch_assoc()) {
        try {
            $dbDesc = json_decode($row['FaceEmbedding'], true);
            if (is_array($dbDesc) && count($dbDesc) >= 128) {
                $dist = euclideanDistance($targetDescriptor, $dbDesc);
                if ($dist < $bestDistance) {
                    $bestDistance = $dist;
                    $bestMatch = [
                        'UserId' => $row['UserId'],
                        'student_id' => $row['student_id'],
                        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                        'distance' => $dist
                    ];
                }
            }
        } catch (Exception $e) {
            continue; // Skip malformed rows
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

if ($bestMatch) {
    echo json_encode([
        'success' => true,
        'student' => $bestMatch
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Face not recognized. Please scan QR or enter ID manually.'
    ]);
}
?>