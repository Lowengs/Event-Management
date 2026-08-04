<?php
/**
 * OSA API: POST Organization (Create)
 * Endpoint: /config/API/endpoints/index.php?action=POSTorganization
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orgName  = trim($_POST['org_name']  ?? $_POST['OrgName'] ?? $_POST['name'] ?? '');
$acronym  = trim($_POST['acronym']   ?? $_POST['Acronym'] ?? '');
$email    = trim($_POST['email']     ?? $_POST['OrgEmail'] ?? '');
$adviser  = trim($_POST['adviser']   ?? '');
$desc     = trim($_POST['description'] ?? '');
$status   = trim($_POST['status']    ?? 'Active');
$dateReg  = trim($_POST['date_registered'] ?? date('Y-m-d'));

$username = trim($_POST['username']  ?? $_POST['org_username'] ?? '');
$password = trim($_POST['password']  ?? $_POST['org_password'] ?? '');

if (empty($orgName)) {
    echo json_encode(['success' => false, 'message' => 'Organization name is required']);
    exit;
}

// Auto-generate username and password if not provided
if (empty($username)) {
    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $orgName));
}
if (empty($password)) {
    $password = 'Naap@2025';
}

$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Handle Logo Upload
$logoPath = '';
if (!empty($_FILES['org_picture']['name']) && $_FILES['org_picture']['error'] === UPLOAD_ERR_OK) {
    $dir = __DIR__ . '/../../../../assets/img/orgs/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($_FILES['org_picture']['name'], PATHINFO_EXTENSION));
    $fn  = 'logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($_FILES['org_picture']['tmp_name'], $dir . $fn)) {
        $logoPath = 'assets/img/orgs/' . $fn;
    }
}

// Handle Banner Upload
$bannerPath = '';
if (!empty($_FILES['org_banner']['name']) && $_FILES['org_banner']['error'] === UPLOAD_ERR_OK) {
    $dir = __DIR__ . '/../../../../assets/img/orgs/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($_FILES['org_banner']['name'], PATHINFO_EXTENSION));
    $fn  = 'banner_' . time() . '_' . rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($_FILES['org_banner']['tmp_name'], $dir . $fn)) {
        $bannerPath = 'assets/img/orgs/' . $fn;
    }
}

try {
    // 1. Insert into organization table
    $stmt = $conn->prepare("INSERT INTO organization (OrgName, OrgPicture, OrgBanner, Description, DateRegistered, Status, Adviser) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $orgName, $logoPath, $bannerPath, $desc, $dateReg, $status, $adviser);
    
    if ($stmt->execute()) {
        $newOrgId = $conn->insert_id;
        $stmt->close();

        // 2. Create default officer user for org login
        $offStmt = $conn->prepare("
            INSERT INTO `user` (first_name, last_name, username, Email, PasswordHash, OrgId, officer_role, Position, is_officer, Status, created_at)
            VALUES (?, 'Officer', ?, ?, ?, ?, 'President', 'President', 1, 'active', NOW())
        ");
        $offStmt->bind_param("ssssi", $orgName, $username, $email, $hashedPassword, $newOrgId);
        $offStmt->execute();
        $offStmt->close();

        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Create Organization', 'osa', $_SESSION['osa_id'] ?? 1, 'success', ['org_name' => $orgName]);
        }

        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false) {
            header('Location: ../../../app/osa/organization.php?success=created');
            exit;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Organization created successfully',
            'org_id'  => $newOrgId
        ]);
    } else {
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false) {
            header('Location: ../../../app/osa/organization.php?error=failed');
            exit;
        }
        echo json_encode(['success' => false, 'message' => $stmt->error]);
    }
} catch (Exception $e) {
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false) {
        header('Location: ../../../app/osa/organization.php?error=' . urlencode($e->getMessage()));
        exit;
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
