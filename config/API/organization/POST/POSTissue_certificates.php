<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Organization login required']); exit; }
$orgId=(int)$_SESSION['org_id']; $eventId=(int)($_POST['EventId'] ?? 0); $templateId=(int)($_POST['TemplateId'] ?? 0);
if (!$eventId || !$templateId) { echo json_encode(['success'=>false,'message'=>'Event and template are required']); exit; }

$check=$conn->prepare('SELECT e.EventName FROM event e JOIN certificate_templates t ON (t.OrgId=e.OrgId OR t.OrgId=0) WHERE e.EventId=? AND e.OrgId=? AND t.TemplateId=?');
if (!$check) { echo json_encode(['success'=>false,'message'=>'Database error preparing event verification']); exit; }
$check->bind_param('iii',$eventId,$orgId,$templateId); $check->execute(); $event=$check->get_result()->fetch_assoc(); $check->close();
if (!$event) { echo json_encode(['success'=>false,'message'=>'Event or template was not found']); exit; }

$conn->query("CREATE TABLE IF NOT EXISTS certificates (CertId INT AUTO_INCREMENT PRIMARY KEY, OrgId INT NOT NULL, EventId INT NOT NULL, UserId INT NOT NULL, TemplateId INT NOT NULL, CertCode VARCHAR(80) NOT NULL, GeneratedImage VARCHAR(500) NULL, IssuedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_certificate (EventId, UserId, TemplateId)) ENGINE=InnoDB");

// Check font engine status
$fontFound = false;
$fontName = 'System Font';
$fontPaths = [
    __DIR__ . '/../../../assets/fonts/Inter-Bold.ttf',
    __DIR__ . '/../../../assets/fonts/Inter.ttf',
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/Arial.ttf',
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

$att=$conn->prepare("SELECT DISTINCT a.UserId FROM attendance a WHERE a.EventId=? AND LOWER(COALESCE(a.AttendanceStatus, 'present'))='present'");
if (!$att) { echo json_encode(['success'=>false,'message'=>'Failed to query present attendance records']); exit; }
$att->bind_param('i',$eventId); $att->execute(); $res=$att->get_result(); $issued=0; $skipped=0;
$insert=$conn->prepare('INSERT IGNORE INTO certificates (OrgId, EventId, UserId, TemplateId, CertCode, GeneratedImage, IssuedAt) VALUES (?, ?, ?, ?, ?, NULL, NOW())');
if (!$insert) { echo json_encode(['success'=>false,'message'=>'Failed to prepare certificate issuance query']); exit; }

while($res && ($row=$res->fetch_assoc())) {
    $userId=(int)$row['UserId'];
    $code='NAAP-' . $eventId . '-' . $userId . '-' . strtoupper(substr(md5($templateId.'|'.$userId),0,6));
    $insert->bind_param('iiiis',$orgId,$eventId,$userId,$templateId,$code);
    $insert->execute();
    if($insert->affected_rows) $issued++; else $skipped++;
}
$att->close(); $insert->close();
if (file_exists(__DIR__ . '/../../../audit.php')) {
    require_once __DIR__ . '/../../../audit.php';
    logAudit($conn, 'Issue Certificates', 'organization', $orgId, 'success', ['EventId' => $eventId, 'EventName' => $event['EventName'], 'Issued' => $issued, 'Skipped' => $skipped]);
}
echo json_encode([
    'success'=>true,
    'message'=>'Certificate issuance completed',
    'event'=>$event['EventName'],
    'issued'=>$issued,
    'skipped'=>$skipped,
    'font_name'=>$fontName,
    'font_found'=>$fontFound
]);
?>
