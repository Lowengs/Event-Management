<?php
/** update_org_settings.php — update org profile info and images */
session_start();
require_once '../db.php';
require_once '../audit.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }

$orgId = (int)$_SESSION['org_id'];
$row   = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();

$orgName  = trim($_POST['OrgName'] ?? $row['OrgName']);
$desc     = trim($_POST['Description'] ?? $row['Description']);
$adviser  = trim($_POST['Adviser'] ?? $row['Adviser']);
$email    = trim($_POST['Email'] ?? $row['Email']);

// Handle logo upload
$orgPic = $row['OrgPicture'];
if (!empty($_FILES['OrgPicture']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['OrgPicture']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
        $dir = __DIR__ . '/../../assets/uploads/orgs/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fn = 'org_logo_' . $orgId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['OrgPicture']['tmp_name'], $dir . $fn))
            $orgPic = 'assets/uploads/orgs/' . $fn;
    }
}

// Handle banner upload
$orgBanner = $row['OrgBanner'];
if (!empty($_FILES['OrgBanner']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['OrgBanner']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
        $dir = __DIR__ . '/../../assets/uploads/orgs/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $fn = 'org_banner_' . $orgId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['OrgBanner']['tmp_name'], $dir . $fn))
            $orgBanner = 'assets/uploads/orgs/' . $fn;
    }
}

$stmt = $conn->prepare("UPDATE organization SET OrgName=?,Description=?,Adviser=?,Email=?,OrgPicture=?,OrgBanner=? WHERE OrgId=?");
$stmt->bind_param('ssssssi', $orgName, $desc, $adviser, $email, $orgPic, $orgBanner, $orgId);

if ($stmt->execute()) {
    $_SESSION['org_name'] = $orgName;
    logAudit($conn,'Update Settings','org',$orgId,'success',['org_name'=>$orgName]);
    echo json_encode(['success'=>true,'message'=>'Settings updated successfully','org_pic'=>$orgPic,'org_banner'=>$orgBanner]);
} else {
    echo json_encode(['success'=>false,'message'=>$conn->error]);
}
$stmt->close();
