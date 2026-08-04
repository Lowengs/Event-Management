<?php
session_start();
require_once '../config/db.php';
require_once '../config/img_helpers.php';

// ── Role-based Redirect for Non-Student Portals ─────────────────────
if (!empty($_SESSION['osa_id'])) {
    header('Location: osa/dashboard_final.php');
    exit;
} elseif (!empty($_SESSION['org_id'])) {
    header('Location: organization/dashboard_org.php');
    exit;
} elseif (!empty($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

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
    $parts        = explode(' ', trim($studentName));
    $studentInitials = strtoupper(($parts[0][0] ?? 'S') . (count($parts) > 1 ? $parts[count($parts) - 1][0] : ''));
}

// ── Fetch Data via Index Handler ─────────────────────────────────────
define('INDEX_DATA_INCLUDE', true);
require_once __DIR__ . '/../config/API/common/GET/GETindex.php';

$totalOrgs     = (int)($stats['total_orgs']     ?? 0);
$totalStudents = (int)($stats['total_students'] ?? 0);
$totalEvents   = (int)($stats['total_events']   ?? 0);
$totalCerts    = (int)($stats['total_certs']    ?? 0);

$studentPhotoSrc = '';
$studentInitials = '';
if ($isLoggedIn && $role === 'student') {
    $studentName = $_SESSION['student_name'] ?? 'Student';
    $parts = explode(' ', trim($studentName));
    $studentInitials = strtoupper(($parts[0][0] ?? '') . (count($parts) > 1 ? $parts[count($parts) - 1][0] : ''));
    
    if (!empty($_SESSION['student_photo'])) {
        $studentPhotoSrc = (strpos($_SESSION['student_photo'], 'http') === 0 || strpos($_SESSION['student_photo'], '../') === 0) ? $_SESSION['student_photo'] : '../' . ltrim($_SESSION['student_photo'], '/');
    }
}

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

    <link rel="stylesheet" href="../assets/css/index.css?v=<?= time() ?>">
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
                        <div class="nav-avatar">
                            <span class="nav-avatar-initials"><?= htmlspecialchars($studentInitials ?: 'S') ?></span>
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
                        <a href="../config/API/endpoints/index.php?action=student_logout" class="nav-dropdown-item danger" role="menuitem">
                            <ion-icon name="log-out-outline"></ion-icon>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            <?php elseif ($isLoggedIn && $role === 'org'): ?>
                <a class="nav-btn nav-btn-login" href="organization/dashboard_org.php">My Dashboard</a>
                <a class="nav-btn-logout" href="../config/API/endpoints/index.php?action=org_logout">Logout</a>
            <?php elseif ($isLoggedIn && $role === 'osa'): ?>
                <a class="nav-btn nav-btn-login" href="osa/dashboard_final.php">OSA Dashboard</a>
                <a class="nav-btn-logout" href="../config/API/endpoints/index.php?action=osa_logout">Logout</a>
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
            <li><a href="" class="active">Home</a></li>
            <li><a href="student/organization.php">Organizations</a></li>
            <li><a href="student/events.php">Events</a></li>
            <?php if ($isLoggedIn): ?>
                <li><a href="<?= $role === 'student' ? 'student/profile-dashboard.php' : ($role === 'org' ? 'organization/dashboard_org.php' : 'osa/dashboard_final.php') ?>">My Dashboard</a></li>
                <li><a href="../config/API/endpoints/index.php?action=<?= $role === 'student' ? 'student' : ($role === 'org' ? 'org' : 'osa') ?>_logout">Logout</a></li>
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
                    <div class="no-events-msg">
                        <ion-icon name="business-outline"></ion-icon>
                        <span>No organizations found.</span>
                    </div>
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

                    <button class="org-card-button" onclick="indexViewOrg(this)"
                        data-orgid="<?= (int)$org['OrgId'] ?>"
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

            <div class="event-card-container">
                <?php
                $scheduledEvents = array_values(array_filter($events, function($ev) {
                    $st = strtolower($ev['EventStatus'] ?? '');
                    return $st !== 'archived' && $st !== 'cancelled';
                }));
                ?>
                <?php if (empty($scheduledEvents)): ?>
                    <div class="no-events-msg">
                        <ion-icon name="calendar-outline"></ion-icon>
                        <span>No events scheduled yet. Check back soon!</span>
                    </div>
                <?php else: ?>
                <?php foreach ($scheduledEvents as $ev):
                    $dt        = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : null;
                    $monthStr  = $dt ? strtoupper($dt->format('M')) : '—';
                    $dayStr    = $dt ? $dt->format('j') : '—';
                    $timeStr   = $dt ? $dt->format('g:i A') : 'TBA';
                    $dateFull  = $dt ? $dt->format('F j, Y g:i A') : 'TBA';
                    $rawStatus = strtolower($ev['EventStatus'] ?? 'scheduled');
                    $cap       = $ev['EventCapacity'] ? (int)$ev['EventCapacity'] : null;
                    $regClass  = 'open';
                    $regLabel  = 'Scheduled';
                    $place     = htmlspecialchars($ev['EventPlace'] ?: ($ev['EventLocation'] ?: 'TBA'));
                    $speaker   = htmlspecialchars($ev['EventSpeaker'] ?? 'N/A');
                    $evDesc    = htmlspecialchars($ev['EventDescription'] ?? 'Join us for this exciting event.');
                    $poster    = $ev['EventPicture'] ? imgUrl($ev['EventPicture']) : '../assets/img/registrar.jpg';
                    $orgName   = htmlspecialchars($ev['OrgName'] ?? 'NAAP');
                ?>
                <div class="event-card">
                    <div class="event-card-badge date-badge"><?= $monthStr ?><br><?= $dayStr ?></div>
                    <div class="event-card-badge status-badge <?= $regClass ?>"><?= $regLabel ?></div>
                    <img src="<?= $poster ?>" alt="<?= htmlspecialchars($ev['EventName']) ?>">
                    <div class="event-card-overlay">
                        <p class="event-org"><?= $orgName ?></p>
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
                            <?php if (!empty($ev['EventSpeaker'])): ?>
                            <div><ion-icon name="mic-outline"></ion-icon> <?= $speaker ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:auto;">
                            <?php if (!$isLoggedIn): ?>
                            <a href="student/login.php" style="text-decoration:none;display:block;">
                                <button type="button" class="event-register-btn" style="width:100%;">
                                    <ion-icon name="person-add-outline"></ion-icon> Login to Pre-Register
                                </button>
                            </a>
                            <?php elseif ($role === 'student'): ?>
                            <a href="student/events.php" style="text-decoration:none;display:block;">
                                <button type="button" class="event-register-btn" style="width:100%;">
                                    <ion-icon name="calendar-outline"></ion-icon> View Events
                                </button>
                            </a>
                            <?php endif; ?>
                        </div>
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

    <!-- Org Detail Modal -->
    <div id="indexOrgModal" class="index-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)closeIndexOrgModal()">
      <div class="index-modal-box">
        <div id="indexOrgModalHdr" class="index-modal-header">
          <div class="index-modal-header-overlay"></div>
          <img id="indexOmLogo" src="" alt="Logo" class="index-modal-logo">
          <div class="index-modal-header-info">
            <h3 id="indexOmName" class="index-modal-title"></h3>
            <p id="indexOmStatus" class="index-modal-status"></p>
          </div>
          <button type="button" onclick="closeIndexOrgModal()" class="index-modal-close-btn">&times;</button>
        </div>
        <div class="index-modal-stats-grid">
          <div class="index-modal-stat-col"><p id="indexOmMembers" class="index-modal-stat-num"></p><p class="index-modal-stat-label">Members</p></div>
          <div class="index-modal-stat-col"><p id="indexOmEvents" class="index-modal-stat-num"></p><p class="index-modal-stat-label">Events</p></div>
          <div class="index-modal-stat-col"><p id="indexOmAdviserSm" class="index-modal-stat-num"></p><p class="index-modal-stat-label">Adviser</p></div>
        </div>
        <div class="index-modal-body">
          <div class="index-modal-about-card"><p class="index-modal-about-label">About</p><p id="indexOmDesc" class="index-modal-desc"></p></div>
        </div>
        <div class="index-modal-footer">
          <a id="indexOmViewEventsBtn" href="student/events.php" style="text-decoration:none;"><button class="index-modal-action-btn">View Events</button></a>
          <button type="button" onclick="closeIndexOrgModal()" class="index-modal-close-action-btn">Close</button>
        </div>
        
      </div>
    </div>

    <!-- Event Detail Modal -->
    <div id="indexEventModal" class="index-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)closeIndexEventModal()">
      <div class="index-modal-box">
        <div id="indexEmHeader" class="index-modal-header">
          <div class="index-modal-header-overlay"></div>
          <img id="indexEmPoster" src="" alt="Poster" class="index-modal-logo">
          <div class="index-modal-header-info">
            <h3 id="indexEmTitle" class="index-modal-title"></h3>
            <p id="indexEmOrg" class="index-modal-status"></p>
          </div>
          <button type="button" onclick="closeIndexEventModal()" class="index-modal-close-btn">&times;</button>
        </div>
        <div class="index-modal-stats-grid">
          <div class="index-modal-stat-col"><p id="indexEmDate" class="index-modal-stat-num" style="font-size:0.8rem;"></p><p class="index-modal-stat-label">Date & Time</p></div>
          <div class="index-modal-stat-col"><p id="indexEmLocation" class="index-modal-stat-num" style="font-size:0.8rem;"></p><p class="index-modal-stat-label">Location</p></div>
          <div class="index-modal-stat-col"><p id="indexEmSpeaker" class="index-modal-stat-num" style="font-size:0.8rem;"></p><p class="index-modal-stat-label">Speaker</p></div>
        </div>
        <div class="index-modal-body">
          <div class="index-modal-about-card">
            <p class="index-modal-about-label">Event Description</p>
            <p id="indexEmDesc" class="index-modal-desc"></p>
          </div>
        </div>
        <div class="index-modal-footer">
          <a id="indexEmActionBtn" href="student/login.php" style="text-decoration:none;"><button class="index-modal-action-btn">Login to Pre-Register</button></a>
          <button type="button" onclick="closeIndexEventModal()" class="index-modal-close-action-btn">Close</button>
        </div>
      </div>
    </div>
    
    <!-- Logout Notification Modal -->
    <div id="logoutModal" class="index-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:99999;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)closeLogoutModal()">
      <div class="index-modal-box" style="max-width:400px;text-align:center;padding:2rem 1.5rem;background:#1e293b;border:1px solid #334155;border-radius:18px;color:#fff;">
        <div style="width:60px;height:60px;background:rgba(34,197,94,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;color:#4ade80;font-size:2rem;">
          <ion-icon name="checkmark-circle-outline"></ion-icon>
        </div>
        <h3 style="margin:0 0 0.5rem;font-size:1.3rem;font-weight:700;color:#fff;">Logged Out Successfully</h3>
        <p style="margin:0 0 1.5rem;color:#94a3b8;font-size:0.9rem;line-height:1.5;">You have been safely logged out of your account.</p>
        <button type="button" onclick="closeLogoutModal()" style="width:100%;padding:0.75rem;background:#3b82f6;color:#fff;border:none;border-radius:10px;font-weight:700;font-size:0.95rem;cursor:pointer;transition:background 0.2s;">OK</button>
      </div>
    </div>

    <script type="module" src="../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../assets/js/index.js"></script>
    <script src="../assets/js/logout_confirm.js"></script>
</body>
</html>
