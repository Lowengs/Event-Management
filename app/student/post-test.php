<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>" />
    <link rel="stylesheet" href="../../assets/css/student/profile-dashboard.css" />
    <link rel="stylesheet" href="../../assets/css/student/pre-test.css" />

    <!-- FONTS -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet" />

  <!-- ICONS -->
  <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>


  <link rel="icon" href="../../assets/img/philsca.png">

</head>
<body>

    <div class="mobile-header">
        <div class="mobile-header-logo">
            <img src="../../assets/img/philsca.png" alt="Logo">
        </div>
        <div class="mobile-header-title">NAAP Student Organization</div>
    </div>

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
            <li><a href="">Organizations</a></li>
            <li><a href="">Events</a></li>
            <li><a href="osa/login.php">Login</a></li>
            <li><a href="">Register</a></li>
          </ul>
    </div>

    <!-- POST-TEST CONTAINER -->
    <div class="pre-test-container">
        <div class="assessment-card">
            <!-- Header Section -->
            <div class="assessment-header">
                <i class='bx bx-check-shield assessment-icon'></i>
                <h1 class="assessment-title">Post-Test Assessment</h1>
                <p class="assessment-subtitle">Aviation Safety Workshop</p>
            </div>

          
            <div class="assessment-body">
                
                <div class="event-details-row">
                    <div class="event-detail-item">
                        <i class='bx bx-buildings'></i>
                        <span class="event-detail-text">Aviation Safety Club</span>
                    </div>
                    <div class="event-detail-item">
                        <i class='bx bx-calendar'></i>
                        <span class="event-detail-text">3/15/2026</span>
                    </div>
                </div>
                <div style="margin-top: -20px; margin-bottom: 30px;">
                    <span class="badge-outline">Workshop</span>
                </div>

                <!-- Instructions -->
                <div class="instructions-section">
                    <h3>Test Instructions</h3>
                    <ul class="instruction-list">
                        <li class="instruction-item">
                            <span class="instruction-number">1</span>
                            <span>This assessment contains <strong>10 multiple choice questions</strong> to evaluate what you've learned.</span>
                        </li>
                        <li class="instruction-item">
                            <span class="instruction-number">2</span>
                            <span>You have <strong>30 minutes</strong> to complete the test.</span>
                        </li>
                        <li class="instruction-item">
                            <span class="instruction-number">3</span>
                            <span>Select one answer for each question by clicking on the option card.</span>
                        </li>
                        <li class="instruction-item">
                            <span class="instruction-number">4</span>
                            <span>You can navigate between questions using the <strong>Previous</strong> and <strong>Next</strong> buttons.</span>
                        </li>
                        <li class="instruction-item warning">
                            <i class='bx bx-error-circle'></i>
                            <span>The test will automatically submit when time runs out.</span>
                        </li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="assessment-actions">
                    <a href="profile-dashboard.php" class="btn-cancel">Cancel</a>
                    <a href="post-test-active.php" class="btn-start">Start Test</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../assets/js/index.js"></script>
</body>
</html>