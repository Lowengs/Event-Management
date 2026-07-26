<?php
// Ensure JSON response regardless of errors
header('Content-Type: application/json');

try {
    // Self-healing migration — add missing event columns silently
    $_selfMigrations = [
        "ALTER TABLE event ADD COLUMN EventDescription TEXT",
        "ALTER TABLE event ADD COLUMN EventDetails TEXT",
        "ALTER TABLE event ADD COLUMN EventType VARCHAR(100) DEFAULT 'General'",
        "ALTER TABLE event ADD COLUMN EventSpeaker VARCHAR(255)",
        "ALTER TABLE event ADD COLUMN EventCapacity INT DEFAULT 0",
        "ALTER TABLE event ADD COLUMN EventPlace VARCHAR(255)",
        "ALTER TABLE event ADD COLUMN EventLocation VARCHAR(255)",
        "ALTER TABLE event ADD COLUMN EventMode VARCHAR(50) DEFAULT 'On-site'",
        "ALTER TABLE event ADD COLUMN EventPicture VARCHAR(500)",
        "ALTER TABLE event ADD COLUMN EndDateTime DATETIME DEFAULT NULL",
        "ALTER TABLE event ADD COLUMN AttendanceEnabled TINYINT(1) DEFAULT 0",
        "ALTER TABLE event ADD COLUMN AttendanceMethod VARCHAR(50) DEFAULT 'QR Code'",
        "CREATE TABLE IF NOT EXISTS system_settings (
            SettingKey VARCHAR(100) NOT NULL PRIMARY KEY,
            SettingValue VARCHAR(500) NOT NULL DEFAULT '',
            UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        "INSERT IGNORE INTO system_settings (SettingKey, SettingValue) VALUES ('financial_report_required', '0')"
    ];
    // Note: will silently fail on columns that already exist — that is intentional

    /** create_org_event.php — org creates an event, saves to DB */
    session_start();
    require_once '../db.php';
    require_once '../audit.php';
    require_once '../rate_limit.php';
    rateLimit('create_event', 20, 60);

    // Run self-healing migrations (silently ignore columns that already exist)
    foreach ($_selfMigrations as $_sql) {
        try { $conn->query($_sql); } catch (Exception $_e) { /* duplicate — OK */ }
    }

    if (empty($_SESSION['org_id'])) { 
        http_response_code(401);
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); 
        exit; 
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        http_response_code(405);
        echo json_encode(['success'=>false,'message'=>'Invalid method']); 
        exit; 
    }

$orgId    = (int)$_SESSION['org_id'];
$name     = trim($_POST['EventName'] ?? '');
$desc     = trim($_POST['EventDescription'] ?? '');
$type     = trim($_POST['EventType'] ?? 'General');
$speaker  = trim($_POST['EventSpeaker'] ?? '');
$capacity = (int)($_POST['EventCapacity'] ?? 0);
$place    = trim($_POST['EventPlace'] ?? $_POST['EventLocation'] ?? '');
$mode     = trim($_POST['EventMode'] ?? 'On-site');
$status   = trim($_POST['EventStatus'] ?? 'Scheduled');
$attEnabled = isset($_POST['AttendanceEnabled']) ? 1 : 0;
$attMethod  = trim($_POST['AttendanceMethod'] ?? 'QR Code');

// Combine date + time sent from JS
$dtStr = trim($_POST['EventDateTime'] ?? '');

if (!$name) {
    echo json_encode(['success'=>false,'message'=>'Event name is required']); exit;
}
if (!$dtStr) {
    echo json_encode(['success'=>false,'message'=>'Event date and time are required']); exit;
}

// Validate datetime
$dt = DateTime::createFromFormat('Y-m-d H:i:s', $dtStr)
    ?: DateTime::createFromFormat('Y-m-d H:i', $dtStr)
    ?: null;
if (!$dt) {
    echo json_encode(['success'=>false,'message'=>'Invalid date/time format: '.$dtStr]); exit;
}
$datetimeStr = $dt->format('Y-m-d H:i:s');

// Handle poster upload
$picturePath = '';
if (!empty($_FILES['EventPicture']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['EventPicture']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowed)) {
        $uploadDir = __DIR__ . '/../../assets/uploads/events/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = 'event_' . time() . '_' . $orgId . '.' . $ext;
        if (move_uploaded_file($_FILES['EventPicture']['tmp_name'], $uploadDir . $filename)) {
            $picturePath = 'assets/uploads/events/' . $filename;
        }
    }
}

// Get existing columns to build flexible INSERT
$colRes = $conn->query("SHOW COLUMNS FROM event");
$existingCols = [];
if ($colRes) while($r=$colRes->fetch_assoc()) $existingCols[] = $r['Field'];

// Build dynamic insert based on what columns actually exist
$cols = ['OrgId', 'EventName', 'EventDateTime', 'EventStatus'];
$vals = [$orgId, $name, $datetimeStr, $status];
$types = 'isss';

$tryAdd = [
    'EventDescription' => [$desc, 's'],
    'EventDetails'     => [$desc, 's'],   // fallback alias
    'EventType'        => [$type, 's'],
    'EventSpeaker'     => [$speaker, 's'],
    'EventCapacity'    => [$capacity, 'i'],
    'EventPlace'       => [$place, 's'],
    'EventLocation'    => [$place, 's'],  // fallback alias
    'EventMode'        => [$mode, 's'],
    'EventPicture'     => [$picturePath, 's'],
    
    'AttendanceEnabled'=> [$attEnabled, 'i'],
    'AttendanceMethod' => [$attMethod, 's'],
];

$usedAliases = []; // so we don't insert both EventDescription AND EventDetails
$aliasGroups = [
    'desc'  => ['EventDescription','EventDetails'],
    'loc'   => ['EventPlace','EventLocation'],
];
foreach ($tryAdd as $col => [$val, $type]) {
    if (!in_array($col, $existingCols)) continue;
    // Skip alias duplicates
    foreach ($aliasGroups as $group => $aliases) {
        if (in_array($col, $aliases) && isset($usedAliases[$group])) continue 2;
        if (in_array($col, $aliases)) $usedAliases[$group] = true;
    }
    $cols[]  = $col;
    $vals[]  = $val;
    $types   .= $type;
}

$colStr  = implode(',', $cols);
$phStr   = implode(',', array_fill(0, count($vals), '?'));
$stmt    = $conn->prepare("INSERT INTO event ($colStr) VALUES ($phStr)");
$stmt->bind_param($types, ...$vals);

if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    
    // Save documents
    $docsDir = __DIR__ . '/../../assets/uploads/events/docs/';
    if (!is_dir($docsDir)) mkdir($docsDir, 0755, true);

    // Make sure org_documents exists
    $conn->query("CREATE TABLE IF NOT EXISTS org_documents (
        DocId INT AUTO_INCREMENT PRIMARY KEY,
        OrgId INT NOT NULL,
        EventId INT DEFAULT NULL,
        Title VARCHAR(255) NOT NULL,
        DocType VARCHAR(100) DEFAULT 'Other',
        Description TEXT,
        FilePath VARCHAR(500) NOT NULL,
        FileSize VARCHAR(50),
        UploadedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $docTypes = ['EventProposal', 'EventProgramFlow', 'FinancialReport'];
    foreach ($docTypes as $dType) {
        if (!empty($_FILES[$dType]['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES[$dType]['name'], PATHINFO_EXTENSION));
            // Validate extension — financial reports also allow spreadsheets
            $allowedDocs = $dType === 'FinancialReport'
                ? ['pdf','doc','docx','xlsx','xls']
                : ['pdf','doc','docx'];
            if (in_array($ext, $allowedDocs)) {
                $fname = strtolower($dType) . '_' . time() . '_' . $newId . '.' . $ext;
                if (move_uploaded_file($_FILES[$dType]['tmp_name'], $docsDir . $fname)) {
                    $p = 'assets/uploads/events/docs/' . $fname;
                    $origName = $_FILES[$dType]['name'];
                    $fileSize = filesize($docsDir . $fname);
                    $fSizeStr = round($fileSize / 1024) . 'KB';
                    $stmtDoc = $conn->prepare("INSERT INTO org_documents (OrgId, EventId, Title, DocType, FilePath, FileSize) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtDoc->bind_param("iissss", $orgId, $newId, $origName, $dType, $p, $fSizeStr);
                    $stmtDoc->execute();
                    $stmtDoc->close();
                }
            }
        }
    }

    // Handle multiple supporting files in EventOther
    if (!empty($_FILES['EventOther']['name']) && is_array($_FILES['EventOther']['name'])) {
        $count = count($_FILES['EventOther']['name']);
        for ($i=0; $i < $count; $i++) {
            if (!empty($_FILES['EventOther']['tmp_name'][$i])) {
                $ext = strtolower(pathinfo($_FILES['EventOther']['name'][$i], PATHINFO_EXTENSION));
                $fname = 'other_' . $i . '_' . time() . '_' . $newId . '.' . $ext;
                if (move_uploaded_file($_FILES['EventOther']['tmp_name'][$i], $docsDir . $fname)) {
                    $p = 'assets/uploads/events/docs/' . $fname;
                    $origName = $_FILES['EventOther']['name'][$i];
                    $fileSize = filesize($docsDir . $fname);
                    $fSizeStr = round($fileSize / 1024) . 'KB';
                    $docType = 'Supporting Document';
                    $stmtDoc = $conn->prepare("INSERT INTO org_documents (OrgId, EventId, Title, DocType, FilePath, FileSize) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtDoc->bind_param("iissss", $orgId, $newId, $origName, $docType, $p, $fSizeStr);
                    $stmtDoc->execute();
                    $stmtDoc->close();
                }
            }
        }
    }

    if (function_exists('logAudit')) {
        logAudit($conn, 'Create Event', 'org', $orgId, 'success', ['event_name'=>$name,'event_id'=>$newId]);
    }
    echo json_encode(['success'=>true,'message'=>'Event created successfully','event_id'=>$newId]);
} else {
    echo json_encode(['success'=>false,'message'=>'Database error: '.$conn->error]);
}
$stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Fatal error: '.$e->getMessage()]);
}
