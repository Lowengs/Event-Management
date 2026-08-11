<?php
session_start();

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$studentId = (int)$_SESSION['student_id'];

// Fetch student profile via API
ob_start();
$_GET['action'] = 'get_student_profile'; require __DIR__ . '/../../config/API/endpoints/index.php';
$profApi = json_decode(ob_get_clean(), true) ?: [];
$studentRow = $profApi['data'] ?? [];
$studentName = trim(($studentRow['first_name'] ?? '') . ' ' . ($studentRow['last_name'] ?? ''));
if (empty($studentName)) {
    $studentName = $_SESSION['student_name'] ?? 'Student';
}
$parts = explode(' ', trim($studentName));
$studentInitials = strtoupper(($parts[0][0] ?? 'S') . (count($parts) > 1 ? $parts[count($parts) - 1][0] : ''));
$photoSrc = !empty($studentRow['profile_photo']) ? ((strpos($studentRow['profile_photo'], 'http') === 0 || strpos($studentRow['profile_photo'], '../../') === 0) ? $studentRow['profile_photo'] : '../../' . ltrim($studentRow['profile_photo'], '/')) : '';

// Fetch announcements via API
ob_start();
$_GET['action'] = 'get_student_announcements'; require __DIR__ . '/../../config/API/endpoints/index.php';
$annApi = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$announcements = $annApi['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP Student Portal - Announcements</title>
    <link rel="stylesheet" href="../../assets/css/index.css?<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="../../assets/img/philsca.png">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/student/announcements.css?<?= time() ?>" />
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
                    $navPhoto = '';
                    $rawPhoto = $studentRow['profile_photo'] ?? '';
                    if (!empty($rawPhoto) && strpos($rawPhoto, 'assets/uploads/profile_photos/') !== false) {
                        $cleanPath = ltrim(str_replace(['../../', '../'], '', $rawPhoto), '/');
                        $diskPath = __DIR__ . '/../../' . $cleanPath;
                        if (file_exists($diskPath) && !is_dir($diskPath) && filesize($diskPath) > 0) {
                            $navPhoto = '../../' . $cleanPath;
                        }
                    }
                ?>
                <button type="button" class="nav-profile nav-profile-trigger" aria-label="Open account menu">
                    <div class="nav-avatar">
                        <?php if ($navPhoto !== ''): ?>
                            <img src="<?= htmlspecialchars($navPhoto) ?>" alt="Avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <span class="nav-avatar-initials" style="display:none;"><?= htmlspecialchars($studentInitials ?: 'S') ?></span>
                        <?php else: ?>
                            <span class="nav-avatar-initials"><?= htmlspecialchars($studentInitials ?: 'S') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="nav-user-info">
                        <span class="nav-user-name"><?= htmlspecialchars($studentName) ?></span>
                        <span class="nav-user-role">Student</span>
                    </div>
                    <ion-icon name="chevron-down-outline" class="nav-dropdown-caret"></ion-icon>
                </button>
                <div class="nav-dropdown-menu" role="menu" aria-label="Account menu">
                    <a href="profile-dashboard.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="person-circle-outline"></ion-icon><span>Profile Dashboard</span></a>
                    <a class="nav-dropdown-item danger" href="../../config/API/endpoints/index.php?action=student_logout" role="menuitem"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a>
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
                <a href="../../config/API/endpoints/index.php?action=student_logout" style="color:#ef4444;"><i class='bx bx-log-out'></i> Logout</a>
            </li>
        </ul>
    </div>

    <div class="ann-page">
        <div class="ann-nav-spacer"></div>
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
                            <span class="ann-org"><?= htmlspecialchars($ann['OrgName']) ?></span>
                            <span class="ann-badge"><?= htmlspecialchars($ann['AudienceLabel']) ?></span>
                        </div>
                        <h2 class="ann-title"><?= htmlspecialchars($ann['Title']) ?></h2>
                        <?php $body = $ann['Content'] ?? $ann['AnnouncementContent'] ?? $ann['Description'] ?? $ann['Body'] ?? ''; ?>
                        <div class="ann-content"><?= nl2br(htmlspecialchars($body)) ?></div>
                        <div class="ann-meta">
                            <span>Posted <?= date('M j, Y', strtotime($ann['DatePosted'] ?? $ann['CreatedAt'])) ?></span>
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

    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../../assets/js/index.js"></script>
    <script src="../../assets/js/logout_confirm.js" defer></script>
    <script>
    (function () {
        let showingVerification = false;
        const endpoint = '../../config/API/endpoints/index.php?action=get_verification_notice';
        function showVerification(notice) {
            if (showingVerification || !notice) return;
            showingVerification = true;
            const antiSpoof = notice.check_type === 'antispoof';
            const label = antiSpoof ? 'Anti-spoofing challenge required' : 'Presence check required';
            const modal = document.createElement('div');
            modal.setAttribute('role', 'dialog'); modal.setAttribute('aria-modal', 'true');
            modal.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(15,23,42,.82);backdrop-filter:blur(5px);display:flex;align-items:center;justify-content:center;padding:20px';
            modal.innerHTML = `<div style="max-width:430px;width:100%;box-sizing:border-box;background:#fff;border-radius:20px;padding:28px;text-align:center;box-shadow:0 25px 70px rgba(0,0,0,.35)"><div style="font-size:42px;margin-bottom:10px">${antiSpoof ? '📷' : '⏱️'}</div><h2 style="margin:0 0 10px;color:#0f172a;font-size:21px;font-weight:700">${label}</h2><p style="margin:0 0 22px;color:#475569;line-height:1.5">${notice.EventName} has requested a live verification. Complete it now to remain marked as present.</p><button id="startVerification" style="width:100%;border:0;border-radius:11px;padding:13px;background:#2563eb;color:#fff;font-weight:800;font-size:15px;cursor:pointer">Start verification</button></div>`;
            document.body.appendChild(modal);
            if ('Notification' in window && Notification.permission === 'granted') new Notification(label, { body: notice.EventName });
            modal.querySelector('#startVerification').addEventListener('click', () => {
                location.href = 'presence-check.php?eventId=' + encodeURIComponent(notice.EventId) + '&type=' + encodeURIComponent(notice.check_type);
            });
        }
        async function checkVerification() {
            try { const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store' }); const data = await response.json(); if (data.success) showVerification(data.notice); } catch (_) {}
        }
        checkVerification(); setInterval(checkVerification, 5000);
    })();
    </script>
</body>
</html>