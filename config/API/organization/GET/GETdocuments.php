<?php
/**
 * Organization API: GET Documents
 * Endpoint: /config/API/endpoints/index.php?action=GETdocuments
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}




if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'documents' => [], 'message' => 'Organization login required']);
if ($isDirectApiCall) exit;
    exit;
}

$orgId = (int)$_SESSION['org_id'];

try {
    $stmt = $conn->prepare("
        SELECT d.DocId, d.OrgId, d.EventId, d.Title, d.DocType, d.Description, 
               d.FilePath, d.FileSize, d.UploadedAt,
               e.EventName, e.EventDateTime
        FROM org_documents d
        LEFT JOIN event e ON e.EventId = d.EventId
        WHERE d.OrgId = ?
        ORDER BY e.EventDateTime DESC, e.EventName ASC, d.UploadedAt DESC
    ");
    $stmt->bind_param("i", $orgId);
    $stmt->execute();
    $q = $stmt->get_result();

    $documents = [];
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $documents[] = $row;
        }
    }

    echo json_encode([
        'success'   => true,
        'documents' => $documents,
        'count'     => count($documents)
    ]);
if ($isDirectApiCall) exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'documents' => [], 'message' => $e->getMessage()]);
if ($isDirectApiCall) exit;
}
?>

