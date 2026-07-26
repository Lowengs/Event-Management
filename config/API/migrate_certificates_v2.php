<?php
/**
 * migrate_certificates_v2.php
 * Run once: http://localhost/Project/config/API/migrate_certificates_v2.php
 * Adds GeneratedImage column to certificates table + creates upload dirs.
 */
require_once '../db.php';
$results = [];

// 1. Create tables if not exist
$conn->query("CREATE TABLE IF NOT EXISTS certificate_templates (
    TemplateId    INT AUTO_INCREMENT PRIMARY KEY,
    OrgId         INT NOT NULL,
    TemplateName  VARCHAR(200) NOT NULL,
    TemplateImage VARCHAR(500) NOT NULL,
    FieldConfig   LONGTEXT NOT NULL DEFAULT '[]',
    CreatedAt     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    IsDeleted     TINYINT(1) DEFAULT 0,
    INDEX idx_org (OrgId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = ' certificate_templates table OK';

$conn->query("CREATE TABLE IF NOT EXISTS certificates (
    CertId         INT AUTO_INCREMENT PRIMARY KEY,
    TemplateId     INT NOT NULL,
    EventId        INT NOT NULL,
    UserId         INT NOT NULL,
    CertCode       VARCHAR(64) UNIQUE NOT NULL,
    GeneratedImage VARCHAR(500) DEFAULT NULL,
    IssuedAt       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user  (UserId),
    INDEX idx_event (EventId),
    UNIQUE KEY unique_user_event (UserId, EventId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$results[] = ' certificates table OK';

// 2. Add GeneratedImage column if missing
$r = $conn->query("ALTER TABLE certificates ADD COLUMN IF NOT EXISTS GeneratedImage VARCHAR(500) DEFAULT NULL");
$results[] = $r ? ' GeneratedImage column added/verified' : '  ' . $conn->error;

// 3. Create upload directories
foreach ([
    __DIR__ . '/../../assets/uploads/cert_templates/',
    __DIR__ . '/../../assets/uploads/generated_certs/',
] as $dir) {
    if (!is_dir($dir)) { mkdir($dir, 0775, true); $results[] = ' Created: ' . $dir; }
    else $results[] = ' Exists: ' . $dir;
    file_put_contents($dir . '.htaccess', "Options -Indexes\nAllow from all\n");
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Certificate Migration v2</title>
<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:40px;line-height:2;}h1{color:#6366f1;}li{margin:4px 0;}</style></head>
<body>
<h1>🎓 Certificate Migration v2</h1>
<ul><?php foreach($results as $r2) echo "<li>$r2</li>"; ?></ul>
<p style="color:#10b981;margin-top:24px;"> Done. <a href="../../app/organization/certificate-templates.php" style="color:#6366f1;">Go to Certificate Templates →</a></p>
</body></html>
