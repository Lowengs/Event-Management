<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../cert_generator.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { 
    echo json_encode(['success' => false, 'message' => 'Organization login required']); 
    exit; 
}

$orgId = (int)$_SESSION['org_id']; 
$eventId = (int)($_POST['EventId'] ?? 0); 
$templateId = (int)($_POST['TemplateId'] ?? 0);

if (!$eventId || !$templateId) { 
    echo json_encode(['success' => false, 'message' => 'Event and template are required']); 
    exit; 
}

// Fetch event details and template configuration
$check = $conn->prepare('SELECT e.EventName, t.TemplateImage, t.FieldConfig FROM event e JOIN certificate_templates t ON (t.OrgId=e.OrgId OR t.OrgId=0) WHERE e.EventId=? AND e.OrgId=? AND t.TemplateId=?');
if (!$check) { 
    echo json_encode(['success' => false, 'message' => 'Database error preparing event verification']); 
    exit; 
}
$check->bind_param('iii', $eventId, $orgId, $templateId); 
$check->execute(); 
$eventData = $check->get_result()->fetch_assoc(); 
$check->close();

if (!$eventData) { 
    echo json_encode(['success' => false, 'message' => 'Event or template was not found']); 
    exit; 
}

// Ensure certificates table exists
$conn->query("CREATE TABLE IF NOT EXISTS certificates (
    CertId INT AUTO_INCREMENT PRIMARY KEY, 
    OrgId INT NOT NULL, 
    EventId INT NOT NULL, 
    UserId INT NOT NULL, 
    TemplateId INT NOT NULL, 
    CertCode VARCHAR(80) NOT NULL, 
    GeneratedImage VARCHAR(500) NULL, 
    IssuedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    UNIQUE KEY uq_event_user (EventId, UserId)
) ENGINE=InnoDB");

// Check font engine status
$fontFound = false;
$fontName = 'System Font';
$baseDir = dirname(dirname(dirname(__DIR__)));
$fontPaths = [
    $baseDir . '/assets/fonts/arialbd.ttf',
    $baseDir . '/assets/fonts/arial.ttf',
    $baseDir . '/assets/fonts/timesbd.ttf',
    $baseDir . '/assets/fonts/times.ttf',
    $baseDir . '/assets/fonts/Inter-Bold.ttf',
    $baseDir . '/assets/fonts/Inter.ttf',
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/arial.ttf',
    'C:/Windows/Fonts/calibrib.ttf',
    'C:/Windows/Fonts/georgia.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
];
foreach ($fontPaths as $fp) {
    if (file_exists($fp)) {
        $fontFound = true;
        $fontName = basename($fp);
        break;
    }
}

// Query all students marked present at this event
$att = $conn->prepare("SELECT DISTINCT a.UserId, u.first_name, u.last_name, u.middle_name 
    FROM attendance a 
    JOIN user u ON u.UserId = a.UserId 
    WHERE a.EventId = ? AND LOWER(COALESCE(a.AttendanceStatus, 'present')) = 'present'");
if (!$att) { 
    echo json_encode(['success' => false, 'message' => 'Failed to query present attendance records']); 
    exit; 
}
$att->bind_param('i', $eventId); 
$att->execute(); 
$res = $att->get_result(); 
$issued = 0; 
$updated = 0;

while ($res && ($row = $res->fetch_assoc())) {
    $userId = (int)$row['UserId'];
    $fn = trim($row['first_name'] ?? '');
    $mn = trim($row['middle_name'] ?? '');
    $ln = trim($row['last_name'] ?? '');
    $studentName = trim($fn . ' ' . (!empty($mn) ? substr($mn, 0, 1) . '. ' : '') . $ln);
    if ($studentName === '') $studentName = 'Student';

    // Generate personalized certificate image file with student name overlay
    $genImg = generateCertificateImageFile(
        $eventData['TemplateImage'],
        $studentName,
        $eventData['FieldConfig'],
        $eventId,
        $userId
    );

    $code = 'NAAP-' . $eventId . '-' . $userId . '-' . strtoupper(substr(md5($templateId . '|' . $userId), 0, 6));

    // Check if certificate already exists for this event and student (Prevent duplicates & OVERRIDE existing)
    $chkExisting = $conn->query("SELECT CertId FROM certificates WHERE EventId = $eventId AND UserId = $userId LIMIT 1");
    if ($chkExisting && ($existRow = $chkExisting->fetch_assoc())) {
        $certId = (int)$existRow['CertId'];
        $up = $conn->prepare("UPDATE certificates SET OrgId = ?, TemplateId = ?, CertCode = ?, GeneratedImage = ?, IssuedAt = NOW() WHERE CertId = ?");
        $up->bind_param('iissi', $orgId, $templateId, $code, $genImg, $certId);
        $up->execute();
        $up->close();

        // Delete any duplicate certificate rows if more than 1 existed previously
        $conn->query("DELETE FROM certificates WHERE EventId = $eventId AND UserId = $userId AND CertId != $certId");
        $updated++;
    } else {
        // Insert new certificate record
        $ins = $conn->prepare("INSERT INTO certificates (OrgId, EventId, UserId, TemplateId, CertCode, GeneratedImage, IssuedAt) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $ins->bind_param('iiiiss', $orgId, $eventId, $userId, $templateId, $code, $genImg);
        $ins->execute();
        $ins->close();
        $issued++;
    }
}
$att->close();

if (file_exists(__DIR__ . '/../../../audit.php')) {
    require_once __DIR__ . '/../../../audit.php';
    logAudit($conn, 'Issue Certificates', 'organization', $orgId, 'success', [
        'EventId' => $eventId, 
        'EventName' => $eventData['EventName'], 
        'Issued' => $issued, 
        'Updated' => $updated
    ]);
}

echo json_encode([
    'success' => true,
    'message' => 'Certificate issuance completed',
    'event' => $eventData['EventName'],
    'issued' => $issued,
    'skipped' => $updated, // Report updated count
    'font_name' => $fontName,
    'font_found' => $fontFound
]);
?>
