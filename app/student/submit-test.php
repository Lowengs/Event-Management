<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/gemini.php';

if (empty($_SESSION['student_id'])) { header('Location: login.php'); exit; }

$student_id = (int)$_SESSION['student_id'];
$student = $conn->query("SELECT first_name, last_name FROM user WHERE UserId = $student_id LIMIT 1")->fetch_assoc();
$initials = strtoupper(substr($student['first_name'],0,1) . substr($student['last_name'],0,1));
$fullName = trim($student['first_name'] . ' ' . $student['last_name']);

// ── Recover context from session ──────────────────────────────────
$assessment_id = (int)($_POST['assessment_id'] ?? 0);
$event_id   = (int)($_POST['event_id']   ?? $_SESSION['active_event_id']   ?? 0);
$test_type  = $_POST['type']   ?? $_SESSION['active_test_type']  ?? 'pre';
$eventName  = $_SESSION['active_event_name'] ?? 'Assessment';
$orgName    = $_SESSION['active_org_name']   ?? 'NAAP';
$eventDate  = $_SESSION['active_event_date'] ?? 'N/A';
$testLabel  = $test_type === 'pretest' ? 'Pre-Test' : 'Post-Test';

if ($test_type === 'pretest' && $event_id > 0) {
    $attendanceStmt = $conn->prepare("SELECT AttendanceId FROM attendance WHERE EventId = ? AND UserId = ? AND LOWER(AttendanceStatus) = 'present' LIMIT 1");
    if ($attendanceStmt) {
        $attendanceStmt->bind_param('ii', $event_id, $student_id);
        $attendanceStmt->execute();
        if (!$attendanceStmt->get_result()->fetch_assoc()) {
            $attendanceStmt->close();
            echo "";
            exit;
        }
        $attendanceStmt->close();
    }
}

// ── Score calculation from Database ─────────────────────────────────────────────
$score = 0;
$total = 0;
$wrongTopics = [];
$perQuestion = [];

if ($assessment_id > 0) {
    $qStmt = $conn->prepare("SELECT question_id, question_text, correct_answer FROM assessment_questions WHERE assessment_id = ?");
    $qStmt->bind_param('i', $assessment_id);
    $qStmt->execute();
    $qResult = $qStmt->get_result();

    while ($row = $qResult->fetch_assoc()) {
        $total++;
        $qId = $row['question_id'];
        $correct = trim($row['correct_answer']);
        $posted = $_POST["answer_{$qId}"] ?? null;

        $isRight = ($posted !== null && trim($posted) === $correct);
        if ($isRight) {
            $score++;
        } else {
            $wrongTopics[] = substr($row['question_text'], 0, 80);
        }
        
        $perQuestion[] = [
            'question_id' => $qId,
            'question_text' => $row['question_text'],
            'correct' => $isRight, 
            'given' => $posted, 
            'expected' => $correct
        ];
    }
} else {
    // Fallback if no assessment_id (e.g. old AI logic)
    $total = max(1, (int)($_POST['total'] ?? 0));
}

$pct    = $total > 0 ? round(($score / $total) * 100) : 0;
$status = $pct >= 85 ? 'Excellent' : ($pct >= 70 ? 'Good' : ($pct >= 50 ? 'Average' : 'Needs Improvement'));
$statusColor = $pct >= 85 ? '#22c55e' : ($pct >= 70 ? '#4fd1c5' : ($pct >= 50 ? '#f59e0b' : '#ef4444'));

$preTestScore = null;
$preTestSubmittedAt = null;
$preTestPct = null;
if ($test_type !== 'pretest' && $event_id > 0) {
    $preStmt = $conn->prepare("SELECT Score, SubmittedAt FROM event_pretest WHERE EventId = ? AND UserId = ? ORDER BY SubmittedAt DESC LIMIT 1");
    if ($preStmt) {
        $preStmt->bind_param('ii', $event_id, $student_id);
        $preStmt->execute();
        $preResult = $preStmt->get_result()->fetch_assoc();
        if ($preResult) {
            $preTestScore = (int)$preResult['Score'];
            $preTestSubmittedAt = $preResult['SubmittedAt'];
            $preTestPct = $total > 0 ? round(($preTestScore / $total) * 100) : 0;
        }
        $preStmt->close();
    }
}

// ── AI Feedback ───────────────────────────────────────────────────
$aiFeedback = generateAIFeedback($eventName, $score, $total, array_slice($wrongTopics, 0, 3), $test_type);

// ── Persist result in DB ──────────────────────────────────────────
$table = ($test_type === 'pretest' || $test_type === 'pre') ? 'event_pretest' : 'event_posttest';

$tab_switches = (int)($_POST['tab_switches'] ?? 0);
$engagement_score = (int)($_POST['engagement_score'] ?? 100);
$monitoring_flagged = (int)($_POST['monitoring_flagged'] ?? 0);

// Check if student already submitted to prevent duplication on reload
$checkStmt = $conn->prepare("SELECT Score FROM $table WHERE EventId = ? AND UserId = ?");
$checkStmt->bind_param("ii", $event_id, $student_id);
$checkStmt->execute();

if ($checkStmt->get_result()->num_rows === 0) {
    // Insert new record
    $insertStmt = $conn->prepare("INSERT INTO $table (EventId, UserId, Score, tab_switches, engagement_score, monitoring_flagged, SubmittedAt) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $insertStmt->bind_param("iiiiii", $event_id, $student_id, $score, $tab_switches, $engagement_score, $monitoring_flagged);
    $insertStmt->execute();
} else {
    // Update existing record instead of adding a new row
    $updateStmt = $conn->prepare("UPDATE $table SET Score = ?, tab_switches = ?, engagement_score = ?, monitoring_flagged = ?, SubmittedAt = NOW() WHERE EventId = ? AND UserId = ?");
    $updateStmt->bind_param("iiiiii", $score, $tab_switches, $engagement_score, $monitoring_flagged, $event_id, $student_id);
    $updateStmt->execute();
}

// ── Persist individual question responses ──
if ($assessment_id > 0 && !empty($perQuestion)) {
    // Delete old answers if any (to prevent duplicates on re-submission)
    $delStmt = $conn->prepare("DELETE FROM student_question_responses WHERE assessment_id = ? AND student_id = ?");
    $delStmt->bind_param("ii", $assessment_id, $student_id);
    $delStmt->execute();
    
    // Insert fresh answers
    $insAnswerStmt = $conn->prepare("INSERT INTO student_question_responses (assessment_id, student_id, question_id, is_correct, given_answer) VALUES (?, ?, ?, ?, ?)");
    foreach ($perQuestion as $pq) {
        $qId = $pq['question_id'];
        $isCor = $pq['correct'] ? 1 : 0;
        $given = substr($pq['given'] ?? '', 0, 255);
        $insAnswerStmt->bind_param("iiiis", $assessment_id, $student_id, $qId, $isCor, $given);
        $insAnswerStmt->execute();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results – NAAP</title>
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>" />
    <link rel="stylesheet" href="../../assets/css/student/submit-test.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
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
    <button id="hamburger-btn" class="hamburger"><ion-icon name="menu-outline"></ion-icon></button>
    <div class="nav-mobile"><ul>
        <li><a href="../index.php">Home</a></li>
        <li><a href="organization.php">Organizations</a></li>
        <li><a href="events.php">Events</a></li>
    </ul></div>

    <div class="submit-container">

        <!-- Success icon -->
        <div class="success-icon-wrapper">
            <div class="img-circle"><i class='bx bx-check'></i></div>
        </div>
        <h1 class="page-title"><?= $testLabel ?> Completed!</h1>
        <p class="page-subtitle"><?= htmlspecialchars($eventName) ?> · <?= htmlspecialchars($orgName) ?></p>

        <!-- Score ring + stats -->
        <div class="card-panel score-section">
            <div class="score-title">
                <i class='bx bx-target-lock'></i>
                <span>Your Score</span>
            </div>

            <div class="score-ring-wrap">
                <div class="score-ring">
                    <span class="big-pct"><?= $pct ?>%</span>
                    <span class="big-lbl">SCORE</span>
                </div>
            </div>

            <div class="badge-status" style="background:<?= $statusColor ?>20;color:<?= $statusColor ?>;border:1px solid <?= $statusColor ?>;">
                <?= $status ?>
            </div>

            <div class="stats-grid">
                <div class="stat-box">
                    <h3 style="color:<?= $statusColor ?>"><?= $score ?>/<?= $total ?></h3>
                    <p>Correct Answers</p>
                </div>
                <div class="stat-box">
                    <h3><?= $total ?></h3>
                    <p>Total Questions</p>
                </div>
                <div class="stat-box">
                    <h3 class="cyan-text"><?= $total - $score ?></h3>
                    <p>Incorrect</p>
                </div>
            </div>
        </div>

        <?php if ($test_type !== 'pretest'): ?>
        <div class="card-panel" style="padding:1.25rem;margin-bottom:24px;">
            <div class="section-title" style="margin-bottom:1rem;"><i class='bx bx-bar-chart-alt-2'></i> Pre-Test Comparison</div>
            <?php if ($preTestScore !== null): ?>
            <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
                <div class="stat-box">
                    <h3 style="color:#60a5fa"><?= $preTestScore ?>/<?= $total ?></h3>
                    <p>Pre-Test Score</p>
                </div>
                <div class="stat-box highlight">
                    <h3 style="color:#f1f5f9"><?= $score - $preTestScore >= 0 ? '+' : '' ?><?= $score - $preTestScore ?></h3>
                    <p>Score Change</p>
                </div>
                <div class="stat-box">
                    <h3 style="color:#4ade80"><?= $preTestPct ?>% → <?= $pct ?>%</h3>
                    <p>Progress</p>
                </div>
            </div>
            <p style="margin-top:.9rem;color:#94a3b8;font-size:.9rem;line-height:1.6;">
                <?php if ($preTestSubmittedAt): ?>
                    Pre-test submitted on <?= htmlspecialchars(date('M j, Y g:i A', strtotime($preTestSubmittedAt))) ?>.
                <?php else: ?>
                    Your pre-test score is being used as the baseline for this comparison.
                <?php endif; ?>
            </p>
            <?php else: ?>
            <p style="color:#94a3b8;font-size:.9rem;line-height:1.6;">
                No pre-test result was found for this event, so comparison is unavailable.
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- AI Feedback -->
        <div class="card-panel ai-card">
            <div class="ai-header">
                <div class="ai-icon"><i class='bx bx-brain'></i></div>
                <div class="ai-title">
                    AI Analysis &amp; Insights
                    <span class="ai-badge" style="margin-left:.5rem;"><i class='bx bx-chip'></i> Gemini AI</span>
                </div>
            </div>
            <div class="ai-text"><?= htmlspecialchars($aiFeedback) ?></div>
        </div>

        <!-- Answer review -->
        <?php if (!empty($perQuestion)): ?>
        <div class="card-panel" style="padding:1.25rem;">
            <div class="section-title" style="margin-bottom:1rem;">
                <i class='bx bx-list-check'></i> Answer Review
            </div>
            <?php
            foreach ($perQuestion as $idx => $r):
                $qText = $r['question_text'] ?? "Question " . ($idx + 1);
                $cls = $r['correct'] ? 'correct' : 'wrong';
                $icon = $r['correct'] ? 'bx-check-circle' : 'bx-x-circle';
                $color = $r['correct'] ? '#22c55e' : '#ef4444';
            ?>
            <div class="review-item <?= $cls ?>">
                <i class='bx <?= $icon ?>' style="color:<?= $color ?>;font-size:1.1rem;margin-top:1px;flex-shrink:0;"></i>
                <div>
                    <p style="margin:0;font-weight:600;color:#e2e8f0;"><?= htmlspecialchars($qText) ?></p>
                    <?php if (!$r['correct']): ?>
                    <p style="margin:.2rem 0 0;color:#94a3b8;font-size:.78rem;">
                        Your answer: <span style="color:#fca5a5;"><?= htmlspecialchars($r['given'] ?? 'Not answered') ?></span>
                        &nbsp;·&nbsp; Correct: <span style="color:#86efac;"><?= htmlspecialchars($r['expected']) ?></span>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Event details -->
        <div class="card-panel details-card">
            <div class="section-title"><i class='bx bx-calendar'></i> Event Details</div>
            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Event</span>
                    <span class="detail-val"><?= htmlspecialchars($eventName) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Organization</span>
                    <span class="detail-val"><?= htmlspecialchars($orgName) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Date</span>
                    <span class="detail-val"><?= htmlspecialchars($eventDate) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Test Type</span>
                    <span class="detail-val"><?= $testLabel ?></span>
                </div>
            </div>
        </div>

        <div class="button-group">
            <a href="profile-dashboard.php" class="btn btn-primary">
                <i class='bx bx-home-alt'></i> Return to Dashboard
            </a>
            <a href="events.php" class="btn btn-secondary">
                Browse Events <i class='bx bx-right-arrow-alt' style="color:#0f172a;margin-left:5px;"></i>
            </a>
        </div>
    </div>

    <script src="../../assets/js/index.js"></script>
  <script src="../../assets/js/student/submit-test.js"></script>
</body>
</html>