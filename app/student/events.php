<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/img_helpers.php';


if ($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS eventregistration (
        RegistrationId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
        Status VARCHAR(50) DEFAULT 'Registered', RegisteredAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $conn->query("CREATE TABLE IF NOT EXISTS event_pretest (
        TestId INT AUTO_INCREMENT PRIMARY KEY, EventId INT, UserId INT,
        Q1 VARCHAR(10), Q2 VARCHAR(10), Q3 VARCHAR(10), Q4 VARCHAR(10), Q5 VARCHAR(10),
        Score INT DEFAULT 0, SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
}

$isLoggedIn = !empty($_SESSION['student_id']);
$studentId  = $isLoggedIn ? (int)$_SESSION['student_id'] : 0;
$studentOrgId = null;


$registeredIds = [];
$pretestedIds  = [];
if ($isLoggedIn && $conn) {
    
    $uResult = $conn->query("SELECT first_name, last_name, profile_photo, OrgId FROM `user` WHERE UserId=$studentId");
    $u = $uResult ? $uResult->fetch_assoc() : null;
    if ($u && !empty($u['OrgId'])) {
        $studentOrgId = (int)$u['OrgId'];
    }

    $rr = $conn->query("SELECT EventId FROM eventregistration WHERE UserId=$studentId");
    if ($rr) while ($row = $rr->fetch_assoc()) $registeredIds[] = (int)$row['EventId'];
    $pp = $conn->query("SELECT EventId FROM event_pretest WHERE UserId=$studentId");
    if ($pp) while ($row = $pp->fetch_assoc()) $pretestedIds[] = (int)$row['EventId'];
}


$allEvents = [];
if ($conn) {
    if ($isLoggedIn && $studentOrgId) {
        
        $whereClause = "e.EventStatus != 'Cancelled'
                        AND e.OrgId = $studentOrgId";
    } else {
        
        $whereClause = "e.EventStatus != 'Cancelled'";
    }

    $q = "
        SELECT e.*, o.OrgName,
            (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS registered_count
        FROM event e
        LEFT JOIN organization o ON e.OrgId = o.OrgId
        WHERE $whereClause
        ORDER BY e.EventDateTime DESC
    ";
    $r = $conn->query($q);
    if ($r) while ($row = $r->fetch_assoc()) $allEvents[] = $row;
}


$eventsPerPage = 6;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalEvents = count($allEvents);
$totalPages = max(1, ceil($totalEvents / $eventsPerPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $eventsPerPage;
$events = array_slice($allEvents, $offset, $eventsPerPage);


$fullName = ''; $initials = ''; $hasPhoto = false; $student = [];
if ($isLoggedIn && isset($u) && $u) {
    $fullName  = trim($u['first_name'] . ' ' . $u['last_name']);
    $initials  = strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1));
    $hasPhoto  = !empty($u['profile_photo']);
    $photoSrc  = $hasPhoto ? imgPathForDepth($u['profile_photo'], 2, '') : '';
    $student   = $u;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events – NAAP Student Portal</title>
    <meta name="description" content="Browse and pre-register for upcoming NAAP student organization events: workshops, seminars, and networking opportunities.">
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/student/events.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="../../assets/img/philsca.png">
    
</head>
<body>

    <div class="mobile-header">
        <div class="mobile-header-logo"><img src="../../assets/img/philsca.png" alt="Logo"></div>
        <div class="mobile-header-title">NAAP Student Organization</div>
    </div>

    <nav>
        <div class="nav-left">
            <img src="../../assets/img/naap logo.png" alt="NAAP Logo">
            <div class="nav-links">
                <a href="../index.php">Home</a>
                <a href="organization.php">Organizations</a>
                <a href="events.php" class="active">Events</a>
            </div>
        </div>
        <div class="nav-actions">
            <?php 
                $is_logged = isset($isLoggedIn) ? $isLoggedIn : (isset($_SESSION['student_id']) && !empty($_SESSION['student_id']));
            ?>
            <?php if ($is_logged): ?>
                <div class="nav-user-dropdown">
                    <button type="button" class="nav-profile nav-profile-trigger" aria-label="Open account menu">
                        <div class="nav-avatar" style="box-shadow:0 0 0 3px rgba(59,130,246,.5);">
                            <?php 
                                $src = '';
                                if (isset($photoSrc) && !empty($photoSrc)) {
                                    $src = $photoSrc;
                                } elseif (isset($student['profile_photo']) && !empty($student['profile_photo'])) {
                                    $p = $student['profile_photo'];
                                    if (strpos($p, '../../') === 0) { $src = $p; }
                                    else { $src = '../../' . ltrim($p, '/'); }
                                    $disk_path = __DIR__ . '/../../' . ltrim(str_replace('../../', '', $src), '/');
                                    if (!file_exists($disk_path)) $src = '';
                                }
                            ?>
                            <?php if ($src != ''): ?>
                                <img src="<?= htmlspecialchars($src) ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <span style="display:none;"><?= isset($initials) ? htmlspecialchars($initials) : 'S' ?></span>
                            <?php else: ?>
                                <?= isset($initials) ? htmlspecialchars($initials) : (isset($student['first_name']) ? strtoupper(substr($student['first_name'],0,1)) : 'U') ?>
                            <?php endif; ?>
                        </div>
                        <div class="nav-user-info">
                            <span class="nav-user-name"><?= htmlspecialchars(isset($fullName) ? $fullName : (isset($student['first_name']) ? trim($student['first_name'] . ' ' . $student['last_name']) : 'Student')) ?></span>
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
            <?php else: ?>
                <a class="nav-btn nav-btn-login" href="login.php">Login</a>
                <a class="nav-btn nav-btn-register" href="register.php">Register</a>
            <?php endif; ?>
        </div>
    </nav>

    <button id="hamburger-btn" class="hamburger" aria-label="Open menu">
        <ion-icon name="menu-outline"></ion-icon>
    </button>
    <div class="nav-mobile">
        <ul>
            <li><a href="../index.php">Home</a></li>
            <li><a href="organization.php">Organizations</a></li>
            <li><a href="events.php" class="active">Events</a></li>
            <?php if ($isLoggedIn): ?>
                <li><a href="profile-dashboard.php">My Dashboard</a></li>
                <li><a href="../../config/API/student_logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Register</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="halfborder"></div>

    <main>
        <div class="event-container">
            <h1>Explore Upcoming Events</h1>
            <p class="org-title-desc">Discover workshops, seminars, and networking opportunities<br>designed to advance your aviation career</p>

            <div class="exploration-filters">
                <div class="filter-dropdowns">
                    <div class="filter-group filter-group-search">
                        <label><ion-icon name="search-outline"></ion-icon> Search Events</label>
                        <div class="search-input-wrapper">
                            <ion-icon name="search-outline"></ion-icon>
                            <input type="text" id="searchInput" placeholder="Search by event name or organization...">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label><ion-icon name="funnel-outline"></ion-icon> Status</label>
                        <select id="statusFilter">
                            <option value="">All Events</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                            <option value="Postponed">Postponed</option>
                            <option value="Rescheduled">Rescheduled</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><ion-icon name="swap-vertical-outline"></ion-icon> Sort By</label>
                        <select id="sortFilter">
                            <option value="date-desc">Newest First</option>
                            <option value="date-asc">Oldest First</option>
                            <option value="name-asc">Name (A-Z)</option>
                            <option value="name-desc">Name (Z-A)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="results-header">
                <span>Showing <strong id="eventCount"><?= count($events) ?></strong> of <strong><?= $totalEvents ?></strong> events | Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong></span>
            </div>

            <div class="event-card-container" id="eventGrid">
            <?php if (empty($events)): ?>
                <div style="width:100%;text-align:center;padding:3rem;color:#64748b;">
                    <h3>No upcoming events at the moment.</h3>
                    <p>Check back later or explore our organizations.</p>
                </div>
            <?php else: ?>
            <?php $eventNum = 0; foreach ($events as $index => $ev):
                    $eventNum++;
                    $dateObj   = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : new DateTime();
                    $month     = strtoupper($dateObj->format('M'));
                    $day       = $dateObj->format('d');
                    $timeRange = $dateObj->format('g:i A');
                    $dateStr   = $dateObj->format('F j, Y');
                    $status    = $ev['EventStatus'] ?: 'Scheduled';
                    $registered  = (int)$ev['registered_count'];
                    $max         = ($ev['EventCapacity'] ?? 0) > 0 ? (int)$ev['EventCapacity'] : 100;
                    $remaining   = max(0, $max - $registered);
                    $percent     = $max > 0 ? min(100, round(($registered / $max) * 100)) : 0;
                    $isLimited   = $remaining <= max(10, round($max * 0.2));
                    $isFull      = $remaining === 0;
                    $desc        = $ev['EventDescription'] ?? ($ev['EventDetails'] ?? '');
                    $place       = $ev['EventPlace'] ?? ($ev['EventLocation'] ?? 'TBA');
                    $poster      = !empty($ev['EventPicture'])
                        ? '../../' . ltrim($ev['EventPicture'], '/')
                        : null;
                    $detailUrl   = 'event_detail.php?id=' . $ev['EventId'];
                    $orgName     = htmlspecialchars($ev['OrgName'] ?? 'NAAP');
                    $isReg       = in_array((int)$ev['EventId'], $registeredIds);
                    $hasPre      = in_array((int)$ev['EventId'], $pretestedIds);

                    
                    $modalData = json_encode([
                        'id'      => (int)$ev['EventId'],
                        'name'    => $ev['EventName'],
                        'org'     => $ev['OrgName'] ?? 'NAAP',
                        'month'   => $month,
                        'day'     => $day,
                        'date'    => $dateStr,
                        'time'    => $timeRange,
                        'place'   => $place,
                        'mode'    => $ev['EventMode'] ?? 'On-site',
                        'status'  => $status,
                        'isReg'   => $isReg,
                        'hasPre'  => $hasPre,
                        'isFull'  => $isFull,
                    ]);
                ?>
                <div class="event-card"
                     data-name="<?= htmlspecialchars(strtolower($ev['EventName'])) ?>"
                     data-org="<?= strtolower($ev['OrgName'] ?? '') ?>"
                     data-status="<?= htmlspecialchars($status) ?>"
                     data-number="<?= $eventNum ?>">

                    
                    <div class="event-card-img-wrap">
                        <?php if ($poster): ?>
                            <img src="<?= htmlspecialchars($poster) ?>"
                                 alt="<?= htmlspecialchars($ev['EventName']) ?>"
                                 onerror="this.closest('.event-card-img-wrap').style.background='linear-gradient(135deg,#1e3a5f,#0f172a)';this.remove();"
                                 style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1e3a5f,#0f172a)">
                                <ion-icon name="calendar-outline" style="font-size:3rem;color:#3b82f6;opacity:.5;"></ion-icon>
                            </div>
                        <?php endif; ?>

                        
                        <div class="event-date-badge">
                            <span class="ev-month"><?= $month ?></span>
                            <span class="ev-day"><?= $day ?></span>
                        </div>

                         
                        <?php if ($isFull): ?>
                            <div class="ev-slots-badge ev-slots-full">Full</div>
                        <?php elseif ($isLimited): ?>
                            <div class="ev-slots-badge ev-slots-limited">Limited Slots</div>
                        <?php endif; ?>

                        
                        <div class="ev-org-overlay">
                            <span><?= $orgName ?></span>
                        </div>
                    </div>

                    
                    <div class="event-card-content">
                        <h3 class="ev-card-title"><?= htmlspecialchars($ev['EventName']) ?></h3>
                        <p class="ev-card-desc"><?= htmlspecialchars(mb_substr($desc, 0, 110)) ?><?= mb_strlen($desc) > 110 ? '…' : '' ?></p>

                        
                        <div class="ev-cap-bar-wrap">
                            <div class="ev-cap-bar"><div class="ev-cap-fill <?= $percent >= 80 ? 'danger' : ($percent >= 50 ? 'warn' : '') ?>" style="width:<?= $percent ?>%"></div></div>
                            <span class="ev-cap-label"><?= $registered ?>/<?= $max ?></span>
                        </div>
                        <p class="ev-spots"><?= $remaining ?> spot<?= $remaining !== 1 ? 's' : '' ?> remaining</p>

                        
                        <div class="ev-meta-row">
                            <div><ion-icon name="time-outline"></ion-icon> <?= htmlspecialchars($timeRange) ?></div>
                            <div><ion-icon name="location-outline"></ion-icon> <?= htmlspecialchars($place) ?></div>
                        </div>

                        
                        <?php if ($isFull): ?>
                            <button class="ev-prereg-btn ev-prereg-full" disabled>Event Full</button>
                        <?php elseif ($isReg): ?>
                            <button class="ev-prereg-btn ev-prereg-registered" disabled>
                                <ion-icon name="checkmark-circle-outline"></ion-icon> Registered
                            </button>
                        <?php elseif ($isLoggedIn): ?>
                            <button class="ev-prereg-btn" data-event='<?= htmlspecialchars($modalData, ENT_QUOTES) ?>' onclick="openPreregModal(this)">
                                <ion-icon name="person-add-outline"></ion-icon> Pre-Register
                            </button>
                        <?php else: ?>
                            <button class="ev-prereg-btn ev-prereg-login" onclick="location.href='login.php?redirect=<?= urlencode($detailUrl) ?>'">
                                <ion-icon name="log-in-outline"></ion-icon> Login to Pre-Register
                            </button>
                        <?php endif; ?>

                        <a href="<?= $detailUrl ?>" class="ev-detail-link">View Full Details →</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>

            
            <?php if ($totalPages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-controls">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=1" class="pagination-btn pagination-first" title="First page">
                            <ion-icon name="chevron-back-outline"></ion-icon>
                        </a>
                        <a href="?page=<?= $currentPage - 1 ?>" class="pagination-btn pagination-prev">
                            <ion-icon name="play-back-outline"></ion-icon>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn pagination-first disabled">
                            <ion-icon name="chevron-back-outline"></ion-icon>
                        </span>
                        <span class="pagination-btn pagination-prev disabled">
                            <ion-icon name="play-back-outline"></ion-icon>
                        </span>
                    <?php endif; ?>

                    <div class="pagination-numbers">
                        <?php 
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        if ($startPage > 1): ?>
                            <a href="?page=1" class="pagination-num">1</a>
                            <?php if ($startPage > 2): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                            <?php if ($p == $currentPage): ?>
                                <span class="pagination-num current"><?= $p ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $p ?>" class="pagination-num"><?= $p ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <span class="pagination-dots">...</span>
                            <?php endif; ?>
                            <a href="?page=<?= $totalPages ?>" class="pagination-num"><?= $totalPages ?></a>
                        <?php endif; ?>
                    </div>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?= $currentPage + 1 ?>" class="pagination-btn pagination-next">
                            <ion-icon name="play-forward-outline"></ion-icon>
                        </a>
                        <a href="?page=<?= $totalPages ?>" class="pagination-btn pagination-last" title="Last page">
                            <ion-icon name="chevron-forward-outline"></ion-icon>
                        </a>
                    <?php else: ?>
                        <span class="pagination-btn pagination-next disabled">
                            <ion-icon name="play-forward-outline"></ion-icon>
                        </span>
                        <span class="pagination-btn pagination-last disabled">
                            <ion-icon name="chevron-forward-outline"></ion-icon>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="pagination-info">
                    <span>Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong></span>
                    <span>•</span>
                    <span><strong><?= $totalEvents ?></strong> total events</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="site-footer" id="footer">
        <div class="footer-content">
            <div class="footer-card footer-card-brand">
                <div class="footer-logo">
                    <span class="footer-logo-badge"><img src="../../assets/img/osa logo.jpg" alt="OSA Logo"></span>
                    <h3>NAAP Student Hub</h3>
                </div>
                <p>Centralized hub for student organizations. Connect with program-based communities and discover upcoming events.</p>
            </div>
            <div class="footer-card footer-card-contact">
                <h3>Contact OSA Office</h3>
                <ul>
                    <li><ion-icon name="location-outline"></ion-icon><span>Ground Floor, Building A, Piccio Garden, Villamor, Pasay City</span></li>
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

    
    <div class="prereg-overlay" id="preregOverlay" role="dialog" aria-modal="true" aria-label="Pre-Register for Event">
        <div class="prereg-modal" id="preregModal">
            <div class="prereg-modal-header">
                <div>
                    <h2 id="modalEventName">Event Name</h2>
                    <p id="modalOrgName">by Organization</p>
                </div>
                <button class="modal-close-btn" onclick="closePreregModal()" aria-label="Close">✕</button>
            </div>
            <div class="prereg-modal-body">
                
                <div class="modal-event-info">
                    <div class="ev-date-pill">
                        <span class="m" id="modalMonth">JAN</span>
                        <span class="d" id="modalDay">01</span>
                    </div>
                    <div class="ev-info-text">
                        <strong id="modalDate">January 1, 2025</strong>
                        <span id="modalMeta">—</span>
                    </div>
                </div>

                    
                    <div class="modal-steps" style="display:none;">
                        <div class="modal-step" id="step1Indicator">
                            <ion-icon name="document-text-outline"></ion-icon>Pre-Test
                        </div>
                        <div class="modal-step" id="step2Indicator">
                            <ion-icon name="person-add-outline"></ion-icon>Register
                        </div>
                        <div class="modal-step" id="step3Indicator">
                            <ion-icon name="checkmark-circle-outline"></ion-icon>Done
                        </div>
                    </div>

                
                <div id="step1Content"></div>

                
                <div id="step2Content" style="display:none;"></div>

                
                <div id="step3Content" style="display:none;"></div>

                <div class="modal-msg" id="modalMsg"></div>
            </div>
        </div>
    </div>

    

    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../../assets/js/index.js"></script>
  <script src="../../assets/js/student/events.js?v=<?= time() ?>"></script>
</body>
</html>