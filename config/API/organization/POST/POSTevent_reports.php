<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Organization login required']); exit; }
$orgId = (int)$_SESSION['org_id'];
$eventId = (int)($_POST['EventId'] ?? 0);
$title = trim($_POST['Title'] ?? '');
$description = trim($_POST['Description'] ?? '');
$docType = trim($_POST['DocType'] ?? 'PostActivityReport');
if (!in_array($docType, ['PostActivityReport', 'FinancialReport'], true)) { echo json_encode(['success'=>false,'message'=>'Invalid report type']); exit; }
if (!$eventId || !$title) { echo json_encode(['success'=>false,'message'=>'Event and report title are required']); exit; }
$owner = $conn->prepare('SELECT EventId, EventName FROM event WHERE EventId = ? AND OrgId = ?');
$owner->bind_param('ii', $eventId, $orgId); $owner->execute();
$eventRow = $owner->get_result()->fetch_assoc();
if (!$eventRow) { echo json_encode(['success'=>false,'message'=>'Event not found']); exit; }

$cleanEventName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $eventRow['EventName'] ?? 'Event');
$cleanDocType = preg_replace('/[^a-zA-Z0-9_-]/', '_', $docType);

$files = [['key'=>'DocFile', 'type'=>$docType, 'title'=>$title]];
foreach ($files as $file) {
    $f = $_FILES[$file['key']] ?? [];
    if (empty($f['name']) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'message'=>'Please select the required report file']); exit; }
    if (($f['size'] ?? 0) > 25 * 1024 * 1024) { echo json_encode(['success'=>false,'message'=>'Each report must be 25MB or smaller']); exit; }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') { echo json_encode(['success'=>false,'message'=>'Only PDF files (.pdf) are allowed for event reports']); exit; }
}
$dir = __DIR__ . '/../../../../assets/uploads/documents/';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) { echo json_encode(['success'=>false,'message'=>'Could not create upload directory']); exit; }
$conn->begin_transaction();
try {
    foreach ($files as $file) {
        $f = $_FILES[$file['key']];
        $rawReportName = basename($f['name']);
        $safeReportName = preg_replace('/[^\w\s\(\)\-\.]/u', '_', $rawReportName);
        if (empty($safeReportName)) $safeReportName = 'event_report.pdf';
        
        if (!move_uploaded_file($f['tmp_name'], $dir . $safeReportName)) throw new RuntimeException('Could not save report file');
        $path = 'assets/uploads/documents/' . $safeReportName;
        $size = round($f['size'] / 1024, 1) . ' KB';
        $stmt = $conn->prepare('INSERT INTO org_documents (OrgId, EventId, Title, DocType, Description, FilePath, FileSize, UploadedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('iisssss', $orgId, $eventId, $file['title'], $file['type'], $description, $path, $size);
        if (!$stmt->execute()) throw new RuntimeException('Could not record uploaded report');
    }
    $conn->commit();
    require_once __DIR__ . '/../../../audit.php';
    logAudit($conn, 'Upload Event Report', 'organization', $orgId, 'success', [
        'event_id'    => $eventId,
        'title'       => $title,
        'report_type' => $docType,
        'description' => $description
    ]);
    echo json_encode(['success'=>true,'message'=>'Event reports uploaded']);
} catch (Throwable $e) { $conn->rollback(); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
?>
