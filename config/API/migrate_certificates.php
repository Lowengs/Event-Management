<?php
/**
 * migrate_certificates.php
 * Run once in browser: http://localhost/Project/config/API/migrate_certificates.php
 * Creates certificate_templates, certificates tables and adds EventType to event.
 */
require_once '../db.php';

$results = [];

// 1. Add EventType to event table
$r1 = $conn->query("ALTER TABLE event ADD COLUMN IF NOT EXISTS EventType ENUM('online','onsite') NOT NULL DEFAULT 'onsite'");
$results[] = $r1 ? '✅ event.EventType column added (or already exists)' : '⚠️  event.EventType: ' . $conn->error;

// 2. Create certificate_templates table
$sql2 = "CREATE TABLE IF NOT EXISTS certificate_templates (
    TemplateId    INT AUTO_INCREMENT PRIMARY KEY,
    OrgId         INT NOT NULL,
    TemplateName  VARCHAR(200) NOT NULL,
    TemplateImage VARCHAR(500) NOT NULL,
    FieldConfig   LONGTEXT NOT NULL COMMENT 'JSON array of field definitions',
    CreatedAt     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    IsDeleted     TINYINT(1) DEFAULT 0,
    INDEX idx_org (OrgId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$r2 = $conn->query($sql2);
$results[] = $r2 ? '✅ certificate_templates table OK' : '❌ certificate_templates: ' . $conn->error;

// 3. Create certificates table
$sql3 = "CREATE TABLE IF NOT EXISTS certificates (
    CertId        INT AUTO_INCREMENT PRIMARY KEY,
    TemplateId    INT NOT NULL,
    EventId       INT NOT NULL,
    UserId        INT NOT NULL,
    CertCode      VARCHAR(64) UNIQUE NOT NULL COMMENT 'UUID for verification',
    IssuedAt      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user  (UserId),
    INDEX idx_event (EventId),
    UNIQUE KEY unique_user_event (UserId, EventId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$r3 = $conn->query($sql3);
$results[] = $r3 ? '✅ certificates table OK' : '❌ certificates: ' . $conn->error;

// 4. Create uploads directory hint
$uploadDir = __DIR__ . '/../../assets/uploads/cert_templates';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
    $results[] = '✅ cert_templates upload directory created';
} else {
    $results[] = '✅ cert_templates upload directory already exists';
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Certificate Migration</title>
<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:40px;line-height:2;}
h1{color:#6366f1;}li{margin:4px 0;}</style></head>
<body>
<h1>🎓 Certificate System Migration</h1>
<ul>
<?php foreach ($results as $r): ?>
  <li><?= htmlspecialchars($r) ?></li>
<?php endforeach; ?>
</ul>
<p style="color:#10b981;margin-top:24px;">✅ Migration complete. <a href="../../app/organization/certificate-templates.php" style="color:#6366f1;">Go to Certificate Templates →</a></p>
</body></html>
