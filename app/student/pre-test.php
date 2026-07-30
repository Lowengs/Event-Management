<?php
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$event_id = (int)($_GET['event_id'] ?? 0);
$type = $_GET['type'] ?? 'pretest';

// Auto-close assessments older than 1 day (24 hours) based on event date or creation date
// Auto-close assessments 1 day (24 hours) AFTER event date has passed
$conn->query("
    UPDATE assessments a 
    JOIN event e ON a.event_id = e.EventId 
    SET a.status = 'closed' 
    WHERE a.status = 'published' 
    AND e.EventDateTime IS NOT NULL 
    AND e.EventDateTime < NOW() - INTERVAL 1 DAY
");

// Fetch assessment (support both type and test_type column names)
$stmt = $conn->prepare("
    SELECT a.*, e.EventName, e.EventDateTime, e.EventMode, o.OrgName
    FROM assessments a
    JOIN event e ON a.event_id = e.EventId
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    WHERE a.event_id = ? AND (a.type = ? OR a.test_type = ?) AND a.status = 'published'
    LIMIT 1
");
$stmt->bind_param('iss', $event_id, $type, $type);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

$errorMessage = null;
$hasAttendance = false;

if (!$assessment) {
    $errorMessage = "No active " . ($type === 'pretest' ? 'pre-test' : 'post-test') . " assessment is currently available for this event. It may be closed, in draft status, or not yet created.";
} else {
    $attendanceStmt = $conn->prepare("SELECT AttendanceId FROM attendance WHERE EventId = ? AND UserId = ? AND LOWER(AttendanceStatus) = 'present' LIMIT 1");
    $attendanceStmt->bind_param('ii', $event_id, $student_id);
    $attendanceStmt->execute();
    $hasAttendance = (bool)$attendanceStmt->get_result()->fetch_assoc();

    if ($type === 'pretest' && !$hasAttendance) {
        $errorMessage = "You must be marked PRESENT in attendance for this event before taking the Pre-Test Assessment.";
    }
}

// Fetch question count if assessment exists
$qCount = 0;
if ($assessment) {
    $qStmt = $conn->prepare("SELECT COUNT(*) as q_count FROM assessment_questions WHERE assessment_id = ?");
    $qStmt->bind_param('i', $assessment['assessment_id']);
    $qStmt->execute();
    $qCount = $qStmt->get_result()->fetch_assoc()['q_count'];
}

$testTitle = $type === 'pretest' ? 'Pre-Test Assessment' : 'Post-Test Assessment';
$eventDate = ($assessment && !empty($assessment['EventDateTime'])) ? date('M j, Y', strtotime($assessment['EventDateTime'])) : 'TBA';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($testTitle) ?> | NAAP</title>

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

    <div class="pre-test-container">
        <div class="assessment-card">
            <?php if ($errorMessage): ?>
                <div class="assessment-header" style="background:#fef2f2;border-bottom:1px solid #fee2e2;">
                    <i class='bx bx-info-circle assessment-icon' style="color:#ef4444;"></i>
                    <h1 class="assessment-title" style="color:#991b1b;"><?= htmlspecialchars($testTitle) ?> Unavailable</h1>
                    <p class="assessment-subtitle" style="color:#b91c1c;"><?= htmlspecialchars($errorMessage) ?></p>
                </div>
                <div class="assessment-body" style="padding: 30px; text-align: center;">
                    <a href="profile-dashboard.php" class="btn-start" style="display:inline-block;text-decoration:none;background:#2563eb;">Return to Dashboard</a>
                </div>
            <?php else: ?>
                <!-- Header Section -->
                <div class="assessment-header">
                    <i class='bx bx-file-blank assessment-icon'></i>
                    <h1 class="assessment-title"><?= htmlspecialchars($testTitle) ?></h1>
                    <p class="assessment-subtitle"><?= htmlspecialchars($assessment['title']) ?></p>
                </div>

                <div class="assessment-body">
                    <div class="event-details-row">
                        <div class="event-detail-item">
                            <i class='bx bx-buildings'></i>
                            <span class="event-detail-text"><?= htmlspecialchars($assessment['OrgName'] ?? 'NAAP') ?></span>
                        </div>
                        <div class="event-detail-item">
                            <i class='bx bx-calendar'></i>
                            <span class="event-detail-text"><?= $eventDate ?></span>
                        </div>
                    </div>
                    <div style="margin-top: -20px; margin-bottom: 30px;">
                        <span class="badge-outline"><?= htmlspecialchars($assessment['EventName']) ?></span>
                    </div>

                    <!-- Instructions -->
                    <div class="instructions-section">
                        <h3>Test Instructions</h3>
                        
                        <?php if (!empty($assessment['instructions'])): ?>
                        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 20px; line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($assessment['instructions'])) ?>
                        </p>
                        <?php endif; ?>

                        <ul class="instruction-list">
                            <li class="instruction-item">
                                <span class="instruction-number">1</span>
                                <span>This assessment contains <strong><?= $qCount ?> questions</strong>.</span>
                            </li>
                            <li class="instruction-item">
                                <span class="instruction-number">2</span>
                                <span>Select one answer for each question by clicking on the option.</span>
                            </li>
                            <li class="instruction-item">
                                <span class="instruction-number">3</span>
                                <span>You can navigate between questions using the <strong>Previous</strong> and <strong>Next</strong> buttons.</span>
                            </li>
                            <li class="instruction-item warning" style="margin-top: 10px;">
                                <i class='bx bx-error-circle'></i>
                                <span>The test must be submitted once started.</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="assessment-actions">
                        <a href="profile-dashboard.php" class="btn-cancel">Cancel</a>
                        <?php if ($type === 'posttest'): ?>
                            <a href="post-test-active.php?assessment_id=<?= $assessment['assessment_id'] ?>" class="btn-start">Start Test</a>
                        <?php else: ?>
                            <?php if ($hasAttendance): ?>
                                <a href="pre-test-active.php?assessment_id=<?= $assessment['assessment_id'] ?>" class="btn-start">Start Test</a>
                            <?php else: ?>
                                <span class="btn-start" style="opacity:.55;cursor:not-allowed;pointer-events:none;">Attendance required first</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../../assets/js/index.js"></script>
    <script src="../../assets/js/student/pre-test.js"></script>
</body>
</html>