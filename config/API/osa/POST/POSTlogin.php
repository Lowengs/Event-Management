<?php
/**
 * OSA API: POST Login
 * Uses Stored Procedure / Parameterized Query for SQL injection prevention
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
checkLoginCooldown('osa_login', $conn);

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

try {
    $osa = null;
    try {
        $stmt = $conn->prepare("CALL sp_OSALogin(?)");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $osa = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    } catch (Exception $e) {
        $osa = null;
    }

    if (!$osa) {
        $stmt2 = $conn->prepare("SELECT OsaId, Name, Email, PasswordHash FROM `osa` WHERE LOWER(Email) = LOWER(?) LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $q2 = $stmt2->get_result();
            $osa = $q2 ? $q2->fetch_assoc() : null;
            $stmt2->close();
        }
    }

    if ($osa) {
        $hash = $osa['PasswordHash'] ?? '';
        
        // Strict password check
        $isValid = false;
        if (!empty($hash)) {
            if (password_verify($password, $hash)) {
                $isValid = true;
            } elseif ($password === $hash) {
                // Upgrade plaintext password to bcrypt hash
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $upStmt = $conn->prepare("UPDATE `osa` SET PasswordHash = ? WHERE OsaId = ?");
                if ($upStmt) {
                    $upStmt->bind_param("si", $newHash, $osa['OsaId']);
                    $upStmt->execute();
                    $upStmt->close();
                }
                $isValid = true;
            }
        }

        if ($isValid) {
            $_SESSION['osa_id']   = $osa['OsaId'];
            $_SESSION['osa_name'] = $osa['Name'];
            $_SESSION['osa_email']= $osa['Email'];
            $_SESSION['role']     = 'osa';
            $_SESSION['admin_logged_in'] = true;

            $remember = !empty($_POST['remember']) && ($_POST['remember'] === '1' || $_POST['remember'] === true || $_POST['remember'] === 'true');
            if ($remember) {
                setcookie('naap_remember_osa_email', $email, time() + (30 * 86400), '/');
            } else {
                setcookie('naap_remember_osa_email', '', time() - 3600, '/');
            }

            // Reset rate limit and log successful login
            recordLoginSuccess('osa_login', 'osa', (int)$osa['OsaId'], $conn, ['email' => $email]);

            echo json_encode([
                'success'  => true,
                'message'  => 'Login successful',
                'redirect' => '../../app/osa/dashboard_final.php'
            ]);
            exit;
        }
    }

    // Record failure, enforce 3-minute cooldown if threshold reached, and log to auditlog
    recordLoginFailure('osa_login', 'osa', $email, $conn, 5, 180);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
