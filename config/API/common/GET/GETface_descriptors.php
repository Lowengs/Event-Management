<?php
/**
 * Common API: GET Face Descriptors
 * Endpoint: /config/API/endpoints/index.php?action=get_face_descriptors
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

try {
    $faces = [];
    
    // face_data table uses FaceEmbedding (blob) column, not 'descriptor'
    $result = $conn->query("
        SELECT fd.UserId, fd.FaceEmbedding, u.student_id, u.first_name, u.last_name
        FROM face_data fd
        JOIN `user` u ON u.UserId = fd.UserId
        WHERE fd.FaceEmbedding IS NOT NULL
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $raw = $row['FaceEmbedding'];
            if (empty($raw)) continue;
            
            // Try JSON decode first (stored as JSON text in blob)
            $descriptor = json_decode($raw, true);
            
            // If not valid JSON array, skip this record
            if (!is_array($descriptor) || count($descriptor) === 0) continue;
            
            $faces[] = [
                'student_id' => $row['student_id'] ?: (string)$row['UserId'],
                'user_id'    => $row['UserId'],
                'name'       => trim($row['first_name'] . ' ' . $row['last_name']),
                'descriptor' => $descriptor
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'faces'   => $faces,
        'count'   => count($faces)
    ]);
    if ($isDirectApiCall) exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'faces' => [], 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
?>
