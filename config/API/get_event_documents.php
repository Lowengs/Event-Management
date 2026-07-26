<?php
/** get_event_documents.php - Returns files associated with an event */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID required']); exit;
}

// Ensure the table exists just in case
$conn->query("CREATE TABLE IF NOT EXISTS org_documents (
    DocId INT AUTO_INCREMENT PRIMARY KEY,
    OrgId INT NOT NULL,
    EventId INT DEFAULT NULL,
    Title VARCHAR(255) NOT NULL,
    DocType VARCHAR(100) DEFAULT 'Other',
    Description TEXT,
    FilePath VARCHAR(500) NOT NULL,
    FileSize VARCHAR(50),
    UploadedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$res = $conn->query("SELECT DocId, DocType as DocumentType, Title as FileName, FilePath, UploadedAt FROM org_documents WHERE EventId = $eventId ORDER BY UploadedAt ASC");

$files = [];
while ($row = $res->fetch_assoc()) {
    $files[] = $row;
}

echo json_encode(['success' => true, 'files' => $files]);
