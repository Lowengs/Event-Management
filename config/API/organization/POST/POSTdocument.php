<?php
/**
 * Organization API: Upload Document
 * Endpoint: /config/API/endpoints/index.php?action=POSTdocument
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orgId       = (int)$_SESSION['org_id'];
$title       = trim($_POST['Title']       ?? '');
$docType     = trim($_POST['DocType']     ?? 'Other');
$eventId     = !empty($_POST['EventId'])  ? (int)$_POST['EventId'] : null;
$description = trim($_POST['Description'] ?? '');

if (empty($title) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Document title and description are required']);
    exit;
}

if (empty($_FILES['DocFile']['name']) || $_FILES['DocFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a file to upload']);
    exit;
}

$fileTmp  = $_FILES['DocFile']['tmp_name'];
$fileName = $_FILES['DocFile']['name'];
$fileSize = $_FILES['DocFile']['size'];

// Format human readable size
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
}

$formattedSize = formatBytes($fileSize);
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
$allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];

if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'File format not supported. Allowed: PDF, DOC, DOCX, JPG, PNG']);
    exit;
}

$uploadDir = __DIR__ . '/../../../../assets/uploads/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Preserve original user uploaded document name
$rawFileName = basename($fileName);
$safeFileName = preg_replace('/[^\w\s\(\)\-\.]/u', '_', $rawFileName);
if (empty($safeFileName)) $safeFileName = 'document.' . $ext;

$targetPath = $uploadDir . $safeFileName;
$dbFilePath = 'assets/uploads/documents/' . $safeFileName;

$saved = false;
if (is_uploaded_file($fileTmp)) {
    $saved = move_uploaded_file($fileTmp, $targetPath);
} else if (file_exists($fileTmp)) {
    $saved = copy($fileTmp, $targetPath);
}

if ($saved) {
    try {
        $stmt = $conn->prepare("INSERT INTO org_documents (OrgId, EventId, Title, DocType, Description, FilePath, FileSize, UploadedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisssss", $orgId, $eventId, $title, $docType, $description, $dbFilePath, $formattedSize);
        
        if ($stmt->execute()) {
            $docId = $stmt->insert_id;
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Upload Document', 'organization', $orgId, 'success', [
                'document_id' => $docId,
                'title'       => $title,
                'doc_type'    => $docType,
                'event_id'    => $eventId,
                'file_name'   => $fileName,
                'file_size'   => $formattedSize,
                'file_path'   => $dbFilePath
            ]);
            echo json_encode(['success' => true, 'message' => 'Document uploaded successfully', 'doc_id' => $docId]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
}
?>
