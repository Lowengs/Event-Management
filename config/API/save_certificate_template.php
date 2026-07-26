<?php
/**
 * save_certificate_template.php
 * POST: TemplateName, TemplateImage (file), NameX, NameY, FontSize, FontColor
 *       Optional: TemplateId (for update/replace)
 */
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');
error_reporting(E_ERROR);
ini_set('display_errors', 0);

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid method']); exit; }

$orgId      = (int)$_SESSION['org_id'];
$templateId = isset($_POST['TemplateId']) ? (int)$_POST['TemplateId'] : 0;
$name       = trim($_POST['TemplateName'] ?? '');

// Name-overlay config
$nameX     = floatval($_POST['NameX']     ?? 0.5);   // 0–1 relative X
$nameY     = floatval($_POST['NameY']     ?? 0.45);  // 0–1 relative Y
$fontSize  = max(8, min(200, (int)($_POST['FontSize']  ?? 60)));
$fontColor = preg_replace('/[^0-9a-fA-F#]/', '', $_POST['FontColor'] ?? '#1e293b');

// Store as simple FieldConfig JSON (backwards-compatible)
$fieldConfig = json_encode([
    ['id'=>'student_name','label'=>'Student Name','value'=>'{{student_name}}',
     'x'=>$nameX,'y'=>$nameY,'fontSize'=>$fontSize,'fontFamily'=>'Inter',
     'color'=>$fontColor,'bold'=>true,'italic'=>false,'align'=>'center']
]);

if (!$name) { echo json_encode(['success'=>false,'message'=>'Template name is required']); exit; }

// Upload directory
$uploadDir = __DIR__ . '/../../assets/uploads/cert_templates/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$imagePath = '';

// Handle file upload
if (!empty($_FILES['TemplateImage']['tmp_name']) && $_FILES['TemplateImage']['error'] === UPLOAD_ERR_OK) {
    $file    = $_FILES['TemplateImage'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success'=>false,'message'=>'Only JPG/PNG/WEBP images are allowed']); exit;
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success'=>false,'message'=>'File must be under 10 MB']); exit;
    }
    $filename = 'cert_' . $orgId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success'=>false,'message'=>'File upload failed – check directory permissions']); exit;
    }
    $imagePath = 'assets/uploads/cert_templates/' . $filename;
}

$eventId = isset($_POST['EventId']) && (int)$_POST['EventId'] > 0 ? (int)$_POST['EventId'] : null;

if ($templateId > 0) {
    // Update existing template
    if ($imagePath) {
        $stmt = $conn->prepare("UPDATE certificate_templates SET TemplateName=?, TemplateImage=?, FieldConfig=?, EventId=? WHERE TemplateId=? AND OrgId=?");
        $stmt->bind_param('sssiii', $name, $imagePath, $fieldConfig, $eventId, $templateId, $orgId);
    } else {
        $stmt = $conn->prepare("UPDATE certificate_templates SET TemplateName=?, FieldConfig=?, EventId=? WHERE TemplateId=? AND OrgId=?");
        $stmt->bind_param('ssiii', $name, $fieldConfig, $eventId, $templateId, $orgId);
    }
    $stmt->execute();
    logAudit($conn,
        'Certificate Template Updated',
        'org',
        $orgId,
        'success',
        ['template_id' => $templateId, 'template_name' => $name, 'image_replaced' => $imagePath ? 'yes' : 'no']
    );
    echo json_encode(['success'=>true,'message'=>'Template updated','template_id'=>$templateId]);
} else {
    // Insert new template
    if (!$imagePath) {
        echo json_encode(['success'=>false,'message'=>'Please upload a certificate image']); exit;
    }
    $stmt = $conn->prepare("INSERT INTO certificate_templates (OrgId, TemplateName, TemplateImage, FieldConfig, EventId) VALUES (?,?,?,?,?)");
    $stmt->bind_param('isssi', $orgId, $name, $imagePath, $fieldConfig, $eventId);
    $stmt->execute();
    $newId = $conn->insert_id;
    logAudit($conn,
        'Certificate Template Uploaded',
        'org',
        $orgId,
        'success',
        ['template_id' => $newId, 'template_name' => $name, 'image' => $imagePath]
    );
    echo json_encode(['success'=>true,'message'=>'Template saved','template_id'=>$newId,'image_path'=>$imagePath]);
}
