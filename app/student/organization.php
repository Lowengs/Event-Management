<?php
session_start();
require_once '../../config/db.php';

// Fetch orgs same query as index.php
$organizations = [];
if ($conn) {
    $q = "
        SELECT o.*,
            (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId) AS member_count,
            (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId) AS event_count,
            (SELECT CONCAT(first_name,' ',last_name) FROM user u2
             WHERE u2.OrgId = o.OrgId AND u2.Role='student'
             ORDER BY u2.created_at ASC LIMIT 1) AS president_name
        FROM organization o
        WHERE LOWER(o.Status)='active'
        ORDER BY o.OrgName ASC
    ";
    $r = $conn->query($q);
    if ($r) while ($row = $r->fetch_assoc()) $organizations[] = $row;
}

$isLoggedIn = !empty($_SESSION['student_id']);
$fullName = ''; $initials = ''; $hasPhoto = false; $student = [];
if ($isLoggedIn) {
    $sid = (int)$_SESSION['student_id'];
    $u = $conn->query("SELECT first_name, last_name, profile_photo FROM `user` WHERE UserId = $sid")->fetch_assoc();
    if ($u) {
        $fullName = trim($u['first_name'] . ' ' . $u['last_name']);
        $initials = strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1));
        if (!empty($u['profile_photo']) && strpos($u['profile_photo'], 'assets') === 0) {
            $u['profile_photo'] = '../../' . $u['profile_photo'];
        }
        $hasPhoto = !empty($u['profile_photo']);
        $student = $u;
    }
}

require_once '../../config/img_helpers.php';
function imgUrl2(string $p): string { return imgPathForDepth($p, 2, '../../assets/img/philsca.png'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizations – NAAP Student Portal</title>
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/student/organization.css">
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
                <a href="organization.php" class="active">Organizations</a>
                <a href="events.php">Events</a>
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
            <li><a href="organization.php" class="active">Organizations</a></li>
            <li><a href="events.php">Events</a></li>
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
        <div class="organization-container">
            <h1>Explore NAAP Organizations</h1>
            <p class="org-title-desc">Be part of your course-based student organization and build connections<br>with fellow students on the same academic journey.</p>

            <div class="exploration-filters">
                <div class="search-sort-row">
                    <div class="search-input-wrapper">
                        <ion-icon name="search-outline"></ion-icon>
                        <input type="text" id="orgSearch" placeholder="Search organizations by name or description...">
                    </div>
                </div>
            </div>

            <div class="results-header">
                <span>Showing <strong id="orgCount"><?= count($organizations) ?></strong> organizations</span>
            </div>

            <div class="org-card-container" id="orgGrid">
            <?php if (empty($organizations)): ?>
                <p style="grid-column:1/-1;text-align:center;color:#94a3b8;padding:60px;font-family:'Inter',sans-serif;">No organizations found.</p>
            <?php else: ?>
            <?php foreach ($organizations as $org):
                $bannerUrl = $org['OrgBanner']  ? imgUrl2($org['OrgBanner'])  : '../../assets/img/philsca.png';
                $logoUrl   = $org['OrgPicture'] ? imgUrl2($org['OrgPicture']) : '../../assets/img/philsca.png';
                $members   = (int)$org['member_count'];
                $evCount   = (int)$org['event_count'];
                $adviser   = htmlspecialchars($org['Adviser'] ?? 'N/A');
                $desc      = htmlspecialchars($org['Description'] ?? 'A NAAP student organization dedicated to academic and professional excellence.');
                $status    = htmlspecialchars($org['Status'] ?? 'Active');
            ?>
            <div class="org-card"
                 data-name="<?= strtolower(htmlspecialchars($org['OrgName'])) ?>"
                 data-desc="<?= strtolower($org['Description'] ?? '') ?>">
                <div class="org-card-header">
                    <img src="<?= $bannerUrl ?>" alt="<?= htmlspecialchars($org['OrgName']) ?> Banner"
                         onerror="this.src='../../assets/img/philsca.png'">
                </div>
                <div class="org-card-title-group">
                    <div class="org-card-icon">
                        <img src="<?= $logoUrl ?>" alt="<?= htmlspecialchars($org['OrgName']) ?> Logo"
                             onerror="this.src='../../assets/img/philsca.png'">
                    </div>
                    <div class="org-card-title-group-text">
                        <h3><?= htmlspecialchars($org['OrgName']) ?></h3>
                        <span class="badge badge--status"><?= $status ?></span>
                    </div>
                </div>

                <div class="org-description">
                    <p class="org-card-description"><?= mb_strimwidth($desc, 0, 130, '...') ?></p>
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

                <button class="org-card-button" onclick="viewOrg(this)"
                    data-name="<?= htmlspecialchars($org['OrgName']) ?>"
                    data-status="<?= $status ?>"
                    data-adviser="<?= $adviser ?>"
                    data-president="<?= htmlspecialchars($org['president_name'] ?? '') ?>"
                    data-members="<?= $members ?>"
                    data-events="<?= $evCount ?>"
                    data-desc="<?= $desc ?>"
                    data-pic="<?= $logoUrl ?>"
                    data-banner="<?= $bannerUrl ?>">View Details</button>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Organization Detail Modal -->
    <div id="orgModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:9999;align-items:center;justify-content:center;padding:1rem;" onclick="if(event.target===this)closeOrgModal()">
      <div style="background:#fff;border-radius:18px;width:92%;max-width:540px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.3);max-height:90vh;display:flex;flex-direction:column;">
        <div id="orgModalHeader" style="background:linear-gradient(135deg,#003366,#0a5eb0);padding:1.5rem;display:flex;align-items:center;gap:1rem;position:relative;min-height:110px;background-size:cover;background-position:center;">
          <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(0,30,80,.75),rgba(10,94,176,.65));border-radius:18px 18px 0 0;"></div>
          <img id="omLogo" src="" alt="Logo" style="width:68px;height:68px;border-radius:50%;border:3px solid #fff;object-fit:cover;flex-shrink:0;position:relative;z-index:1;">
          <div style="position:relative;z-index:1;flex:1;">
            <h3 id="omName" style="color:#fff;margin:0;font-size:1.2rem;font-weight:800;"></h3>
            <p id="omStatus" style="color:rgba(255,255,255,.75);margin:.2rem 0 0;font-size:.8rem;"></p>
          </div>
          <button onclick="closeOrgModal()" style="position:relative;z-index:1;background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;font-size:1.2rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">&times;</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;background:#f8fafc;border-bottom:1px solid #e2e8f0;">
          <div style="padding:.85rem;text-align:center;border-right:1px solid #e2e8f0;"><p id="omMembers" style="margin:0;font-size:1.3rem;font-weight:800;color:#003366;"></p><p style="margin:0;font-size:.7rem;color:#64748b;">Members</p></div>
          <div style="padding:.85rem;text-align:center;border-right:1px solid #e2e8f0;"><p id="omEvents"  style="margin:0;font-size:1.3rem;font-weight:800;color:#003366;"></p><p style="margin:0;font-size:.7rem;color:#64748b;">Events</p></div>
          <div style="padding:.85rem;text-align:center;"><p id="omPresident" style="margin:0;font-size:.9rem;font-weight:700;color:#003366;"></p><p style="margin:0;font-size:.7rem;color:#64748b;">Representative</p></div>
        </div>
        <div style="padding:1.25rem 1.5rem;overflow-y:auto;display:grid;grid-template-columns:1fr 1fr;gap:.85rem;">
          <div style="background:#f8fafc;border-radius:10px;padding:.75rem;"><p style="margin:0;font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Faculty Adviser</p><p id="omAdviser" style="margin:.2rem 0 0;font-weight:700;font-size:.9rem;color:#0f172a;"></p></div>
          <div style="background:#f8fafc;border-radius:10px;padding:.75rem;"><p style="margin:0;font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Status</p><p id="omStatusInner" style="margin:.2rem 0 0;font-weight:700;font-size:.9rem;color:#0f172a;"></p></div>
          <div style="background:#f8fafc;border-radius:10px;padding:.75rem;grid-column:1/-1;"><p style="margin:0;font-size:.68rem;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">About</p><p id="omDesc" style="margin:.3rem 0 0;font-size:.85rem;color:#334155;line-height:1.6;"></p></div>
        </div>
        <div style="border-top:1px solid #f1f5f9;padding:.85rem 1.5rem;display:flex;justify-content:flex-end;gap:.5rem;background:#fff;">
          <a href="events.php" style="text-decoration:none;"><button style="padding:.5rem 1.4rem;background:#0ea5e9;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer;">View Events</button></a>
          <button onclick="closeOrgModal()" style="padding:.5rem 1.4rem;background:#003366;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer;">Close</button>
        </div>
      </div>
    </div>

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

    

    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../../assets/js/index.js"></script>
  <script src="../../assets/js/student/organization.js"></script>
</body>
</html>