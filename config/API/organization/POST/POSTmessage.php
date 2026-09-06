<?php
/**
 * Organization API: Send Message to OSA
 * Endpoint: /config/API/endpoints/index.php?action=POSTmessage
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

$orgId   = (int)$_SESSION['org_id'];
$message = trim($_POST['message'] ?? '');
$hasAtt  = !empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK;

if (empty($message) && !$hasAtt) {
    echo json_encode(['success' => false, 'message' => 'Message or attachment is required']);
    exit;
}

$attPath = null;
$attName = null;
$attType = null;

if ($hasAtt) {
    $f = $_FILES['attachment'];
    $origName = basename($f['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['pdf', 'docx', 'doc', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
    
    if (!in_array($ext, $allowedExts, true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed formats: PDF, DOCX, DOC, Images (PNG, JPG, WEBP, GIF).']);
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

    $newFileName = 'org_msg_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $ext;
    $targetPath = $uploadDir . $newFileName;

    if (move_uploaded_file($f['tmp_name'], $targetPath)) {
        $attPath = 'assets/uploads/messages/' . $newFileName;
        $attName = $origName;
        $attType = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'docx');
    }
}

try {
    $stmt = $conn->prepare("INSERT INTO org_messages (OrgId, SenderType, SenderId, Message, AttachmentPath, AttachmentName, AttachmentType, IsRead, SentAt) VALUES (?, 'org', ?, ?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param("iissss", $orgId, $orgId, $message, $attPath, $attName, $attType);

    if ($stmt->execute()) {
        require_once __DIR__ . '/../../../audit.php';
        logAudit($conn, 'Send Message', 'organization', $orgId, 'success', [
            'recipient'       => 'OSA',
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
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
