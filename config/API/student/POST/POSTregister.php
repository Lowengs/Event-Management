<?php
/**
 * Student API: POST Student Registration
 * Endpoint: /config/API/endpoints/index.php?action=POSTregister
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../audit.php';

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

$fullName = trim("$firstName $lastName");

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
        $stmtCheckSid->close();
        logAudit($conn, 'Student Registration', 'student', null, 'failed', [
            'student_id' => $studentId,
            'email'      => $email,
            'reason'     => 'Duplicate student ID'
        ], $fullName);
        echo json_encode(['success' => false, 'message' => 'Student ID is already registered.', 'field' => 'student_id']);
        exit;
    }
    $stmtCheckSid->close();

    // Check duplicate email
    $stmtCheckEmail = $conn->prepare("SELECT UserId FROM `user` WHERE LOWER(Email) = LOWER(?) LIMIT 1");
    $stmtCheckEmail->bind_param("s", $email);
    $stmtCheckEmail->execute();
    if ($stmtCheckEmail->get_result()->num_rows > 0) {
        $stmtCheckEmail->close();
        logAudit($conn, 'Student Registration', 'student', null, 'failed', [
            'student_id' => $studentId,
            'email'      => $email,
            'reason'     => 'Duplicate email address'
        ], $fullName);
        echo json_encode(['success' => false, 'message' => 'Email address is already in use.', 'field' => 'email']);
        exit;
    }
    $stmtCheckEmail->close();

    // Check duplicate username
    $stmtCheckUser = $conn->prepare("SELECT UserId FROM `user` WHERE LOWER(username) = LOWER(?) LIMIT 1");
    $stmtCheckUser->bind_param("s", $username);
    $stmtCheckUser->execute();
    if ($stmtCheckUser->get_result()->num_rows > 0) {
        $stmtCheckUser->close();
        logAudit($conn, 'Student Registration', 'student', null, 'failed', [
            'student_id' => $studentId,
            'username'   => $username,
            'reason'     => 'Duplicate username'
        ], $fullName);
        echo json_encode(['success' => false, 'message' => 'Username is already taken.', 'field' => 'username']);
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

    // Fallback: If no file uploaded, use the captured webcam face photo (base64 DataURL)
    if (empty($profilePath) && !empty($facePhoto) && strpos($facePhoto, 'data:image') === 0) {
        $pDir = __DIR__ . '/../../../../assets/uploads/profile_photos/';
        if (!is_dir($pDir)) mkdir($pDir, 0755, true);
        $parts = explode(',', $facePhoto, 2);
        if (count($parts) === 2) {
            $imgData = base64_decode($parts[1]);
            if ($imgData !== false) {
                $pName = 'student_face_' . time() . '_' . rand(100, 999) . '.png';
                if (file_put_contents($pDir . $pName, $imgData)) {
                    $profilePath = 'assets/uploads/profile_photos/' . $pName;
                }
            }
        }
    }

    $corPath = '';
    if (!empty($_FILES['cor_document']['name']) && $_FILES['cor_document']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['cor_document']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            echo json_encode(['success' => false, 'message' => 'Please upload your Certificate of Registration (COR) in PDF format only.']);
            exit;
        }
        $cDir = __DIR__ . '/../../../../assets/uploads/cors/';
        if (!is_dir($cDir)) mkdir($cDir, 0755, true);
        $rawCorName = basename($_FILES['cor_document']['name']);
        $cName = preg_replace('/[^\w\s\(\)\-\.]/u', '_', $rawCorName);
        if (empty($cName)) $cName = 'COR.' . $ext;
        if (move_uploaded_file($_FILES['cor_document']['tmp_name'], $cDir . $cName)) {
            $corPath = 'assets/uploads/cors/' . $cName;
        }
    }

    $passHash = password_hash($password, PASSWORD_BCRYPT);

    // Determine Organization ID based on Student Course
    $orgId = 6;
    $ucCourse = strtoupper($course);
    if (strpos($ucCourse, 'BSAIS') !== false || strpos($ucCourse, 'AVCOMM') !== false || strpos($ucCourse, 'AVTOUR') !== false) {
        $orgId = 1; // AISERS
    } elseif (strpos($ucCourse, 'AMT') !== false || strpos($ucCourse, 'AIRCRAFT') !== false) {
        $orgId = 2; // AMTSO
    } elseif (strpos($ucCourse, 'BSAT') !== false || strpos($ucCourse, 'AERO') !== false || strpos($ucCourse, 'BSAEE') !== false) {
        $orgId = 3; // AEROATSO
    } elseif (strpos($ucCourse, 'AET') !== false || strpos($ucCourse, 'AAET') !== false || strpos($ucCourse, 'BET') !== false) {
        $orgId = 4; // AETSO
    } elseif (strpos($ucCourse, 'BSAIT') !== false || strpos($ucCourse, 'BSIT') !== false || strpos($ucCourse, 'BSCS') !== false || strpos($ucCourse, 'TECH') !== false) {
        $orgId = 5; // ELITECH
    }

    // Determine verification status & organization review requirements
    $needsReview = !empty($_POST['needs_org_review']) || (!empty($_POST['verification_status']) && in_array($_POST['verification_status'], ['needs_org_review', 'pending'], true));
    $userStatus  = $needsReview ? 'pending' : 'active';
    $verifStatus = $needsReview ? 'needs_org_review' : 'ai_verified';
    $aiScore     = isset($_POST['ai_verification_score']) && is_numeric($_POST['ai_verification_score']) ? (int)$_POST['ai_verification_score'] : ($needsReview ? 35 : 100);
    
    $rawDetails  = trim($_POST['ai_verification_details'] ?? '');
    if (empty($rawDetails)) {
        $aiDetailsJson = $needsReview 
            ? json_encode(['Document details mismatch - Flagged for Student Organization Manual Review']) 
            : json_encode(['Active Enrollment Status Confirmed']);
    } else {
        $aiDetailsJson = (strpos($rawDetails, '[') === 0) ? $rawDetails : json_encode([$rawDetails]);
    }

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
        // Clear any pending results from the failed SP call
        try {
            if (isset($stmtInsert) && $stmtInsert instanceof mysqli_stmt) {
                @$stmtInsert->close();
            }
            while (@$conn->more_results() && @$conn->next_result()) { ; }
            if ($result = @$conn->store_result()) {
                $result->free();
            }
        } catch (\Throwable $ignore) {}
        $registered = false;
    }

    // Direct Parameterized SQL Fallback if stored procedure was unavailable
    if (!$registered || $newUserId === 0) {
        while (@$conn->more_results() && @$conn->next_result()) { ; }
        if ($result = @$conn->store_result()) {
            $result->free();
        }
        
        $stmtFallback = $conn->prepare("INSERT INTO `user` (first_name, middle_name, last_name, student_id, Address, Email, course, year_level, section, username, PasswordHash, phone, profile_photo, cor_document, OrgId, status, verification_status, ai_verification_score, ai_verification_details, Role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'student', NOW())");
        if ($stmtFallback) {
            $stmtFallback->bind_param("ssssssssssssssissis", 
                $firstName, $middleName, $lastName, $studentId, $address, $email, 
                $course, $yearLevel, $section, $username, $passHash, $phone, 
                $profilePath, $corPath, $orgId, $userStatus, $verifStatus, $aiScore, $aiDetailsJson
            );
            if ($stmtFallback->execute()) {
                $newUserId = $conn->insert_id;
                $registered = true;
            } else {
                error_log("Registration fallback INSERT failed: " . $stmtFallback->error);
            }
            $stmtFallback->close();
        } else {
            error_log("Registration fallback PREPARE failed: " . $conn->error);
        }
    }

    if ($registered && $newUserId > 0) {
        // Explicitly guarantee all fields (OrgId, verification status, details, photos) are stored accurately
        $pEsc = $conn->real_escape_string($profilePath);
        $cEsc = $conn->real_escape_string($corPath);
        $dEsc = $conn->real_escape_string($aiDetailsJson);
        $sEsc = $conn->real_escape_string($userStatus);
        $vEsc = $conn->real_escape_string($verifStatus);

        $conn->query("
            UPDATE `user` 
            SET OrgId = $orgId,
                status = '$sEsc',
                verification_status = '$vEsc',
                ai_verification_score = $aiScore,
                ai_verification_details = '$dEsc'" . 
                (!empty($profilePath) ? ", profile_photo = '$pEsc'" : "") .
                (!empty($corPath) ? ", cor_document = '$cEsc'" : "") . "
            WHERE UserId = $newUserId
        ");

        // Face Data insertion
        if (!empty($faceDescriptor)) {
            $stmtFace = $conn->prepare("INSERT INTO face_data (UserId, FaceEmbedding, CreatedOn) VALUES (?, ?, NOW())");
            if (!$stmtFace) {
                $stmtFace = $conn->prepare("INSERT INTO face_data (UserId, descriptor, CreatedOn) VALUES (?, ?, NOW())");
            }
            if ($stmtFace) {
                $stmtFace->bind_param("is", $newUserId, $faceDescriptor);
                $stmtFace->execute();
                $stmtFace->close();
            }
        }

        // Record Audit Log for Student Registration
        logAudit($conn, 'Student Registration', 'student', $newUserId, 'success', [
            'student_id'          => $studentId,
            'name'                => $fullName,
            'email'               => $email,
            'course'              => $course,
            'year_level'          => $yearLevel,
            'section'             => $section,
            'org_id'              => $orgId,
            'status'              => $userStatus,
            'verification_status' => $verifStatus
        ], $fullName);

        $successMsg = $needsReview 
            ? 'Registration submitted successfully! Your account is queued for review by your Student Organization officers.'
            : 'Registration submitted successfully! Your account is active and ready.';

        echo json_encode([
            'success'             => true,
            'message'             => $successMsg,
            'status'              => $userStatus,
            'verification_status' => $verifStatus
        ]);
    } else {
        $errMsg = !empty($conn->error) ? $conn->error : 'Failed to save student account';
        logAudit($conn, 'Student Registration', 'student', null, 'failed', [
            'student_id' => $studentId,
            'name'       => $fullName,
            'email'      => $email,
            'reason'     => $errMsg
        ], $fullName);

        echo json_encode(['success' => false, 'message' => 'Failed to save student account: ' . $errMsg]);
    }
} catch (Exception $e) {
    logAudit($conn, 'Student Registration', 'student', null, 'failed', [
        'student_id' => $studentId,
        'email'      => $email,
        'reason'     => $e->getMessage()
    ], $fullName);

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
