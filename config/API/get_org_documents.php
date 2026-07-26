<?php
// get_org_documents.php — list documents for the org
session_start();
require_once '../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId = (int)$_SESSION['org_id'];

// Check if table exists, create if not
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

$docs = [];
$r = $conn->query("SELECT d.*, e.EventName FROM org_documents d LEFT JOIN event e ON d.EventId=e.EventId WHERE d.OrgId=$orgId ORDER BY d.UploadedAt DESC");
if ($r) while($row=$r->fetch_assoc()) $docs[] = $row;

echo json_encode(['success'=>true,'documents'=>$docs,'total'=>count($docs)]);
