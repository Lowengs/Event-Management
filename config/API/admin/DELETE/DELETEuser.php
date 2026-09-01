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
$userId = (int)($input['user_id'] ?? $input['UserId'] ?? $input['id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? 0);
$role   = strtolower(trim($input['role'] ?? $_POST['role'] ?? $_GET['role'] ?? 'student'));

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
        $check = $conn->prepare("SELECT first_name, last_name, email, student_id, username FROM `user` WHERE UserId = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        if ($row = $check->get_result()->fetch_assoc()) {
            $targetName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['username'] ?? 'Student');
            $targetEmail = $row['email'] ?? '';
            $targetIdentifier = $row['student_id'] ?? ($row['username'] ?? '');
        }
        $check->close();

        // Cascade cleanup all student linked data
        $conn->query("DELETE FROM `eventregistration` WHERE UserId = $userId");
        $conn->query("DELETE FROM `attendance` WHERE UserId = $userId");
        $conn->query("DELETE FROM `event_pretest` WHERE UserId = $userId");
        $conn->query("DELETE FROM `event_posttest` WHERE UserId = $userId");
        $conn->query("DELETE FROM `preposttest` WHERE StudentId = $userId");
        $conn->query("DELETE FROM `student_verification_checks` WHERE UserId = $userId");
        $conn->query("DELETE FROM `student_question_responses` WHERE student_id = $userId");
        $conn->query("DELETE FROM `assessment_answers` WHERE student_id = $userId");
        $conn->query("DELETE FROM `assessment_responses` WHERE user_id = $userId");
        $conn->query("DELETE FROM `certificates` WHERE UserId = $userId");
        $conn->query("DELETE FROM `certificate_backup` WHERE UserId = $userId");
        $conn->query("DELETE FROM `face_data` WHERE UserId = $userId");
        $conn->query("DELETE FROM `message` WHERE SenderId = $userId OR ReceiverId = $userId");
        $conn->query("UPDATE `auditlog` SET UserId = NULL WHERE UserId = $userId");

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

        // Cascade cleanup all foreign key dependencies
        $conn->query("UPDATE `user` SET OrgId = NULL WHERE OrgId = $userId");
        $conn->query("DELETE FROM `orgtype` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `announcement` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `org_documents` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `org_messages` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `certificates` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `certificatetemplate` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `certificate_templates` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `event_report` WHERE OrgId = $userId");
        $conn->query("DELETE FROM `eventregistration` WHERE OrgId = $userId");

        // Cleanup assessments & events linked to organization
        $conn->query("DELETE er FROM eventregistration er JOIN event e ON e.EventId = er.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE a FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE ep FROM event_pretest ep JOIN event e ON e.EventId = ep.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE ep FROM event_posttest ep JOIN event e ON e.EventId = ep.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE pt FROM preposttest pt JOIN event e ON e.EventId = pt.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE vc FROM student_verification_checks vc JOIN event e ON e.EventId = vc.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE aa FROM assessment_answers aa JOIN assessments ass ON ass.assessment_id = aa.assessment_id JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $userId");
        $conn->query("DELETE ar FROM assessment_responses ar JOIN assessments ass ON ass.assessment_id = ar.assessment_id JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $userId");
        $conn->query("DELETE aq FROM assessment_questions aq JOIN assessments ass ON ass.assessment_id = aq.assessment_id JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $userId");
        $conn->query("DELETE sqr FROM student_question_responses sqr JOIN assessments ass ON ass.assessment_id = sqr.assessment_id JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $userId");
        $conn->query("DELETE ass FROM assessments ass JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $userId");
        $conn->query("DELETE c FROM certificates c JOIN event e ON e.EventId = c.EventId WHERE e.OrgId = $userId");
        $conn->query("DELETE FROM `event` WHERE OrgId = $userId");

        $stmt = $conn->prepare("DELETE FROM `organization` WHERE OrgId = ?");
    } elseif ($role === 'osa') {
        $check = $conn->prepare("SELECT Name, Email FROM `osa` WHERE OsaId = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        if ($row = $check->get_result()->fetch_assoc()) {
            $targetName = $row['Name'] ?? 'OSA Admin';
            $targetEmail = $row['Email'] ?? '';
            $targetIdentifier = $row['Email'] ?? '';
        }
        $check->close();

        // Unlink organizations pointing to this OSA
        $conn->query("UPDATE `organization` SET OsaId = NULL WHERE OsaId = $userId");

        $stmt = $conn->prepare("DELETE FROM `osa` WHERE OsaId = ?");
    } else {
        // Admin account deletion
        if (!empty($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] === $userId) {
            echo json_encode(['success' => false, 'message' => 'You cannot delete your own active administrator account.']);
            exit;
        }

        $check = $conn->prepare("SELECT Name, Email, Role FROM `admin` WHERE AdminId = ?");
        $check->bind_param("i", $userId);
        $check->execute();
        if ($row = $check->get_result()->fetch_assoc()) {
            $targetName = $row['Name'] ?? 'Admin';
            $targetEmail = $row['Email'] ?? '';
            $targetIdentifier = $row['Email'] ?? '';
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

        echo json_encode(['success' => true, 'message' => ucfirst($role) . ' account deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
