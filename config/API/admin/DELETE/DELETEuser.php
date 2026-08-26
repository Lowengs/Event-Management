<?php
/**
 * Admin API: DELETE System User
 * Endpoint: /config/API/endpoints/index.php?action=DELETEuser
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Admin or OSA login required']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$userId = (int)($input['user_id'] ?? $_GET['user_id'] ?? 0);
$role   = strtolower(trim($input['role'] ?? $_GET['role'] ?? 'student'));

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

$actorType = !empty($_SESSION['admin_logged_in']) ? 'admin' : 'osa';
$actorId   = (int)($_SESSION['admin_id'] ?? $_SESSION['osa_id'] ?? 1);

try {
    $targetName = '—';
    $targetEmail = '';
    $targetIdentifier = '';

    // Fetch user details prior to deletion for audit log
    if ($role === 'student') {
        $check = $conn->prepare("SELECT first_name, last_name, email, student_id, user_name FROM `user` WHERE UserId = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        if ($row = $check->get_result()->fetch_assoc()) {
            $targetName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['user_name'] ?? 'Student');
            $targetEmail = $row['email'] ?? '';
            $targetIdentifier = $row['student_id'] ?? ($row['user_name'] ?? '');
        }
        $check->close();

        // Cascade cleanup student data
        $conn->query("DELETE FROM `eventregistration` WHERE UserId = $userId");
        $conn->query("DELETE FROM `attendance` WHERE UserId = $userId");
        $conn->query("DELETE FROM `student_question_responses` WHERE student_id = $userId");
        $conn->query("DELETE FROM `student_test_attempts` WHERE student_id = $userId");
        $conn->query("DELETE FROM `student_assessments` WHERE student_id = $userId");

        $stmt = $conn->prepare("DELETE FROM `user` WHERE UserId = ?");
    } elseif ($role === 'organization') {
        $check = $conn->prepare("SELECT OrgName, email, username FROM `organization` WHERE OrgId = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        if ($row = $check->get_result()->fetch_assoc()) {
            $targetName = $row['OrgName'] ?? 'Organization';
            $targetEmail = $row['email'] ?? '';
            $targetIdentifier = $row['username'] ?? '';
        }
        $check->close();

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
        $check = $conn->prepare("SELECT Name, email, username FROM `osa` WHERE OsaId = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        if ($row = $check->get_result()->fetch_assoc()) {
            $targetName = $row['Name'] ?? 'OSA Admin';
            $targetEmail = $row['email'] ?? '';
            $targetIdentifier = $row['username'] ?? '';
        }
        $check->close();

        $stmt = $conn->prepare("DELETE FROM osa WHERE OsaId = ?");
    } else {
        $check = $conn->prepare("SELECT Name, email, username FROM `admin` WHERE AdminId = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        if ($row = $check->get_result()->fetch_assoc()) {
            $targetName = $row['Name'] ?? 'Admin';
            $targetEmail = $row['email'] ?? '';
            $targetIdentifier = $row['username'] ?? '';
        }
        $check->close();

        $stmt = $conn->prepare("DELETE FROM `admin` WHERE AdminId = ?");
    }

    $stmt->bind_param("i", $userId);
    if ($stmt->execute()) {
        $stmt->close();

        // Audit Trail Recording
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, "Delete " . ucfirst($role) . " User", $actorType, $actorId, 'success', [
                'user_id'    => $userId,
                'role'       => $role,
                'name'       => $targetName,
                'identifier' => $targetIdentifier,
                'email'      => $targetEmail
            ]);
        }

        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
