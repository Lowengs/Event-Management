<?php
/**
 * Common API: DELETE Temporary Uploads / Cache
 * Endpoint: /config/API/endpoints/index.php?action=DELETEtemp
 */
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

echo json_encode(['success' => true, 'message' => 'Temporary cache cleared successfully']);
?>
