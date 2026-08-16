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
                $row['Status']             = $row['Status'] ?? $row['status'] ?? 'pending';
                $row['VerificationStatus'] = $row['VerificationStatus'] ?? $row['verification_status'] ?? 'pending';
                $row['VerificationScore']  = $row['VerificationScore'] ?? $row['ai_verification_score'] ?? null;
                $row['VerificationDetails']= $row['VerificationDetails'] ?? $row['ai_verification_details'] ?? null;
                $row['CorDocumentUrl']     = $row['CorDocumentUrl'] ?? $row['cor_document'] ?? '';
                $row['CreatedAt']          = $row['CreatedAt'] ?? $row['RegistrationDate'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
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
            u.status,
            u.verification_status AS VerificationStatus,
            u.verification_status,
            u.ai_verification_score,
            u.ai_verification_score AS VerificationScore,
            u.ai_verification_details,
            u.ai_verification_details AS VerificationDetails,
            u.created_at AS CreatedAt
        FROM `user` u
        WHERE u.OrgId = $orgId
        ORDER BY u.first_name ASC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            if (empty($r['StudentIdNumber'])) $r['StudentIdNumber'] = !empty($r['student_id']) ? $r['student_id'] : 'N/A';
            $r['Status'] = $r['Status'] ?? $r['status'] ?? 'pending';
            $r['VerificationStatus'] = $r['VerificationStatus'] ?? $r['verification_status'] ?? 'pending';
            $r['VerificationScore'] = $r['VerificationScore'] ?? $r['ai_verification_score'] ?? null;
            $r['VerificationDetails'] = $r['VerificationDetails'] ?? $r['ai_verification_details'] ?? null;
            $members[] = $r;
        }
    }
}

$tot = count($members);
$act = 0;
$pen = 0;
$ai  = 0;
$man = 0;

foreach ($members as $m) {
    $st = strtolower($m['Status'] ?? $m['status'] ?? 'pending');
    $vs = strtolower($m['VerificationStatus'] ?? $m['verification_status'] ?? 'pending');
    if ($st === 'active') {
        $act++;
        if ($vs === 'ai_verified' || $vs === 'approved') {
            $ai++;
        }
    } else {
        $pen++;
        if ($vs !== 'ai_verified') {
            $man++;
        }
    }
}

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

