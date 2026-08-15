<?php
/**
 * Organization API: GET Members
 * Endpoint: /config/API/endpoints/index.php?action=get_org_members
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
$members = [];

try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgMembers(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Ensure required field aliases exist
                $row['FirstName']          = $row['FirstName'] ?? $row['first_name'] ?? 'Member';
                $row['LastName']           = $row['LastName'] ?? $row['last_name'] ?? '';
                $row['student_id']         = !empty($row['student_id']) ? $row['student_id'] : 'N/A';
                $row['StudentIdNumber']    = $row['student_id'];
                $row['YearLevel']          = $row['YearLevel'] ?? $row['year_level'] ?? 'N/A';
                $row['Section']            = $row['Section'] ?? $row['section'] ?? 'N/A';
                $row['Status']             = $row['Status'] ?? 'active';
                $row['VerificationStatus'] = $row['VerificationStatus'] ?? 'ai_verified';
                $row['CorDocumentUrl']     = $row['CorDocumentUrl'] ?? $row['cor_document'] ?? '';
                $row['CreatedAt']          = $row['CreatedAt'] ?? $row['RegistrationDate'] ?? date('Y-m-d H:i:s');
                $members[] = $row;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Exception $e) {
    // proceed to fallback query
}

if (empty($members)) {
    $q = $conn->query("
        SELECT DISTINCT 
            u.UserId, 
            u.student_id,
            u.student_id AS StudentIdNumber,
            u.first_name, 
            u.first_name AS FirstName, 
            u.last_name, 
            u.last_name AS LastName, 
            u.Email, 
            u.course, 
            u.year_level, 
            u.year_level AS YearLevel, 
            u.section, 
            u.section AS Section, 
            u.phone, 
            u.phone AS Phone,
            u.profile_photo, 
            u.cor_document,
            u.cor_document AS CorDocumentUrl,
            u.status AS Status,
            u.verification_status AS VerificationStatus,
            u.created_at AS CreatedAt
        FROM `user` u
        WHERE u.OrgId = $orgId
        ORDER BY u.first_name ASC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            if (empty($r['StudentIdNumber'])) $r['StudentIdNumber'] = !empty($r['student_id']) ? $r['student_id'] : 'N/A';
            $members[] = $r;
        }
    }
}

$tot = count($members);
$act = $tot;
$pen = 0;
$ai  = $tot;
$man = 0;

echo json_encode([
    'success' => true,
    'message' => 'Organization members retrieved successfully',
    'stats'   => [
        'total'          => $tot,
        'active'         => $act,
        'pending'        => $pen,
        'ai_approved'    => $ai,
        'manual_review'  => $man
    ],
    'members' => $members,
    'data'    => $members
]);
if ($isDirectApiCall) exit;

