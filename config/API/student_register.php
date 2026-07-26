<?php
/**
 * student_register.php  (v2 – maps to your actual DB schema)
 *
 * Inserts into:
 *   → user          (all student profile fields + Role = 'student')
 *   → face_data     (FaceEmbedding as BLOB, QRCode as BLOB)
 *
 * POST text fields:
 *   student_id, first_name, middle_name, last_name,
 *   address, email, course, year_level, section,
 *   username, password, phone
 *
 * POST files:
 *   profile_photo, cor_document
 *
 * POST face data:
 *   face_descriptor  – JSON string of 128 floats from face-api.js
 *   face_photo       – base64 data-URL JPEG (live snapshot)
 */

header('Content-Type: application/json');
require_once '../db.php';
require_once '../audit.php';
require_once '../rate_limit.php';
rateLimit('student_register', 5, 60);

// ── Only accept POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// ── Collect & sanitize text fields ───────────────────────────────
$student_no      = trim($_POST['student_id']       ?? '');
$first_name      = trim($_POST['first_name']       ?? '');
$middle_name     = trim($_POST['middle_name']      ?? '');
$last_name       = trim($_POST['last_name']        ?? '');
$address         = trim($_POST['address']          ?? '');   // ← from user.Address
$email           = strtolower(trim($_POST['email'] ?? ''));
$course          = trim($_POST['course']           ?? '');
$year_level      = trim($_POST['year_level']       ?? '');
$section         = trim($_POST['section']          ?? '');
$username        = trim($_POST['username']         ?? '');
$password        = $_POST['password']              ?? '';
$phone           = trim($_POST['phone']            ?? '');
$face_descriptor = trim($_POST['face_descriptor']  ?? '');
$face_photo_b64  = $_POST['face_photo']            ?? '';

// Full display name stored in user.Name (kept for backward compat)
$full_name = trim("$first_name $middle_name $last_name");

// ── Required field validation ─────────────────────────────────────
$required = [
    'student_id'  => $student_no,
    'first_name'  => $first_name,
    'last_name'   => $last_name,
    'address'     => $address,
    'email'       => $email,
    'course'      => $course,
    'year_level'  => $year_level,
    'username'    => $username,
    'password'    => $password,
    'phone'       => $phone,
];
foreach ($required as $field => $val) {
    if ($val === '') {
        echo json_encode(['success' => false, 'message' => "Field '$field' is required."]);
        exit;
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.', 'field' => 'email']);
    exit;
}
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}
if (strlen($username) < 4) {
    echo json_encode(['success' => false, 'message' => 'Username must be at least 4 characters.', 'field' => 'username']);
    exit;
}

// ── Validate face descriptor ──────────────────────────────────────
if (empty($face_descriptor)) {
    echo json_encode(['success' => false, 'message' => 'Face registration data is missing. Please complete Step 3.']);
    exit;
}
$descriptorArray = json_decode($face_descriptor, true);
if (!is_array($descriptorArray) || count($descriptorArray) !== 128) {
    echo json_encode(['success' => false, 'message' => 'Invalid face data. Please retake your photo.']);
    exit;
}

// ── File upload checks ────────────────────────────────────────────
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Profile photo is required.']);
    exit;
}
if (!isset($_FILES['cor_document']) || $_FILES['cor_document']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'COR document is required.']);
    exit;
}
if ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Profile photo must be less than 5 MB.']);
    exit;
}
if ($_FILES['cor_document']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'COR must be less than 10 MB.']);
    exit;
}

// ── Duplicate checks (against user table) ────────────────────────
function isDuplicate(mysqli $conn, string $column, string $value): bool {
    $stmt = $conn->prepare("SELECT UserId FROM `user` WHERE `$column` = ? LIMIT 1");
    $stmt->bind_param('s', $value);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

if (isDuplicate($conn, 'Email',      $email))      {
    logAudit($conn, 'Student Registration', 'student', null, 'failed', ['email' => $email, 'reason' => 'Duplicate email']);
    echo json_encode(['success'=> false, 'message'=> 'Email is already registered.',           'field'=> 'email']);      exit;
}
if (isDuplicate($conn, 'student_id', $student_no)) {
    logAudit($conn, 'Student Registration', 'student', null, 'failed', ['student_id' => $student_no, 'reason' => 'Duplicate student ID']);
    echo json_encode(['success'=> false, 'message'=> 'This Student ID is already registered.', 'field'=> 'student_id']); exit;
}
if (isDuplicate($conn, 'username',   $username))   {
    logAudit($conn, 'Student Registration', 'student', null, 'failed', ['username' => $username, 'reason' => 'Duplicate username']);
    echo json_encode(['success'=> false, 'message'=> 'Username is already taken.',             'field'=> 'username']);    exit;
}

// ── Upload directories ────────────────────────────────────────────
$uploadBase = rtrim(dirname(__DIR__, 2), '/\\') . DIRECTORY_SEPARATOR
            . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

function ensureDir(string $p): void { if (!is_dir($p)) mkdir($p, 0755, true); }

// Profile photo
$allowedImg = ['jpg','jpeg','png','gif','webp'];
$photoExt   = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
if (!in_array($photoExt, $allowedImg, true)) {
    echo json_encode(['success' => false, 'message' => 'Profile photo must be JPG, PNG, GIF, or WebP.']);
    exit;
}
$photoDir  = $uploadBase . 'profile_photos' . DIRECTORY_SEPARATOR;
ensureDir($photoDir);
$photoFile     = 'student_' . uniqid('', true) . '.' . $photoExt;
$photoFullPath = $photoDir . $photoFile;
if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $photoFullPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save profile photo. Check folder permissions.']);
    exit;
}
$photoRelative = 'assets/uploads/profile_photos/' . $photoFile;

// COR document
$allowedCor = ['jpg','jpeg','png','gif','pdf'];
$corExt     = strtolower(pathinfo($_FILES['cor_document']['name'], PATHINFO_EXTENSION));
if (!in_array($corExt, $allowedCor, true)) {
    echo json_encode(['success' => false, 'message' => 'COR must be JPG, PNG, GIF, or PDF.']);
    exit;
}
$corDir  = $uploadBase . 'cor_documents' . DIRECTORY_SEPARATOR;
ensureDir($corDir);
$corFile     = 'cor_' . uniqid('', true) . '.' . $corExt;
$corFullPath = $corDir . $corFile;
if (!move_uploaded_file($_FILES['cor_document']['tmp_name'], $corFullPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save COR document. Check folder permissions.']);
    exit;
}
$corRelative = 'assets/uploads/cor_documents/' . $corFile;

// Face photo (base64 → JPEG file + keep raw bytes for QRCode placeholder)
$faceRelative  = null;
$faceFullPath  = null;
$faceJpegBytes = null;

if (!empty($face_photo_b64) && strpos($face_photo_b64, 'data:image') === 0) {
    $base64Data   = preg_replace('#^data:image/\w+;base64,#i', '', $face_photo_b64);
    $decoded      = base64_decode($base64Data, true);
    if ($decoded !== false) {
        $faceDir      = $uploadBase . 'student_faces' . DIRECTORY_SEPARATOR;
        ensureDir($faceDir);
        $faceName     = 'face_' . uniqid('', true) . '.jpg';
        $faceFullPath = $faceDir . $faceName;
        file_put_contents($faceFullPath, $decoded);
        $faceRelative  = 'assets/uploads/student_faces/' . $faceName;
        $faceJpegBytes = $decoded;   // will be stored as QRCode BLOB (face snapshot)
    }
}

// ── Password hash ─────────────────────────────────────────────────
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// ── Course-to-Org Mapping ─────────────────────────────────────────
$course_org_map = [
    'BSAIT'   => 5, // ELITECH
    'BSAIS'   => 1, // AISERS
    
    // ILAS (ILLASO)
    'BSAVCOMM'=> 6, // AVCOM
    'BSAVLOG' => 6, // AVLOG
    'BSAVTOUR'=> 6, // AVTOUR
    'BSAVSEC' => 6, // AVSSM
    'BSAVSSM' => 6, // AVSSM

    // AERO-AT (AEROATSO)
    'BSAEE'   => 3, // AEROENG
    'BSAT'    => 3, // AT

    // AETSO
    'AAET'    => 4, // AAET
    'BSAET'   => 4, // BSAET

    // AMTSO
    'AAMT'    => 2, // AAMT
    'BSAMT'   => 2, // BSAMT
];

$org_id = null;
if (isset($course_org_map[$course])) {
    $org_id = $course_org_map[$course];
}

// ── Transaction: insert into user + face_data ─────────────────────
$conn->begin_transaction();

try {
    // ── INSERT INTO user ──────────────────────────────────────────
    $stmt = $conn->prepare(
        "INSERT INTO `user`
            (Name, first_name, middle_name, last_name,
             Address, Email, PasswordHash, Role,
             student_id, course, year_level, section,
             username, phone, profile_photo, cor_document,
             status, created_at, OrgId)
         VALUES
            (?, ?, ?, ?,
             ?, ?, ?, 'student',
             ?, ?, ?, ?,
             ?, ?, ?, ?,
             'pending', NOW(), ?)"
    );

    if (!$stmt) {
        throw new RuntimeException('Prepare error (user): ' . $conn->error);
    }

    $stmt->bind_param(
        'ssss' .   // Name, first_name, middle_name, last_name
        'sss' .    // Address, Email, PasswordHash (Role is literal)
        'ssss' .   // student_id, course, year_level, section
        'ssss' .   // username, phone, profile_photo, cor_document
        'i',       // OrgId
        $full_name, $first_name, $middle_name, $last_name,
        $address, $email, $passwordHash,
        $student_no, $course, $year_level, $section,
        $username, $phone, $photoRelative, $corRelative,
        $org_id
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Insert error (user): ' . $stmt->error);
    }

    $newUserId = (int) $conn->insert_id;
    
    // AI Verification
    $student_cor = $uploadBase . 'cor_documents' . DIRECTORY_SEPARATOR . $corFile;
    
    // Dynamically resolve the absolute path to the project's Python virtual environment
    $project_root = dirname(__DIR__, 2); // Goes up from config/API to Project root
    $python_exe = $project_root . '/.venv/Scripts/python.exe';
    
    if (!file_exists($python_exe)) {
        $python_exe = 'python'; // Fallback to global python if the venv isn't found
    }
    
    $script_path = __DIR__ . '/../AI/verify_cor.py';
    
    // Force UTF-8 encoding for Python to prevent Windows charmap errors on terminal output
    putenv('PYTHONIOENCODING=utf-8');
    
    $command = escapeshellcmd($python_exe) . " " . escapeshellarg($script_path) . " " . escapeshellarg($student_cor);
    $output = shell_exec($command . ' 2>&1'); // Capture errors too
    
    file_put_contents(__DIR__ . '/error_log.txt', "Command: $command\nOutput: $output\n", FILE_APPEND);
    
    // Extract JSON from output (in case there's other text/warnings from Python)
    $json_output = null;
    if (preg_match('/\{.*\}/s', $output, $matches)) {
        $json_output = $matches[0];
    }
    
    $result = json_decode($json_output, true);
    
    if ($result) {
        $ai_score = isset($result['score']) ? (int)$result['score'] : 0;
        $v_status = isset($result['status']) ? $result['status'] : 'pending';
        $details = isset($result['details']) ? json_encode($result['details']) : null;
        
        // If AI verification passes, automatically set their main user status to active
        $u_status = 'pending';
        if ($v_status === 'ai_verified') {
            $u_status = 'active';
        }
        
        $update_stmt = $conn->prepare("UPDATE user SET ai_verification_score = ?, verification_status = ?, ai_verification_details = ?, status = ? WHERE UserId = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("isssi", $ai_score, $v_status, $details, $u_status, $newUserId);
            $update_stmt->execute();
            $update_stmt->close();
        }
    }
    $stmt->close();

    // ── INSERT INTO face_data ─────────────────────────────────────
    // FaceEmbedding: stored as BLOB (JSON string bytes)
    // QRCode:        stored as BLOB (the captured face JPEG bytes)
    //                — swap for a real QR code PNG if you add a QR lib
    $faceEmbeddingBlob = $face_descriptor;         // 128-float JSON text → BLOB
    $qrCodeBlob        = $faceJpegBytes ?? null;   // face snapshot used as placeholder

    $stmt2 = $conn->prepare(
        "INSERT INTO `face_data` (UserId, FaceEmbedding, QRCode, CreatedOn)
         VALUES (?, ?, ?, NOW())"
    );

    if (!$stmt2) {
        throw new RuntimeException('Prepare error (face_data): ' . $conn->error);
    }

    $stmt2->bind_param('ibb', $newUserId, $faceEmbeddingBlob, $qrCodeBlob);

    // send_long_data for BLOB columns
    $stmt2->send_long_data(1, $faceEmbeddingBlob);
    if ($qrCodeBlob !== null) {
        $stmt2->send_long_data(2, $qrCodeBlob);
    }

    if (!$stmt2->execute()) {
        throw new RuntimeException('Insert error (face_data): ' . $stmt2->error);
    }
    $stmt2->close();

    $conn->commit();

    $audit_status = (isset($u_status) && $u_status === 'active') ? 'active' : 'pending';

    // ── Audit: registration submitted ────────────────────────────
    logAudit($conn, 'Student Registration', 'student', $newUserId, 'success', [
        'student_id' => $student_no,
        'email'      => $email,
        'name'       => $full_name,
        'status'     => $audit_status,
        'ai_verified'=> ($audit_status === 'active')
    ]);

    // ── Send Email Notification ──────────────────────────────────
    $to = $email;
    $headers = "From: noreply@naap.edu.ph\r\n"
             . "Reply-To: noreply@naap.edu.ph\r\n"
             . "X-Mailer: PHP/" . phpversion();
             
    if ($audit_status === 'active') {
        $subject = "Welcome to NAAP Student Portal - Account Active";
        $body = "Hello {$full_name},\n\n"
              . "Your registration was successful and has been automatically verified by our AI system.\n"
              . "Your account is now ACTIVE and ready to use.\n\n"
              . "Login Details:\n"
              . "  Email: {$email}\n"
              . "  Student ID: {$student_no}\n\n"
              . "You may now log in to the Student Portal with the password you created.\n\n"
              . "Best Regards,\nNAAP Office of Student Affairs";

        $message = 'Registration successful! Your account is automatically verified and active. An email has been sent to you.';
    } else {
        $subject = "NAAP Student Portal - Registration Pending";
        $body = "Hello {$full_name},\n\n"
              . "Your registration has been submitted and is currently under review by our AI or Manual Verification.\n"
              . "You will not be able to log in until your account is approved.\n\n"
              . "We will send another email once your verification is complete.\n\n"
              . "Best Regards,\nNAAP Office of Student Affairs";

        $message = 'Registration submitted! Your account is under review. An email has been sent to you.';
    }

    // Try sending email (using @ to suppress warnings if mailserver isn't configured like in XAMPP)
    @mail($to, $subject, $body, $headers);

    echo json_encode([
        'success' => true,
        'message' => $message,
        'status' => $audit_status,
    ]);

} catch (RuntimeException $e) {
    $conn->rollback();

    // Clean up saved files
    foreach ([$photoFullPath, $corFullPath, $faceFullPath] as $f) {
        if ($f && file_exists($f)) unlink($f);
    }

    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
