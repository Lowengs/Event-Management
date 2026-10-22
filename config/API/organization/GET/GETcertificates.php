<?php
/**
 * Organization API: GET Certificates
 * Uses Stored Procedure: sp_GetOrgCertificates & sp_GetOrgEvents
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    if ($isDirectApiCall) exit;
    return;
}

$orgId = (int)$_SESSION['org_id'];
$events = [];
$certs = [];

// 1. Fetch Organization Events
try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgEvents(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $events[] = $row;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed
}

// Fall back to the events table if sp_GetOrgEvents returned empty
if (empty($events)) {
    $stmt = $conn->prepare("SELECT * FROM event WHERE OrgId = ? ORDER BY EventDateTime DESC");
    if ($stmt) {
        $stmt->bind_param('i', $orgId);
        if ($stmt->execute() && ($res = $stmt->get_result())) {
            while ($row = $res->fetch_assoc()) $events[] = $row;
        }
        $stmt->close();
    }
}

// 2. Fetch Issued Certificates (for issued_certificates_org.php)
try {
    if ($stmtC = $conn->prepare("CALL sp_GetOrgCertificates(?)")) {
        $stmtC->bind_param("i", $orgId);
        $stmtC->execute();
        $resC = $stmtC->get_result();
        if ($resC) {
            while ($row = $resC->fetch_assoc()) {
                $certs[] = $row;
            }
        }
        $stmtC->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed
}

if (empty($certs)) {
    $stmtC = $conn->prepare("
        SELECT c.CertId, c.CertCode, c.IssuedAt, c.GeneratedImage,
               e.EventName, e.EventDateTime,
               u.first_name, u.last_name, u.student_id, u.profile_photo,
               t.TemplateName, t.TemplateImage
        FROM certificates c
        JOIN event e ON e.EventId = c.EventId
        JOIN user u ON u.UserId = c.UserId
        JOIN certificate_templates t ON t.TemplateId = c.TemplateId
        WHERE c.OrgId = ?
        ORDER BY c.IssuedAt DESC
    ");
    if ($stmtC) {
        $stmtC->bind_param('i', $orgId);
        if ($stmtC->execute() && ($resC = $stmtC->get_result())) {
            while ($row = $resC->fetch_assoc()) {
                $certs[] = $row;
            }
        }
        $stmtC->close();
    }
}

echo json_encode([
    'success' => true,
    'data' => $certs,
    'events' => $events
]);
if ($isDirectApiCall) exit;
?>
