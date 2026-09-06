<?php
/**
 * Student API: Download or Preview Certificate Stream
 * Endpoint: /config/API/endpoints/index.php?action=download_certificate&cert_id=X[&preview=1]
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../cert_generator.php';

$certId  = (int)($_GET['cert_id'] ?? $_GET['certificate_id'] ?? $_GET['id'] ?? 0);
$eventId = (int)($_GET['event_id'] ?? 0);
$preview = !empty($_GET['preview']);

$studentId = (int)($_SESSION['student_id'] ?? 0);
$isStaff   = !empty($_SESSION['admin_id']) || !empty($_SESSION['osa_id']) || !empty($_SESSION['org_id']);

if (!$studentId && !$isStaff) {
    http_response_code(403);
    die('Login required to access certificates');
}

$baseDir = dirname(__DIR__, 4);

$sql = "SELECT c.*, e.EventName, e.EventDateTime, o.OrgName, t.TemplateImage, t.FieldConfig,
               u.first_name, u.last_name, u.middle_name
        FROM certificates c
        LEFT JOIN event e ON e.EventId = c.EventId
        LEFT JOIN organization o ON o.OrgId = COALESCE(c.OrgId, e.OrgId)
        LEFT JOIN certificate_templates t ON t.TemplateId = c.TemplateId
        LEFT JOIN `user` u ON u.UserId = c.UserId
        WHERE ";

if ($certId > 0) {
    $sql .= "c.CertId = " . $certId;
    if (!$isStaff) {
        $sql .= " AND c.UserId = " . $studentId;
    }
} elseif ($eventId > 0 && $studentId > 0) {
    $sql .= "c.EventId = " . $eventId . " AND c.UserId = " . $studentId . " ORDER BY c.CertId DESC";
} else {
    http_response_code(400);
    die('Certificate ID or Event ID required');
}
$sql .= " LIMIT 1";

$res = $conn->query($sql);
$cert = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

if (!$cert) {
    http_response_code(404);
    die('Certificate not found');
}

// Student Name
$fn = trim($cert['first_name'] ?? '');
$mn = trim($cert['middle_name'] ?? '');
$ln = trim($cert['last_name'] ?? '');
$studentName = trim($fn . ' ' . (!empty($mn) ? substr($mn, 0, 1) . '. ' : '') . $ln);
if ($studentName === '') $studentName = 'Participant';

$fullFilePath = '';
if (!empty($cert['GeneratedImage'])) {
    $clean = ltrim(str_replace(['../', '..\\'], '', $cert['GeneratedImage']), '/\\');
    $p = $baseDir . '/' . $clean;
    if (file_exists($p) && filesize($p) > 500) {
        $fullFilePath = $p;
    }
}

// Auto-generate if missing
if (empty($fullFilePath)) {
    $extraInfo = [
        'EventName' => $cert['EventName'] ?? '',
        'OrgName'   => $cert['OrgName'] ?? '',
        'CertCode'  => $cert['CertCode'] ?? '',
        'EventDate' => !empty($cert['EventDateTime']) ? date('F j, Y', strtotime($cert['EventDateTime'])) : date('F j, Y')
    ];
    $genRel = generateCertificateImageFile(
        $cert['TemplateImage'] ?? '',
        $studentName,
        $cert['FieldConfig'] ?? '',
        (int)$cert['EventId'],
        (int)$cert['UserId'],
        $extraInfo
    );
    if ($genRel) {
        $fullFilePath = $baseDir . '/' . $genRel;
        $cleanRelEsc = $conn->real_escape_string($genRel);
        $cId = (int)$cert['CertId'];
        $conn->query("UPDATE certificates SET GeneratedImage = '$cleanRelEsc' WHERE CertId = $cId");
    }
}

if (empty($fullFilePath) || !file_exists($fullFilePath)) {
    http_response_code(500);
    die('Could not generate certificate image');
}

$ext = strtolower(pathinfo($fullFilePath, PATHINFO_EXTENSION));
$mimeType = ($ext === 'pdf') ? 'application/pdf' : 'image/png';

if (ob_get_level()) {
    ob_end_clean();
}

if ($preview) {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . filesize($fullFilePath));
    readfile($fullFilePath);
    exit;
}

// Attachment Download
$safeCode = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $cert['CertCode'] ?: ('Event_' . $cert['EventId']));
$downloadName = 'Certificate_' . $safeCode . '.' . $ext;

header('Content-Description: File Transfer');
header('Content-Type: ' . ($ext === 'pdf' ? 'application/pdf' : 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullFilePath));
readfile($fullFilePath);
exit;
?>
