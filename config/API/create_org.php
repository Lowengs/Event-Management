<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';
require_once '../../config/audit.php';

$osa_id = $_SESSION['osa_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['action'] ?? '') !== 'create_org') {
    header('Location: ../../app/osa/organization.php');
    exit;
}

$org_name   = trim($_POST['org_name']   ?? '');
$email      = trim($_POST['email']      ?? '');
$adviser    = trim($_POST['adviser']    ?? '');
$date_reg   = $_POST['date_registered'] ?? null;
$status     = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';
$desc       = trim($_POST['description'] ?? '');
$username   = trim($_POST['username']   ?? '');
$password   = $_POST['password']        ?? '';

if (empty($org_name)) {
    logAudit($conn, 'Create Organization Failed', 'osa', $osa_id, 'failed', ['reason' => 'Missing org name']);
    header('Location: ../../app/osa/organization.php?error=missing_name');
    exit;
}

// Handle logo upload
$pic_path = null;
if (!empty($_FILES['org_picture']['name'])) {
    $uploadDir = __DIR__ . '/../../assets/img/orgs/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $ext   = strtolower(pathinfo($_FILES['org_picture']['name'], PATHINFO_EXTENSION));
    $fname = 'org_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($_FILES['org_picture']['tmp_name'], $uploadDir . $fname)) {
        $pic_path = 'assets/img/orgs/' . $fname; // clean root-relative path
    }
}

// Handle banner upload
$banner_path = null;
if (!empty($_FILES['org_banner']['name'])) {
    $bannerDir = __DIR__ . '/../../assets/img/banners/';
    if (!is_dir($bannerDir)) mkdir($bannerDir, 0755, true);
    $ext   = strtolower(pathinfo($_FILES['org_banner']['name'], PATHINFO_EXTENSION));
    $bname = 'banner_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    if (move_uploaded_file($_FILES['org_banner']['tmp_name'], $bannerDir . $bname)) {
        $banner_path = 'assets/img/banners/' . $bname; // clean root-relative path
    }
}

// Insert organization
$stmt = $conn->prepare("INSERT INTO organization (OrgName, Email, Adviser, DateRegistered, Status, Description, OrgPicture, OrgBanner) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('ssssssss', $org_name, $email, $adviser, $date_reg, $status, $desc, $pic_path, $banner_path);

if (!$stmt->execute()) {
    logAudit($conn, 'Create Organization Failed', 'osa', $osa_id, 'failed', ['org_name' => $org_name, 'reason' => $conn->error]);
    header('Location: ../../app/osa/organization.php?error=db_error');
    exit;
}

$new_org_id = $stmt->insert_id;
$stmt->close();

// Create org login credentials if username + password provided
if (!empty($username) && !empty($password)) {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $ls = $conn->prepare("UPDATE organization SET Username = ?, PasswordHash = ? WHERE OrgId = ?");
    $ls->bind_param('ssi', $username, $hash, $new_org_id);
    $ls->execute();
    $ls->close();
}

logAudit($conn, 'Organization Created', 'osa', $osa_id, 'success', ['org_id' => $new_org_id, 'org_name' => $org_name]);

header('Location: ../../app/osa/organization.php?success=created');
exit;
