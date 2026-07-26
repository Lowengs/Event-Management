<?php
/** get_org_members.php — members of the logged-in org */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId = (int)$_SESSION['org_id'];

$total   = (int)$conn->query("SELECT COUNT(*) FROM user WHERE OrgId=$orgId")->fetch_row()[0];
$active  = (int)$conn->query("SELECT COUNT(*) FROM user WHERE OrgId=$orgId AND LOWER(status)='active'")->fetch_row()[0];
$pending = (int)$conn->query("SELECT COUNT(*) FROM user WHERE OrgId=$orgId AND LOWER(status)='pending'")->fetch_row()[0];
$ai_approved = (int)$conn->query("SELECT COUNT(*) FROM user WHERE OrgId=$orgId AND LOWER(status)='active' AND LOWER(verification_status)='ai_verified'")->fetch_row()[0];
$manual_review = (int)$conn->query("SELECT COUNT(*) FROM user WHERE OrgId=$orgId AND LOWER(status)='pending' AND LOWER(verification_status) IN ('pending', 'needs_org_review', 'rejected')")->fetch_row()[0];

$members = [];
$r = $conn->query("SELECT UserId,student_id,first_name,last_name,Email,phone,course,year_level,section,status,verification_status,ai_verification_details,ai_verification_score,officer_role,is_officer,created_at,profile_photo,cor_document FROM user WHERE OrgId=$orgId ORDER BY last_name,first_name");
if ($r) while($row=$r->fetch_assoc()){
    $row['FirstName']      = $row['first_name'];
    $row['LastName']       = $row['last_name'];
    $row['StudentIdNumber']= $row['student_id'];
    $row['YearLevel']      = $row['year_level'];
    $row['Section']        = $row['section'];
    $row['Status']         = $row['status'];
    $row['VerificationStatus'] = $row['verification_status'] ?? 'pending';
    $row['VerificationScore'] = $row['ai_verification_score'];
    $row['VerificationDetails'] = json_decode($row['ai_verification_details'] ?? '[]', true);
    $row['CreatedAt']      = $row['created_at'];
    $row['ProfilePhotoUrl'] = !empty($row['profile_photo']) && strpos($row['profile_photo'], 'assets') === 0
        ? '../../' . $row['profile_photo']
        : ($row['profile_photo'] ?? '');
    $row['CorDocumentUrl'] = !empty($row['cor_document']) && strpos($row['cor_document'], 'assets') === 0
        ? '../../' . $row['cor_document']
        : ($row['cor_document'] ?? '');
    $members[] = $row;
}

echo json_encode(['success'=>true,'stats'=>compact('total','active','pending','ai_approved','manual_review'),'members'=>$members]);
