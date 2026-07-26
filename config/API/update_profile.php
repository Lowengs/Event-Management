<?php
/**
 * update_profile.php — Save student profile edits via JSON API
 */
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../audit.php';

header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$uid = (int)$_SESSION['student_id'];

$first_name  = trim($_POST['first_name']  ?? '');
$last_name   = trim($_POST['last_name']   ?? '');
$middle_name = trim($_POST['middle_name'] ?? '');
$phone       = trim($_POST['phone']       ?? '');
$address     = trim($_POST['address']     ?? '');

if (empty($first_name) || empty($last_name)) {
    echo json_encode(['success' => false, 'message' => 'First and last name are required.']);
    exit;
}

// ── Profile photo upload ──────────────────────────────────────────
$photo_path = null;
if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($_FILES['profile_photo']['type'], $allowed)) {
        $uploadDir = __DIR__ . '/../../assets/uploads/profiles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext   = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $fname = 'profile_' . $uid . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $fname)) {
            $photo_path = '../../assets/uploads/profiles/' . $fname;
        }
    }
}

// ── Password change ───────────────────────────────────────────────
$new_password = $_POST['new_password']     ?? '';
$conf_password = $_POST['confirm_password'] ?? '';
$cur_password  = $_POST['current_password'] ?? '';

if (!empty($new_password)) {
    if ($new_password !== $conf_password) {
        echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
        exit;
    }
    if (strlen($new_password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }
    $row = $conn->query("SELECT PasswordHash FROM user WHERE UserId = $uid LIMIT 1")->fetch_assoc();
    if (!password_verify($cur_password, $row['PasswordHash'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }
    $hash = password_hash($new_password, PASSWORD_BCRYPT);
    $ps   = $conn->prepare("UPDATE user SET PasswordHash = ? WHERE UserId = ?");
    $ps->bind_param('si', $hash, $uid);
    $ps->execute();
    $ps->close();
}

// ── Profile update ────────────────────────────────────────────────
if ($photo_path !== null) {
    $stmt = $conn->prepare("UPDATE user SET first_name=?, last_name=?, middle_name=?, phone=?, Address=?, profile_photo=? WHERE UserId=?");
    $stmt->bind_param('ssssssi', $first_name, $last_name, $middle_name, $phone, $address, $photo_path, $uid);
} else {
    $stmt = $conn->prepare("UPDATE user SET first_name=?, last_name=?, middle_name=?, phone=?, Address=? WHERE UserId=?");
    $stmt->bind_param('sssssi', $first_name, $last_name, $middle_name, $phone, $address, $uid);
}

if ($stmt->execute()) {
    $stmt->close();
    logAudit($conn, 'Student Profile Updated', 'student', $uid, 'success', [
        'name' => "$first_name $last_name"
    ]);
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'photo'   => $photo_path
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save. ' . $conn->error]);
}
