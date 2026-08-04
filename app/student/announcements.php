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
    
  <link rel="stylesheet" href="../../assets/css/student/announcements.css?<?= time() ?>" />
</head>
<body>
    <nav>
        <div class="nav-left">
            <img src="../../assets/img/naap logo.png" alt="NAAP Logo">
            <div class="nav-links">
                <a href="../index.php">Home</a>
                <a href="organization.php">Organizations</a>
                <a href="events.php">Events</a>
            </div>
        </div>

        <div class="nav-actions">
            <div class="nav-user-dropdown">
                <button type="button" class="nav-profile nav-profile-trigger" aria-label="Open account menu">
                    <div class="nav-avatar" style="width:40px;height:40px;border-radius:50%;overflow:hidden;box-shadow:0 0 0 2.5px rgba(59,130,246,.6);display:flex;align-items:center;justify-content:center;background:#1e293b;flex-shrink:0;">
                        <?php if (!empty($photoSrc)): ?>
                            <img src="<?= htmlspecialchars($photoSrc) ?>" alt="PFP" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';">
                            <span style="display:none;align-items:center;justify-content:center;width:100%;height:100%;font-weight:700;color:#fff;font-size:13px;"><?= htmlspecialchars($studentInitials ?: 'S') ?></span>
                        <?php else: ?>
                            <span style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;font-weight:700;color:#fff;font-size:13px;"><?= htmlspecialchars($studentInitials ?: 'S') ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="nav-user-info">
                        <span class="nav-user-name"><?= htmlspecialchars($studentName) ?></span>
                        <span class="nav-user-role">Student</span>
                    </div>
                    <ion-icon name="chevron-down-outline" class="nav-dropdown-caret"></ion-icon>
                </button>
                <div class="nav-dropdown-menu" role="menu" aria-label="Account menu">
                    <a href="announcements.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="megaphone-outline"></ion-icon><span>Announcement</span><span style="width:8px;height:8px;background:#ef4444;border-radius:50%;margin-left:auto;"></span></a>
                    <a href="profile-dashboard.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="person-circle-outline"></ion-icon><span>Profile Dashboard</span></a>
                    <a class="nav-dropdown-item danger" href="../../config/API/endpoints/index.php?action=student_logout" role="menuitem"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a>
                </div>
            </div>
        </div>
    </nav>

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
</body>
</html>