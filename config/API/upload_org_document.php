<?php
/** upload_org_document.php */
session_start();
require_once '../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId   = (int)$_SESSION['org_id'];
$title   = trim($_POST['Title'] ?? '');
$type    = trim($_POST['DocType'] ?? 'Other');
$desc    = trim($_POST['Description'] ?? '');
$eventId = $_POST['EventId'] ? (int)$_POST['EventId'] : null;

if (!$title || empty($_FILES['DocFile']['tmp_name'])) {
    echo json_encode(['success'=>false,'message'=>'Title and file required']); exit;
}

$file = $_FILES['DocFile'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['pdf','doc','docx','jpg','jpeg','png','gif'];
if (!in_array($ext, $allowed)) { echo json_encode(['success'=>false,'message'=>'File type not allowed']); exit; }
if ($file['size'] > 10*1024*1024) { echo json_encode(['success'=>false,'message'=>'File too large (max 10MB)']); exit; }

$dir = __DIR__ . '/../../assets/uploads/documents/';
if (!is_dir($dir)) mkdir($dir, 0755, true);
$fn = 'doc_'.$orgId.'_'.time().'.'.$ext;
if (!move_uploaded_file($file['tmp_name'], $dir.$fn)) {
    echo json_encode(['success'=>false,'message'=>'Upload failed']); exit;
}

$size = round($file['size']/1024/1024, 2).' MB';
$path = 'assets/uploads/documents/'.$fn;
$conn->query("CREATE TABLE IF NOT EXISTS org_documents (DocId INT AUTO_INCREMENT PRIMARY KEY,OrgId INT NOT NULL,EventId INT DEFAULT NULL,Title VARCHAR(255),DocType VARCHAR(100) DEFAULT 'Other',Description TEXT,FilePath VARCHAR(500),FileSize VARCHAR(50),UploadedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");

$stmt = $conn->prepare("INSERT INTO org_documents (OrgId,EventId,Title,DocType,Description,FilePath,FileSize) VALUES (?,?,?,?,?,?,?)");
$stmt->bind_param('iisssss', $orgId, $eventId, $title, $type, $desc, $path, $size);
$stmt->execute()
    ? print json_encode(['success'=>true,'message'=>'Document uploaded successfully'])
    : print json_encode(['success'=>false,'message'=>$conn->error]);
$stmt->close();
