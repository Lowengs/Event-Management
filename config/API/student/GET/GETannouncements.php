<?php
/**
 * Student API: GET Announcements
 * Endpoint: /config/API/endpoints/index.php?action=GETannouncements
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$studentId = (int)($_SESSION['student_id'] ?? 0);
$studentOrgId = 0;

if ($studentId) {
    $uRow = $conn->query("SELECT OrgId FROM `user` WHERE UserId = $studentId LIMIT 1");
    if ($uRow && $r = $uRow->fetch_assoc()) $studentOrgId = (int)($r['OrgId'] ?? 0);
}

try {
    $stmt = $conn->prepare("CALL sp_GetStudentAnnouncements()");
    $stmt->execute();
    $res = $stmt->get_result();
    $announcements = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $st = strtolower(trim($row['Status'] ?? 'approved'));
            if ($st !== 'approved') continue;

            $ac = strtolower(trim($row['Audience'] ?? ''));
            if ($ac === 'by_org' && $studentOrgId && !empty($row['OrgId']) && (int)$row['OrgId'] !== $studentOrgId) {
                continue;
            }

            if ($ac === 'by_org') $row['AudienceLabel'] = 'By Organization';
            elseif ($ac === 'all_org') $row['AudienceLabel'] = 'All Organizations';
            elseif ($ac === 'students') $row['AudienceLabel'] = 'Students';
            elseif ($ac === 'all') $row['AudienceLabel'] = 'All';
            else $row['AudienceLabel'] = $row['Audience'] ?? 'All Students';
            $announcements[] = $row;
        }
    }
    $stmt->close();
    while ($conn->more_results() && $conn->next_result()) { ; }
    echo json_encode(['success' => true, 'data' => $announcements]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
if ($isDirectApiCall) exit;
?>
