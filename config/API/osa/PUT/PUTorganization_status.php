<?php
/**
 * OSA API: PUT Organization Status (Update)
 * Endpoint: /config/API/endpoints/index.php?action=PUTorganization_status
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    exit;
}

$inputData = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'PUT' || empty($_POST)) {
    parse_str(file_get_contents('php://input'), $putData);
    if (!empty($putData)) $inputData = array_merge($inputData, $putData);
}

$orgId  = (int)($inputData['OrgId']  ?? $inputData['org_id'] ?? $inputData['id'] ?? 0);
$status = trim($inputData['Status'] ?? $inputData['status'] ?? 'Active');

if (!$orgId) {
    echo json_encode(['success' => false, 'message' => 'Organization ID is required']);
    exit;
}

$stmt = $conn->prepare('UPDATE organization SET Status = ? WHERE OrgId = ?');
if (!$stmt) { echo json_encode(['success'=>false,'message'=>$conn->error]); exit; }
$stmt->bind_param('si', $status, $orgId);
$executed = $stmt->execute();
$stmt->close();

if ($executed && file_exists(__DIR__ . '/../../../audit.php')) {
    require_once __DIR__ . '/../../../audit.php';
    $osaId = (int)($_SESSION['osa_id'] ?? $_SESSION['admin_id'] ?? 1);
    logAudit($conn, 'Update Organization Status', 'osa', $osaId, 'success', ['OrgId' => $orgId, 'Status' => $status]);
}
echo json_encode($executed ? ['success'=>true,'message'=>'Organization status updated successfully'] : ['success'=>false,'message'=>$conn->error]);
?>
