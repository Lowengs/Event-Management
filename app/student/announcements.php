<?php
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];

// Fetch student info
$student = $conn->query("
    SELECT u.UserId, u.first_name, u.last_name, u.middle_name,
           u.Email, u.course, u.year_level, u.section, u.student_id,
           u.phone, u.Address, u.profile_photo, u.Position,
           o.OrgName, o.OrgPicture, o.OrgId AS student_orgid
    FROM `user` u
    LEFT JOIN `organization` o ON o.OrgId = u.OrgId
    WHERE u.UserId = $student_id LIMIT 1
")->fetch_assoc();

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$fullName    = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$initials    = strtoupper(substr($student['first_name'] ?? 'S', 0, 1) . substr($student['last_name'] ?? '', 0, 1));
$course      = $student['course'] ?: 'N/A';
$email       = $student['Email'] ?: '';

$resolvePhotoUrl = function (?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
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
        if (file_exists($diskPath)) return $candidate;
    }
    return $candidates[0] ?? '';
};

$rawPhoto = $student['profile_photo'] ?? '';
$student['profile_photo'] = $resolvePhotoUrl($rawPhoto);
$hasPhoto = !empty($student['profile_photo']);

// Fetch announcements via API
ob_start();
$_GET['action'] = 'get_student_announcements';
require __DIR__ . '/../../config/API/endpoints/index.php';
$annApi = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$announcements = $annApi['data'] ?? [];

$activeTab = 'announcements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements – NAAP</title>
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/student/profile-dashboard.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/student/announcements.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <link rel="icon" href="../../assets/img/philsca.png">
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
            <div class="nav-user-dropdown">
                <?php 
                    $src = '';
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
                        <span class="nav-user-name"><?= htmlspecialchars($fullName ?: 'Student') ?></span>
                        <span class="nav-user-role">Student</span>
                    </div>
                    <ion-icon name="chevron-down-outline" class="nav-dropdown-caret"></ion-icon>
                </button>
                <div class="nav-dropdown-menu" role="menu" aria-label="Account menu">
                    <a href="profile-dashboard.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="person-circle-outline"></ion-icon><span>Profile Dashboard</span></a>
                    <a class="nav-dropdown-item danger" href="../../config/API/student_logout.php" role="menuitem"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a>
                </div>
            </div>
        </div>
    </nav>

    <div class="nav-mobile">
        <ul>
            <li><a href="../index.php"><i class='bx bx-home'></i> Home</a></li>
            <li><a href="organization.php"><i class='bx bx-group'></i> Organizations</a></li>
            <li><a href="events.php"><i class='bx bx-calendar-event'></i> Events</a></li>
            <li style="border-top:1px solid rgba(255,255,255,0.15);margin-top:8px;padding-top:8px;">
                <a href="profile-dashboard.php?tab=dashboard"><i class='bx bx-grid-alt'></i> Dashboard</a>
            </li>
            <li>
                <a href="announcements.php" class="active"><i class='bx bx-bell'></i> Announcements</a>
            </li>
            <li>
                <a href="profile-dashboard.php?tab=registrations"><i class='bx bx-calendar'></i> My Registrations</a>
            </li>
            <li>
                <a href="profile-dashboard.php?tab=profile"><i class='bx bx-user'></i> My Profile</a>
            </li>
            <li>
                <a href="profile-dashboard.php?tab=certificates"><i class='bx bx-medal'></i> Certificates</a>
            </li>
            <li>
                <a href="profile-dashboard.php?tab=online-attendance"><i class='bx bx-wifi'></i> Online Attendance</a>
            </li>
            <li style="border-top:1px solid rgba(255,255,255,0.15);margin-top:8px;padding-top:8px;">
                <a href="../../config/API/student_logout.php" style="color:#ef4444;"><i class='bx bx-log-out'></i> Logout</a>
            </li>
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
                <a href="profile-dashboard.php?tab=dashboard" class="nav-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
                    <i class='bx bx-grid-alt'></i> Dashboard
                </a>
                <a href="announcements.php" class="nav-item <?= $activeTab === 'announcements' ? 'active' : '' ?>">
                    <i class='bx bx-bell'></i> Announcements
                </a>
                <a href="profile-dashboard.php?tab=registrations" class="nav-item <?= $activeTab === 'registrations' ? 'active' : '' ?>">
                    <i class='bx bx-calendar'></i> My Registrations
                </a>
                <a href="profile-dashboard.php?tab=profile" class="nav-item <?= $activeTab === 'profile' ? 'active' : '' ?>">
                    <i class='bx bx-user'></i> My Profile
                </a>
                <a href="profile-dashboard.php?tab=certificates" class="nav-item <?= $activeTab === 'certificates' ? 'active' : '' ?>">
                    <i class='bx bx-medal'></i> Certificates
                </a>
                <a href="profile-dashboard.php?tab=online-attendance" class="nav-item <?= $activeTab === 'online-attendance' ? 'active' : '' ?>">
                    <i class='bx bx-wifi'></i> Online Attendance
                </a>
            </div>

            <div class="sidebar-footer">
                <a href="../../config/API/student_logout.php" class="logout-btn">
                    <i class='bx bx-log-out'></i> Logout
                </a>
            </div>
        </aside>

        <main class="main-content">
            <div class="ann-page">
                <div class="ann-hero">
                    <h1>Announcements</h1>
                    <p>Approved notices from OSA and your organization are shown here based on the audience selected by the sender.</p>
                    <div class="ann-count"><ion-icon name="megaphone-outline"></ion-icon> <?= count($announcements) ?> announcement<?= count($announcements) === 1 ? '' : 's' ?></div>
                </div>

                <?php if (empty($announcements)): ?>
                    <div class="ann-empty">
                        <ion-icon name="mail-open-outline"></ion-icon>
                        <h3>No announcements yet</h3>
                        <p>When an organization or OSA posts an approved notice for you, it will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="ann-grid">
                        <?php foreach ($announcements as $ann): 
                            $postedDate = strtotime($ann['DatePosted'] ?? $ann['CreatedAt'] ?? 'now');
                            $isNew = (time() - $postedDate) <= (3 * 86400); // Posted within last 3 days
                        ?>
                            <article class="ann-card" style="position:relative;">
                                <?php if ($isNew): ?>
                                    <span style="position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:0.68rem;font-weight:800;padding:3px 8px;border-radius:12px;box-shadow:0 2px 8px rgba(239,68,68,0.4);display:inline-flex;align-items:center;gap:3px;">
                                        <span style="width:6px;height:6px;background:#fff;border-radius:50%;display:inline-block;animation:pulse 1.5s infinite;"></span> NEW
                                    </span>
                                <?php endif; ?>
                                <div class="ann-card-header">
                                    <span class="ann-org"><?= htmlspecialchars($ann['OrgName'] ?? 'General') ?></span>
                                    <span class="ann-badge"><?= htmlspecialchars($ann['AudienceLabel'] ?? 'All Students') ?></span>
                                </div>
                                <h2 class="ann-title"><?= htmlspecialchars($ann['Title'] ?? 'Notice') ?></h2>
                                <?php $body = $ann['Content'] ?? $ann['AnnouncementContent'] ?? $ann['Description'] ?? $ann['Body'] ?? ''; ?>
                                <div class="ann-content"><?= nl2br(htmlspecialchars($body)) ?></div>
                                <div class="ann-meta">
                                    <span>Posted <?= date('M j, Y', strtotime($ann['DatePosted'] ?? $ann['CreatedAt'] ?? 'now')) ?></span>
                                    <?php if (!empty($ann['AttachmentPath'])): ?>
                                        <a href="<?= htmlspecialchars('../../' . ltrim($ann['AttachmentPath'], '/')) ?>" target="_blank" class="ann-file-link">
                                            <ion-icon name="document-attach-outline"></ion-icon> Attachment
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../../assets/js/index.js"></script>
    <script src="../../assets/js/logout_confirm.js" defer></script>
    <script src="../../assets/js/student/verification_notifier.js?v=<?= time() ?>"></script>
</body>
</html>