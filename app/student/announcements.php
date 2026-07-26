<?php
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$studentId = (int)$_SESSION['student_id'];
$studentRow = $conn->query("SELECT OrgId, first_name, last_name FROM `user` WHERE UserId = $studentId LIMIT 1")->fetch_assoc();
$studentOrgId = (int)($studentRow['OrgId'] ?? 0);
$studentName = trim(($studentRow['first_name'] ?? '') . ' ' . ($studentRow['last_name'] ?? '')) ?: 'Student';
$studentInitials = strtoupper(substr((string)($studentRow['first_name'] ?? 'S'), 0, 1) . substr((string)($studentRow['last_name'] ?? ''), 0, 1));

$announcements = [];
$sql = "SELECT a.*, COALESCE(o.OrgName, 'NAAP OSA') AS OrgName
        FROM announcement a
        LEFT JOIN organization o ON a.OrgId = o.OrgId
        WHERE a.Status = 'approved'
          AND (
                        a.Audience IN ('all', 'students')
            OR (a.Audience = 'by_org' AND a.OrgId = $studentOrgId)
          )
        ORDER BY a.DatePosted DESC, a.CreatedAt DESC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $audienceCode = strtolower(trim($row['Audience'] ?? ''));
        if ($audienceCode === 'by_org') {
            $row['AudienceLabel'] = 'By Organization';
        } elseif ($audienceCode === 'all_org') {
            $row['AudienceLabel'] = 'All Organizations';
        } elseif ($audienceCode === 'students') {
            $row['AudienceLabel'] = 'Students';
        } elseif ($audienceCode === 'all') {
            $row['AudienceLabel'] = 'All';
        } else {
            $row['AudienceLabel'] = $row['Audience'] ?? 'All Students';
        }
        $announcements[] = $row;
    }
}
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
                    <div class="nav-avatar" style="box-shadow:0 0 0 3px rgba(59,130,246,.5);">
                        <?= htmlspecialchars($studentInitials ?: 'S') ?>
                    </div>
                    <div class="nav-user-info">
                        <span class="nav-user-name"><?= htmlspecialchars($studentName) ?></span>
                        <span class="nav-user-role">Student</span>
                    </div>
                    <ion-icon name="chevron-down-outline" class="nav-dropdown-caret"></ion-icon>
                </button>
                <div class="nav-dropdown-menu" role="menu" aria-label="Account menu">
                    <a href="announcements.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="megaphone-outline"></ion-icon><span>Announcement</span></a>
                    <a href="profile-dashboard.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="person-circle-outline"></ion-icon><span>Profile Dashboard</span></a>
                    <a class="nav-dropdown-item danger" href="../../config/API/student_logout.php" role="menuitem"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a>
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
            <div class="empty-state">
                <h2>No announcements yet</h2>
                <p>Check back later for new updates from OSA and your organization.</p>
            </div>
        <?php else: ?>
            <div class="ann-grid">
                <?php foreach ($announcements as $ann):
                    $datePosted = !empty($ann['DatePosted']) ? date('M d, Y', strtotime($ann['DatePosted'])) : 'N/A';
                    $body = trim((string)($ann['Body'] ?? ''));
                    $excerpt = mb_strimwidth($body, 0, 220, '…');
                ?>
                <article class="ann-card">
                    <div class="ann-card-head">
                        <div>
                            <h2 class="ann-title"><?= htmlspecialchars($ann['Title'] ?? 'Announcement') ?></h2>
                            <div class="ann-meta"><?= htmlspecialchars($ann['OrgName'] ?? 'NAAP OSA') ?> · <?= htmlspecialchars($datePosted) ?></div>
                        </div>
                    </div>
                    <div class="ann-body"><?= htmlspecialchars($excerpt ?: 'No content provided.') ?></div>
                    <div class="ann-tags">
                        <span class="ann-tag"><ion-icon name="people-outline"></ion-icon> <?= htmlspecialchars($ann['AudienceLabel'] ?? 'All Students') ?></span>
                        <span class="ann-tag muted"><?= htmlspecialchars($ann['Category'] ?? 'General Notice') ?></span>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>