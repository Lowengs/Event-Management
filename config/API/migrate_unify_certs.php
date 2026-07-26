<?php
/**
 * migrate_unify_certs.php
 * Run ONCE in browser: http://localhost/Project/config/API/migrate_unify_certs.php
 *
 * Steps:
 *   1. Allow TemplateId to be NULL (for legacy/manual certs)
 *   2. Add OrgId + CertificateURL columns to certificates
 *   3. Migrate all rows from old `certificate` table -> `certificates`
 *   4. Rename old `certificate` table to `certificate_backup`
 */
require_once '../db.php';
$results = [];

// Step 1: Allow TemplateId to be NULL
$r1 = $conn->query("ALTER TABLE certificates MODIFY COLUMN TemplateId INT DEFAULT NULL");
$results[] = $r1
    ? 'ok:Step 1: TemplateId now allows NULL'
    : 'err:Step 1 failed: ' . $conn->error;

// Step 2: Add missing columns
$r2a = $conn->query("ALTER TABLE certificates ADD COLUMN IF NOT EXISTS OrgId INT DEFAULT NULL");
$results[] = $r2a
    ? 'ok:Step 2a: OrgId column added (or already exists)'
    : 'err:Step 2a failed: ' . $conn->error;

$r2b = $conn->query("ALTER TABLE certificates ADD COLUMN IF NOT EXISTS CertificateURL VARCHAR(500) DEFAULT NULL");
$results[] = $r2b
    ? 'ok:Step 2b: CertificateURL column added (or already exists)'
    : 'err:Step 2b failed: ' . $conn->error;

// Step 3: Check old table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'certificate'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    $results[] = 'info:Step 3: Old certificate table not found - skipping (already done?)';
} else {
    $countRes = $conn->query("SELECT COUNT(*) FROM certificate");
    $total    = $countRes ? (int)$countRes->fetch_row()[0] : 0;
    $r3 = $conn->query("
        INSERT INTO certificates (TemplateId,EventId,UserId,CertCode,CertificateURL,OrgId,IssuedAt)
        SELECT NULL,c.EventId,c.UserId,CONCAT('legacy_',c.CertificateId),c.CertificateURL,c.OrgId,IFNULL(c.DateIssued,NOW())
        FROM certificate c
        WHERE NOT EXISTS (SELECT 1 FROM certificates x WHERE x.UserId=c.UserId AND x.EventId=c.EventId)
    ");
    if ($r3) {
        $migrated = $conn->affected_rows;
        $results[] = "ok:Step 3: Migrated $migrated / $total row(s) from certificate -> certificates";
        $conn->query("DROP TABLE IF EXISTS certificate_backup");
        $r4 = $conn->query("RENAME TABLE certificate TO certificate_backup");
        $results[] = $r4 ? 'ok:Step 4: certificate renamed to certificate_backup' : 'err:Step 4 rename failed: ' . $conn->error;
    } else {
        $results[] = 'err:Step 3 failed: ' . $conn->error;
    }
}

$vRes = $conn->query("SHOW COLUMNS FROM certificates");
$cols = [];
if ($vRes) while ($row = $vRes->fetch_assoc()) $cols[] = $row['Field'];
$results[] = 'info:Columns in certificates: ' . implode(', ', $cols);
$countFinal = (int)$conn->query("SELECT COUNT(*) FROM certificates")->fetch_row()[0];
$results[] = "info:Total rows in certificates: $countFinal";
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Certificate Unification</title>
<style>
body{font-family:Arial,sans-serif;max-width:700px;margin:40px auto;padding:0 20px;background:#f8fafc;color:#0f172a}
h1{font-size:1.5rem;margin-bottom:8px}
.sub{color:#64748b;font-size:.9rem;margin-bottom:28px}
.r{padding:12px 16px;border-radius:8px;margin-bottom:10px;font-size:.9rem}
.ok{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.err{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.info{background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd}
.foot{margin-top:32px;font-size:.8rem;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:16px}
a{color:#6366f1;font-weight:600}
</style></head><body>
<h1>Certificate Table Unification</h1>
<p class="sub">Merging <code>certificate</code> (legacy) into <code>certificates</code> (unified)</p>
<?php foreach($results as $line):
  [$cls,$msg]=explode(':',$line,2);
?><div class="r <?=$cls?>"><?=htmlspecialchars($msg)?></div><?php endforeach;?>
<p class="foot">Migration complete. Old data preserved in <code>certificate_backup</code>.<br><br>
<a href="../../app/organization/issued_certificates_org.php">View Issued Certificates</a> &nbsp;|&nbsp;
<a href="../../app/student/profile-dashboard.php">Student Dashboard</a></p>
</body></html>
