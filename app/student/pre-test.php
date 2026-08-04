<?php
/**
 * Student Pre-Test / Post-Test Overview Page
 * Uses GETevent_detail and GETassessment_questions API endpoints
 */
session_start();

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$event_id   = (int)($_GET['event_id'] ?? 0);
$type       = strtolower(trim($_GET['type'] ?? 'pretest'));
$isPre      = (strpos($type, 'pre') !== false);
$typeStr    = $isPre ? 'pretest' : 'posttest';

// 1. Fetch Student Profile via API
ob_start();
$_GET['action'] = 'get_student_profile';
require __DIR__ . '/../../config/API/endpoints/index.php';
$profApi = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$studentRow = $profApi['data'] ?? [];

$studentName = trim(($studentRow['first_name'] ?? '') . ' ' . ($studentRow['last_name'] ?? '')) ?: ($_SESSION['student_name'] ?? 'Student');
$parts = explode(' ', trim($studentName));
$initials = strtoupper(($parts[0][0] ?? 'S') . (count($parts) > 1 ? $parts[count($parts) - 1][0] : ''));
$photoSrc = !empty($studentRow['profile_photo']) ? ((strpos($studentRow['profile_photo'], 'http') === 0 || strpos($studentRow['profile_photo'], '../../') === 0) ? $studentRow['profile_photo'] : '../../' . ltrim($studentRow['profile_photo'], '/')) : '';

// 2. Access control check: attendance and pre-test requirements
require_once __DIR__ . '/../../config/db.php';

$hasAttendance = false;
$hasPretest    = false;

if ($conn && $event_id && $student_id) {
    $attCheck = $conn->query("SELECT 1 FROM attendance WHERE EventId = $event_id AND UserId = $student_id LIMIT 1");
    $hasAttendance = ($attCheck && $attCheck->num_rows > 0);

    $preCheck = $conn->query("SELECT 1 FROM event_pretest WHERE EventId = $event_id AND UserId = $student_id LIMIT 1");
    if (!$preCheck || $preCheck->num_rows === 0) {
        $preCheck2 = $conn->query("SELECT 1 FROM preposttest WHERE EventId = $event_id AND StudentId = $student_id AND LOWER(TestType) = 'pre' LIMIT 1");
        $preCheck3 = $conn->query("SELECT 1 FROM assessment_responses ar JOIN assessments a ON a.assessment_id = ar.assessment_id WHERE a.event_id = $event_id AND LOWER(COALESCE(a.type, a.test_type, '')) LIKE '%pre%' AND ar.user_id = $student_id LIMIT 1");
        $hasPretest = ($preCheck2 && $preCheck2->num_rows > 0) || ($preCheck3 && $preCheck3->num_rows > 0);
    } else {
        $hasPretest = true;
    }
}

$accessDenied = false;
$accessReason = '';

if ($isPre) {
    if (!$hasAttendance) {
        $accessDenied = true;
        $accessReason = 'Attendance is required before taking the Pre-Test. Please check in or scan your attendance at the event venue first.';
    }
} else {
    if (!$hasAttendance) {
        $accessDenied = true;
        $accessReason = 'Attendance is required before taking the Post-Test. Please check in or scan your attendance at the event venue first.';
    } elseif (!$hasPretest) {
        $accessDenied = true;
        $accessReason = 'You must complete the Pre-Test before taking the Post-Test. Please take and submit your Pre-Test first.';
    }
}

// 3. Fetch Event & Assessment via API
ob_start();
$_GET['event_id'] = $event_id;
$_GET['type']     = $typeStr;
$_GET['action']   = 'get_assessment_questions';
$assessApi = json_decode(ob_get_clean() ?: '[]', true) ?: [];

$assessmentCreated = ($assessApi['success'] ?? true) && (!empty($assessApi['assessment']) || !empty($assessApi['questions']));

if (!$assessmentCreated) {
    $accessDenied = true;
    $accessReason = 'The event organizer has not created or published questions for this assessment yet. The assessment is currently unavailable.';
}

$assessment = $assessApi['assessment'] ?? null;
$qCount     = (int)($assessApi['question_count'] ?? (isset($assessApi['questions']) ? count($assessApi['questions']) : 5));

// Fetch Event Details for title fallback
ob_start();
$_GET['action'] = 'get_event_detail';
require __DIR__ . '/../../config/API/endpoints/index.php';
$evApi = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$eventDetails = $evApi['data'] ?? [];

$eventName    = $assessment['title'] ?? $eventDetails['EventName'] ?? 'Organization Event';
$orgName      = $eventDetails['OrgName'] ?? 'NAAP Organization';
$eventDate    = !empty($eventDetails['EventDateTime']) ? date('F j, Y — g:i A', strtotime($eventDetails['EventDateTime'])) : 'Scheduled Event';
$instructions = $assessment['instructions'] ?? 'Read each question carefully and select the best answer option. Submit once complete.';
$timeLimit    = min(30, max(1, (int)($assessment['time_limit'] ?? 30)));

$testTitle = $isPre ? 'Pre-Test Assessment' : 'Post-Test Assessment';
$testBadge = $isPre ? 'Pre-Test' : 'Post-Test';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($testTitle) ?> | NAAP Student Portal</title>
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="icon" href="../../assets/img/philsca.png">
    <link rel="stylesheet" href="../../assets/css/student/pre-test.css?v=<?= time() ?>">
</head>
<body>
    <nav class="portal-nav">
        <a href="profile-dashboard.php?tab=registrations" class="nav-brand">
            <i class='bx bx-arrow-back' style="font-size:1.2rem;color:#94a3b8;"></i>
            <img src="../../assets/img/naap logo.png" alt="NAAP Logo">
            <span>NAAP Student Portal</span>
        </a>
        <div class="nav-user">
            <div class="nav-avatar">
                <?php if ($photoSrc !== ''): ?>
                    <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Profile">
                <?php else: ?>
                    <span><?= htmlspecialchars($initials) ?></span>
                <?php endif; ?>
            </div>
            <span style="font-weight:600;font-size:0.9rem;color:#e2e8f0;display:none;@media(min-width:640px){display:inline;}"><?= htmlspecialchars($studentName) ?></span>
        </div>
    </nav>

    <main class="landing-shell">
        <div class="hero-card">
            <div class="hero-header" style="<?= !$isPre ? 'background:linear-gradient(135deg,rgba(2,132,199,0.25) 0%,rgba(13,148,136,0.25) 100%);' : '' ?>">
                <div class="badges-row">
                    <span class="badge-type" style="<?= !$isPre ? 'background:linear-gradient(135deg,#0284c7,#0d9488);box-shadow:0 4px 14px rgba(13,148,136,0.4);' : '' ?>"><?= htmlspecialchars($testBadge) ?></span>
                    <span class="badge-ai"><i class='bx bx-brain'></i> AI Analysis Ready</span>
                </div>
                <h1 class="event-title"><?= htmlspecialchars($eventName) ?></h1>
                <div class="org-name">
                    <i class='bx bx-building-house' style="color:#38bdf8;"></i> <?= htmlspecialchars($orgName) ?>
                    &nbsp;·&nbsp; <i class='bx bx-calendar' style="color:#38bdf8;"></i> <?= htmlspecialchars($eventDate) ?>
                </div>
            </div>

            <?php if ($accessDenied): ?>
            <!-- Lock Warning Card -->
            <div class="instructions-body" style="text-align:center;padding:48px 32px;">
                <div style="width:72px;height:72px;border-radius:50%;background:rgba(239,68,68,0.15);border:1.5px solid rgba(239,68,68,0.4);color:#ef4444;display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 20px;">
                    <i class='bx bx-lock-alt'></i>
                </div>
                <h2 style="font-size:1.5rem;font-weight:800;color:#ffffff;margin-bottom:10px;">Assessment Locked</h2>
                <p style="color:#cbd5e1;font-size:0.98rem;max-width:520px;margin:0 auto 28px;line-height:1.6;">
                    <?= htmlspecialchars($accessReason) ?>
                </p>
                <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
                    <button type="button" disabled style="padding:12px 24px;border-radius:12px;background:rgba(255,255,255,0.05);color:#64748b;border:1px solid rgba(255,255,255,0.08);font-weight:700;font-size:0.92rem;cursor:not-allowed;display:inline-flex;align-items:center;gap:8px;">
                        <i class='bx bx-block'></i> Start <?= htmlspecialchars($testBadge) ?> (Unclickable)
                    </button>
                    <a href="profile-dashboard.php?tab=registrations" class="btn-cancel" style="background:#2563eb;color:#fff;border:none;">
                        <i class='bx bx-left-arrow-alt'></i> Return to My Registrations
                    </a>
                </div>
            </div>
            <?php else: ?>
            <!-- Normal Overview Grid -->
            <div class="stats-grid">
                <div class="stat-box">
                    <i class='bx bx-help-circle'></i>
                    <div class="stat-val"><?= $qCount ?></div>
                    <div class="stat-lbl">Questions</div>
                </div>
                <div class="stat-box">
                    <i class='bx bx-time-five'></i>
                    <div class="stat-val"><?= $timeLimit ?> Mins</div>
                    <div class="stat-lbl">Time Limit</div>
                </div>
                <div class="stat-box">
                    <i class='bx bx-target-lock'></i>
                    <div class="stat-val">70%</div>
                    <div class="stat-lbl">Pass Score</div>
                </div>
                <div class="stat-box">
                    <i class='bx bx-revision'></i>
                    <div class="stat-val">1 Attempt</div>
                    <div class="stat-lbl">Allowance</div>
                </div>
            </div>

            <div class="instructions-body">
                <div class="section-title">
                    <i class='bx bx-info-circle' style="color:#38bdf8;font-size:1.25rem;"></i>
                    Assessment Instructions
                </div>

                <ul class="instructions-list">
                    <li class="instruction-item">
                        <div class="step-num">1</div>
                        <div class="step-txt">
                            <strong>Read all questions carefully</strong> before selecting your response. You can change your answers anytime before final submission.
                        </div>
                    </li>
                    <li class="instruction-item">
                        <div class="step-num">2</div>
                        <div class="step-txt">
                            <strong>Time Management</strong>: Keep an eye on the countdown timer. The assessment automatically submits when the time expires.
                        </div>
                    </li>
                    <li class="instruction-item">
                        <div class="step-num">3</div>
                        <div class="step-txt">
                            <strong>AI Feedback & Analytics</strong>: Upon completion, your performance insights and learning analytics will be generated automatically.
                        </div>
                    </li>
                </ul>

                <div class="actions-bar">
                    <a href="profile-dashboard.php?tab=registrations" class="btn-cancel">
                        <i class='bx bx-left-arrow-alt'></i> Back to Dashboard
                    </a>
                    <a href="pre-test-active.php?event_id=<?= $event_id ?>&type=<?= $typeStr ?>" class="btn-start" style="<?= !$isPre ? 'background:linear-gradient(135deg,#0284c7,#0d9488);box-shadow:0 8px 24px rgba(13,148,136,0.4);' : '' ?>">
                        Start <?= htmlspecialchars($testBadge) ?> Now
                        <i class='bx bx-right-arrow-alt' style="font-size:1.2rem;"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>