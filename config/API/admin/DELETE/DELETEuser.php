<?php
/**
 * Admin API: DELETE System User
 * Endpoint: /config/API/endpoints/index.php?action=DELETEuser
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Admin login required']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId = (int)($input['user_id'] ?? $_GET['user_id'] ?? 0);
$role   = trim($input['role']    ?? $_GET['role']    ?? 'student');

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    if ($role === 'student') {
        $stmt = $conn->prepare("DELETE FROM `user` WHERE UserId = ?");
    } elseif ($role === 'organization') {
        // Cascade cleanup foreign key dependencies to prevent constraint errors
        $conn->query("UPDATE `user` SET OrgId = NULL WHERE OrgId = $userId");
        $conn->query("DELETE FROM `announcement` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `org_documents` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `org_messages` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `certificates` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `certificatetemplate` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `certificate_templates` WHERE OrgId = $userId");
        $conn->query("DELETE a FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE er FROM eventregistration er JOIN event e ON e.EventId = er.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE ass FROM assessments ass JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $userId");
        $conn->query("DELETE FROM `event` WHERE OrgId = $userId");

        $stmt = $conn->prepare("DELETE FROM organization WHERE OrgId = ?");
    } elseif ($role === 'osa') {
        $stmt = $conn->prepare("DELETE FROM osa WHERE OsaId = ?");
    } else {
        $stmt = $conn->prepare("DELETE FROM `admin` WHERE AdminId = ?");
    }
    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
