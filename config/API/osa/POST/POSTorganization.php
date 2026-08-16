<?php
/**
 * OSA API: POST Organization (Create)
 * Endpoint: /config/API/endpoints/index.php?action=create_org
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isBrowserForm = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false);

if (empty($_SESSION['osa_id']) && empty($_SESSION['admin_logged_in']) && ($_SESSION['role'] ?? '') !== 'osa' && ($_SESSION['role'] ?? '') !== 'admin') {
    if ($isBrowserForm) {
        header('Location: ../../../app/osa/organization.php?error=auth_required&msg=' . urlencode('OSA administrator login required'));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'OSA administrator login required']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if ($isBrowserForm) {
        header('Location: ../../../app/osa/organization.php?error=invalid_method');
        exit;
    }
    header('Content-Type: application/json');
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
if (empty($dateReg)) $dateReg = date('Y-m-d');

$username = trim($_POST['username']  ?? $_POST['org_username'] ?? '');
$password = trim($_POST['password']  ?? $_POST['org_password'] ?? '');
$osaId    = (int)($_SESSION['osa_id'] ?? 1);

if (empty($orgName)) {
    if ($isBrowserForm) {
        header('Location: ../../../app/osa/organization.php?error=missing_name');
        exit;
    }
    header('Content-Type: application/json');
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
    // 1. Insert into organization table including username, password, email, and OsaId
    $stmt = $conn->prepare("
        INSERT INTO organization (OrgName, OrgPicture, OrgBanner, Description, DateRegistered, Status, Adviser, username, PasswordHash, password_hash, email, OsaId)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $stmt->bind_param(
        "sssssssssssi",
        $orgName,
        $logoPath,
        $bannerPath,
        $desc,
        $dateReg,
        $status,
        $adviser,
        $username,
        $hashedPassword,
        $hashedPassword,
        $email,
        $osaId
    );
    
    if ($stmt->execute()) {
        $newOrgId = $conn->insert_id;
        $stmt->close();

        // 2. Create default officer user for org login
        $offStmt = $conn->prepare("
            INSERT INTO `user` (first_name, last_name, username, Email, PasswordHash, OrgId, officer_role, Position, is_officer, status, Role, created_at)
            VALUES (?, 'Officer', ?, ?, ?, ?, 'President', 'President', 1, 'active', 'organization', NOW())
        ");
        if ($offStmt) {
            $offStmt->bind_param("ssssi", $orgName, $username, $email, $hashedPassword, $newOrgId);
            $offStmt->execute();
            $offStmt->close();
        }

        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Create Organization', 'osa', $osaId, 'success', ['org_name' => $orgName, 'org_id' => $newOrgId]);
        }

        if ($isBrowserForm) {
            header('Location: ../../../app/osa/organization.php?success=created');
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Organization created successfully',
            'org_id'  => $newOrgId
        ]);
        exit;
    } else {
        $errorMsg = $stmt->error ?: 'Failed to insert organization';
        $stmt->close();
        if ($isBrowserForm) {
            header('Location: ../../../app/osa/organization.php?error=failed&msg=' . urlencode($errorMsg));
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $errorMsg]);
        exit;
    }
} catch (Exception $e) {
    if ($isBrowserForm) {
        header('Location: ../../../app/osa/organization.php?error=failed&msg=' . urlencode($e->getMessage()));
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
