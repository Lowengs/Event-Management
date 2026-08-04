<?php
/**
 * Organization API: GET Certificate Templates (Uses existing `certificate_templates` table)
 * Endpoint: /config/API/endpoints/index.php?action=GETcertificate_templates
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
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$templates = [];

try {
    $stmt = $conn->prepare("
        SELECT ct.*, e.EventName 
        FROM certificate_templates ct
        LEFT JOIN event e ON e.EventId = ct.EventId
        WHERE ct.OrgId = ? AND (ct.IsDeleted = 0 OR ct.IsDeleted IS NULL)
        ORDER BY ct.CreatedAt DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['FieldConfig'])) {
                    $fc = json_decode($row['FieldConfig'], true);
                    if (is_array($fc) && !empty($fc[0])) {
                        $row['NameX'] = ($fc[0]['x'] ?? 0.5) * 100;
                        $row['NameY'] = ($fc[0]['y'] ?? 0.5) * 100;
                        $row['FontSize'] = $fc[0]['fontSize'] ?? 60;
                        $row['FontColor'] = $fc[0]['color'] ?? '#000000';
                    }
                }
                $templates[] = $row;
            }
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // proceed
}

echo json_encode([
    'success' => true,
    'message' => 'Templates retrieved successfully',
    'templates' => $templates,
    'data'    => $templates
]);
if ($isDirectApiCall) exit;

