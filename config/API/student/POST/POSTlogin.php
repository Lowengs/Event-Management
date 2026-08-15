<?php
/**
 * Student API: POST Login
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
checkLoginCooldown('student_login', $conn);

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

try {
    $user = null;
    try {
        $stmt = $conn->prepare("CALL sp_StudentLogin(?)");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    } catch (Exception $e) {
        $user = null;
    }

    if (!$user) {
        $stmt2 = $conn->prepare("SELECT UserId, first_name, last_name, Email, student_id, username, PasswordHash, Status FROM `user` WHERE LOWER(Email) = LOWER(?) OR LOWER(student_id) = LOWER(?) OR LOWER(username) = LOWER(?) LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param("sss", $email, $email, $email);
            $stmt2->execute();
            $q2 = $stmt2->get_result();
            $user = $q2 ? $q2->fetch_assoc() : null;
            $stmt2->close();
        }
    }

    if ($user) {
        $hash = $user['PasswordHash'] ?? '';
        
        // Strict password check
        $isValid = false;
        if (!empty($hash)) {
            if (password_verify($password, $hash)) {
                $isValid = true;
            } elseif ($password === $hash) {
                // Upgrade plaintext password to bcrypt hash
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $upStmt = $conn->prepare("UPDATE `user` SET PasswordHash = ? WHERE UserId = ?");
                if ($upStmt) {
                    $upStmt->bind_param("si", $newHash, $user['UserId']);
                    $upStmt->execute();
                    $upStmt->close();
                }
                $isValid = true;
            }
        }

        if ($isValid) {
            $_SESSION['student_id']   = $user['UserId'];
            $_SESSION['student_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $_SESSION['student_email']= $user['Email'];
            $_SESSION['role']         = 'student';

            $remember = !empty($_POST['remember']) || !empty($_POST['remember_me']);
            if ($remember) {
                setcookie(session_name(), session_id(), time() + (30 * 86400), '/');
                setcookie('naap_remember_student', (string)$user['UserId'], time() + (30 * 86400), '/');
            }

            // Reset rate limit and log successful login
            recordLoginSuccess('student_login', 'student', (int)$user['UserId'], $conn, ['email' => $user['Email']]);

            echo json_encode([
                'success'  => true,
                'message'  => 'Login successful',
                'redirect' => '../index.php'
            ]);
            exit;
        }
    }

    // Record failure, enforce 3-minute cooldown if threshold reached, and log to auditlog
    recordLoginFailure('student_login', 'student', $email, $conn, 5, 180);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
