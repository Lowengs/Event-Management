<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) { echo json_encode(['success'=>false,'message'=>'OSA administrator login required']); exit; }
$orgId = (int)($_POST['org_id'] ?? $_POST['to_org_id'] ?? 0);
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? $_POST['body'] ?? '');
if (!$orgId || $message === '') { echo json_encode(['success'=>false,'message'=>'Organization and message are required']); exit; }
$attPath = null;
$attName = null;
$attType = null;

if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['attachment'];
    $origName = basename($f['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'docx', 'doc', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
    
    if (!in_array($ext, $allowedExts, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed formats: PDF, DOCX, Images (PNG, JPG, WEBP).']);
        exit;
    }

    if ($f['size'] > (15 * 1024 * 1024)) { // 15MB limit
        echo json_encode(['success' => false, 'message' => 'File size exceeds the 15MB limit.']);
        exit;
    }

    $uploadDir = __DIR__ . '/../../../../assets/uploads/messages/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $cleanName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
    $newFileName = 'osa_msg_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
    $targetPath = $uploadDir . $newFileName;

    if (move_uploaded_file($f['tmp_name'], $targetPath)) {
        $attPath = 'assets/uploads/messages/' . $newFileName;
        $attName = $origName;
        $attType = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'docx');
    }
}

$osaId = (int)($_SESSION['osa_id'] ?? 0);

$chk = $conn->query("SHOW COLUMNS FROM `org_messages` LIKE 'AttachmentPath'");
$hasAttCols = ($chk && $chk->num_rows > 0);
if (!$hasAttCols) {
    if ($conn->query("ALTER TABLE `org_messages` ADD COLUMN `AttachmentPath` VARCHAR(255) NULL, ADD COLUMN `AttachmentName` VARCHAR(255) NULL, ADD COLUMN `AttachmentType` VARCHAR(50) NULL")) {
        $hasAttCols = true;
    }
}

if ($hasAttCols) {
    $stmt = $conn->prepare("INSERT INTO org_messages (OrgId, SenderType, SenderId, Subject, Message, AttachmentPath, AttachmentName, AttachmentType, IsRead, SentAt) VALUES (?, 'osa', ?, ?, ?, ?, ?, ?, 0, NOW())");
    if (!$stmt) { echo json_encode(['success'=>false,'message'=>$conn->error]); exit; }
    $stmt->bind_param('iisssss', $orgId, $osaId, $subject, $message, $attPath, $attName, $attType);
} else {
    $finalMsg = $message;
    if ($attPath) {
        $finalMsg .= ($finalMsg !== '' ? "\n" : "") . "[Attachment: " . $attName . " (" . $attPath . ")]";
    }
    $stmt = $conn->prepare("INSERT INTO org_messages (OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt) VALUES (?, 'osa', ?, ?, ?, 0, NOW())");
    if (!$stmt) { echo json_encode(['success'=>false,'message'=>$conn->error]); exit; }
    $stmt->bind_param('iiss', $orgId, $osaId, $subject, $finalMsg);
}
if ($stmt->execute()) {
    require_once __DIR__ . '/../../../audit.php';
    logAudit($conn, 'Send Message', 'osa', $osaId ?: 1, 'success', [
        'to_org_id'       => $orgId,
        'subject'         => $subject,
        'has_attachment'  => !empty($attPath),
        'attachment_name' => $attName,
        'message_preview' => mb_substr($message, 0, 120)
    ]);
    echo json_encode([
        'success'         => true,
        'message'         => 'Message sent successfully',
        'attachment_path' => $attPath,
        'attachment_name' => $attName,
        'attachment_type' => $attType
    ]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
$stmt->close();
?>
