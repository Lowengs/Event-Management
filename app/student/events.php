<?php
session_start();
require_once '../../config/img_helpers.php';

$isLoggedIn = !empty($_SESSION['student_id']);
$studentId  = $isLoggedIn ? (int)$_SESSION['student_id'] : 0;
$studentOrgId = null;

$selectedOrg = trim($_GET['org'] ?? '');
$registeredIds = [];
$pretestedIds  = [];

// ── Call API Endpoint: config/API/endpoints/index.php?action=GETevents ──────────
ob_start();
$_GET['action'] = 'get_student_events'; require __DIR__ . '/../../config/API/endpoints/index.php';
$eventsApiRes = json_decode(ob_get_clean(), true) ?: [];
$allEvents    = $eventsApiRes['data'] ?? [];

ob_start();
$_GET['action'] = 'get_student_organizations'; require __DIR__ . '/../../config/API/endpoints/index.php';
$orgsApiRes   = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$orgList      = $orgsApiRes['data'] ?? [];

foreach ($allEvents as $ev) {
    if (!empty($ev['is_registered'])) {
        $registeredIds[] = (int)$ev['EventId'];
    }
}

// Students browse scheduled, upcoming, ongoing, active, or non-archived events
$allEvents = array_values(array_filter($allEvents, function ($event) {
    $st = strtolower(trim((string)($event['EventStatus'] ?? 'scheduled')));
    return empty($st) || !in_array($st, ['archived', 'cancelled'], true);
}));

// Sort events: Ongoing and Scheduled/Upcoming on top; Completed at bottom
usort($allEvents, function($a, $b) {
    $orderMap = ['ongoing' => 1, 'scheduled' => 2, 'upcoming' => 3, 'active' => 4, 'completed' => 9];
    $stA = $orderMap[strtolower(trim($a['EventStatus'] ?? ''))] ?? 5;
    $stB = $orderMap[strtolower(trim($b['EventStatus'] ?? ''))] ?? 5;
    if ($stA !== $stB) return $stA <=> $stB;
    return strtotime($b['EventDateTime'] ?? '') <=> strtotime($a['EventDateTime'] ?? '');
});

$eventsPerPage = 6;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$totalEvents = count($allEvents);
$totalPages = max(1, ceil($totalEvents / $eventsPerPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $eventsPerPage;
$events = array_slice($allEvents, $offset, $eventsPerPage);

ob_start();
$_GET['action'] = 'get_student_profile'; require __DIR__ . '/../../config/API/endpoints/index.php';
$profApiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$studentRow = $profApiRes['data'] ?? [];

$fullName = ''; $initials = 'S'; $hasPhoto = false; $student = $studentRow;
if ($isLoggedIn) {
    $fullName = trim(($studentRow['first_name'] ?? $studentRow['FirstName'] ?? '') . ' ' . ($studentRow['last_name'] ?? $studentRow['LastName'] ?? ''));
    if (empty($fullName)) {
        $fullName = $_SESSION['student_name'] ?? 'Student';
    }
    $parts = explode(' ', trim($fullName));
    $initials = strtoupper(($parts[0][0] ?? 'S') . (count($parts) > 1 ? $parts[count($parts)-1][0] : ''));
    $profilePhoto = $studentRow['profile_photo'] ?? $studentRow['ProfilePhoto'] ?? $studentRow['ProfilePicture'] ?? '';
    $hasPhoto = !empty($profilePhoto);
    $photoSrc = $hasPhoto ? imgPathForDepth($profilePhoto, 2, '') : '';
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
                        <div class="nav-avatar" style="width:40px;height:40px;border-radius:50%;overflow:hidden;box-shadow:0 0 0 2.5px rgba(59,130,246,.6);display:flex;align-items:center;justify-content:center;background:#1e293b;flex-shrink:0;">
                            <?php 
                                $navPhoto = '';
                                if (isset($photoSrc) && !empty($photoSrc)) {
                                    $navPhoto = $photoSrc;
                                } elseif (!empty($student['profile_photo'] ?? $student['ProfilePhoto'] ?? $student['ProfilePicture'] ?? '')) {
                                    $p = $student['profile_photo'] ?? $student['ProfilePhoto'] ?? $student['ProfilePicture'];
                                    $navPhoto = (strpos($p, 'http') === 0 || strpos($p, '../../') === 0) ? $p : '../../' . ltrim($p, '/');
                                }
                            ?>
                            <?php if ($navPhoto !== ''): ?>
                                <img src="<?= htmlspecialchars($navPhoto) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="PFP" onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';">
                                <span class="nav-avatar-initials" style="display:none;font-size:13px;"><?= htmlspecialchars($initials ?: 'ST') ?></span>
                            <?php else: ?>
                                <span class="nav-avatar-initials" style="font-size:13px;"><?= htmlspecialchars($initials ?: 'ST') ?></span>
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
                        <a href="profile-dashboard.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="person-outline"></ion-icon><span>My Profile</span></a>
                        <div class="nav-dropdown-divider"></div>
                        <a href="../../config/API/student_logout.php" class="nav-dropdown-item danger" role="menuitem"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a>
                    </div>
                </div>
            <?php else: ?>
                <button class="nav-auth-btn nav-auth-btn-login" onclick="location.href='login.php'">
                    <ion-icon name="log-in-outline"></ion-icon>
                    <span>Login</span>
                </button>
                <button class="nav-auth-btn nav-auth-btn-register" onclick="location.href='register.php'">
                    <ion-icon name="person-add-outline"></ion-icon>
                    <span>Register</span>
                </button>
            <?php endif; ?>
        </div>
    </nav>

    <main class="event-container">
        <h1 style="text-align:center;">Discover & Pre-Register for Events</h1>
        <p class="event-title-desc" style="text-align:center;">Join workshops, seminars, competitions, and student organization activities.</p>

        <div class="exploration-filters">
            <div class="filter-dropdowns">
                <div class="filter-group">
                    <label><ion-icon name="search-outline"></ion-icon> Search Keyword</label>
                    <div class="search-input-wrapper">
                        <ion-icon name="search-outline"></ion-icon>
                        <input type="text" id="eventSearchInput" placeholder="Search by title, location...">
                    </div>
                </div>
                <div class="filter-group">
                    <label><ion-icon name="business-outline"></ion-icon> Organization</label>
                    <select id="orgFilter">
                        <option value="">All Organizations</option>
                        <?php foreach ($orgList as $ol): ?>
                        <option value="<?= (int)$ol['OrgId'] ?>" <?= ($selectedOrg == (string)$ol['OrgId'] || strtolower($selectedOrg) === strtolower($ol['OrgName'])) ? 'selected' : '' ?>><?= htmlspecialchars($ol['OrgName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><ion-icon name="calendar-outline"></ion-icon> Date</label>
                    <input type="date" id="dateFilter" class="filter-date-input">
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
                    $registered  = (int)($ev['reg_count'] ?? $ev['registered_count'] ?? $ev['RegisteredCount'] ?? 0);
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
                     data-orgid="<?= (int)($ev['OrgId'] ?? 0) ?>"
                     data-date="<?= $dateObj->format('Y-m-d') ?>"
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

                        <?php 
                            $stLower = strtolower(trim($ev['EventStatus'] ?? ''));
                            $hasAttended = !empty($ev['has_attended']);
                        ?>
                        <?php if ($stLower === 'completed'): ?>
                            <?php if ($hasAttended): ?>
                                <button class="ev-prereg-btn" style="background:#10b981;color:#fff;border:none;cursor:default;" disabled>
                                    <ion-icon name="checkmark-done-circle-outline"></ion-icon> Attended
                                </button>
                            <?php else: ?>
                                <button class="ev-prereg-btn" style="background:#64748b;color:#fff;border:none;cursor:not-allowed;" disabled>
                                    <ion-icon name="close-circle-outline"></ion-icon> Event Closed
                                </button>
                            <?php endif; ?>
                        <?php elseif ($isFull): ?>
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
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> NAAP Student Organization System. All rights reserved.</p>
        </div>
    </footer>

    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../../assets/js/student/events.js"></script>
    <script src="../../assets/js/logout_confirm.js" defer></script>
    <script src="../../assets/js/modal_alert.js"></script>
</body>
</html>
