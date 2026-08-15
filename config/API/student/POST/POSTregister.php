<?php
/**
 * Student API: POST Student Registration
 * Endpoint: /config/API/endpoints/index.php?action=POSTregister
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$studentId  = trim($_POST['student_id']  ?? '');
$firstName  = trim($_POST['first_name']  ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName   = trim($_POST['last_name']   ?? '');
$address    = trim($_POST['address']     ?? '');
$email      = trim($_POST['email']       ?? '');
$course     = trim($_POST['course']      ?? '');
$yearLevel  = trim($_POST['year_level']  ?? '');
$section    = trim($_POST['section']     ?? '');
$username   = trim($_POST['username']    ?? '');
$password   = $_POST['password']         ?? '';
$phone      = trim($_POST['phone']       ?? '');

$faceDescriptor = $_POST['face_descriptor'] ?? '';
$facePhoto      = $_POST['face_photo']      ?? '';

// Validation
if (empty($studentId) || empty($firstName) || empty($lastName) || empty($email) || empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

try {
    // Check duplicate student_id
    $stmtCheckSid = $conn->prepare("SELECT UserId FROM `user` WHERE LOWER(student_id) = LOWER(?) LIMIT 1");
    $stmtCheckSid->bind_param("s", $studentId);
    $stmtCheckSid->execute();
    if ($stmtCheckSid->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Student ID is already registered.', 'field' => 'student_id']);
        $stmtCheckSid->close();
        exit;
    }
    $stmtCheckSid->close();

    // Check duplicate email
    $stmtCheckEmail = $conn->prepare("SELECT UserId FROM `user` WHERE LOWER(Email) = LOWER(?) LIMIT 1");
    $stmtCheckEmail->bind_param("s", $email);
    $stmtCheckEmail->execute();
    if ($stmtCheckEmail->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email address is already in use.', 'field' => 'email']);
        $stmtCheckEmail->close();
        exit;
    }
    $stmtCheckEmail->close();

    // Check duplicate username
    $stmtCheckUser = $conn->prepare("SELECT UserId FROM `user` WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $stmtCheckUser->bind_param("s", $username);
    $stmtCheckUser->execute();
    if ($stmtCheckUser->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username is already taken.', 'field' => 'username']);
        $stmtCheckUser->close();
        exit;
    }
    $stmtCheckUser->close();

    // File Uploads
    $profilePath = '';
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $pDir = __DIR__ . '/../../../../assets/uploads/profile_photos/';
        if (!is_dir($pDir)) mkdir($pDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $pName = 'student_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $pDir . $pName)) {
            $profilePath = 'assets/uploads/profile_photos/' . $pName;
        }
    }

    $corPath = '';
    if (!empty($_FILES['cor_document']['name']) && $_FILES['cor_document']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['cor_document']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            echo json_encode(['success' => false, 'message' => 'Please upload your Certificate of Registration (COR) in PDF format only.']);
            if ($isDirectApiCall) exit;
            return;
        }
        $cDir = __DIR__ . '/../../../../assets/uploads/cors/';
        if (!is_dir($cDir)) mkdir($cDir, 0755, true);
        $cName = 'cor_' . time() . '_' . rand(100, 999) . '.' . $ext;
        if (move_uploaded_file($_FILES['cor_document']['tmp_name'], $cDir . $cName)) {
            $corPath = 'assets/uploads/cors/' . $cName;
        }
    }

    $passHash = password_hash($password, PASSWORD_BCRYPT);

    $newUserId = 0;
    $registered = false;

    try {
        $stmtInsert = $conn->prepare("CALL sp_StudentRegister(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmtInsert) {
            $stmtInsert->bind_param("ssssssssssssss", 
                $firstName, $middleName, $lastName, $studentId, $address, $email, 
                $course, $yearLevel, $section, $username, $passHash, $phone, 
                $profilePath, $corPath
            );
            if ($stmtInsert->execute()) {
                $resIns = $stmtInsert->get_result();
                if ($resIns && $rowIns = $resIns->fetch_assoc()) {
                    $newUserId = (int)($rowIns['new_user_id'] ?? 0);
                }
                $registered = true;
            }
            $stmtInsert->close();
            while ($conn->more_results() && $conn->next_result()) { ; }
        }
    } catch (\Throwable $e) {
        $registered = false;
    }

    // Direct Parameterized SQL Fallback if stored procedure was unavailable
    if (!$registered || $newUserId === 0) {
        $stmtFallback = $conn->prepare("INSERT INTO `user` (first_name, middle_name, last_name, student_id, Address, Email, course, year_level, section, username, PasswordHash, phone, profile_photo, cor_document, Status, verification_status, Role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'ai_verified', 'student', NOW())");
        if ($stmtFallback) {
            $stmtFallback->bind_param("ssssssssssssss", 
                $firstName, $middleName, $lastName, $studentId, $address, $email, 
                $course, $yearLevel, $section, $username, $passHash, $phone, 
                $profilePath, $corPath
            );
            if ($stmtFallback->execute()) {
                $newUserId = $conn->insert_id;
                $registered = true;
            }
            $stmtFallback->close();
        }
    }

    if ($registered) {

        // Face Data insertion
        if (!empty($faceDescriptor)) {
            try {
                $stmtFace = $conn->prepare("INSERT INTO face_data (UserId, descriptor, CreatedOn) VALUES (?, ?, NOW())");
                $stmtFace->bind_param("is", $newUserId, $faceDescriptor);
                $stmtFace->execute();
                $stmtFace->close();
            } catch (Exception $e) {
                try {
                    $stmtFace = $conn->prepare("INSERT INTO face_data (UserId, FaceEmbedding, CreatedOn) VALUES (?, ?, NOW())");
                    $stmtFace->bind_param("is", $newUserId, $faceDescriptor);
                    $stmtFace->execute();
                    $stmtFace->close();
                } catch (Exception $e2) {
                    // Ignore face insertion errors if table schema varies
                }
            }
        }

        // Record Audit Log for Student Registration
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $fullName = trim("$firstName $lastName");
            $action = 'Student Registration';
            $details = json_encode([
                'student_id' => $studentId,
                'name' => $fullName,
                'email' => $email,
                'course' => $course,
                'year_level' => $yearLevel,
                'section' => $section,
                'status' => 'Active / Verified'
            ]);
            $status = 'success';
            $actorType = 'student';
            $stmtAudit = $conn->prepare("INSERT INTO auditlog (UserId, ActorType, ActorId, ActorName, Action, Details, Status, IpAddress, Date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if ($stmtAudit) {
                $stmtAudit->bind_param("isisssss", $newUserId, $actorType, $newUserId, $fullName, $action, $details, $status, $ip);
                $stmtAudit->execute();
                $stmtAudit->close();
            }
        } catch (Throwable $e) {
            // Ignore audit trail fail
        }

        echo json_encode([
            'success' => true,
            'message' => 'Registration submitted successfully!',
            'status'  => 'active'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save student account: ' . $stmtInsert->error]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
