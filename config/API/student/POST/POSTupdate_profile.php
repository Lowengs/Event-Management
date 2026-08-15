<?php
/**
 * Student API: POST Update Profile (with file upload)
 * Endpoint: /config/API/endpoints/index.php?action=POSTupdate_profile
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$studentId = (int)($_SESSION['student_id'] ?? 0);
if (!$studentId) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    if ($isDirectApiCall) exit;
    return;
}

$fn  = trim($_POST['first_name']  ?? '');
$ln  = trim($_POST['last_name']   ?? '');
$mn  = trim($_POST['middle_name'] ?? '');
$ph  = trim($_POST['phone']       ?? '');
$adr = trim($_POST['address']     ?? '');

// Get current photo
$curPhoto = '';
$qP = $conn->query("SELECT profile_photo FROM `user` WHERE UserId = $studentId LIMIT 1");
if ($qP) $curPhoto = $qP->fetch_assoc()['profile_photo'] ?? '';
$photo_path = $curPhoto;

if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (in_array($_FILES['profile_photo']['type'], $allowed)) {
        $dir = __DIR__ . '/../../../../assets/uploads/profile_photos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext   = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $fname = 'profile_' . $studentId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $fname)) {
            $photo_path = 'assets/uploads/profile_photos/' . $fname;
        }
    }
}

if (empty($fn) || empty($ln)) {
    echo json_encode(['success' => false, 'message' => 'First and last name required']);
    if ($isDirectApiCall) exit;
    return;
}

try {
    $stmt = $conn->prepare("CALL sp_UpdateStudentProfile(?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $studentId, $fn, $ln, $mn, $ph, $adr, $photo_path);
    if ($stmt->execute()) {
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
        $_SESSION['student_name'] = trim("$fn $ln");

        // Handle password change if provided
        $newPass  = $_POST['new_password']     ?? '';
        $confPass = $_POST['confirm_password'] ?? '';
        $passMsg  = '';
        if (!empty($newPass)) {
            if ($newPass === $confPass && strlen($newPass) >= 8) {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $emptyMail = '';
                $ps = $conn->prepare("CALL sp_UpdateStudentPassword(?, ?, ?)");
                $ps->bind_param("iss", $studentId, $emptyMail, $hash);
                $ps->execute();
                $ps->close();
                while ($conn->more_results() && $conn->next_result()) { ; }
                $passMsg = 'Password updated';
            } else {
                $passMsg = 'Passwords must match and be at least 8 chars';
            }
        }

        // Audit log
        require_once __DIR__ . '/../../../audit.php';
        logAudit($conn, 'Update Profile', 'student', $studentId, 'success', [
            'name'             => trim("$fn $ln"),
            'photo_uploaded'   => !empty($_FILES['profile_photo']['name']),
            'photo_path'       => $photo_path,
            'password_changed' => !empty($newPass) && $newPass === $confPass
        ], trim("$fn $ln"));

        echo json_encode(['success' => true, 'message' => 'Profile updated', 'password_msg' => $passMsg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
if ($isDirectApiCall) exit;

