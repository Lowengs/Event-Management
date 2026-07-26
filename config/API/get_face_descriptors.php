<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$q = "SELECT u.student_id, u.first_name, u.last_name, fd.FaceEmbedding 
      FROM user u 
      JOIN face_data fd ON u.UserId = fd.UserId 
      WHERE fd.FaceEmbedding IS NOT NULL";
$res = $conn->query($q);

$faces = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $embedding = json_decode($row['FaceEmbedding'], true);
        if (is_array($embedding) && count($embedding) === 128) {
            $faces[] = [
                'student_id' => $row['student_id'],
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'descriptor' => $embedding
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'faces' => $faces
]);
?>