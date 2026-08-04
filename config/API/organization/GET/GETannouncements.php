<?php
/**
 * Organization API: GET Announcements
 * Endpoint: /config/API/endpoints/index.php?action=GETannouncements
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
$announcements = [];

try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgAnnouncements(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $announcements[] = $row;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // Ignore stored procedure exception if any
}

if (empty($announcements)) {
    $stmt = $conn->prepare('SELECT * FROM announcement WHERE OrgId = ? ORDER BY DatePosted DESC, AnnouncementId DESC');
    if ($stmt) {
        $stmt->bind_param('i', $orgId);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) $announcements[] = $row;
        }
        $stmt->close();
    }
}

// Calculate stats
$total = count($announcements);
$approved = 0; $pending = 0; $draft = 0;
foreach ($announcements as $a) {
    $s = strtolower($a['Status'] ?? 'pending');
    if ($s === 'approved') $approved++;
    elseif ($s === 'draft') $draft++;
    else $pending++;
}

echo json_encode([
    'success'       => true,
    'message'       => 'Announcements retrieved successfully',
    'stats'         => [
        'total'    => $total,
        'approved' => $approved,
        'pending'  => $pending,
        'draft'    => $draft
    ],
    'announcements' => $announcements,
    'data'          => $announcements
]);
if ($isDirectApiCall) exit;

