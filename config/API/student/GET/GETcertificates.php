<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../cert_generator.php';
if (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT) header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$userId = (int)$_SESSION['student_id'];

// Get student's formatted full name for certificate generation
$studentName = '';
$userStmt = $conn->prepare("SELECT first_name, last_name, middle_name FROM user WHERE UserId = ? LIMIT 1");
if ($userStmt) {
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $uRes = $userStmt->get_result();
    if ($uRes && ($uRow = $uRes->fetch_assoc())) {
        $fn = trim($uRow['first_name'] ?? '');
        $mn = trim($uRow['middle_name'] ?? '');
        $ln = trim($uRow['last_name'] ?? '');
        $studentName = trim($fn . ' ' . (!empty($mn) ? substr($mn, 0, 1) . '. ' : '') . $ln);
    }
    $userStmt->close();
}
if ($studentName === '') $studentName = 'Student';

// Query latest certificate per event for this student (deduplication)
$sql = "SELECT c.CertId AS CertificateId, c.CertCode, c.GeneratedImage, c.CertificateURL, c.IssuedAt, c.EventId,
               e.EventName, e.EventDateTime, e.EventLocation,
               o.OrgName, t.TemplateName, t.TemplateImage, t.FieldConfig
        FROM certificates c
        LEFT JOIN event e ON e.EventId = c.EventId
        LEFT JOIN organization o ON o.OrgId = COALESCE(c.OrgId, e.OrgId)
        LEFT JOIN certificate_templates t ON t.TemplateId = c.TemplateId
        WHERE c.UserId = ?
          AND c.CertId = (
              SELECT MAX(c2.CertId) 
              FROM certificates c2 
              WHERE c2.EventId = c.EventId AND c2.UserId = c.UserId
          )
        ORDER BY c.IssuedAt DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$certs = [];

$baseDir = dirname(__DIR__, 4);

while ($result && ($row = $result->fetch_assoc())) {
    // If GeneratedImage is missing or file does not exist on disk, auto-generate it now
    $needGen = false;
    if (empty($row['GeneratedImage'])) {
        $needGen = true;
    } else {
        $cleanImg = ltrim(str_replace(['../', '..\\'], '', $row['GeneratedImage']), '/\\');
        $filePath = $baseDir . '/' . $cleanImg;
        if (!file_exists($filePath)) {
            $needGen = true;
        }
    }

    if ($needGen && !empty($row['TemplateImage'])) {
        $genImg = generateCertificateImageFile(
            $row['TemplateImage'],
            $studentName,
            $row['FieldConfig'],
            (int)$row['EventId'],
            $userId
        );
        if ($genImg) {
            $row['GeneratedImage'] = $genImg;
            $cId = (int)$row['CertificateId'];
            $conn->query("UPDATE certificates SET GeneratedImage = '" . $conn->real_escape_string($genImg) . "' WHERE CertId = $cId");
        }
    }

    $certs[] = $row;
}

echo json_encode(['success' => true, 'message' => 'Certificates retrieved successfully', 'data' => $certs]);
?>
