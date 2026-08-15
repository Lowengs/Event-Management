<?php
/**
 * Admin API: POST Login
 * Uses Stored Procedure / Parameterized Query for SQL injection prevention
 * Strict Password Verification & 3-minute cooldown with audit logging
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../rate_limit.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// 1. Check if client is in 3-minute cooldown lockout
checkLoginCooldown('admin_login', $conn);

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

try {
    $admin = null;
    try {
        $stmt = $conn->prepare("CALL sp_AdminLogin(?)");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $admin = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    } catch (Exception $e) {
        $admin = null;
    }

    if (!$admin) {
        $stmt2 = $conn->prepare("SELECT AdminId, Name, Email, PasswordHash, Role, Status FROM `admin` WHERE LOWER(Email) = LOWER(?) LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $q2 = $stmt2->get_result();
            $admin = $q2 ? $q2->fetch_assoc() : null;
            $stmt2->close();
        }
    }

    if ($admin) {
        $hash = $admin['PasswordHash'] ?? '';
        
        // Strict password check: verify bcrypt hash or migrate legacy plaintext
        $isValid = false;
        if (!empty($hash)) {
            if (password_verify($password, $hash)) {
                $isValid = true;
            } elseif ($password === $hash) {
                // Upgrade plaintext password to bcrypt hash
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $upStmt = $conn->prepare("UPDATE `admin` SET PasswordHash = ? WHERE AdminId = ?");
                if ($upStmt) {
                    $upStmt->bind_param("si", $newHash, $admin['AdminId']);
                    $upStmt->execute();
                    $upStmt->close();
                }
                $isValid = true;
            }
        }

        if ($isValid) {
            $_SESSION['admin_id']        = $admin['AdminId'];
            $_SESSION['admin_name']      = $admin['Name'];
            $_SESSION['admin_email']     = $admin['Email'] ?? $email;
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['role']            = 'admin';

            // Reset rate limit and log successful login in auditlog
            recordLoginSuccess('admin_login', 'admin', (int)$admin['AdminId'], $conn, ['email' => $email]);

            echo json_encode([
                'success'  => true, 
                'message'  => 'Admin login successful', 
                'redirect' => 'dashboard.php'
            ]);
            exit;
        }
    }

    // Record failure, handle 3-minute cooldown lockout if threshold met, and log to auditlog
    recordLoginFailure('admin_login', 'admin', $email, $conn, 5, 180);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
