<?php
/**
 * Organization API: POST Login
 * Uses Parameterized Query for SQL injection prevention
 * Strict Password Verification & 3-minute cooldown with audit logging
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../rate_limit.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 1. Check 3-minute cooldown lockout
checkLoginCooldown('org_login', $conn);

$orgId    = (int)($_POST['org_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($orgId <= 0 || empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Organization, username, and password are all required to sign in.']);
    exit;
}

try {
    $org = null;
    $stmt2 = $conn->prepare("SELECT OrgId, OrgName, OrgPicture, username, PasswordHash, Status FROM organization WHERE OrgId = ? AND (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)) LIMIT 1");
    if ($stmt2) {
        $stmt2->bind_param("iss", $orgId, $username, $username);
        $stmt2->execute();
        $q2 = $stmt2->get_result();
        $org = $q2 ? $q2->fetch_assoc() : null;
        $stmt2->close();
    }

    if ($org) {
        $hash = $org['PasswordHash'] ?? '';
        
        // Strict password check
        $isValid = false;
        if (!empty($hash)) {
            if (password_verify($password, $hash)) {
                $isValid = true;
            } elseif ($password === $hash) {
                // Upgrade plaintext password to bcrypt hash
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $upStmt = $conn->prepare("UPDATE organization SET PasswordHash = ? WHERE OrgId = ?");
                if ($upStmt) {
                    $upStmt->bind_param("si", $newHash, $org['OrgId']);
                    $upStmt->execute();
                    $upStmt->close();
                }
                $isValid = true;
            }
        }

        if ($isValid) {
            $_SESSION['org_id']       = $org['OrgId'];
            $_SESSION['org_name']     = $org['OrgName'];
            $_SESSION['org_username'] = $org['username'] ?? $username;
            $_SESSION['org_logo']     = $org['OrgPicture'] ?? ($org['OrgLogo'] ?? '');
            $_SESSION['role']         = 'organization';

            // Reset rate limit and log successful login
            recordLoginSuccess('org_login', 'organization', (int)$org['OrgId'], $conn, ['username' => $username, 'org_id' => $orgId]);

            echo json_encode([
                'success'  => true,
                'message'  => 'Login successful',
                'redirect' => '../organization/dashboard_org.php'
            ]);
            exit;
        }
    }

    // Record failure, enforce 3-minute cooldown if threshold reached, and log to auditlog
    recordLoginFailure('org_login', 'organization', $username . ' (Org #' . $orgId . ')', $conn, 5, 180);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
