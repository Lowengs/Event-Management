<?php
session_start();
require_once '../config/db.php';
require_once '../config/img_helpers.php';

// ── Session state ─────────────────────────────────────────────────────
$isLoggedIn   = false;
$studentName  = '';
$studentEmail = '';
$role         = '';
if (!empty($_SESSION['student_id'])) {
    $isLoggedIn   = true;
    $role         = 'student';
    $studentName  = $_SESSION['student_name']  ?? 'Student';
    $studentEmail = $_SESSION['student_email'] ?? '';
} elseif (!empty($_SESSION['org_id'])) {
    $isLoggedIn = true;
    $role       = 'org';
} elseif (!empty($_SESSION['osa_id'])) {
    $isLoggedIn = true;
    $role       = 'osa';
}

// ── DB stats ──────────────────────────────────────────────────────────
function _safeCount($conn, string $sql): int {
    $r = @$conn->query($sql);
    return ($r && ($row = $r->fetch_row())) ? (int)$row[0] : 0;
}
$totalOrgs     = _safeCount($conn, "SELECT COUNT(*) FROM organization WHERE LOWER(Status)='active'");
$totalStudents = _safeCount($conn, "SELECT COUNT(*) FROM user WHERE Role='student'");
$totalEvents   = _safeCount($conn, "SELECT COUNT(*) FROM event");
$totalCerts    = _safeCount($conn, "SELECT COUNT(*) FROM certificate");

$studentPhotoSrc = '';
$studentInitials = '';
if ($isLoggedIn && $role === 'student') {
    $sessionPhoto = trim((string)($_SESSION['student_photo'] ?? ''));
    if ($sessionPhoto !== '') {
        $studentPhotoSrc = imgPathForDepth($sessionPhoto, 1, '');
    }

    $sid = (int)$_SESSION['student_id'];
    $uRow = $conn->query("SELECT first_name, last_name, profile_photo FROM `user` WHERE UserId = $sid LIMIT 1")->fetch_assoc();
    if ($uRow) {
        $parts = explode(' ', trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? '')));
        $studentInitials = strtoupper(($parts[0][0] ?? '') . (count($parts) > 1 ? $parts[count($parts) - 1][0] : ''));
        if ($studentPhotoSrc === '' && !empty($uRow['profile_photo'])) {
            $studentPhotoSrc = imgPathForDepth($uRow['profile_photo'], 1, '');
        }
    }
}

// ── Organizations from DB ────────────────────────────────────────────
$orgs = [];
$r = $conn->query("
    SELECT o.*,
        (SELECT COUNT(*) FROM user u WHERE u.OrgId=o.OrgId) AS member_count,
        (SELECT COUNT(*) FROM event e WHERE e.OrgId=o.OrgId) AS event_count,
        (SELECT CONCAT(first_name,' ',last_name) FROM user u2
         WHERE u2.OrgId=o.OrgId AND u2.Role='student'
         ORDER BY u2.created_at ASC LIMIT 1) AS president_name
    FROM organization o
    WHERE LOWER(o.Status)='active'
    ORDER BY o.OrgName ASC
");
if ($r) while ($row = $r->fetch_assoc()) $orgs[] = $row;

// ── Events from DB (all non-cancelled) ───────────────────────────
$events = [];
$r2 = $conn->query("
    SELECT e.*, o.OrgName,
        (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS registered_count
    FROM event e
    LEFT JOIN organization o ON e.OrgId = o.OrgId
    WHERE e.EventStatus != 'Cancelled'
    ORDER BY e.EventDateTime DESC
");
if ($r2) while ($row = $r2->fetch_assoc()) $events[] = $row;

// helper: normalize any stored DB image path and make it relative to app/index.php (depth=1)
function imgUrl(string $p): string { return imgPathForDepth($p, 1, '../assets/img/philsca.png'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP Student Organization Portal</title>
    <meta name="description" content="Connect with program-based student organizations, discover upcoming events, and become part of the NAAP aviation community.">

    <link rel="stylesheet" href="../assets/css/index.css?<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="../assets/img/philsca.png">

    
</head>
<body>

    <div class="mobile-header">
        <div class="mobile-header-logo">
            <img src="../assets/img/philsca.png" alt="Logo">
        </div>
        <div class="mobile-header-title">NAAP Student Organization</div>
    </div>

    <nav>
        <div class="nav-left">
            <img src="../assets/img/naap logo.png" alt="NAAP Logo">
            <div class="nav-links">
                <a href="" class="active">Home</a>
                <a href="student/organization.php">Organizations</a>
                <a href="student/events.php">Events</a>
            </div>
        </div>

        <div class="nav-actions">
            <?php if ($isLoggedIn && $role === 'student'): ?>
                <div class="nav-user-dropdown">
                    <button type="button" class="nav-profile nav-profile-trigger" aria-label="Open account menu">
                        <div class="nav-avatar" style="box-shadow:0 0 0 3px rgba(59,130,246,.5);">
                            <?php if (!empty($studentPhotoSrc)): ?>
                                <img src="<?= htmlspecialchars($studentPhotoSrc) ?>" alt="Student Avatar" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <span style="display:none;"><?= htmlspecialchars($studentInitials ?: 'S') ?></span>
                            <?php else: ?>
                                <?= htmlspecialchars($studentInitials ?: 'S') ?>
                            <?php endif; ?>
                        </div>
                        <div class="nav-user-info">
                            <span class="nav-user-name"><?= htmlspecialchars($studentName) ?></span>
                            <span class="nav-user-role">Student</span>
                        </div>
                        <ion-icon name="chevron-down-outline" class="nav-dropdown-caret"></ion-icon>
                    </button>
                    <div class="nav-dropdown-menu" role="menu" aria-label="Account menu">
                        <a href="student/announcements.php" class="nav-dropdown-item" role="menuitem">
                            <ion-icon name="megaphone-outline"></ion-icon>
                            <span>Announcement</span>
                        </a>
                        <a href="student/profile-dashboard.php" class="nav-dropdown-item" role="menuitem">
                            <ion-icon name="person-circle-outline"></ion-icon>
                            <span>Profile Dashboard</span>
                        </a>
                        <a href="../config/API/student_logout.php" class="nav-dropdown-item danger" role="menuitem">
                            <ion-icon name="log-out-outline"></ion-icon>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            <?php elseif ($isLoggedIn && $role === 'org'): ?>
                <a class="nav-btn nav-btn-login" href="organization/dashboard_org.php">My Dashboard</a>
                <a class="nav-btn-logout" href="../config/API/org_logout.php">Logout</a>
            <?php elseif ($isLoggedIn && $role === 'osa'): ?>
                <a class="nav-btn nav-btn-login" href="osa/dashboard_final.php">OSA Dashboard</a>
                <a class="nav-btn-logout" href="../config/API/osa_logout.php">Logout</a>
            <?php else: ?>
                <a class="nav-btn nav-btn-login"    href="student/login.php">Login</a>
                <a class="nav-btn nav-btn-register" href="student/register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <button id="hamburger-btn" class="hamburger" aria-label="Open menu">
        <ion-icon name="menu-outline"></ion-icon>
    </button>

    <div class="nav-mobile">
        <ul>
            <li><a href="">Home</a></li>
                <a href="student/organization.php" class="active">Organizations</a>
                <a href="student/events.php">Events</a>
            <?php if ($isLoggedIn): ?>
                <li><a href="<?= $role === 'student' ? 'student/profile-dashboard.php' : ($role === 'org' ? 'organization/dashboard_org.php' : 'osa/dashboard_final.php') ?>">My Dashboard</a></li>
                <li><a href="../config/API/<?= $role === 'student' ? 'student' : ($role === 'org' ? 'org' : 'osa') ?>_logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="student/login.php">Login</a></li>
                <li><a href="student/register.php">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="halfborder"></div>

    <main>
        <!-- ── Hero ─────────────────────────────────────────────── -->
        <div class="hero-section">
            <div class="hero-content">
                <div class="hero-logo"><img src="../assets/img/naap logo.png" alt="NAAP logo"></div>
                <div class="hero-title">
                    <h1>Connect, Discover. <span><br>Join the Aviation <br>Community.</span></h1>
                </div>
                <div class="hero-subtitle">
                    <p>Connect with program-based student organizations, discover <br>
                    upcoming events, and become part of our aviation community.</p>
                </div>
                <div class="hero-buttons">
                    <a href="student/organization.php"><button>Explore Organizations</button></a>
                    <a href="student/events.php"><button>View Events</button></a>
                </div>
            </div>
        </div>

        <!-- ── Stats ─────────────────────────────────────────────── -->
        <div class="stats-container">
            <div class="stats-wrapper">
                <div class="stat-item">
                    <div class="stat-icon"><ion-icon name="business-outline"></ion-icon></div>
                    <div class="stat-value"><?= $totalOrgs ?>+</div>
                    <div class="stat-label">Organizations</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><ion-icon name="people-outline"></ion-icon></div>
                    <div class="stat-value"><?= number_format($totalStudents) ?></div>
                    <div class="stat-label">Registered Students</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><ion-icon name="calendar-outline"></ion-icon></div>
                    <div class="stat-value"><?= $totalEvents ?>+</div>
                    <div class="stat-label">Events Hosted</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><ion-icon name="ribbon-outline"></ion-icon></div>
                    <div class="stat-value"><?= $totalCerts ?>+</div>
                    <div class="stat-label">Certificates Issued</div>
                </div>
            </div>
        </div>

        <!-- ── Organizations ─────────────────────────────────────── -->
        <div class="organization-container">
            <h1>Explore NAAP Organizations</h1>
            <p class="org-title-desc">Be part of your course-based student organization and build connections <br>
            with fellow students on the same academic journey.</p>

            <div class="org-card-container">
                <?php if (empty($orgs)): ?>
                    <p style="grid-column:1/-1;text-align:center;color:#94a3b8;padding:60px;font-family:'Inter',sans-serif;">No organizations found.</p>
                <?php else: ?>
                <?php foreach ($orgs as $org):
                    $bannerUrl = $org['OrgBanner']  ? imgUrl($org['OrgBanner'])  : '../assets/img/philsca.png';
                    $logoUrl   = $org['OrgPicture']  ? imgUrl($org['OrgPicture']) : '../assets/img/philsca.png';
                    $members   = (int)$org['member_count'];
                    $evCount   = (int)$org['event_count'];
                    $adviser   = htmlspecialchars($org['Adviser'] ?? 'N/A');
                    $desc      = htmlspecialchars($org['Description'] ?? 'A NAAP student organization dedicated to academic and professional excellence.');
                    $status    = htmlspecialchars($org['Status'] ?? 'Active');
                ?>
                <div class="org-card">
                    <div class="org-card-header">
                        <img src="<?= $bannerUrl ?>" alt="<?= htmlspecialchars($org['OrgName']) ?> Banner">
                    </div>
                    <div class="org-card-title-group">
                        <div class="org-card-icon">
                            <img src="<?= $logoUrl ?>" alt="<?= htmlspecialchars($org['OrgName']) ?> Logo">
                        </div>
                        <div class="org-card-title-group-text">
                            <h3><?= htmlspecialchars($org['OrgName']) ?></h3>
                            <span class="badge badge--status"><?= $status ?></span>
                        </div>
                    </div>

                    <div class="org-description">
                        <p class="org-card-description"><?= mb_strimwidth($desc, 0, 130, '…') ?></p>
                    </div>

                    <div class="org-card-halfborder"></div>

                    <div class="org-card-stats">
                        <div class="members">
                            <ion-icon name="people-outline"></ion-icon>
                            <p><?= number_format($members) ?> Member<?= $members !== 1 ? 's' : '' ?></p>
                        </div>
                        <div class="org-card-event">
                            <ion-icon name="calendar-outline"></ion-icon>
                            <p><?= number_format($evCount) ?> Event<?= $evCount !== 1 ? 's' : '' ?></p>
                        </div>
                    </div>

                    <p class="president">Adviser: <?= $adviser ?></p>

                    <button class="org-card-button" style="text-decoration:none;" onclick="indexViewOrg(this)"
                        data-name="<?= htmlspecialchars($org['OrgName']) ?>"
                        data-status="<?= $status ?>"
                        data-adviser="<?= $adviser ?>"
                        data-members="<?= $members ?>"
                        data-events="<?= $evCount ?>"
                        data-desc="<?= $desc ?>"
                        data-logo="<?= $logoUrl ?>"
                        data-banner="<?= $bannerUrl ?>">View Details</button>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Events ────────────────────────────────────────────── -->
        <div class="event-container">
            <h1>Explore Upcoming Events</h1>
            <p class="org-title-desc">Stay updated with the latest events from your student organizations.</p>

            <div class="event-card-container" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;width:min(1340px,100%);margin:28px auto 0;padding:0 24px 48px;">
                <?php if (empty($events)): ?>
                    <div class="no-events-msg">
                        <ion-icon name="calendar-outline"></ion-icon>
                        No events scheduled yet. Check back soon!
                    </div>
                <?php else: ?>
                <?php foreach ($events as $ev):
                    $dt        = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : null;
                    $monthStr  = $dt ? strtoupper($dt->format('M')) : '—';
                    $dayStr    = $dt ? $dt->format('j') : '—';
                    $timeStr   = $dt ? $dt->format('g:i A') : 'TBA';
                    $rawStatus = strtolower($ev['EventStatus'] ?? 'scheduled');
                    $cap       = $ev['EventCapacity'] ? (int)$ev['EventCapacity'] : null;
                    $regClass  = $rawStatus === 'ongoing' ? 'limited' : 'open';
                    $regLabel  = $rawStatus === 'ongoing' ? 'Ongoing' : ($rawStatus === 'completed' ? 'Completed' : 'Scheduled');
                    $place     = htmlspecialchars($ev['EventPlace'] ?: ($ev['EventLocation'] ?: 'TBA'));
                    $evDesc    = htmlspecialchars($ev['EventDescription'] ?? 'Join us for this exciting event.');
                    $poster    = $ev['EventPicture'] ? imgUrl($ev['EventPicture']) : '../assets/img/registrar.jpg';
                ?>
                <div class="event-card">
                    <div class="event-card-badge date-badge"><?= $monthStr ?><br><?= $dayStr ?></div>
                    <div class="event-card-badge status-badge <?= $regClass ?>"><?= $regLabel ?></div>
                    <img src="<?= $poster ?>" alt="<?= htmlspecialchars($ev['EventName']) ?>">
                    <div class="event-card-overlay">
                        <p class="event-org"><?= htmlspecialchars($ev['OrgName'] ?? 'NAAP') ?></p>
                    </div>
                    <div class="event-card-content">
                        <h3><?= htmlspecialchars($ev['EventName']) ?></h3>
                        <p class="event-desc"><?= mb_strimwidth($evDesc, 0, 120, '…') ?></p>
                        <?php if ($cap): ?>
                        <div class="event-reg-status">
                            <div class="reg-bar"><div class="reg-fill" style="width:60%;"></div></div>
                            <span class="reg-text"><?= number_format($cap) ?> slots</span>
                        </div>
                        <?php endif; ?>
                        <div class="event-meta">
                            <div><ion-icon name="time-outline"></ion-icon> <?= $timeStr ?></div>
                            <div><ion-icon name="location-outline"></ion-icon> <?= $place ?></div>
                            <?php if ($ev['EventSpeaker']): ?>
                            <div><ion-icon name="mic-outline"></ion-icon> <?= htmlspecialchars($ev['EventSpeaker']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!$isLoggedIn): ?>
                        <a href="student/login.php">
                            <button class="event-register-btn">
                                <ion-icon name="person-add-outline"></ion-icon> Login to Pre-Register
                            </button>
                        </a>
                        <?php elseif ($role === 'student'): ?>
                        <a href="student/events.php">
                            <button class="event-register-btn">
                                <ion-icon name="eye-outline"></ion-icon> View Event
                            </button>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer class="site-footer" id="footer">
        <div class="footer-content">
            <div class="footer-card footer-card-brand">
                <div class="footer-logo">
                    <span class="footer-logo-badge"><img src="../assets/img/osa logo.jpg" alt="OSA Logo"></span>
                    <h3>NAAP Student Hub</h3>
                </div>
                <p>Centralized hub for student organizations. Connect with
                program-based communities and discover upcoming events.</p>
            </div>
            <div class="footer-card footer-card-contact">
                <h3>Contact OSA Office</h3>
                <ul>
                    <li><ion-icon name="location-outline"></ion-icon><span>Ground Floor, Building A, Piccio Garden, Villamor, Pasay City, Philippines, 1309</span></li>
                    <li><ion-icon name="mail-outline"></ion-icon><span>osa@naap.edu.ph</span></li>
                    <li><ion-icon name="call-outline"></ion-icon><span>0962 342 7991</span></li>
                </ul>
            </div>
            <div class="footer-card footer-card-social">
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><ion-icon name="logo-facebook"></ion-icon></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Org Detail Modal (index.php) -->
    <div id="indexOrgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)document.getElementById('indexOrgModal').style.display='none'">
      <div style="background:#fff;border-radius:18px;width:92%;max-width:540px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.3);max-height:90vh;display:flex;flex-direction:column;">
        <div id="indexOrgModalHdr" style="background:linear-gradient(135deg,#003366,#0a5eb0);padding:1.5rem;display:flex;align-items:center;gap:1rem;position:relative;min-height:110px;background-size:cover;background-position:center;">
          <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,30,80,.75),rgba(10,94,176,.65));border-radius:18px 18px 0 0;"></div>
          <img id="indexOmLogo" src="" alt="Logo" style="width:68px;height:68px;border-radius:50%;border:3px solid #fff;object-fit:cover;flex-shrink:0;position:relative;z-index:1;">
          <div style="position:relative;z-index:1;flex:1;">
            <h3 id="indexOmName" style="color:#fff;margin:0;font-size:1.2rem;font-weight:800;"></h3>
            <p  id="indexOmStatus" style="color:rgba(255,255,255,.75);margin:.2rem 0 0;font-size:.8rem;"></p>
          </div>
          <button onclick="document.getElementById('indexOrgModal').style.display='none'" style="position:relative;z-index:1;background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
          <div style="padding:.85rem;text-align:center;border-right:1px solid #e2e8f0;"><p id="indexOmMembers" style="margin:0;font-size:1.3rem;font-weight:800;color:#003366;"></p><p style="margin:0;font-size:.7rem;color:#64748b;">Members</p></div>
          <div style="padding:.85rem;text-align:center;border-right:1px solid #e2e8f0;"><p id="indexOmEvents"  style="margin:0;font-size:1.3rem;font-weight:800;color:#003366;"></p><p style="margin:0;font-size:.7rem;color:#64748b;">Events</p></div>
          <div style="padding:.85rem;text-align:center;"><p id="indexOmAdviserSm" style="margin:0;font-size:.8rem;font-weight:700;color:#003366;"></p><p style="margin:0;font-size:.7rem;color:#64748b;">Adviser</p></div>
        </div>
        <div style="padding:1.25rem 1.5rem;overflow-y:auto;">
          <div style="background:#f8fafc;border-radius:10px;padding:.75rem;margin-bottom:.75rem;"><p style="margin:0;font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">About</p><p id="indexOmDesc" style="margin:.3rem 0 0;font-size:.85rem;color:#334155;line-height:1.6;"></p></div>
        </div>
        <div style="border-top:1px solid #f1f5f9;padding:.85rem 1.5rem;display:flex;justify-content:flex-end;gap:.5rem;background:#fff;">
          <a href="student/events.php" style="text-decoration:none;"><button style="padding:.5rem 1.4rem;background:#0ea5e9;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer;">View Events</button></a>
          <button onclick="document.getElementById('indexOrgModal').style.display='none'" style="padding:.5rem 1.4rem;background:#003366;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer;">Close</button>
        </div>
      </div>
    </div>

    

    <script type="module" src="../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../assets/js/index.js"></script>
</body>
</html>
