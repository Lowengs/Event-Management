<?php
/**
 * Organization API: Add New Officer
 * Endpoint: /config/API/endpoints/index.php?action=POSTofficer
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$orgId     = (int)$_SESSION['org_id'];
$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');
$studentId = trim($_POST['student_id'] ?? '');
$email     = trim($_POST['Email']      ?? $_POST['email'] ?? '');
$yearLevel = trim($_POST['year_level'] ?? '1st Year');
$course    = trim($_POST['course']     ?? 'BSCS');
$section   = trim($_POST['section']    ?? 'A');
$role      = trim($_POST['officer_role_hidden'] ?? $_POST['officer_role'] ?? 'Officer');

if (empty($firstName) || empty($lastName) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
    exit;
}

try {
    // Enforce: only one person per position/role in the organization
    if (!empty($role) && strtolower($role) !== 'officer' && strtolower($role) !== 'others') {
        $checkStmt = $conn->prepare("
            SELECT UserId, CONCAT(first_name, ' ', last_name) AS OfficerName 
            FROM `user` 
            WHERE OrgId = ? 
              AND (is_officer = 1 OR (Position IS NOT NULL AND Position != ''))
              AND (LOWER(TRIM(officer_role)) = LOWER(TRIM(?)) OR LOWER(TRIM(Position)) = LOWER(TRIM(?)))
            LIMIT 1
        ");
        $checkStmt->bind_param("iss", $orgId, $role, $role);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();
        if ($checkRes && $checkRes->num_rows > 0) {
            $holder = $checkRes->fetch_assoc();
            $holderName = trim($holder['OfficerName']) ?: 'another officer';
            $checkStmt->close();
            echo json_encode([
                'success' => false, 
                'message' => "The position '$role' is already assigned to $holderName. Only one person can hold this position."
            ]);
            exit;
        }
        $checkStmt->close();
    }

    // Check if user already exists by Email or student_id
    $escapedEmail = $conn->real_escape_string($email);
    $qCheck = $conn->query("SELECT UserId FROM `user` WHERE LOWER(Email) = LOWER('$escapedEmail') LIMIT 1");
    $existingUser = $qCheck ? $qCheck->fetch_assoc() : null;

    if ($existingUser) {
        $uId = (int)$existingUser['UserId'];
        $stmt = $conn->prepare("UPDATE `user` SET OrgId = ?, officer_role = ?, year_level = ? WHERE UserId = ?");
        $stmt->bind_param("issi", $orgId, $role, $yearLevel, $uId);
        $stmt->execute();
        if (file_exists(__DIR__ . '/../../../audit.php')) {
            require_once __DIR__ . '/../../../audit.php';
            logAudit($conn, 'Add Officer', 'organization', $orgId, 'success', ['Name' => "$firstName $lastName", 'Role' => $role, 'Email' => $email]);
        }
        echo json_encode(['success' => true, 'message' => 'Existing student promoted to officer successfully']);
    } else {
        $defaultPass = password_hash('Naap@2025', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            INSERT INTO `user` (first_name, last_name, student_id, Email, PasswordHash, OrgId, officer_role, course, year_level, section, Status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->bind_param("sssssissss", $firstName, $lastName, $studentId, $email, $defaultPass, $orgId, $role, $course, $yearLevel, $section);
        
        if ($stmt->execute()) {
            if (file_exists(__DIR__ . '/../../../audit.php')) {
                require_once __DIR__ . '/../../../audit.php';
                logAudit($conn, 'Add Officer', 'organization', $orgId, 'success', ['Name' => "$firstName $lastName", 'Role' => $role, 'Email' => $email]);
            }
            echo json_encode(['success' => true, 'message' => 'New officer added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
