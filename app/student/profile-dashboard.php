<?php
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];

$course_org_map = [
    'BSAIT'   => 5, 
    'BSAIS'   => 1, 
    'AAMT'    => 2, 
    'BSAMT'   => 2, 
    'AAET'    => 4, 
    'BSAET'   => 4, 
    'BSAEE'   => 3, 
    'BSAT'    => 3, 
    'BSAVTOUR'=> 6, 
    'BSAVCOMM'=> 6, 
    'BSAVLOG' => 6, 
    'BSAVSEC' => 6,
    'BSAVSSM' => 6, 
];


$courseQ = $conn->query("SELECT * FROM `user` WHERE UserId = $student_id LIMIT 1");
$current_course_check = ($courseQ && $courseQ->num_rows > 0) ? $courseQ->fetch_assoc() : null;
if ($current_course_check) {
    $cVal = $current_course_check['Course'] ?? $current_course_check['course'] ?? '';
    if (!empty($cVal) && isset($course_org_map[$cVal])) {
        $mapped_org = $course_org_map[$cVal];
        if ((int)($current_course_check['OrgId'] ?? 0) !== $mapped_org) {
            $conn->query("UPDATE `user` SET OrgId = $mapped_org WHERE UserId = $student_id");
        }
    }
}


// Fetch student profile via API / Stored Procedure
ob_start();
$_GET['action'] = 'get_student_profile';
require __DIR__ . '/../../config/API/endpoints/index.php';
$profApi = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$student = $profApi['data'] ?? null;

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$fullName    = trim($student['first_name'] . ' ' . $student['last_name']);
$initials    = strtoupper(substr($student['first_name'],0,1) . substr($student['last_name'],0,1));
$course      = $student['course']      ?: 'N/A';
$year        = $student['year_level']  ?: '';
$section     = $student['section']     ?: '';
$email       = $student['Email']       ?: '';
$phone       = $student['phone']       ?: '';
$address     = $student['Address']     ?: '';
$position    = $student['Position']    ?: 'Member';
$studentNo   = $student['student_id']  ?: 'N/A';
$middleName  = $student['middle_name'] ?: '';
$orgName     = $student['OrgName']     ?: 'No organization';
$resolvePhotoUrl = function (?string $path): string {
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }

    $candidates = [];
    if (strpos($path, '../../') === 0) {
        $candidates[] = $path;
        $candidates[] = ltrim(substr($path, 6), '/');
    } elseif (strpos($path, 'assets/') === 0) {
        $candidates[] = '../../' . $path;
        $candidates[] = $path;
    } else {
        $candidates[] = '../../' . ltrim($path, '/');
        $candidates[] = ltrim($path, '/');
    }

    foreach ($candidates as $candidate) {
        $diskPath = __DIR__ . '/../../' . ltrim(str_replace('../../', '', $candidate), '/');
        if (file_exists($diskPath)) {
            return $candidate;
        }
    }

    return $candidates[0] ?? '';
};

$rawPhoto = $student['profile_photo'] ?? '';
$student['profile_photo'] = $resolvePhotoUrl($rawPhoto);
$hasPhoto = !empty($student['profile_photo']);

$rawCor = $student['cor_document'] ?? '';
$corDocUrl = $resolvePhotoUrl($rawCor);
$corFileName = !empty($rawCor) ? basename($rawCor) : '';
$hasCor = !empty($rawCor) && !empty($corDocUrl);
$corExt = !empty($corFileName) ? strtolower(pathinfo($corFileName, PATHINFO_EXTENSION)) : '';

$rawOrgPic = $student['OrgPicture'] ?? '';
if ($rawOrgPic && strpos($rawOrgPic, 'assets') === 0) {
    $student['OrgPicture'] = '../../' . $rawOrgPic;
} elseif ($rawOrgPic && strpos($rawOrgPic, '/') === 0) {
    
}



$regCount = (int)$conn->query("SELECT COUNT(DISTINCT EventId) c FROM eventregistration WHERE UserId = $student_id")->fetch_assoc()['c'];
$preRegCount = $regCount;
// An event counts as attended only after the student has checked out.
$attCount = (int)$conn->query("SELECT COUNT(DISTINCT EventId) c FROM attendance WHERE UserId = $student_id AND LOWER(COALESCE(LogType, '')) = 'log out'")->fetch_assoc()['c'];


$takenTests = [];

$preQ = $conn->query("SELECT EventId FROM event_pretest WHERE UserId = $student_id");
if ($preQ) {
    while ($prow = $preQ->fetch_assoc()) {
        $takenTests[$prow['EventId']]['pretest'] = true;
    }
}

$postQ = $conn->query("SELECT EventId FROM event_posttest WHERE UserId = $student_id");
if ($postQ) {
    while ($ptrow = $postQ->fetch_assoc()) {
        $takenTests[$ptrow['EventId']]['posttest'] = true;
    }
}

// Only offer assessment buttons when an organization has actually created one.
$availableAssessments = [];
$assessmentQ = $conn->query("SELECT event_id, type FROM assessments WHERE status = 'published'");
if ($assessmentQ) {
    while ($assessmentRow = $assessmentQ->fetch_assoc()) {
        $assessmentType = strtolower((string)$assessmentRow['type']);
        if (strpos($assessmentType, 'pre') !== false) $availableAssessments[(int)$assessmentRow['event_id']]['pretest'] = true;
        if (strpos($assessmentType, 'post') !== false) $availableAssessments[(int)$assessmentRow['event_id']]['posttest'] = true;
    }
}




$regs = [];
$rq = $conn->query("
    SELECT er.RegistrationId, er.DateIssued,
        e.EventId, e.EventName, e.EventDateTime, e.EventLocation, e.EventStatus, e.EventDescription,
           o.OrgName,
           (SELECT MIN(att.AttendanceId)
              FROM attendance att
             WHERE att.EventId = er.EventId
               AND att.UserId = er.UserId
               AND LOWER(COALESCE(att.LogType, '')) = 'log in') AS AttendanceId
    FROM eventregistration er
    JOIN event e ON e.EventId = er.EventId
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    WHERE er.UserId = $student_id
    ORDER BY e.EventDateTime DESC
    LIMIT 20
");
if ($rq) {
    while ($row = $rq->fetch_assoc()) {
        $row['RegStatus'] = 'Registered';
        $regs[] = $row;
    }
}


$certs = [];
$cq = $conn->query("
    SELECT c.CertId AS CertificateId, c.GeneratedImage AS CertificateURL, c.IssuedAt AS DateIssued, c.CertCode,
           e.EventName, e.EventDateTime, e.EventLocation,
           o.OrgName,
           t.TemplateName, t.TemplateImage, t.FieldConfig
    FROM certificates c
    JOIN event e ON e.EventId = c.EventId
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    LEFT JOIN certificate_templates t ON t.TemplateId = c.TemplateId
    WHERE c.UserId = $student_id
    ORDER BY c.IssuedAt DESC
");
if ($cq) while ($row = $cq->fetch_assoc()) $certs[] = $row;
// Fetch notifications via API
ob_start();
$_GET['action'] = 'get_student_notifications';
require __DIR__ . '/../../config/API/endpoints/index.php';
$notifApi = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$notifData = $notifApi['data'] ?? [];

$annCount             = (int)($notifData['announcements_count'] ?? 0);
$certCount            = (int)($notifData['certificates_count'] ?? 0);
$regNoticeCount       = (int)($notifData['pending_tests_count'] ?? 0);
$onlineAttNoticeCount = (int)($notifData['online_attendance_count'] ?? 0);

$profileMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $fn  = trim($_POST['first_name']  ?? '');
    $ln  = trim($_POST['last_name']   ?? '');
    $mn  = trim($_POST['middle_name'] ?? '');
    $ph  = trim($_POST['phone']       ?? '');
    $adr = trim($_POST['address']     ?? '');

    $photo_path = $student['profile_photo'];
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (in_array($_FILES['profile_photo']['type'], $allowed)) {
            $dir = '../../assets/uploads/profile_photos/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext   = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $fname = 'profile_' . $student_id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $dir . $fname)) {
                $photo_path = $dir . $fname;
            }
        }
    }

    if (!empty($fn) && !empty($ln)) {
        $stmt = $conn->prepare("UPDATE user SET first_name=?, last_name=?, middle_name=?, phone=?, Address=?, profile_photo=? WHERE UserId=?");
        $stmt->bind_param('ssssssi', $fn, $ln, $mn, $ph, $adr, $photo_path, $student_id);
        if ($stmt->execute()) {
            $profileMsg = 'success';
            
            $student['first_name']   = $fn;
            $student['last_name']    = $ln;
            $student['middle_name']  = $mn;
            $student['phone']        = $ph;
            $student['Address']      = $adr;
            $student['profile_photo'] = $photo_path;
            $fullName  = trim("$fn $ln");
            $initials  = strtoupper(substr($fn,0,1) . substr($ln,0,1));
            $phone     = $ph;
            $address   = $adr;
            $hasPhoto  = !empty($photo_path);
        } else {
            $profileMsg = 'error';
        }
    }

    
    $newPass = $_POST['new_password']     ?? '';
    $confPass = $_POST['confirm_password'] ?? '';
    $curPass  = $_POST['current_password'] ?? '';
    if (!empty($newPass)) {
        $row = $conn->query("SELECT PasswordHash FROM user WHERE UserId = $student_id LIMIT 1")->fetch_assoc();
        if (password_verify($curPass, $row['PasswordHash'] ?? '')) {
            if ($newPass === $confPass && strlen($newPass) >= 8) {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $ps = $conn->prepare("UPDATE user SET PasswordHash=? WHERE UserId=?");
                $ps->bind_param('si', $hash, $student_id);
                $ps->execute();
            }
        }
    }

    header('Location: profile-dashboard.php?tab=profile&saved=1');
    exit;
}

$activeTab = $_GET['tab'] ?? 'dashboard';
$saved = isset($_GET['saved']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard – NAAP</title>
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/student/profile-dashboard.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <link rel="icon" href="../../assets/img/philsca.png">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
</head>
<body>

    <div class="mobile-header">
        <button id="hamburger-btn" class="hamburger" aria-label="Open menu">
            <ion-icon name="menu-outline"></ion-icon>
        </button>
        <div class="mobile-header-logo"><img src="../../assets/img/philsca.png" alt="Logo"></div>
        <div class="mobile-header-title">NAAP Student Organization</div>
    </div>

    <nav>
        <div class="nav-left">
            <img src="../../assets/img/naap logo.png" alt="NAAP Logo">
            <div class="nav-links">
                <?php $currPage = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
                <a href="../index.php" class="<?= $currPage === 'index.php' ? 'active' : '' ?>">Home</a>
                <a href="organization.php" class="<?= $currPage === 'organization.php' ? 'active' : '' ?>">Organizations</a>
                <a href="events.php" class="<?= $currPage === 'events.php' ? 'active' : '' ?>">Events</a>
            </div>
        </div>
        <div class="nav-actions">
            <?php 
                $is_logged = isset($isLoggedIn) ? $isLoggedIn : (isset($_SESSION['student_id']) && !empty($_SESSION['student_id']));
            ?>
            <?php if ($is_logged): ?>
                <div class="nav-user-dropdown">
                    <?php 
                        $src = '';
                        $rawPhoto = $student['profile_photo'] ?? '';
                        if (!empty($rawPhoto) && strpos($rawPhoto, 'assets/uploads/profile_photos/') !== false) {
                            $cleanPath = ltrim(str_replace(['../../', '../'], '', $rawPhoto), '/');
                            $diskPath = __DIR__ . '/../../' . $cleanPath;
                            if (file_exists($diskPath) && !is_dir($diskPath) && filesize($diskPath) > 0) {
                                $src = '../../' . $cleanPath;
                            }
                        }
                    ?>
                    <button type="button" class="nav-profile nav-profile-trigger" aria-label="Open account menu">
                        <div class="nav-avatar">
                            <span class="nav-avatar-initials"><?= htmlspecialchars($initials ?: 'S') ?></span>
                            <?php if ($src !== ''): ?>
                                <img src="<?= htmlspecialchars($src) ?>" alt="Avatar" onerror="this.remove();">
                            <?php endif; ?>
                        </div>
                        <div class="nav-user-info">
                            <span class="nav-user-name"><?= htmlspecialchars(isset($fullName) ? $fullName : (isset($student['first_name']) ? trim($student['first_name'] . ' ' . $student['last_name']) : 'Student')) ?></span>
                            <span class="nav-user-role">Student</span>
                        </div>
                        <ion-icon name="chevron-down-outline" class="nav-dropdown-caret"></ion-icon>
                    </button>
                    <div class="nav-dropdown-menu" role="menu" aria-label="Account menu">
                        <a href="profile-dashboard.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="person-circle-outline"></ion-icon><span>Profile Dashboard</span></a>
                        <a class="nav-dropdown-item danger" href="../../config/API/student_logout.php" role="menuitem"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a>
                    </div>
                </div>
            <?php else: ?>
                <a class="nav-btn nav-btn-login" href="login.php">Login</a>
                <a class="nav-btn nav-btn-register" href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>

        <div class="nav-mobile">
        <ul>
            <li><a href="../index.php"><i class='bx bx-home'></i> Home</a></li>
            <li><a href="organization.php"><i class='bx bx-group'></i> Organizations</a></li>
            <li><a href="events.php"><i class='bx bx-calendar-event'></i> Events</a></li>
            <?php if ($is_logged): ?>
                <li style="border-top:1px solid rgba(255,255,255,0.15);margin-top:8px;padding-top:8px;">
                    <a href="#" class="mobile-dash-nav <?= $activeTab === 'dashboard' ? 'active' : '' ?>" data-target="dashboard-content"><i class='bx bx-grid-alt'></i> Dashboard</a>
                </li>
                <li>
                    <a href="announcements.php" class="<?= $activeTab === 'announcements' ? 'active' : '' ?>">
                        <i class='bx bx-bell'></i> Announcements 
                        <span id="badge-announcements-mobile" style="background:#2563eb;color:#fff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 7px;margin-left:6px;display:<?= $annCount > 0 ? 'inline-block' : 'none' ?>;"><?= $annCount ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" class="mobile-dash-nav <?= $activeTab === 'registrations' ? 'active' : '' ?>" data-target="registrations-content">
                        <i class='bx bx-calendar'></i> My Registrations 
                        <span id="badge-registrations-mobile" style="background:#f59e0b;color:#fff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 7px;margin-left:6px;display:<?= $regNoticeCount > 0 ? 'inline-block' : 'none' ?>;"><?= $regNoticeCount ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" class="mobile-dash-nav <?= $activeTab === 'profile' ? 'active' : '' ?>" data-target="profile-content"><i class='bx bx-user'></i> My Profile</a>
                </li>
                <li>
                    <a href="#" class="mobile-dash-nav <?= $activeTab === 'certificates' ? 'active' : '' ?>" data-target="certificates-content">
                        <i class='bx bx-medal'></i> Certificates 
                        <span id="badge-certificates-mobile" style="background:#2563eb;color:#fff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 7px;margin-left:6px;display:<?= $certCount > 0 ? 'inline-block' : 'none' ?>;"><?= $certCount ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" class="mobile-dash-nav <?= $activeTab === 'online-attendance' ? 'active' : '' ?>" data-target="online-attendance-content">
                        <i class='bx bx-wifi'></i> Online Attendance 
                        <span id="badge-attendance-mobile" style="background:#10b981;color:#fff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 7px;margin-left:6px;display:<?= $onlineAttNoticeCount > 0 ? 'inline-block' : 'none' ?>;"><?= $onlineAttNoticeCount > 0 ? ($onlineAttNoticeCount === 1 ? 'LIVE' : $onlineAttNoticeCount) : '' ?></span>
                    </a>
                </li>
                <li style="border-top:1px solid rgba(255,255,255,0.15);margin-top:8px;padding-top:8px;">
                    <a href="../../config/API/student_logout.php" style="color:#ef4444;"><i class='bx bx-log-out'></i> Logout</a>
                </li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="halfborder"></div>

    <div class="dashboard-container">
        
        <aside class="sidebar">
            <div class="profile-section">
                <div class="avatar">
                    <?php if ($hasPhoto): ?>
                        <img src="<?= htmlspecialchars($student['profile_photo']) ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" alt="Profile">
                    <?php else: ?>
                        <?= $initials ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h3 class="user-name"><?= htmlspecialchars($fullName) ?></h3>
                    <p class="user-email"><?= htmlspecialchars($email) ?></p>
                    <span class="user-role"><?= htmlspecialchars($course) ?></span>
                </div>
            </div>

            <div class="sidebar-nav">
                <a href="#" class="nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>" data-target="dashboard-content">
                    <i class='bx bx-grid-alt'></i> Dashboard
                </a>
                <a href="announcements.php" class="nav-item <?= $activeTab === 'announcements' ? 'active' : '' ?>">
                    <i class='bx bx-bell'></i> Announcements
                    <span id="badge-announcements" style="margin-left:auto;background:#2563eb;color:#ffffff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 7px;display:<?= $annCount > 0 ? 'inline-block' : 'none' ?>;"><?= $annCount ?></span>
                </a>
                <a href="#" class="nav-item <?= $activeTab === 'registrations' ? 'active' : '' ?>" data-target="registrations-content">
                    <i class='bx bx-calendar'></i> My Registrations
                    <span id="badge-registrations" style="margin-left:auto;background:#f59e0b;color:#ffffff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 7px;display:<?= $regNoticeCount > 0 ? 'inline-block' : 'none' ?>;"><?= $regNoticeCount ?></span>
                </a>

                <a href="#" class="nav-item <?= $activeTab === 'profile' ? 'active' : '' ?>" data-target="profile-content">
                    <i class='bx bx-user'></i> My Profile
                </a>
                <a href="#" class="nav-item <?= $activeTab === 'certificates' ? 'active' : '' ?>" data-target="certificates-content">
                    <i class='bx bx-medal'></i> Certificates
                    <span id="badge-certificates" style="margin-left:auto;background:#2563eb;color:#ffffff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 7px;display:<?= $certCount > 0 ? 'inline-block' : 'none' ?>;"><?= $certCount ?></span>
                </a>
                <a href="#" class="nav-item <?= $activeTab === 'online-attendance' ? 'active' : '' ?>" data-target="online-attendance-content">
                    <i class='bx bx-wifi'></i> Online Attendance
                    <span id="badge-attendance" style="margin-left:auto;background:#10b981;color:#ffffff;border-radius:999px;font-size:.65rem;font-weight:700;padding:1px 7px;display:<?= $onlineAttNoticeCount > 0 ? 'inline-block' : 'none' ?>;"><?= $onlineAttNoticeCount > 0 ? ($onlineAttNoticeCount === 1 ? 'LIVE' : $onlineAttNoticeCount) : '' ?></span>
                </a>
            </div>

            <div class="sidebar-footer">
                <a href="../../config/API/student_logout.php" class="logout-btn">
                    <i class='bx bx-log-out'></i> Logout
                </a>
            </div>
        </aside>

        
        <main class="main-content">

            
            <section id="dashboard-content" class="content-section <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
                <h1 class="page-title">Welcome back, <?= htmlspecialchars($student['first_name']) ?>! </h1>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon"><i class='bx bx-calendar'></i></div>
                        <div class="stat-info">
                            <span class="stat-value" id="studentRegCount"><?= $regCount ?></span>
                            <span class="stat-label">Event Registrations</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon text-cyan"><i class='bx bx-user-check'></i></div>
                        <div class="stat-info">
                            <span class="stat-value" id="studentPreRegCount"><?= $preRegCount ?></span>
                            <span class="stat-label">Pre-Registered Events</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon text-cyan"><i class='bx bx-check-circle'></i></div>
                        <div class="stat-info">
                            <span class="stat-value" id="studentAttCount"><?= $attCount ?></span>
                            <span class="stat-label">Events Attended</span>
                        </div>
                    </div>
                </div>

                
                <div style="margin-top:1.75rem; background:linear-gradient(135deg,rgba(30,41,59,0.6),rgba(15,23,42,0.85)); border:1px solid rgba(255,255,255,0.06); border-radius:18px; padding:1.5rem;">
                    <h3 style="margin:0 0 1.25rem; font-size:1rem; color:#f8fafc; font-weight:700; display:flex; align-items:center; gap:8px;">
                        <i class='bx bx-pie-chart-alt-2' style="font-size:1.2rem; color:#38bdf8;"></i>
                        Event Attendance Overview
                    </h3>
                    <?php 
                        $totalEvents = max($regCount, $attCount);
                        $notAttended = max(0, $totalEvents - $attCount);
                        $attPct = $totalEvents > 0 ? min(100, round(($attCount / $totalEvents) * 100)) : 0;
                    ?>
                    <?php if ($totalEvents === 0): ?>
                        <div style="text-align:center; padding:2rem; color:#64748b;">
                            <i class='bx bx-calendar-x' style="font-size:3rem; display:block; margin-bottom:.5rem;"></i>
                            <p style="margin:0;">No event registrations yet. <a href="events.php" style="color:#38bdf8; font-weight:600;">Browse Events →</a></p>
                        </div>
                    <?php else: ?>
                    <div style="display:flex; align-items:center; gap:2rem; flex-wrap:wrap;">
                        <div style="position:relative; width:180px; height:180px; flex-shrink:0; margin:0 auto;">
                            <canvas id="attendancePieChart" width="180" height="180"></canvas>
                            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; pointer-events:none;">
                                <span style="font-size:1.6rem; font-weight:800; color:#f8fafc;"><?= $attPct ?>%</span>
                                <span style="display:block; font-size:.7rem; color:#94a3b8; font-weight:600; margin-top:2px;">Attended</span>
                            </div>
                        </div>
                        <div style="flex:1; min-width:160px;">
                            <div style="display:flex; flex-direction:column; gap:.85rem;">
                                <div style="display:flex; align-items:center; gap:.75rem;">
                                    <span style="width:12px; height:12px; border-radius:50%; background:#2563eb; display:inline-block; flex-shrink:0;"></span>
                                    <div>
                                        <p style="margin:0; font-size:.8rem; color:#94a3b8; font-weight:600;">Events Attended</p>
                                        <p style="margin:0; font-size:1.15rem; font-weight:800; color:#f8fafc;"><?= $attCount ?></p>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:.75rem;">
                                    <span style="width:12px; height:12px; border-radius:50%; background:#334155; border:1.5px solid #475569; display:inline-block; flex-shrink:0;"></span>
                                    <div>
                                        <p style="margin:0; font-size:.8rem; color:#94a3b8; font-weight:600;">Registered but Not Attended</p>
                                        <p style="margin:0; font-size:1.15rem; font-weight:800; color:#f8fafc;"><?= $notAttended ?></p>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:.75rem;">
                                    <span style="width:12px; height:12px; border-radius:50%; background:#38bdf8; display:inline-block; flex-shrink:0;"></span>
                                    <div>
                                        <p style="margin:0; font-size:.8rem; color:#94a3b8; font-weight:600;">Pre-Registered Events</p>
                                        <p style="margin:0; font-size:1.15rem; font-weight:800; color:#f8fafc;"><?= $preRegCount ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    (function(){
                        function initAttendanceChart() {
                            const ctx = document.getElementById('attendancePieChart');
                            if (!ctx || typeof Chart === 'undefined') return;
                            new Chart(ctx.getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: ['Attended', 'Not Attended'],
                                    datasets: [{
                                        data: [<?= max(0, (int)$attCount) ?>, <?= max(0, (int)$notAttended) ?>],
                                        backgroundColor: ['#2563eb', '#334155'],
                                        borderColor: ['#1d4ed8', '#475569'],
                                        borderWidth: 2,
                                        hoverOffset: 6
                                    }]
                                },
                                options: {
                                    cutout: '72%',
                                    responsive: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            callbacks: {
                                                label: (c) => ` ${c.label}: ${c.parsed} event(s)`
                                            }
                                        }
                                    }
                                }
                            });
                        }
                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initAttendanceChart);
                        } else {
                            initAttendanceChart();
                        }
                    })();
                    </script>
                    <?php endif; ?>
                </div>

                <div class="org-banner" style="background:linear-gradient(135deg,rgba(30,41,59,0.7),rgba(15,23,42,0.9));border:1px solid rgba(255,255,255,0.05);border-radius:16px;padding:1.5rem;margin-top:1.5rem;display:flex;align-items:center;gap:1.25rem;">
                    <?php if (!empty($student['OrgPicture'])): ?>
                        <img src="<?= htmlspecialchars($student['OrgPicture']) ?>" style="width:64px;height:64px;border-radius:12px;object-fit:cover;box-shadow:0 4px 12px rgba(0,0,0,0.3);" alt="Org" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div style="width:64px;height:64px;border-radius:12px;background:#334155;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:#94a3b8;box-shadow:0 4px 12px rgba(0,0,0,0.3);"><i class='bx bx-group'></i></div>
                    <?php endif; ?>
                    <div style="flex:1;">
                        <p style="margin:0;font-size:.85rem;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-bottom:4px;">Your Course-based Student Organization</p>
                        <h2 style="margin:0;font-size:1.25rem;color:#f8fafc;font-weight:700;"><?= htmlspecialchars($orgName) ?></h2>
                        <p style="margin:4px 0 0;font-size:.85rem;color:#cbd5e1;"><i class='bx bxs-graduation' style="margin-right:4px;"></i><?= htmlspecialchars($course) ?></p>
                    </div>
                    <span style="background:rgba(56,189,248,.15);color:#38bdf8;border:1px solid rgba(56,189,248,.3);padding:6px 14px;border-radius:20px;font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:6px;">
                        <i class='bx bxs-user-badge'></i> <?= htmlspecialchars($position) ?>
                    </span>
                </div>

                <!-- My Pre-Registered Events Section on Dashboard -->
                <div style="margin-top:1.75rem; background:linear-gradient(135deg,rgba(30,41,59,0.6),rgba(15,23,42,0.85)); border:1px solid rgba(255,255,255,0.06); border-radius:18px; padding:1.5rem;">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:8px;">
                        <h3 style="margin:0; font-size:1rem; color:#f8fafc; font-weight:700; display:flex; align-items:center; gap:8px;">
                            <i class='bx bx-calendar-star' style="font-size:1.2rem; color:#38bdf8;"></i>
                            My Pre-Registered & Upcoming Events
                        </h3>
                        <a href="#" class="nav-item" data-target="registrations-content" onclick="switchTab('registrations-content'); return false;" style="color:#38bdf8; font-size:0.85rem; font-weight:600; text-decoration:none;">View All Registrations →</a>
                    </div>
                    <?php if (empty($regs)): ?>
                        <div style="text-align:center; padding:2rem; color:#64748b;">
                            <i class='bx bx-calendar-x' style="font-size:2.5rem; display:block; margin-bottom:.5rem;"></i>
                            <p style="margin:0;">No pre-registered events found. <a href="events.php" style="color:#38bdf8; font-weight:600;">Explore Events →</a></p>
                        </div>
                    <?php else: ?>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach (array_slice($regs, 0, 5) as $reg):
                            $dateStr   = !empty($reg['EventDateTime']) ? date('M d, Y', strtotime($reg['EventDateTime'])) : 'TBA';
                            $timeStr   = !empty($reg['EventDateTime']) ? date('g:i A', strtotime($reg['EventDateTime'])) : '';
                            $evStatus  = $reg['EventStatus'] ?? 'Upcoming';
                            $eventId   = (int)$reg['EventId'];
                        ?>
                            <div style="background:rgba(15,23,42,0.6); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                                <div>
                                    <h4 style="margin:0 0 4px; font-size:0.95rem; color:#f8fafc; font-weight:700;"><?= htmlspecialchars($reg['EventName']) ?></h4>
                                    <div style="font-size:0.8rem; color:#94a3b8; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                                        <span><i class='bx bx-group' style="color:#38bdf8;"></i> <?= htmlspecialchars($reg['OrgName'] ?: 'NAAP') ?></span>
                                        <span><i class='bx bx-calendar' style="color:#38bdf8;"></i> <?= $dateStr ?> <?= $timeStr ?></span>
                                        <?php if (!empty($reg['EventLocation'])): ?>
                                            <span><i class='bx bx-map' style="color:#38bdf8;"></i> <?= htmlspecialchars($reg['EventLocation']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <span style="padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:700; background:rgba(56,189,248,0.15); color:#38bdf8; border:1px solid rgba(56,189,248,0.3);">
                                        <?= htmlspecialchars($evStatus) ?>
                                    </span>
                                    <button type="button" onclick="switchTab('registrations-content')" style="padding:6px 14px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#f8fafc; border-radius:8px; font-size:0.8rem; font-weight:600; cursor:pointer;">
                                        Manage
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </section>


            
            <section id="registrations-content" class="content-section <?= $activeTab === 'registrations' ? 'active' : '' ?>">
                <h1 class="page-title">My Event Registrations</h1>
                <div class="reg-toolbar">
                    <input id="registrationSearch" class="reg-search-input" type="search" placeholder="Search registrations...">
                    <div class="reg-toolbar-actions">
                        <select id="registrationStatusFilter" class="reg-status-select">
                            <option value="">All statuses</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                        </select>
                        <button id="registrationSearchBtn" class="reg-search-btn" type="button">Search</button>
                    </div>
                </div>
                <div id="registrationSummary" style="margin:0 0 14px;color:#94a3b8;font-size:.88rem;">Showing <strong><?= min(3, $regCount) ?></strong> of <strong><?= $regCount ?></strong> registrations | Page <strong>1</strong> of <strong><?= max(1, (int)ceil($regCount / 3)) ?></strong></div>
                <div class="registration-list" id="registrationList">
                    <?php if (empty($regs)): ?>
                        <div style="text-align:center;padding:3rem;color:#64748b;">
                            <i class='bx bx-calendar-x' style="font-size:3rem;"></i>
                            <p style="margin-top:.5rem;">No event registrations yet.</p>
                            <a href="events.php" style="color:#38bdf8;font-weight:600;">Browse Events →</a>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($regs, 0, 3) as $reg):
                            $regStatus = $reg['RegStatus'] ?? 'Registered';
                            $evStatus  = $reg['EventStatus'] ?? 'Upcoming';
                            
                            $statusDisplay = htmlspecialchars($regStatus);
                            if ($evStatus !== 'Upcoming' && $evStatus !== 'Scheduled') {
                                $statusDisplay .= ' (' . htmlspecialchars($evStatus) . ')';
                            }
                            
                            $statusClass = strtolower($regStatus);
                            if (strtolower($evStatus) === 'completed') {
                                $statusClass = 'completed';
                            }
                            
                            $dateStr   = !empty($reg['EventDateTime']) ? date('M d, Y', strtotime($reg['EventDateTime'])) : 'TBA';
                            $timeStr   = !empty($reg['EventDateTime']) ? date('g:i A', strtotime($reg['EventDateTime'])) : '';
                        
                            $eventId = (int)$reg['EventId'];
                            $hasPreTest = isset($takenTests[$eventId]['pretest']);
                            $hasPostTest = isset($takenTests[$eventId]['posttest']);
                            $hasPreAssessment = isset($availableAssessments[$eventId]['pretest']);
                            $hasPostAssessment = isset($availableAssessments[$eventId]['posttest']);
                            $hasAttendance = !empty($reg['AttendanceId']);
                            $safeEventName = htmlspecialchars($reg['EventName'] ?? 'Event');
                            $safeOrgName = htmlspecialchars($reg['OrgName'] ?: 'NAAP');
                            $hasAttendance = !empty($reg['AttendanceId']);
                            $safeEventName = htmlspecialchars($reg['EventName'] ?? 'Event');
                            $safeOrgName = htmlspecialchars($reg['OrgName'] ?: 'NAAP');
                            $safeEventDate = htmlspecialchars($dateStr);
                            $safeEventTime = htmlspecialchars($timeStr ?: 'TBA');
                            $safeEventLocation = htmlspecialchars($reg['EventLocation'] ?: 'TBA');
                            $safeEventStatus = htmlspecialchars($reg['EventStatus'] ?? 'Upcoming');
                            $safeEventDesc = htmlspecialchars($reg['EventDescription'] ?? 'No event description available.');
                        ?>
                        <div class="registration-card">
                            <div class="reg-header">
                                <div>
                                    <h3 class="event-name"><?= htmlspecialchars($reg['EventName']) ?></h3>
                                    <p class="event-org"><?= htmlspecialchars($reg['OrgName'] ?: 'NAAP') ?></p>
                                    <div class="event-date">
                                        <i class='bx bx-calendar'></i> <?= $dateStr ?>
                                        <?php if ($timeStr): ?>&nbsp;&nbsp;<i class='bx bx-time'></i> <?= $timeStr ?><?php endif; ?>
                                        <?php if (!empty($reg['EventLocation'])): ?>
                                            &nbsp;·&nbsp; <i class='bx bx-map'></i> <?= htmlspecialchars($reg['EventLocation']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="status-badge <?= $statusClass ?>" style="<?= strtolower($evStatus) === 'ongoing' ? 'background:#10b981!important;color:#ffffff!important;border:1px solid #059669!important;box-shadow:0 0 12px rgba(16,185,129,0.4)!important;font-weight:800!important;' : '' ?>"><?= $statusDisplay ?></span>
                            </div>
                            <div class="reg-actions" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
                                <?php if ($hasPreTest): ?>
                                <span style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:rgba(16,185,129,0.15);border-radius:8px;color:#34d399;font-size:.82rem;font-weight:700;border:1px solid rgba(52,211,153,0.3);cursor:default;">
                                    <i class='bx bx-check-circle' style="font-size:1rem;color:#10b981;"></i> Pre-Test Taken
                                </span>
                                <?php elseif (!$hasAttendance): ?>
                                <span style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#fef3c7;border-radius:8px;color:#b45309;font-size:.82rem;font-weight:700;border:1px solid #fde68a;cursor:not-allowed;">
                                    <i class='bx bx-time-five' style="font-size:1rem;color:#d97706;"></i> Attendance required first
                                </span>
                                <?php elseif (!$hasPreAssessment): ?>
                                <span style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:rgba(255,255,255,0.08);border-radius:8px;color:#94a3b8;font-size:.82rem;font-weight:700;border:1px solid rgba(255,255,255,0.1);cursor:not-allowed;">
                                    <i class='bx bx-file-blank' style="font-size:1rem;"></i> Pre-Test not created
                                </span>
                                <?php else: ?>
                                <a href="pre-test.php?event_id=<?= $eventId ?>&type=pretest"
                                   style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#1d4ed8,#2563eb);border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;text-decoration:none;transition:background .2s;border:none;box-shadow:0 4px 12px rgba(37,99,235,0.3);"
                                   onmouseover="this.style.background='#1e40af'" onmouseout="this.style.background='#1d4ed8'">
                                    <i class='bx bx-file' style="font-size:1rem;"></i> Take Pre-Test
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($hasPostTest): ?>
                                <span style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:rgba(16,185,129,0.15);border-radius:8px;color:#34d399;font-size:.82rem;font-weight:700;border:1px solid rgba(52,211,153,0.3);cursor:default;">
                                    <i class='bx bx-check-circle' style="font-size:1rem;color:#10b981;"></i> Post-Test Taken
                                </span>
                                <a href="test_results.php?event_id=<?= $eventId ?>&type=post"
                                   style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;text-decoration:none;transition:opacity .2s;border:none;box-shadow:0 4px 12px rgba(37,99,235,0.35);"
                                   onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
                                    <i class='bx bx-brain' style="font-size:1rem;"></i> AI Insight
                                </a>
                                <?php elseif (!$hasAttendance || !$hasPreTest): ?>
                                <span style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#fef3c7;border-radius:8px;color:#b45309;font-size:.82rem;font-weight:700;border:1px solid #fde68a;cursor:not-allowed;">
                                    <i class='bx bx-lock-alt' style="font-size:1rem;color:#d97706;"></i> Attendance & Pre-Test required
                                </span>
                                <?php elseif (!$hasPostAssessment): ?>
                                <span style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:rgba(255,255,255,0.08);border-radius:8px;color:#94a3b8;font-size:.82rem;font-weight:700;border:1px solid rgba(255,255,255,0.1);cursor:not-allowed;">
                                    <i class='bx bx-file-blank' style="font-size:1rem;"></i> Post-Test not created
                                </span>
                                <?php else: ?>
                                <a href="pre-test.php?event_id=<?= $eventId ?>&type=posttest"
                                   style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;text-decoration:none;transition:background .2s;border:none;box-shadow:0 4px 12px rgba(37,99,235,0.35);"
                                   onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                                    <i class='bx bx-check-square' style="font-size:1rem;"></i> Take Post-Test
                                </a>
                                <?php endif; ?>

                                <button type="button"
                                        class="registration-details-btn"
                                        onclick="openEventDetailsModal({
                                             title: '<?= $safeEventName ?>',
                                            org: '<?= $safeOrgName ?>',
                                            date: '<?= $safeEventDate ?>',
                                            time: '<?= $safeEventTime ?>',
                                            location: '<?= $safeEventLocation ?>',
                                            status: '<?= $safeEventStatus ?>',
                                            description: '<?= $safeEventDesc ?>'
                                        })">
                                    <i class='bx bx-expand-alt' style="font-size:1rem;"></i> View Full Details
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="registrationPagination" style="display:flex;justify-content:center;gap:10px;align-items:center;margin-top:20px;"></div>
            </section>

            
            <section id="profile-content" class="content-section <?= $activeTab === 'profile' ? 'active' : '' ?>">
                <h1 class="page-title">My Profile</h1>

                <?php if ($saved): ?>
                <div class="success-toast">
                    <i class='bx bx-check-circle' style="font-size:1.2rem;"></i>
                    Profile updated successfully!
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" action="profile-dashboard.php">
                    <input type="hidden" name="action" value="update_profile">

                    
                    <div class="stat-card" style="padding:1.25rem 1.5rem;margin-bottom:1rem; display:block;">
                        <div class="divider-label">Student ID QR Code</div>
                        <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                            <div id="qrCodeCanvas" style="background:#fff;padding:10px;border-radius:10px;display:inline-block;"></div>
                            <div>
                                <p style="margin:0;font-weight:700;font-size:1rem;"><?= htmlspecialchars($fullName) ?></p>
                                <p style="margin:.2rem 0;font-size:.8rem;color:#94a3b8;">ID: <?= htmlspecialchars($studentNo) ?></p>
                                <p style="margin:.2rem 0;font-size:.8rem;color:#94a3b8;"><?= htmlspecialchars($orgName) ?></p>
                                <div style="display:flex;gap:8px;margin-top:.6rem;flex-wrap:wrap;">
                                    <button type="button" id="saveQrBtn" onclick="downloadQR()" style="padding:.4rem .9rem;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:8px;color:#fff;font-weight:600;font-size:.8rem;cursor:pointer;box-shadow:0 4px 10px rgba(37,99,235,0.35);">
                                        <i class='bx bx-download'></i> Save QR
                                    </button>
                                    <button type="button" onclick="openZoomedQrModal()" style="display:inline-flex;align-items:center;gap:6px;padding:.4rem .9rem;background:linear-gradient(135deg,#0284c7,#2563eb);border:none;border-radius:8px;color:#fff;font-weight:600;font-size:.8rem;cursor:pointer;box-shadow:0 4px 10px rgba(37,99,235,0.35);">
                                        <i class='bx bx-id-card'></i> View QR Card
                                    </button>
                                </div>
                                <div id="qrLoadStatus" style="margin-top:.35rem;font-size:.75rem;color:#94a3b8;">QR ready for attendance scanning.</div>
                            </div>
                        </div>
                    </div>


                    <div class="stat-card" style="padding:1.25rem 1.5rem;margin-bottom:1rem; display:block;">
                        <div class="avatar-upload-area">
                            <div class="avatar-preview" id="avatarPreview">
                                <?php if ($hasPhoto): ?>
                                    <img src="<?= htmlspecialchars($student['profile_photo']) ?>" id="avatarImg" alt="Profile">
                                <?php else: ?>
                                    <span id="avatarInitials"><?= $initials ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label for="photoInput" class="upload-btn"><i class='bx bx-upload'></i> Change Photo</label>
                                <input type="file" id="photoInput" name="profile_photo" accept="image/*" style="display:none;" onchange="previewPhoto(this)">
                                <p style="margin:.4rem 0 0;font-size:.75rem;color:#64748b;">JPG, PNG, GIF or WebP · Max 5MB</p>
                            </div>
                        </div>

                        
                        <div class="profile-form-grid" style="margin-bottom:.75rem;">
                            <div class="form-group">
                                <label>Student No.</label>
                                <input type="text" value="<?= htmlspecialchars($studentNo) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" value="<?= htmlspecialchars($email) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Course / Program</label>
                                <input type="text" value="<?= htmlspecialchars($course) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Year Level &amp; Section</label>
                                <input type="text" value="<?= htmlspecialchars(trim($year . ' ' . $section)) ?>" readonly>
                            </div>
                            <div class="form-group full-width">
                                <label>Organization</label>
                                <input type="text" value="<?= htmlspecialchars($orgName) ?>" readonly>
                            </div>
                        </div>

                        <!-- Certificate of Registration (COR) Section -->
                        <div style="margin-top:1.25rem;padding:1.1rem 1.25rem;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:44px;height:44px;border-radius:10px;background:rgba(37,99,235,0.15);color:#38bdf8;display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0;">
                                    <i class='bx bxs-file-pdf'></i>
                                </div>
                                <div>
                                    <h4 style="margin:0 0 2px;font-size:0.95rem;color:#f8fafc;font-weight:700;">Certificate of Registration (COR)</h4>
                                    <p style="margin:0;font-size:0.8rem;color:#94a3b8;">
                                        <?php if ($hasCor): ?>
                                            <span style="color:#34d399;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><i class='bx bx-check-circle'></i> <?= htmlspecialchars($corFileName) ?></span>
                                        <?php else: ?>
                                            <span style="color:#f87171;display:inline-flex;align-items:center;gap:4px;"><i class='bx bx-x-circle'></i> No COR document uploaded</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php if ($hasCor): ?>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <button type="button" onclick="openStudentCorModal('<?= htmlspecialchars($corDocUrl) ?>', '<?= htmlspecialchars($corFileName) ?>')" style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:8px;color:#fff;font-weight:700;font-size:0.85rem;cursor:pointer;box-shadow:0 4px 12px rgba(37,99,235,0.35);">
                                    <i class='bx bx-show'></i> View COR
                                </button>
                                <a href="<?= htmlspecialchars($corDocUrl) ?>" target="_blank" download style="display:inline-flex;align-items:center;gap:6px;padding:9px 14px;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);border-radius:8px;color:#f8fafc;font-weight:600;font-size:0.85rem;text-decoration:none;cursor:pointer;" title="Download COR">
                                    <i class='bx bx-download'></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    
                    <div class="stat-card" style="padding:1.25rem 1.5rem;margin-bottom:1rem; display:block;">
                        <div class="divider-label">Personal Information</div>
                        <div class="profile-form-grid">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($student['first_name']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($student['last_name']) ?>" readonly>
                            </div>
                            <div class="form-group full-width">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" value="<?= htmlspecialchars($middleName) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" placeholder="+63 000 000 0000" readonly>
                            </div>
                            <div class="form-group full-width">
                                <label>Address</label>
                                <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" placeholder="Home address" readonly>
                            </div>
                        </div>
                    </div>

                    
                    <div class="stat-card" style="padding:1.25rem 1.5rem;margin-bottom:1.25rem; display:block;">
                        <div class="divider-label">Change Password <span style="font-size:.7rem;font-weight:400;text-transform:none;letter-spacing:0;color:#64748b;">— leave blank to keep current password</span></div>
                        <div class="profile-form-grid">
                            <div class="form-group full-width">
                                <label>Current Password</label>
                                <div class="pw-input-wrap">
                                    <input type="password" id="studentCurrentPassword" name="current_password" placeholder="Enter current password" autocomplete="current-password">
                                    <button type="button" class="pw-toggle-btn" data-target="studentCurrentPassword" aria-label="Toggle password visibility">
                                        <ion-icon name="eye-outline"></ion-icon>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <div class="pw-input-wrap">
                                    <input type="password" id="studentNewPassword" name="new_password" placeholder="Min. 8 characters" autocomplete="new-password">
                                    <button type="button" class="pw-toggle-btn" data-target="studentNewPassword" aria-label="Toggle password visibility">
                                        <ion-icon name="eye-outline"></ion-icon>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <div class="pw-input-wrap">
                                    <input type="password" id="studentConfirmPassword" name="confirm_password" placeholder="Repeat new password" autocomplete="new-password">
                                    <button type="button" class="pw-toggle-btn" data-target="studentConfirmPassword" aria-label="Toggle password visibility">
                                        <ion-icon name="eye-outline"></ion-icon>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:.75rem;">
                        <button type="button" onclick="switchTab('dashboard-content')" style="padding:.65rem 1.2rem;background:rgba(255,255,255,.06);border:1.5px solid rgba(255,255,255,.12);border-radius:10px;color:#cbd5e1;font-family:inherit;font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="submit" class="save-btn"><i class='bx bx-save'></i> Save Changes</button>
                    </div>
                </form>
            </section>

            
                        
            <section id="certificates-content" class="content-section <?= $activeTab === 'certificates' ? 'active' : '' ?>">
                <h1 class="page-title">My Certificates</h1>
                <div id="certsContainer">
                    <div style="display:flex;align-items:center;justify-content:center;padding:80px;flex-direction:column;gap:14px;">
                        <span style="font-size:13px;color:#64748b;">Loading your certificates…</span>
                    </div>
                </div>
            </section>

            
            <section id="organizations-content" class="content-section <?= $activeTab === 'organizations' ? 'active' : '' ?>">
                <h1 class="page-title">My Organization</h1>
                <?php if ($orgName !== 'No organization'): ?>
                <div class="stat-card" style="display:flex;gap:1.5rem;align-items:center;padding:1.5rem;max-width:500px;">
                    <?php if (!empty($student['OrgPicture'])): ?>
                        <img src="<?= htmlspecialchars($student['OrgPicture']) ?>" style="width:64px;height:64px;border-radius:12px;object-fit:cover;"
                             onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div>
                        <h2 style="margin:0;font-size:1.1rem;"><?= htmlspecialchars($orgName) ?></h2>
                        <p style="margin:.3rem 0 0;color:#64748b;font-size:.85rem;">Position: <?= htmlspecialchars($position) ?></p>
                    </div>
                </div>
                <?php else: ?>
                <p style="color:#64748b;">You are not currently a member of any organization.</p>
                <a href="organization.php" style="color:#38bdf8;font-weight:600;">Browse Organizations →</a>
                <?php endif; ?>
            </section>

            <!-- Online Attendance Tab Section -->
            <section id="online-attendance-content" class="content-section <?= $activeTab === 'online-attendance' ? 'active' : '' ?>">
                <div style="margin-bottom: 24px;">
                    <div style="display:inline-flex;align-items:center;gap:6px;background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.25);color:#10b981;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;padding:5px 12px;border-radius:20px;margin-bottom:12px;">
                        <span style="width:7px;height:7px;background:#10b981;border-radius:50%;display:inline-block;animation:pulse 1.5s infinite;"></span> Online Event Attendance Check-In
                    </div>
                    <h1 class="page-title" style="margin-bottom:6px;display:flex;align-items:center;gap:10px;">
                        <i class='bx bx-wifi' style="color:#6366f1;"></i> Online Event Attendance
                    </h1>
                    <p style="font-size:13.5px;color:#94a3b8;line-height:1.5;">
                        This section is strictly for Online and Hybrid events. Verify your identity using Facial Recognition or QR Code to record your attendance automatically.
                    </p>
                </div>

                <!-- Supported Attendance Types Info Cards -->
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:16px;margin-bottom:28px;">
                    <!-- Method Card 1: Facial Recognition -->
                    <div style="background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.25);border-radius:16px;padding:20px;display:flex;align-items:flex-start;gap:14px;">
                        <div style="width:44px;height:44px;border-radius:12px;background:rgba(99,102,241,0.2);color:#a78bfa;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                            <i class='bx bx-face'></i>
                        </div>
                        <div>
                            <h4 style="margin:0 0 4px;font-size:14.5px;font-weight:700;color:#f1f5f9;">Facial Recognition</h4>
                            <p style="margin:0;font-size:12.5px;color:#94a3b8;line-height:1.4;">Live camera facial matching against your registered student face data.</p>
                        </div>
                    </div>

                    <!-- Method Card 2: Student QR Code -->
                    <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);border-radius:16px;padding:20px;display:flex;align-items:flex-start;gap:14px;">
                        <div style="width:44px;height:44px;border-radius:12px;background:rgba(16,185,129,0.2);color:#34d399;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
                            <i class='bx bx-qr-scan'></i>
                        </div>
                        <div>
                            <h4 style="margin:0 0 4px;font-size:14.5px;font-weight:700;color:#f1f5f9;">Student QR Code</h4>
                            <p style="margin:0;font-size:12.5px;color:#94a3b8;line-height:1.4;">Scan or present your encrypted student QR code for instant check-in.</p>
                        </div>
                    </div>
                </div>

                <!-- Active / Ongoing Online Event Check -->
                <?php
                $onlineEventsList = [];
                // Only show active/ongoing online events that the student has registered for
                $qEvents = $conn->query("
                    SELECT DISTINCT e.EventId, e.EventName, e.EventDateTime, e.EventStatus
                    FROM event e
                    INNER JOIN eventregistration er
                      ON er.EventId = e.EventId AND er.UserId = $student_id
                    WHERE (LOWER(TRIM(COALESCE(e.EventMode, ''))) IN ('online', 'hybrid')
                           OR LOWER(COALESCE(e.EventLocation, '')) REGEXP 'zoom|teams|meet|online'
                           OR LOWER(COALESCE(e.EventPlace, '')) REGEXP 'zoom|teams|meet|online')
                      AND LOWER(TRIM(COALESCE(e.EventStatus, ''))) IN ('ongoing', 'scheduled', 'upcoming')
                    ORDER BY e.EventDateTime ASC
                    LIMIT 10
                ");
                if ($qEvents) {
                    while ($rowEv = $qEvents->fetch_assoc()) {
                        $onlineEventsList[] = $rowEv;
                    }
                }
                ?>

                <?php if (empty($onlineEventsList)): ?>
                <div style="background:rgba(239,68,68,0.08);border:1.5px solid rgba(239,68,68,0.25);border-radius:16px;padding:40px 24px;text-align:center;">
                    <div style="width:60px;height:60px;border-radius:50%;background:rgba(239,68,68,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#f87171;font-size:30px;">
                        <i class='bx bx-info-circle'></i>
                    </div>
                    <h3 style="margin:0 0 8px;font-size:1.15rem;font-weight:800;color:#ffffff;">No registered online events available right now</h3>
                    <p style="margin:0 0 20px;font-size:0.875rem;color:#94a3b8;max-width:500px;margin-left:auto;margin-right:auto;line-height:1.6;">
                        You have not registered for any active online or hybrid events, or there are no live online events ongoing. Please pre-register for upcoming events to access online attendance.
                    </p>
                    <a href="events.php" style="display:inline-flex;align-items:center;gap:8px;padding:11px 24px;border-radius:10px;text-decoration:none;font-size:0.875rem;background:#2563eb;color:#fff;font-weight:600;transition:all 0.2s;">
                        <i class='bx bx-calendar'></i> Browse &amp; Register for Events
                    </a>
                </div>
                <?php else: ?>
                <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:24px;">
                    <h3 style="margin:0 0 8px;font-size:1.1rem;color:#fff;display:flex;align-items:center;gap:8px;">
                        <i class='bx bx-wifi' style="color:#10b981;"></i> Active Online Events Available
                    </h3>
                    <p style="color:#94a3b8;font-size:0.9rem;margin-bottom:18px;">Click below to launch the self-attendance scanner for live online events.</p>
                    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:20px;">
                        <?php foreach($onlineEventsList as $oEv): ?>
                        <div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                            <div>
                                <h4 style="margin:0 0 2px;font-size:14px;color:#f1f5f9;"><?= htmlspecialchars($oEv['EventName']) ?></h4>
                                <span style="font-size:12px;color:#94a3b8;"><?= date('M d, Y', strtotime($oEv['EventDateTime'])) ?></span>
                            </div>
                            <a href="online-attendance.php?eventId=<?= $oEv['EventId'] ?>" class="btn-primary" style="display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:8px;text-decoration:none;font-size:0.85rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-weight:700;">
                                <i class='bx bx-camera'></i> Start Attendance Scanner
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </section>

        </main>
    </div>

    <script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
    <script src="../../assets/js/student/profile-dashboard.js?v=<?= time() ?>"></script>
    <script src="../../assets/js/index.js"></script>
    <script src="../../assets/js/logout_confirm.js?v=<?= time() ?>"></script>
    <script src="../../assets/js/student/verification_notifier.js?v=<?= time() ?>"></script>
    <script>
   
    function previewPhoto(input) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('avatarPreview');
            const initials = document.getElementById('avatarInitials');
            if (initials) initials.remove();
            let img = preview.querySelector('img');
            if (!img) { img = document.createElement('img'); preview.appendChild(img); }
            img.src = e.target.result;
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
        };
        reader.readAsDataURL(input.files[0]);
    }

    // Tab switching & Smart Notification Dismissal
    // Tab switching & Smart Notification Dismissal
    function switchTab(targetId) {
        document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.nav-item, .mobile-dash-nav').forEach(n => n.classList.remove('active'));
        const sec = document.getElementById(targetId);
        if (sec) sec.classList.add('active');
        document.querySelectorAll(`[data-target="${targetId}"]`).forEach(link => link.classList.add('active'));

        // Dismiss certificate notification when viewing certificates
        if (targetId === 'certificates-content') {
            localStorage.setItem('student_dismissed_certs', 'true');
            localStorage.setItem('student_seen_certs_count', '<?= (int)$certCount ?>');
            const cb = document.getElementById('badge-certificates');
            const cbm = document.getElementById('badge-certificates-mobile');
            if (cb) cb.style.display = 'none';
            if (cbm) cbm.style.display = 'none';
        }
        // Dismiss registration notification when viewing registrations
        if (targetId === 'registrations-content') {
            localStorage.setItem('student_dismissed_regs', 'true');
            localStorage.setItem('student_seen_regs_count', '<?= (int)$regNoticeCount ?>');
            const rb = document.getElementById('badge-registrations');
            const rbm = document.getElementById('badge-registrations-mobile');
            if (rb) rb.style.display = 'none';
            if (rbm) rbm.style.display = 'none';
        }
        // Dismiss attendance notification when viewing online attendance
        if (targetId === 'online-attendance-content') {
            localStorage.setItem('student_dismissed_attendance', 'true');
            localStorage.setItem('student_seen_attendance_count', '<?= (int)$onlineAttNoticeCount ?>');
            const ab = document.getElementById('badge-attendance');
            const abm = document.getElementById('badge-attendance-mobile');
            if (ab) ab.style.display = 'none';
            if (abm) abm.style.display = 'none';
        }
    }

    // Check localStorage on page load to hide already seen notifications
    (function checkNotificationBadges() {
        const annCount = <?= (int)$annCount ?>;
        const certCount = <?= (int)$certCount ?>;
        const regCount = <?= (int)$regNoticeCount ?>;
        const attCount = <?= (int)$onlineAttNoticeCount ?>;

        const seenAnn = parseInt(localStorage.getItem('student_seen_announcements_count') || '0', 10);
        const seenCerts = parseInt(localStorage.getItem('student_seen_certs_count') || '0', 10);
        const seenRegs = parseInt(localStorage.getItem('student_seen_regs_count') || '0', 10);
        const seenAtt = parseInt(localStorage.getItem('student_seen_attendance_count') || '0', 10);

        if (seenAnn >= annCount || localStorage.getItem('student_dismissed_announcements') === 'true') {
            const ab = document.getElementById('badge-announcements');
            const abm = document.getElementById('badge-announcements-mobile');
            if (ab) ab.style.display = 'none';
            if (abm) abm.style.display = 'none';
        }

        if (seenCerts >= certCount || localStorage.getItem('student_dismissed_certs') === 'true') {
            const cb = document.getElementById('badge-certificates');
            const cbm = document.getElementById('badge-certificates-mobile');
            if (cb) cb.style.display = 'none';
            if (cbm) cbm.style.display = 'none';
        }

        if (seenRegs >= regCount || localStorage.getItem('student_dismissed_regs') === 'true') {
            const rb = document.getElementById('badge-registrations');
            const rbm = document.getElementById('badge-registrations-mobile');
            if (rb) rb.style.display = 'none';
            if (rbm) rbm.style.display = 'none';
        }

        if (seenAtt >= attCount || localStorage.getItem('student_dismissed_attendance') === 'true') {
            const ab = document.getElementById('badge-attendance');
            const abm = document.getElementById('badge-attendance-mobile');
            if (ab) ab.style.display = 'none';
            if (abm) abm.style.display = 'none';
        }
    })();

    // Activate tab from URL param
    const urlTab = new URLSearchParams(location.search).get('tab');
    if (urlTab) {
        const map = { dashboard:'dashboard-content', registrations:'registrations-content', profile:'profile-content', certificates:'certificates-content', organizations:'organizations-content', 'online-attendance':'online-attendance-content' };
        if (map[urlTab]) switchTab(map[urlTab]);
    }

    // QR code generation (compact payload for maximum camera readability & size)
    const qrData = JSON.stringify({
        type: 'student_qr',
        student_id: <?= json_encode($studentNo) ?>,
        user_id: <?= (int)$student['UserId'] ?>
    });
    let qrGenerated = false;
    function generateQR() {
        if (qrGenerated) return true;
        const el = document.getElementById('qrCodeCanvas');
        const status = document.getElementById('qrLoadStatus');
        const saveBtn = document.getElementById('saveQrBtn');
        if (!el || typeof QRCode === 'undefined') {
            if (status) status.textContent = 'QR generator not available. Refresh the page and try again.';
            return false;
        }

        el.innerHTML = '';
        new QRCode(el, {
            text: qrData,
            width: 180,
            height: 180,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });

        qrGenerated = true;
        if (status) status.textContent = 'QR ready for attendance scanning.';
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.style.cursor = 'pointer';
            saveBtn.style.opacity = '1';
        }
        return true;
    }

    function downloadQR() {
        if (!generateQR()) return;

        const canvas = document.querySelector('#qrCodeCanvas canvas');
        if (!canvas) {
            const status = document.getElementById('qrLoadStatus');
            if (status) status.textContent = 'QR image is not ready yet. Please wait a moment and try again.';
            return;
        }

        const a = document.createElement('a');
        a.download = 'student-qr-<?= $studentNo ?>.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
    }
    // Generate QR & Load Certs on tab switch
    const origSwitchTab = switchTab;
    window.switchTab = function(id) {
        origSwitchTab(id);
        if (id === 'profile-content') {
            generateQR();
        } else if (id === 'certificates-content') {
            if (typeof loadCerts === 'function') loadCerts();
        } else if (id === 'registrations-content') {
            if (typeof loadRegistrations === 'function') loadRegistrations(1);
        }
    };
    // If already on profile or certificates tab, trigger handlers
    generateQR();
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof loadCerts === 'function') loadCerts();
    });

    // Nav item click
    document.querySelectorAll('.nav-item[data-target]').forEach(item => {
        item.addEventListener('click', e => {
            e.preventDefault();
            switchTab(item.dataset.target);
        });
    });
    </script>
    <script src="../../assets/js/student/student_api_loader.js"></script>
    <div id="eventDetailsModal" class="details-modal" aria-hidden="true">
        <div class="details-modal-content">
            <div class="details-modal-header" style="display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button type="button" onclick="closeEventDetailsModal()" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#f8fafc;padding:6px 14px;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;transition:all 0.2s ease;" onmouseover="this.style.background='rgba(255,255,255,0.15)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'">
                        <i class='bx bx-arrow-back'></i> Back
                    </button>
                    <div>
                        <p class="details-modal-kicker" style="margin:0;">Event Details</p>
                        <h2 id="detailsModalTitle" style="margin:0;">Event</h2>
                    </div>
                </div>
                <button type="button" class="details-modal-close" onclick="closeEventDetailsModal()">&times;</button>
            </div>
            <div class="details-modal-body">
                <div class="details-summary">
                    <div>
                        <span class="details-label">Organization</span>
                        <strong id="detailsModalOrg"></strong>
                    </div>
                    <div>
                        <span class="details-label">Status</span>
                        <strong id="detailsModalStatus"></strong>
                    </div>
                    <div>
                        <span class="details-label">Date</span>
                        <strong id="detailsModalDate"></strong>
                    </div>
                    <div>
                        <span class="details-label">Time</span>
                        <strong id="detailsModalTime"></strong>
                    </div>
                    <div style="grid-column:1/-1;">
                        <span class="details-label">Location</span>
                        <strong id="detailsModalLocation"></strong>
                    </div>
                </div>

                <div class="details-description">
                    <span class="details-label">About this Event</span>
                    <p id="detailsModalDescription"></p>
                </div>
            </div>
            <div class="details-modal-footer">
                <button type="button" class="details-modal-secondary" onclick="closeEventDetailsModal()" style="display:inline-flex;align-items:center;gap:6px;">
                    <i class='bx bx-arrow-back'></i> Back
                </button>
            </div>
        </div>
    </div>

    

    

<div class="viewer-overlay" id="viewerOverlay" onclick="if(event.target===this)closeViewer()">
    <div class="viewer-loading" id="viewerLoading">
        <span style="color:#64748b">Rendering certificate…</span>
    </div>
    <div class="viewer-canvas-wrap" id="viewerCanvasWrap" style="display:none;">
        <canvas id="viewerCanvas"></canvas>
    </div>
    <div class="viewer-actions">
        <button class="viewer-btn viewer-btn-dl" onclick="downloadViewer()">
            <i class='bx bx-download'></i> Download PNG
        </button>
        <button class="viewer-btn viewer-btn-close" onclick="closeViewer()">
            <ion-icon name="close-outline"></ion-icon> Close
        </button>
    </div>
</div>

<script>
const STUDENT_NAME = <?= json_encode($fullName) ?>;
const STUDENT_NO   = <?= json_encode($studentNo) ?>;
let currentCert    = null;

async function loadCerts() {
    try {
        const res  = await fetch('../../config/API/endpoints/index.php?action=get_student_certificates&_t=' + Date.now());
        const data = await res.json();
        renderCerts(data.certificates || data.data || []);
    } catch(e) {
        document.getElementById('certsContainer').innerHTML = `
            <div class="empty-state">
                <ion-icon name="warning-outline"></ion-icon>
                <h3>Could not load certificates</h3>
                <p>Please check your connection and try again.</p>
            </div>`;
    }
}

function renderCerts(certs) {
    const container = document.getElementById('certsContainer');
    if (!certs || certs.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <ion-icon name="ribbon-outline"></ion-icon>
                <h3>No certificates yet</h3>
                <p>Attend and complete NAAP events to earn certificates. They'll appear here once issued by your organization.</p>
            </div>`;
        return;
    }

    const groups = {};
    certs.forEach(c => {
        const evName = c.EventName || 'Other Events';
        if (!groups[evName]) groups[evName] = [];
        groups[evName].push(c);
    });

    container.innerHTML = '';

    for (const [eventName, eventCerts] of Object.entries(groups)) {
        const section = document.createElement('div');
        section.style.marginBottom = '40px';
        
        const header = document.createElement('h3');
        header.style.color = '#e2e8f0';
        header.style.marginBottom = '16px';
        header.style.fontSize = '18px';
        header.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
        header.style.paddingBottom = '10px';
        header.innerHTML = `<ion-icon name="calendar-outline" style="vertical-align:middle;margin-right:6px;color:#a78bfa;"></ion-icon> ${escHtml(eventName)}`;
        section.appendChild(header);

        const grid = document.createElement('div');
        grid.className = 'certs-grid';

        eventCerts.forEach(c => {
            const card = document.createElement('div');
            card.className = 'cert-card';
            const imgSrc = '../../' + (c.GeneratedImage || c.TemplateImage);
            const issueDate = new Date(c.IssuedAt).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
            const evDate    = new Date(c.EventDateTime).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });

            card.innerHTML = `
              <div class="cert-card-strip"></div>
              <div class="cert-card-preview">
                <img src="${imgSrc}" onerror="this.style.display='none'">
                <div class="cert-name-overlay"><span>${escHtml(STUDENT_NAME)}</span></div>
              </div>
              <div class="cert-card-body">
                <div class="cert-org">${escHtml(c.OrgName)}</div>
                <div class="cert-meta">
                  <div class="cert-meta-item"><ion-icon name="calendar-outline"></ion-icon> ${evDate}</div>
                  <div class="cert-meta-item"><ion-icon name="checkmark-circle-outline" style="color:#10b981;"></ion-icon> Issued ${issueDate}</div>
                </div>
                <div class="cert-code">Code: ${escHtml(c.CertCode).substring(0,20)}…</div>
                <div class="cert-actions" style="margin-top:12px;">
                  <button class="cert-btn cert-btn-view" onclick='openViewer(${JSON.stringify(c).replace(/'/g,"\\'")})'> 
                    <ion-icon name="eye-outline"></ion-icon> Preview
                  </button>
                  <button class="cert-btn cert-btn-dl" onclick='openAndDownload(${JSON.stringify(c).replace(/'/g,"\\'")})'> 
                    <i class='bx bx-download'></i> Download
                  </button>
                </div>
              </div>
            `;
            grid.appendChild(card);
        });
        section.appendChild(grid);
        container.appendChild(section);
    }
}

async function renderCertificate(cert) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        const imagePath = String(cert.TemplateImage || cert.GeneratedImage || '').replace(/^(\.\.\/)+/, '').replace(/^\//, '');
        if (!imagePath) {
            reject(new Error('Certificate image is unavailable'));
            return;
        }
        img.onload = () => {
            const canvas = document.getElementById('viewerCanvas');
            const MAX_W  = Math.min(window.innerWidth - 48, 900);
            const scale  = MAX_W / img.width;
            canvas.width  = img.width;
            canvas.height = img.height;
            canvas.style.width  = Math.round(img.width  * scale) + 'px';
            canvas.style.height = Math.round(img.height * scale) + 'px';

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);

            // A generated certificate already contains its personalized fields.
            // Fall back to rendering fields only when using the blank template.
            const fields = cert.TemplateImage ? (cert.FieldConfig || []) : [];
            if(typeof fields === 'string') {
                try { cert.FieldConfig = JSON.parse(fields); } catch(e) {}
            }
            
            (cert.FieldConfig || []).forEach(f => {
                let text = (f.value || f.label || '');
                text = text
                    .replace('{{student_name}}', STUDENT_NAME)
                    .replace('{{event_name}}',   cert.EventName)
                    .replace('{{org_name}}',     cert.OrgName)
                    .replace('{{event_date}}',   new Date(cert.EventDateTime).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'}));

                const px = Math.round((f.x || .5) * canvas.width);
                const py = Math.round((f.y || .5) * canvas.height);
                const fs = f.fontSize || 24;

                let fontStr = '';
                if (f.italic) fontStr += 'italic ';
                if (f.bold)   fontStr += 'bold ';
                fontStr += fs + 'px "' + (f.fontFamily || 'Inter') + '", sans-serif';

                ctx.font      = fontStr;
                ctx.fillStyle = f.color || '#1e293b';
                ctx.textAlign = f.align || 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(text, px, py);
            });
            resolve(canvas);
        };
        img.onerror = () => reject(new Error('Failed to load template image'));
        img.src = '../../' + imagePath;
    });
}

function openViewer(cert) {
    currentCert = cert;
    document.getElementById('viewerOverlay').classList.add('open');
    document.getElementById('viewerLoading').style.display = 'flex';
    document.getElementById('viewerCanvasWrap').style.display = 'none';
    renderCertificate(cert).then(() => {
        document.getElementById('viewerLoading').style.display = 'none';
        document.getElementById('viewerCanvasWrap').style.display = '';
    }).catch(e => {
        document.getElementById('viewerLoading').innerHTML = '<span style="color:#ef4444;">Failed to render. Template image may be unavailable.</span>';
    });
}

async function openAndDownload(cert) {
    currentCert = cert;
    try {
        await renderCertificate(cert);
        downloadViewer();
    } catch(e) {
        showModal('Failed to render certificate image.', 'error', 'Certificate Error');
    }
}

function downloadViewer() {
    const canvas = document.getElementById('viewerCanvas');
    if (!canvas || !currentCert) return;
    const a = document.createElement('a');
    a.download = 'certificate-' + (currentCert.EventName || 'NAAP').replace(/\s+/g,'-') + '.png';
    a.href = canvas.toDataURL('image/png');
    a.click();
}

function closeViewer() {
    const overlay = document.getElementById('viewerOverlay');
    if (overlay) overlay.classList.remove('open');
    currentCert = null;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeViewer();
        closeZoomedQrModal();
        closeStudentCorModal();
    }
});

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function openZoomedQrModal() {
    const modal = document.getElementById('zoomedQrModal');
    const container = document.getElementById('zoomedQrContainer');
    if (!modal || !container) return;
    container.innerHTML = '';
    
    // Render high resolution QR code inside modal
    if (typeof QRCode !== 'undefined') {
        new QRCode(container, {
            text: '<?= htmlspecialchars($studentNo) ?>',
            width: 220,
            height: 220,
            colorDark: "#003366",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    } else {
        const orig = document.querySelector('#qrCodeCanvas canvas') || document.querySelector('#qrCodeCanvas img');
        if (orig) {
            const img = document.createElement('img');
            img.src = orig.toDataURL ? orig.toDataURL() : orig.src;
            img.style.width = '220px';
            img.style.height = '220px';
            container.appendChild(img);
        }
    }
    modal.style.display = 'flex';
}

function closeZoomedQrModal() {
    const modal = document.getElementById('zoomedQrModal');
    if (modal) modal.style.display = 'none';
}

function openStudentCorModal(url, title) {
    const modal = document.getElementById('studentCorModal');
    const titleEl = document.getElementById('studentCorModalTitle');
    const bodyEl = document.getElementById('studentCorModalBody');
    const dlBtn = document.getElementById('studentCorDownloadBtn');
    if (!modal || !bodyEl) return;

    if (titleEl && title) titleEl.textContent = title;
    if (dlBtn) {
        dlBtn.href = url || '#';
        dlBtn.download = title || 'COR_Document';
    }

    bodyEl.innerHTML = '';

    if (!url) {
        bodyEl.innerHTML = '<div style="padding:40px;color:#94a3b8;font-size:14px;text-align:center;">No COR document available.</div>';
        modal.style.display = 'flex';
        return;
    }

    const cleanUrl = url.split('?')[0].toLowerCase();
    const ext = cleanUrl.split('.').pop();

    if (ext === 'pdf') {
        bodyEl.innerHTML = '<iframe src="' + url + '" title="COR Document" style="width:100%;height:560px;border:none;background:#fff;display:block;"></iframe>';
    } else {
        bodyEl.innerHTML = '<div style="padding:20px;display:flex;align-items:center;justify-content:center;max-height:560px;overflow:auto;"><img src="' + url + '" alt="COR Preview" style="max-width:100%;max-height:520px;border-radius:8px;object-fit:contain;box-shadow:0 4px 15px rgba(0,0,0,0.1);"></div>';
    }

    modal.style.display = 'flex';
}

function closeStudentCorModal() {
    const modal = document.getElementById('studentCorModal');
    if (modal) modal.style.display = 'none';
}
</script>

<!-- Zoomed QR Lightbox Modal -->
<div id="zoomedQrModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,0.85);backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#ffffff;border-radius:24px;max-width:380px;width:100%;padding:28px;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);position:relative;">
        <button type="button" onclick="closeZoomedQrModal()" style="position:absolute;top:16px;right:16px;background:#f1f5f9;border:none;width:36px;height:36px;border-radius:50%;font-size:20px;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
        <div style="background:linear-gradient(135deg,#003366,#0f172a);margin:-28px -28px 24px -28px;padding:20px;border-top-left-radius:24px;border-top-right-radius:24px;color:#fff;">
            <p style="margin:0;font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#38bdf8;font-weight:700;">PhilSCA Student Pass</p>
            <h3 style="margin:4px 0 0;font-size:18px;color:#fff;"><?= htmlspecialchars($fullName) ?></h3>
            <p style="margin:2px 0 0;font-size:12px;color:#cbd5e1;">ID: <?= htmlspecialchars($studentNo) ?></p>
        </div>
        <div id="zoomedQrContainer" style="background:#fff;padding:16px;border-radius:16px;border:2px solid #e2e8f0;display:inline-block;margin-bottom:16px;box-shadow:0 10px 25px rgba(0,0,0,0.08);">
            <!-- High-res zoomed canvas rendered here -->
        </div>
        <p style="margin:0;font-size:13px;color:#64748b;font-weight:600;">Scan QR code for automated attendance check-in</p>
        <button type="button" onclick="downloadQR()" style="margin-top:20px;width:100%;padding:12px;background:linear-gradient(135deg,#2563eb,#3b82f6);border:none;border-radius:12px;color:#fff;font-weight:700;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;">
            <i class='bx bx-download' style="font-size:18px;"></i> Download High-Res QR
        </button>
    </div>
</div>

<!-- Student COR Lightbox Modal -->
<div id="studentCorModal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,0.88);backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#ffffff;border-radius:20px;max-width:850px;width:100%;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);overflow:hidden;position:relative;">
        <div style="background:linear-gradient(135deg,#003366,#0f172a);padding:18px 24px;display:flex;align-items:center;justify-content:space-between;color:#fff;">
            <div style="display:flex;align-items:center;gap:10px;">
                <i class='bx bxs-file-pdf' style="font-size:24px;color:#38bdf8;"></i>
                <div>
                    <h3 id="studentCorModalTitle" style="margin:0;font-size:16px;color:#fff;font-weight:700;">Certificate of Registration (COR)</h3>
                    <p style="margin:2px 0 0;font-size:12px;color:#cbd5e1;"><?= htmlspecialchars($fullName) ?> (<?= htmlspecialchars($studentNo) ?>)</p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                <a id="studentCorDownloadBtn" href="#" target="_blank" download style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.25);border-radius:8px;color:#fff;font-size:12.5px;font-weight:600;text-decoration:none;">
                    <i class='bx bx-download'></i> Download
                </a>
                <button type="button" onclick="closeStudentCorModal()" style="background:rgba(255,255,255,0.15);border:none;width:32px;height:32px;border-radius:50%;font-size:18px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
            </div>
        </div>
        <div id="studentCorModalBody" style="flex:1;background:#f8fafc;min-height:480px;position:relative;overflow:auto;display:flex;align-items:center;justify-content:center;">
            <!-- Rendered iframe or image preview -->
        </div>
    </div>
</div>

<script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
<script src="../../assets/js/logout_confirm.js?v=<?= time() ?>"></script>
<script src="../../assets/js/student/profile-dashboard.js?v=<?= time() ?>"></script>
<script src="../../assets/js/student/verification_notifier.js?v=<?= time() ?>"></script>
</body>
</html>
