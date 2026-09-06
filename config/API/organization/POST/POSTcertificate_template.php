<?php
/**
 * Organization API: Save Certificate Template
 * Endpoint: /config/API/endpoints/index.php?action=POSTcertificate_template
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

@ini_set('upload_max_filesize', '64M');
@ini_set('post_max_size', '64M');
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '120');

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$orgId     = (int)$_SESSION['org_id'];
$templateId = (int)($_POST['TemplateId'] ?? $_POST['template_id'] ?? $_POST['id'] ?? 0);

@$conn->query("CREATE TABLE IF NOT EXISTS certificate_templates (
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
if (!empty($_FILES['TemplateImage'])) {
    $uploadErr = $_FILES['TemplateImage']['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
        echo json_encode(['success' => false, 'message' => 'Uploaded image exceeds server size limit. Please choose a smaller image.']);
        exit;
    } elseif ($uploadErr !== UPLOAD_ERR_OK && $uploadErr !== UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'File upload error (code ' . $uploadErr . ')']);
        exit;
    }

    if ($uploadErr === UPLOAD_ERR_OK && !empty($_FILES['TemplateImage']['tmp_name'])) {
        $dir = __DIR__ . '/../../../../assets/uploads/cert_templates/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $rawName = $_FILES['TemplateImage']['name'] ?? '';
        $ext = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));
        if (empty($ext)) {
            $mime = $_FILES['TemplateImage']['type'] ?? '';
            if ($mime === 'image/png') $ext = 'png';
            elseif ($mime === 'image/webp') $ext = 'webp';
            else $ext = 'jpg';
        }
        $fname = 'cert_' . $orgId . '_' . time() . '_' . substr(md5(rand()), 0, 8) . '.' . $ext;
        $targetFile = $dir . $fname;
        if (move_uploaded_file($_FILES['TemplateImage']['tmp_name'], $targetFile)) {
            // Optional server-side optimization for very large files (> 2.5MB)
            if (filesize($targetFile) > 2.5 * 1024 * 1024 && extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
                try {
                    $info = @getimagesize($targetFile);
                    if ($info && !empty($info[2])) {
                        $src = null;
                        if ($info[2] === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($targetFile);
                        elseif ($info[2] === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($targetFile);
                        elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($targetFile);
                        if ($src) {
                            $w = imagesx($src);
                            $h = imagesy($src);
                            $maxDim = 2048;
                            if ($w > $maxDim || $h > $maxDim) {
                                $nw = $w > $h ? $maxDim : round($w * $maxDim / $h);
                                $nh = $w > $h ? round($h * $maxDim / $w) : $maxDim;
                                $dst = imagecreatetruecolor($nw, $nh);
                                if ($dst) {
                                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                                    imagedestroy($src);
                                    $src = $dst;
                                }
                            }
                            if ($ext === 'jpg' || $ext === 'jpeg') {
                                @imagejpeg($src, $targetFile, 86);
                            } elseif ($ext === 'png') {
                                @imagepng($src, $targetFile, 8);
                            } elseif ($ext === 'webp') {
                                @imagewebp($src, $targetFile, 86);
                            }
                            imagedestroy($src);
                        }
                    }
                } catch (Throwable $t) {
                    // Fail silently and keep original file
                }
            }
            $imgPath = 'assets/uploads/cert_templates/' . $fname;
        }
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
