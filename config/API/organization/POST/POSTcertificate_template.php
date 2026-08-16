<?php
/**
 * Organization API: Save Certificate Template
 * Endpoint: /config/API/endpoints/index.php?action=POSTcertificate_template
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$orgId     = (int)$_SESSION['org_id'];
$templateId = (int)($_POST['TemplateId'] ?? $_POST['template_id'] ?? $_POST['id'] ?? 0);

$conn->query("CREATE TABLE IF NOT EXISTS certificate_templates (
    TemplateId INT AUTO_INCREMENT PRIMARY KEY, OrgId INT NOT NULL, EventId INT NULL,
    TemplateName VARCHAR(255) NOT NULL, TemplateImage VARCHAR(500) NOT NULL,
    FieldConfig TEXT NULL, CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    IsDeleted TINYINT(1) DEFAULT 0
) ENGINE=InnoDB");

$name      = trim($_POST['TemplateName'] ?? $_POST['name'] ?? '');
$nameXVal  = (float)($_POST['NameX'] ?? 50);
$nameYVal  = (float)($_POST['NameY'] ?? 50);
$nameX     = $nameXVal > 1 ? ($nameXVal / 100.0) : $nameXVal;
$nameY     = $nameYVal > 1 ? ($nameYVal / 100.0) : $nameYVal;
$fontSize  = (int)($_POST['FontSize']    ?? 60);
$fontColor = trim($_POST['FontColor']   ?? '#1e293b');
$fontFamily = trim($_POST['FontFamily'] ?? "'Inter', sans-serif");
$eventId   = !empty($_POST['EventId'])   ? (int)$_POST['EventId'] : null;

$imgPath = '';
if (!empty($_FILES['TemplateImage']['name']) && $_FILES['TemplateImage']['error'] === UPLOAD_ERR_OK) {
    $dir = __DIR__ . '/../../../../assets/uploads/cert_templates/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext   = strtolower(pathinfo($_FILES['TemplateImage']['name'], PATHINFO_EXTENSION));
    $fname = 'cert_' . $orgId . '_' . time() . '_' . substr(md5(rand()), 0, 8) . '.' . $ext;
    if (move_uploaded_file($_FILES['TemplateImage']['tmp_name'], $dir . $fname)) {
        $imgPath = 'assets/uploads/cert_templates/' . $fname;
    }
}

// ── Handle Replace Image Only ──
if ($templateId > 0 && ($name === '_keep_' || empty($name))) {
    if (empty($imgPath)) {
        echo json_encode(['success' => false, 'message' => 'Template image file is required to replace']);
        exit;
    }
    $upStmt = $conn->prepare("UPDATE certificate_templates SET TemplateImage = ?, UpdatedAt = NOW() WHERE TemplateId = ? AND OrgId = ?");
    if ($upStmt) {
        $upStmt->bind_param("sii", $imgPath, $templateId, $orgId);
        $upStmt->execute();
        $upStmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Template image replaced successfully',
            'template_id' => $templateId,
            'image_path' => $imgPath
        ]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Failed to replace template image: ' . $conn->error]);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Template name is required']);
    exit;
}

$fieldConfig = json_encode([[
    'id' => 'student_name',
    'label' => 'Student Name',
    'value' => '{{student_name}}',
    'x' => $nameX,
    'y' => $nameY,
    'fontSize' => $fontSize,
    'fontFamily' => $fontFamily,
    'color' => $fontColor,
    'bold' => true,
    'italic' => false,
    'align' => 'center'
]], JSON_UNESCAPED_SLASHES);

try {
    if ($templateId > 0) {
        // Update existing template
        if (!empty($imgPath)) {
            $stmt = $conn->prepare("
                UPDATE certificate_templates
                SET TemplateName = ?, TemplateImage = ?, FieldConfig = ?, EventId = COALESCE(?, EventId), UpdatedAt = NOW()
                WHERE TemplateId = ? AND OrgId = ?
            ");
            $stmt->bind_param("sssiii", $name, $imgPath, $fieldConfig, $eventId, $templateId, $orgId);
        } else {
            $stmt = $conn->prepare("
                UPDATE certificate_templates
                SET TemplateName = ?, FieldConfig = ?, EventId = COALESCE(?, EventId), UpdatedAt = NOW()
                WHERE TemplateId = ? AND OrgId = ?
            ");
            $stmt->bind_param("ssiii", $name, $fieldConfig, $eventId, $templateId, $orgId);
        }
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode([
                'success' => true,
                'message' => 'Template updated successfully',
                'template_id' => $templateId
            ]);
            exit;
        }
    } else {
        // Insert new template
        if (empty($imgPath)) {
            echo json_encode(['success' => false, 'message' => 'Template image file is required']);
            exit;
        }
        $stmt = $conn->prepare("
            INSERT INTO `certificate_templates` (`OrgId`, `EventId`, `TemplateName`, `TemplateImage`, `FieldConfig`)
            VALUES (?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("iisss", $orgId, $eventId, $name, $imgPath, $fieldConfig);
            if ($stmt->execute()) {
                $tplId = $stmt->insert_id;
                $stmt->close();
                if (file_exists(__DIR__ . '/../../../audit.php')) {
                    require_once __DIR__ . '/../../../audit.php';
                    logAudit($conn, 'Create Certificate Template', 'organization', $orgId, 'success', ['TemplateId' => $tplId, 'TemplateName' => $name, 'EventId' => $eventId]);
                }
                echo json_encode([
                    'success' => true,
                    'message' => 'Template saved successfully',
                    'template_id' => $tplId,
                    'image_path' => $imgPath
                ]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false, 'message' => $conn->error ?: 'Database operation failed']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
