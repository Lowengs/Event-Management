<?php
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$event_id   = (int)($_GET['event_id'] ?? 0);
$type       = $_GET['type'] ?? 'pretest';
$reason     = $_GET['reason'] ?? 'not_found';

$isPre = (str_contains(strtolower($type), 'pre'));
$testTitle = $isPre ? 'Pre-Test Assessment' : 'Post-Test Assessment';

// Fetch Event Details via API
ob_start();
$_GET['event_id'] = $event_id;
$_GET['action']   = 'get_event_detail';
require __DIR__ . '/../../config/API/endpoints/index.php';
$evApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$event = $evApiRes['data'] ?? null;

// Fetch Student Profile via API
ob_start();
$_GET['action'] = 'get_student_profile';
require __DIR__ . '/../../config/API/endpoints/index.php';
$profApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$studentRow = $profApiRes['data'] ?? [];

$eventName = $event['EventName'] ?? 'Selected Event';
$orgName   = $event['OrgName'] ?? 'Student Organization';
$eventDate = (!empty($event['EventDateTime'])) ? date('M j, Y — g:i A', strtotime($event['EventDateTime'])) : 'Scheduled Event';
$student   = $studentRow;
$initials = strtoupper(substr($student['first_name'] ?? 'S', 0, 1) . substr($student['last_name'] ?? 'U', 0, 1));
$fullName = trim(($student['first_name'] ?? 'Student') . ' ' . ($student['last_name'] ?? ''));
$photoUrl = (!empty($student['profile_photo']) && file_exists(__DIR__ . '/../../' . $student['profile_photo'])) ? '../../' . $student['profile_photo'] : '';

// Determine Error Message
if ($reason === 'not_registered') {
    $errorHeadline = "Event Registration Required";
    $errorMessage  = "You must be registered for <strong>" . htmlspecialchars($eventName) . "</strong> before attempting to access the " . htmlspecialchars($testTitle) . ".";
} elseif ($reason === 'closed') {
    $errorHeadline = "Assessment Closed";
    $errorMessage  = "The " . htmlspecialchars($testTitle) . " for <strong>" . htmlspecialchars($eventName) . "</strong> has already been closed by the event organizers.";
} elseif ($reason === 'attendance_required') {
    $errorHeadline = "Attendance / Log-Out Required";
    $errorMessage  = "To take the <strong>" . htmlspecialchars($testTitle) . "</strong> for <strong>" . htmlspecialchars($eventName) . "</strong>, you must first record your attendance or log out for this event.";
} else {
    $errorHeadline = $testTitle . " Unavailable";
    $errorMessage  = "No active " . htmlspecialchars(strtolower($testTitle)) . " is currently published for <strong>" . htmlspecialchars($eventName) . "</strong>.<br><br>The student organization has not yet created or published questions for this assessment. Please check back later or contact your organization officers.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($testTitle) ?> Error | NAAP</title>
    <link rel="icon" href="../../assets/img/philsca.png">
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="../../assets/css/student/assessment_error.css?v=<?= time() ?>">
</head>
<body>

    <!-- Standard Navbar -->
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
                        <?php if ($photoUrl): ?>
                            <img src="<?= htmlspecialchars($photoUrl) ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar">
                        <?php else: ?>
                            <?= htmlspecialchars($initials) ?>
                        <?php endif; ?>
                    </div>
                    <div class="nav-user-info">
                        <span class="nav-user-name"><?= htmlspecialchars($fullName) ?></span>
                        <span class="nav-user-role">Student</span>
                    </div>
                    <ion-icon name="chevron-down-outline" class="nav-dropdown-caret"></ion-icon>
                </button>
                <div class="nav-dropdown-menu" role="menu" aria-label="Account menu">
                    <a href="announcements.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="megaphone-outline"></ion-icon><span>Announcement</span></a>
                    <a href="profile-dashboard.php" class="nav-dropdown-item" role="menuitem"><ion-icon name="person-circle-outline"></ion-icon><span>Profile Dashboard</span></a>
                    <a class="nav-dropdown-item danger" href="../../config/API/endpoints/index.php?action=student_logout" role="menuitem"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Error Card Section -->
    <div class="error-wrapper">
        <div class="error-card">
            <div class="icon-badge">
                <i class='bx bx-file-blank'></i>
            </div>
            <h1 class="error-title"><?= htmlspecialchars($errorHeadline) ?></h1>
            
            <div class="event-banner">
                <span class="event-banner-label">Target Event</span>
                <div class="event-banner-name"><?= htmlspecialchars($eventName) ?></div>
                <div class="event-banner-sub"><?= htmlspecialchars($orgName) ?> &nbsp;·&nbsp; <?= htmlspecialchars($eventDate) ?></div>
            </div>

            <div class="error-desc">
                <?= $errorMessage ?>
            </div>

            <div class="action-btns">
                <a href="profile-dashboard.php" class="btn-main btn-primary-blue">
                    <i class='bx bx-arrow-back'></i> Return to Profile Dashboard
                </a>
                <a href="events.php" class="btn-main btn-outlined">
                    <i class='bx bx-calendar'></i> Browse Events
                </a>
            </div>
        </div>
    </div>

    <script src="../../assets/js/custom_modal.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.showAlertModal) {
                const title = <?= json_encode($errorHeadline) ?>;
                const rawMsg = <?= json_encode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $errorMessage))) ?>;
                window.showAlertModal(rawMsg, title, 'warning');
            }
        });
    </script>
</body>
</html>
