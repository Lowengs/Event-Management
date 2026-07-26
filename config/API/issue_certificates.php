<?php
/**
 * issue_certificates.php
 * POST: EventId, TemplateId
 * Generates personalized certificate images (GD) for all present attendees.
 * Stores generated image path in certificates.GeneratedImage
 */
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');
// Suppress PHP warnings so they don't break JSON output
ini_set('display_errors', 0);
error_reporting(E_ERROR);

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId      = (int)$_SESSION['org_id'];
$eventId    = (int)($_POST['EventId']    ?? 0);
$templateId = (int)($_POST['TemplateId'] ?? 0);

if (!$eventId || !$templateId) {
    echo json_encode(['success'=>false,'message'=>'EventId and TemplateId are required']); exit;
}

// Verify event belongs to this org and is Completed
$evRow = $conn->query("SELECT EventId, EventName, EventDateTime, EventStatus FROM event WHERE EventId=$eventId AND OrgId=$orgId LIMIT 1")->fetch_assoc();
if (!$evRow) { echo json_encode(['success'=>false,'message'=>'Event not found']); exit; }
if (strtolower(trim($evRow['EventStatus'] ?? '')) !== 'completed') {
    echo json_encode(['success'=>false,'message'=>'Certificates can only be issued for Completed events.']); exit;
}

// Load template
$tplRow = $conn->query("SELECT * FROM certificate_templates WHERE TemplateId=$templateId AND OrgId=$orgId AND IsDeleted=0 LIMIT 1")->fetch_assoc();
if (!$tplRow) { echo json_encode(['success'=>false,'message'=>'Template not found']); exit; }

$fieldConfig = json_decode($tplRow['FieldConfig'] ?? '[]', true) ?? [];
$templateImagePath = __DIR__ . '/../../' . $tplRow['TemplateImage'];

if (!file_exists($templateImagePath)) {
    echo json_encode(['success'=>false,'message'=>'Template image file not found on server. Please re-upload the template.']); exit;
}

// Determine name overlay config from FieldConfig
$nameCfg = null;
foreach ($fieldConfig as $f) {
    if (!empty($f['value']) && (strpos($f['value'],'{{student_name}}') !== false || $f['label'] === 'Student Name')) {
        $nameCfg = $f;
        break;
    }
}
// Fallback defaults
$nameX     = $nameCfg['x']        ?? 0.5;
$nameY     = $nameCfg['y']        ?? 0.45;
$fontSize  = $nameCfg['fontSize'] ?? 60;
$fontColor = $nameCfg['color']    ?? '#1e293b';
$isBold    = !empty($nameCfg['bold']);

// Get present attendees
$attendees = [];
$aRes = $conn->query("
    SELECT DISTINCT a.UserId, CONCAT(u.first_name,' ',u.last_name) AS FullName
    FROM attendance a
    JOIN user u ON u.UserId = a.UserId
    WHERE a.EventId=$eventId AND a.AttendanceStatus='present'
");
if ($aRes) while ($r = $aRes->fetch_assoc()) $attendees[] = $r;

if (empty($attendees)) {
    echo json_encode(['success'=>false,'message'=>'No present attendees found for this event']); exit;
}

// Prepare generated certs directory
$genDir = __DIR__ . '/../../assets/uploads/generated_certs/';
if (!is_dir($genDir)) mkdir($genDir, 0775, true);

// Parse color hex to RGB
function hexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = str_repeat($hex[0],2).str_repeat($hex[1],2).str_repeat($hex[2],2);
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

// Check GD is available
if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg')) {
    echo json_encode([
        'success' => false,
        'message' => 'PHP GD extension is not enabled. Please enable it in your php.ini (uncomment extension=gd) and restart XAMPP.'
    ]); exit;
}

// Load source image
$ext = strtolower(pathinfo($templateImagePath, PATHINFO_EXTENSION));
$srcImg = match($ext) {
    'jpg','jpeg' => @imagecreatefromjpeg($templateImagePath),
    'png'        => @imagecreatefrompng($templateImagePath),
    'webp'       => @imagecreatefromwebp($templateImagePath),
    default      => null
};
if (!$srcImg) {
    echo json_encode(['success'=>false,'message'=>'Could not load template image. Ensure GD extension is enabled and the template file is a valid image.']); exit;
}

$imgW = imagesx($srcImg);
$imgH = imagesy($srcImg);

// Find a TTF font (bundled with PHP/XAMPP)
$fontPaths = [
    __DIR__ . '/../../assets/fonts/Inter-Bold.ttf',
    __DIR__ . '/../../assets/fonts/Inter.ttf',
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/Arial.ttf',
    'C:/Windows/Fonts/calibrib.ttf',
    'C:/Windows/Fonts/georgia.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
];
$font = null;
$fontName = 'GD Internal Font';
foreach ($fontPaths as $fp) {
    if (file_exists($fp)) { 
        $font = $fp; 
        $fontName = basename($fp);
        break; 
    }
}

// Prepare insert statement (unified certificates table)
$stmt = $conn->prepare("
    INSERT INTO certificates (TemplateId, EventId, UserId, CertCode, GeneratedImage, OrgId)
    VALUES (?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
        GeneratedImage = VALUES(GeneratedImage),
        IssuedAt       = CURRENT_TIMESTAMP
");

$issued  = 0;
$skipped = 0;
[$r,$g,$b] = hexToRgb($fontColor);

$eventDate = date('F j, Y', strtotime($evRow['EventDateTime']));

foreach ($attendees as $att) {
    $userId   = (int)$att['UserId'];
    $fullName = $att['FullName'];
    $certCode = bin2hex(random_bytes(16));

    // Generate personalized certificate image
    $certImg  = null;
    $certPath = '';

    if ($font) {
        // Clone the template for this student
        $certImg = imagecreatetruecolor($imgW, $imgH);
        // Preserve transparency for PNG
        imagealphablending($certImg, false);
        imagesavealpha($certImg, true);
        imagecopy($certImg, $srcImg, 0, 0, 0, 0, $imgW, $imgH);
        imagealphablending($certImg, true);

        $color = imagecolorallocate($certImg, $r, $g, $b);

        // Calculate pixel position from relative coords
        $px = (int)($nameX * $imgW);
        $py = (int)($nameY * $imgH);

        // Use imagettftext for nice font rendering
        // First measure text width to center it
        $bbox = imagettfbbox($fontSize, 0, $font, $fullName);
        $tw   = $bbox[2] - $bbox[0];
        $px   = (int)($px - $tw / 2); // center horizontally
        $py   = (int)($py + $fontSize / 2); // vertical center

        imagettftext($certImg, $fontSize, 0, $px, $py, $color, $font, $fullName);

        $certFilename = 'cert_' . $eventId . '_' . $userId . '_' . time() . '.jpg';
        $certFullPath = $genDir . $certFilename;
        imagejpeg($certImg, $certFullPath, 92);
        imagedestroy($certImg);
        $certPath = 'assets/uploads/generated_certs/' . $certFilename;
    }

    // Insert into DB
    $stmt->bind_param('iiissi', $templateId, $eventId, $userId, $certCode, $certPath, $orgId);
    $stmt->execute();
    if ($stmt->affected_rows > 0) $issued++;
    else $skipped++;
}

imagedestroy($srcImg);
$stmt->close();

// Audit log — record the issuance action
if ($issued > 0) {
    logAudit($conn,
        'Issue Certificates',
        'org',
        $orgId,
        'success',
        [
            'event_name'    => $evRow['EventName'],
            'template_name' => $tplRow['TemplateName'],
            'issued'        => $issued,
            'skipped'       => $skipped,
            'font'          => $fontName
        ]
    );
}

echo json_encode([
    'success' => true,
    'message' => "Issued $issued certificate(s). $skipped already existed.",
    'issued'  => $issued,
    'skipped' => $skipped,
    'event'   => $evRow['EventName'],
    'font_found' => $font !== null,
    'font_name'  => $fontName,
]);
