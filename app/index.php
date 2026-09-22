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
    
    if (!empty($_SESSION['student_photo']) && strpos($_SESSION['student_photo'], 'assets/uploads/profile_photos/') !== false) {
        $cleanP = ltrim(str_replace(['../', '../../'], '', $_SESSION['student_photo']), '/');
        $dPath = __DIR__ . '/../' . $cleanP;
        if (file_exists($dPath) && !is_dir($dPath) && filesize($dPath) > 0) {
            $studentPhotoSrc = '../' . $cleanP;
        }
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="../assets/img/philsca.png">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<script src="../../assets/js/security.js"></script>
</head>
<body>

    <div class="mobile-header">
        <button id="hamburger-btn" class="hamburger" aria-label="Open menu">
            <ion-icon name="menu-outline"></ion-icon>
        </button>
        <div class="mobile-header-logo">
            <img src="../assets/img/philsca.png" alt="Logo">
        </div>
        <div class="mobile-header-title">NAAP Student Organization</div>
    </div>

    <nav>
        <div class="nav-left">
            <img src="../assets/img/naap logo.png" alt="NAAP Logo">
            <div class="nav-links">
                <?php $currPage = basename($_SERVER['SCRIPT_NAME'] ?? ''); ?>
                <a href="index.php" class="<?= $currPage === 'index.php' ? 'active' : '' ?>">Home</a>
                <a href="student/organization.php" class="<?= $currPage === 'organization.php' ? 'active' : '' ?>">Organizations</a>
                <a href="student/events.php" class="<?= $currPage === 'events.php' ? 'active' : '' ?>">Events</a>
            </div>
        </div>

        <div class="nav-actions">
            <?php if ($isLoggedIn && $role === 'student'): ?>
                <div class="nav-user-dropdown">
                    <button type="button" class="nav-profile nav-profile-trigger" aria-label="Open account menu">
                        <div class="nav-avatar">
                            <span class="nav-avatar-initials"><?= htmlspecialchars($studentInitials ?: 'S') ?></span>
                            <?php if (!empty($studentPhotoSrc)): ?>
                                <img src="<?= htmlspecialchars($studentPhotoSrc) ?>" alt="Avatar" onerror="this.remove();">
                            <?php endif; ?>
                        </div>
                        <div class="nav-user-info">
                            <span class="nav-user-name"><?= htmlspecialchars($studentName) ?></span>
                            <span class="nav-user-role">Student</span>
                        </div>
                        <ion-icon name="chevron-down-outline" class="nav-dropdown-caret"></ion-icon>
                    </button>
                    <div class="nav-dropdown-menu" role="menu" aria-label="Account menu">
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

    <div class="nav-mobile">
        <ul>
            <li><a href="index.php" class="active"><i class='bx bx-home'></i> Home</a></li>
            <li><a href="student/organization.php"><i class='bx bx-group'></i> Organizations</a></li>
            <li><a href="student/events.php"><i class='bx bx-calendar-event'></i> Events</a></li>
            <?php if ($isLoggedIn): ?>
                <?php if ($role === 'student'): ?>
                    <li style="border-top:1px solid rgba(255,255,255,0.15);margin-top:8px;padding-top:8px;">
                        <a href="student/profile-dashboard.php?tab=dashboard"><i class='bx bx-grid-alt'></i> Dashboard</a>
                    </li>
                    <li><a href="student/announcements.php"><i class='bx bx-bell'></i> Announcements</a></li>
                    <li><a href="student/profile-dashboard.php?tab=registrations"><i class='bx bx-calendar'></i> My Registrations</a></li>
                    <li><a href="student/profile-dashboard.php?tab=profile"><i class='bx bx-user'></i> My Profile</a></li>
                    <li><a href="student/profile-dashboard.php?tab=certificates"><i class='bx bx-medal'></i> Certificates</a></li>
                    <li><a href="student/profile-dashboard.php?tab=online-attendance"><i class='bx bx-wifi'></i> Online Attendance</a></li>
                <?php else: ?>
                    <li style="border-top:1px solid rgba(255,255,255,0.15);margin-top:8px;padding-top:8px;">
                        <a href="<?= $role === 'org' ? 'organization/dashboard_org.php' : 'osa/dashboard_final.php' ?>"><i class='bx bx-grid-alt'></i> My Dashboard</a>
                    </li>
                <?php endif; ?>
                <li style="border-top:1px solid rgba(255,255,255,0.15);margin-top:8px;padding-top:8px;">
                    <a href="../config/API/endpoints/index.php?action=<?= $role === 'student' ? 'student' : ($role === 'org' ? 'org' : 'osa') ?>_logout" style="color:#ef4444;"><i class='bx bx-log-out'></i> Logout</a>
                </li>
            <?php else: ?>
                <li><a href="student/login.php"><i class='bx bx-log-in'></i> Login</a></li>
                <li><a href="student/register.php"><i class='bx bx-user-plus'></i> Register</a></li>
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
            <h1>Explore NAAP Events</h1>
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
                    $now       = new DateTime();
                    $monthStr  = $dt ? strtoupper($dt->format('M')) : '—';
                    $dayStr    = $dt ? $dt->format('j') : '—';
                    $timeStr   = $dt ? $dt->format('g:i A') : 'TBA';
                    $dateFull  = $dt ? $dt->format('F j, Y g:i A') : 'TBA';
                    $rawStatus = strtolower(trim($ev['EventStatus'] ?? ''));
                    $cap       = $ev['EventCapacity'] ? (int)$ev['EventCapacity'] : null;
                    $place     = htmlspecialchars($ev['EventPlace'] ?: ($ev['EventLocation'] ?: 'TBA'));
                    $speaker   = htmlspecialchars($ev['EventSpeaker'] ?? 'N/A');
                    $evDesc    = htmlspecialchars($ev['EventDescription'] ?? 'Join us for this exciting event.');
                    $poster    = $ev['EventPicture'] ? imgUrl($ev['EventPicture']) : '../assets/img/registrar.jpg';
                    $orgName   = htmlspecialchars($ev['OrgName'] ?? 'NAAP');

                    $isPast = $dt && ($dt < $now);

                    if ($rawStatus === 'completed') {
                        $regLabel = 'Completed';
                        $regClass = 'completed';
                        $isJoinable = false;
                    } elseif ($rawStatus === 'ongoing') {
                        $regLabel = 'Ongoing';
                        $regClass = 'ongoing';
                        $isJoinable = true;
                    } elseif ($rawStatus === 'delayed') {
                        $regLabel = 'Delayed';
                        $regClass = 'delayed';
                        $isJoinable = false;
                    } elseif ($rawStatus === 'cancelled') {
                        $regLabel = 'Cancelled';
                        $regClass = 'cancelled';
                        $isJoinable = false;
                    } else {
                        if ($isPast) {
                            $regLabel = 'Ended';
                            $regClass = 'completed';
                            $isJoinable = false;
                        } else {
                            $regLabel = 'Scheduled';
                            $regClass = 'open';
                            $isJoinable = true;
                        }
                    }
                    $regCount    = (int)($ev['reg_count'] ?? 0);
                    $fillPercent = ($cap && $cap > 0) ? min(100, max(0, round(($regCount / $cap) * 100))) : 0;
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
                            <div class="reg-bar"><div class="reg-fill" style="width:<?= $fillPercent ?>%;"></div></div>
                            <span class="reg-text"><?= $regCount > 0 ? ($regCount . ' / ' . number_format($cap)) : number_format($cap) ?> slots</span>
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
                            <?php if (!$isJoinable): ?>
                                <button type="button" class="event-register-btn" disabled style="width:100%;background:#475569;color:#cbd5e1;cursor:not-allowed;opacity:0.8;box-shadow:none;">
                                    <ion-icon name="lock-closed-outline"></ion-icon> <?= ($regLabel === 'Completed' || $regLabel === 'Ended') ? 'Registration Closed (Ended)' : 'Event ' . $regLabel ?>
                                </button>
                            <?php elseif (!$isLoggedIn): ?>
                                <a href="student/login.php" style="text-decoration:none;display:block;">
                                    <button type="button" class="event-register-btn" style="width:100%;">
                                        <ion-icon name="person-add-outline"></ion-icon> Login to Pre-Register
                                    </button>
                                </a>
                            <?php elseif ($role === 'student'): ?>
                                <a href="student/events.php" style="text-decoration:none;display:block;">
                                    <button type="button" class="event-register-btn" style="width:100%;">
                                        <ion-icon name="calendar-outline"></ion-icon> View &amp; Pre-Register
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
                <p>Centralized hub for student organizations. Connect with program-based communities, discover upcoming events, and participate in campus life.</p>
                <div class="footer-badge-item">
                    <ion-icon name="shield-checkmark"></ion-icon>
                    <span>Office of Student Affairs Official Portal</span>
                </div>
            </div>

            <div class="footer-card footer-card-links">
                <h3>About &amp; Policies</h3>
                <ul class="footer-nav-list">
                    <li><button type="button" class="footer-nav-btn" onclick="openLegalModal('about')"><ion-icon name="information-circle-outline"></ion-icon> About Us</button></li>
                    <li><button type="button" class="footer-nav-btn" onclick="openLegalModal('privacy')"><ion-icon name="shield-checkmark-outline"></ion-icon> Privacy Policy</button></li>
                    <li><button type="button" class="footer-nav-btn" onclick="openLegalModal('terms')"><ion-icon name="document-text-outline"></ion-icon> Terms of Service</button></li>
                </ul>
            </div>

            <div class="footer-card footer-card-contact">
                <h3>Contact OSA Office</h3>
                <ul>
                    <li><ion-icon name="location-outline"></ion-icon><span>Ground Floor, Building A, Piccio Garden, Villamor, Pasay City, Philippines, 1309</span></li>
                    <li><ion-icon name="mail-outline"></ion-icon><span>naaporganization@gmail.com</span></li>
                    <li><ion-icon name="call-outline"></ion-icon><span>0962 342 7991</span></li>
                    <li><ion-icon name="logo-facebook"></ion-icon><a href="https://www.facebook.com/naaposavillamorcampus" target="_blank" rel="noopener noreferrer">/naaposavillamorcampus</a></li>
                </ul>
            </div>

            <div class="footer-card footer-card-social">
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="https://www.facebook.com/naaposavillamorcampus" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><ion-icon name="logo-facebook"></ion-icon></a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-bottom-inner">
                <p class="footer-copyright">&copy; <?= date('Y') ?> NAAP Student Organization Portal &bull; Office of Student Affairs. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <button type="button" class="footer-bottom-btn" onclick="openLegalModal('about')">About Us</button>
                    <span class="footer-bottom-dot">&bull;</span>
                    <button type="button" class="footer-bottom-btn" onclick="openLegalModal('privacy')">Privacy Policy</button>
                    <span class="footer-bottom-dot">&bull;</span>
                    <button type="button" class="footer-bottom-btn" onclick="openLegalModal('terms')">Terms of Service</button>
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

    <!-- ═══ Privacy Policy, Terms of Service & About Us Modal ═════════ -->
    <div id="legalInfoModal" class="legal-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="legalModalTitle" onclick="if(event.target===this)closeLegalModal()">
      <div class="legal-modal-box">
        <div class="legal-modal-header">
          <div class="legal-modal-top">
            <div class="legal-modal-brand">
              <div class="legal-modal-brand-icon">
                <ion-icon id="legalModalIcon" name="information-circle-outline"></ion-icon>
              </div>
              <div class="legal-modal-title-wrap">
                <h3 id="legalModalTitle" class="legal-modal-title">About NAAP Student Hub</h3>
                <p class="legal-modal-subtitle">Office of Student Affairs &bull; Villamor Campus, Pasay City</p>
              </div>
            </div>
            <button type="button" onclick="closeLegalModal()" class="legal-modal-close-btn" aria-label="Close modal">&times;</button>
          </div>
          <div class="legal-modal-tabs" role="tablist">
            <button type="button" class="legal-tab-btn active" data-tab="about" onclick="switchLegalTab('about')" role="tab" aria-selected="true">
              <ion-icon name="information-circle-outline"></ion-icon>
              <span>About Us</span>
            </button>
            <button type="button" class="legal-tab-btn" data-tab="privacy" onclick="switchLegalTab('privacy')" role="tab" aria-selected="false">
              <ion-icon name="shield-checkmark-outline"></ion-icon>
              <span>Privacy Policy</span>
            </button>
            <button type="button" class="legal-tab-btn" data-tab="terms" onclick="switchLegalTab('terms')" role="tab" aria-selected="false">
              <ion-icon name="document-text-outline"></ion-icon>
              <span>Terms of Service</span>
            </button>
          </div>
        </div>

        <div class="legal-modal-body">
          <!-- ── Tab: About Us ── -->
          <div id="pane-about" class="legal-tab-pane active">
            <div class="legal-banner-card">
              <span class="legal-banner-badge"><ion-icon name="airplane"></ion-icon> Official Student Hub</span>
              <h4 class="legal-banner-title">Empowering the NAAP Aviation Community</h4>
              <p class="legal-banner-text">The NAAP Student Organization Portal is the centralized digital ecosystem designed and administered by the Office of Student Affairs (OSA) to foster collegiate leadership, student welfare, and extracurricular excellence.</p>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="flag-outline"></ion-icon> Our Mission &amp; Purpose</h4>
              <p>Our mission is to bridge academic training with collaborative student engagement. We provide every student at NAAP with seamless access to accredited academic and co-curricular organizations, events, workshops, and recognition records.</p>
              <div class="legal-cards-grid">
                <div class="legal-card-mini">
                  <ion-icon name="school-outline" class="legal-card-mini-icon"></ion-icon>
                  <h4>Academic Growth</h4>
                  <p>Seminars, technical aviation clinics, research symposiums, and tutorials.</p>
                </div>
                <div class="legal-card-mini">
                  <ion-icon name="people-circle-outline" class="legal-card-mini-icon"></ion-icon>
                  <h4>Leadership &amp; Teamwork</h4>
                  <p>Student governance, mentorship, event organizing, and community outreach.</p>
                </div>
                <div class="legal-card-mini">
                  <ion-icon name="ribbon-outline" class="legal-card-mini-icon"></ion-icon>
                  <h4>Accredited Recognition</h4>
                  <p>Verified certificates of participation and authenticated membership records.</p>
                </div>
              </div>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="grid-outline"></ion-icon> Recognized Program Organizations</h4>
              <p>The platform hosts officially chartered organizations across aviation disciplines:</p>
              <div class="legal-orgs-table">
                <div class="legal-org-badge"><span class="code">AISERS</span><span>Aviation Institute Students' Educational Research Society</span></div>
                <div class="legal-org-badge"><span class="code">AMTSO</span><span>Aircraft Maintenance Technology Student Organization</span></div>
                <div class="legal-org-badge"><span class="code">AEROATSO</span><span>Aerospace &amp; Air Traffic Service Officers</span></div>
                <div class="legal-org-badge"><span class="code">AETSO</span><span>Aviation Electronics Technology Student Organization</span></div>
                <div class="legal-org-badge"><span class="code">ELITECH</span><span>Electronics &amp; IT Community Hub</span></div>
                <div class="legal-org-badge"><span class="code">ILAS</span><span>International Language &amp; Arts Society</span></div>
              </div>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="business-outline"></ion-icon> Office of Student Affairs (OSA)</h4>
              <p>The Office of Student Affairs oversees the governance, operations, and sanctioning of all student organizations and campus-wide extra-curricular engagements.</p>
              <div class="legal-callout-info">
                <strong>Visit Us:</strong> Ground Floor, Building A, Piccio Garden, Villamor, Pasay City, Philippines 1309<br>
                <strong>Inquiries &amp; Support:</strong> naaporganization@gmail.com &bull; 0962 342 7991<br>
                <strong>Office Hours:</strong> Monday &ndash; Friday, 8:00 AM &ndash; 5:00 PM
              </div>
            </div>
          </div>

          <!-- ── Tab: Privacy Policy ── -->
          <div id="pane-privacy" class="legal-tab-pane">
            <div class="legal-banner-card">
              <span class="legal-banner-badge"><ion-icon name="shield-checkmark"></ion-icon> RA 10173 Compliance</span>
              <h4 class="legal-banner-title">Data Privacy Agreement &amp; Policy</h4>
              <p class="legal-banner-text">In strict compliance with Republic Act No. 10173 (Data Privacy Act of 2012 of the Philippines), this policy explains how your personal data is collected, processed, and safeguarded when using the NAAP Student Organization Portal.</p>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="finger-print-outline"></ion-icon> 1. Personal Information We Collect</h4>
              <p>To deliver portal services and authenticate student activity, we collect:</p>
              <ul class="legal-item-list">
                <li><strong>Identity &amp; Academic Data:</strong> Full name, Student ID number, institutional email address, program/course, year level, section, and contact number.</li>
                <li><strong>Event &amp; Membership Records:</strong> Organization affiliations, pre-registration entries, event check-in records, attendance timestamps, and evaluation feedback.</li>
                <li><strong>Biometric Verification Data:</strong> Optional facial recognition templates and uploaded profile photos utilized exclusively for identity verification and fast-track attendance during authorized campus events.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="checkbox-outline"></ion-icon> 2. Purpose of Data Processing</h4>
              <p>All data collected is utilized solely for academic and institutional functions:</p>
              <ul class="legal-item-list">
                <li>Verifying active student enrollment and membership eligibility.</li>
                <li>Managing event capacities, attendance logging, and preventing proxy attendance.</li>
                <li>Generating authenticated digital certificates of participation and completion.</li>
                <li>Providing analytics to the Office of Student Affairs to enhance campus programs and student services.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="lock-closed-outline"></ion-icon> 3. Security &amp; Confidentiality</h4>
              <p>We enforce strict organizational, physical, and technical safeguards:</p>
              <ul class="legal-item-list">
                <li>Access is restricted strictly to authorized OSA personnel, system administrators, and designated organization advisers with appropriate role permissions.</li>
                <li>Passwords are hashed using modern cryptographic algorithms (`bcrypt`), and data in transit is protected via TLS encryption.</li>
                <li><strong>Zero Commercial Sharing:</strong> Personal data is never sold, leased, or distributed to third-party commercial entities or advertisers.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="person-circle-outline"></ion-icon> 4. Your Rights as a Data Subject</h4>
              <p>Under RA 10173, students possess the right to be informed, right to access their personal records, right to rectify inaccuracies, and the right to file grievances regarding improper data handling. For inquiries, contact the OSA Data Protection Officer at <a href="mailto:naaporganization@gmail.com" style="color:#0284c7;text-decoration:none;font-weight:600;">naaporganization@gmail.com</a>.</p>
            </div>
          </div>

          <!-- ── Tab: Terms of Service ── -->
          <div id="pane-terms" class="legal-tab-pane">
            <div class="legal-banner-card">
              <span class="legal-banner-badge"><ion-icon name="document-text"></ion-icon> Rules of Governance</span>
              <h4 class="legal-banner-title">Terms of Service &amp; Code of Conduct</h4>
              <p class="legal-banner-text">Welcome to the NAAP Student Organization Portal. By accessing, browsing, or registering an account, you agree to comply with these terms, the Student Handbook, and campus regulations.</p>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="shield-outline"></ion-icon> 1. Acceptance &amp; Account Responsibility</h4>
              <p>Every student and organization officer is responsible for:</p>
              <ul class="legal-item-list">
                <li>Providing truthful, accurate, and current student identification details upon registration.</li>
                <li>Maintaining the secrecy of login credentials and passwords. Sharing student accounts is strictly prohibited.</li>
                <li>Promptly notifying OSA or administrators if any unauthorized access or security compromise is detected.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="calendar-outline"></ion-icon> 2. Event Pre-Registration &amp; Attendance</h4>
              <p>To ensure fair access to limited seating and workshop slots:</p>
              <ul class="legal-item-list">
                <li>Pre-registration reserves your event slot. If unable to attend, cancellation should be made at least 24 hours in advance to release slots to peers.</li>
                <li>Event badges, registration QR codes, and facial check-ins are non-transferable. Attempting proxy check-ins or manipulating attendance records is a disciplinary offense subject to Student Affairs sanction.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="megaphone-outline"></ion-icon> 3. Organization Conduct &amp; Publishing</h4>
              <p>Recognized student organizations and their officers must:</p>
              <ul class="legal-item-list">
                <li>Ensure all published announcements, event posters, and documentation conform to the OSA Guidelines and collegiate standards.</li>
                <li>Avoid publishing deceptive, defamatory, discriminatory, or unauthorized promotional content.</li>
                <li>Respect member privacy and use member rosters strictly for official club affairs.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="ban-outline"></ion-icon> 4. Prohibited Activities</h4>
              <p>Users shall not:</p>
              <ul class="legal-item-list">
                <li>Attempt to gain unauthorized access to administrative or organizational dashboards.</li>
                <li>Interfere with system integrity, reverse-engineer endpoints, or submit malicious scripts.</li>
                <li>Falsify documents, certificate proofs, or organizational leadership titles.</li>
              </ul>
            </div>

            <div class="legal-section-block">
              <h4 class="legal-block-title"><ion-icon name="alert-circle-outline"></ion-icon> 5. Suspension &amp; Modifications</h4>
              <p>The Office of Student Affairs reserves the right to suspend or revoke portal privileges for users who violate institutional regulations. These terms may be updated periodically; continued usage constitutes acceptance of current guidelines.</p>
            </div>
          </div>
        </div>

        <div class="legal-modal-footer">
          <div class="legal-modal-footer-info">
            <ion-icon name="shield-checkmark"></ion-icon>
            <span>Official Portal Document &bull; Active Academic Year <?= date('Y') ?></span>
          </div>
          <button type="button" class="legal-modal-close-action" onclick="closeLegalModal()">Close</button>
        </div>
      </div>
    </div>

    <script type="module" src="../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../assets/js/index.js"></script>
    <script src="../assets/js/logout_confirm.js"></script>
    <script src="../assets/js/student/verification_notifier.js?v=<?= time() ?>"></script>

    <script>
    function openLegalModal(tab) {
        const modal = document.getElementById('legalInfoModal');
        if (!modal) return;
        switchLegalTab(tab || 'about');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLegalModal() {
        const modal = document.getElementById('legalInfoModal');
        if (!modal) return;
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    function switchLegalTab(tabName) {
        const validTabs = ['about', 'privacy', 'terms'];
        if (!validTabs.includes(tabName)) tabName = 'about';

        // Update tab buttons
        document.querySelectorAll('.legal-tab-btn').forEach(function(btn) {
            const isMatch = btn.dataset.tab === tabName;
            btn.classList.toggle('active', isMatch);
            btn.setAttribute('aria-selected', isMatch ? 'true' : 'false');
        });

        // Update tab panes
        document.querySelectorAll('.legal-tab-pane').forEach(function(pane) {
            pane.classList.toggle('active', pane.id === 'pane-' + tabName);
        });

        // Update header title and icon
        const headerTitle = document.getElementById('legalModalTitle');
        const headerIcon  = document.getElementById('legalModalIcon');
        if (headerTitle && headerIcon) {
            if (tabName === 'privacy') {
                headerTitle.textContent = 'Data Privacy Policy';
                headerIcon.setAttribute('name', 'shield-checkmark-outline');
            } else if (tabName === 'terms') {
                headerTitle.textContent = 'Terms of Service';
                headerIcon.setAttribute('name', 'document-text-outline');
            } else {
                headerTitle.textContent = 'About NAAP Student Hub';
                headerIcon.setAttribute('name', 'information-circle-outline');
            }
        }
    }

    window.openLegalModal   = openLegalModal;
    window.closeLegalModal  = closeLegalModal;
    window.switchLegalTab   = switchLegalTab;
    window.openAboutModal   = function() { openLegalModal('about'); };
    window.openPrivacyModal = function() { openLegalModal('privacy'); };
    window.openTermsModal   = function() { openLegalModal('terms'); };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLegalModal();
        }
    });
    </script>
</body>
</html>
