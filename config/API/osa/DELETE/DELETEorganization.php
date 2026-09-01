<?php
/**
 * OSA API: DELETE Organization
 * Endpoint: /config/API/endpoints/index.php?action=DELETEorganization
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'OSA login required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$orgId = (int)($input['org_id'] ?? $input['OrgId'] ?? $_POST['org_id'] ?? $_GET['org_id'] ?? 0);

if (!$orgId) {
    echo json_encode(['success' => false, 'message' => 'Organization ID required']);
    exit;
}

try {
    // Cascade cleanup all foreign key dependencies
    $conn->query("UPDATE `user` SET OrgId = NULL WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `orgtype` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `announcement` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `org_documents` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `org_messages` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `certificates` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `certificatetemplate` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `certificate_templates` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `event_report` WHERE OrgId = $orgId");
    $conn->query("DELETE FROM `eventregistration` WHERE OrgId = $orgId");

    // Cleanup assessments & events linked to organization
    $conn->query("DELETE er FROM eventregistration er JOIN event e ON e.EventId = er.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE a FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE ep FROM event_pretest ep JOIN event e ON e.EventId = ep.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE ep FROM event_posttest ep JOIN event e ON e.EventId = ep.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE pt FROM preposttest pt JOIN event e ON e.EventId = pt.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE vc FROM student_verification_checks vc JOIN event e ON e.EventId = vc.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE aa FROM assessment_answers aa JOIN assessments ass ON ass.assessment_id = aa.assessment_id JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $orgId");
    $conn->query("DELETE ar FROM assessment_responses ar JOIN assessments ass ON ass.assessment_id = ar.assessment_id JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $orgId");
    $conn->query("DELETE aq FROM assessment_questions aq JOIN assessments ass ON ass.assessment_id = aq.assessment_id JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $orgId");
    $conn->query("DELETE sqr FROM student_question_responses sqr JOIN assessments ass ON ass.assessment_id = sqr.assessment_id JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $orgId");
    $conn->query("DELETE ass FROM assessments ass JOIN event e ON e.EventId = ass.event_id WHERE e.OrgId = $orgId");
    $conn->query("DELETE c FROM certificates c JOIN event e ON e.EventId = c.EventId WHERE e.OrgId = $orgId");
    $conn->query("DELETE FROM `event` WHERE OrgId = $orgId");

    $stmt = $conn->prepare("DELETE FROM `organization` WHERE OrgId = ?");
    $stmt->bind_param("i", $orgId);
    if ($stmt->execute()) {
        $stmt->close();
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            $osaId = (int)($_SESSION['osa_id'] ?? $_SESSION['admin_id'] ?? 1);
            logAudit($conn, 'Delete Organization', 'osa', $osaId, 'success', ['OrgId' => $orgId]);
        }
        echo json_encode(['success' => true, 'message' => 'Organization deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
