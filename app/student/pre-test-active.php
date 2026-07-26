<?php
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];
$assessment_id = (int)($_GET['assessment_id'] ?? 0);

if ($assessment_id === 0) { 
    header('Location: profile-dashboard.php'); 
    exit; 
}

// Fetch assessment info
$stmt = $conn->prepare("
    SELECT a.*, e.EventName, e.EventDateTime, o.OrgName
    FROM assessments a
    JOIN event e ON a.event_id = e.EventId
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    WHERE a.assessment_id = ?
");
$stmt->bind_param('i', $assessment_id);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

if (!$assessment) {
    header('Location: profile-dashboard.php');
    exit;
}

// Fetch questions
$qStmt = $conn->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY question_id ASC");
$qStmt->bind_param('i', $assessment_id);
$qStmt->execute();
$qResult = $qStmt->get_result();

$questions = [];
while ($row = $qResult->fetch_assoc()) {
    $opts = [];
    if($row['option_a']) $opts['A'] = $row['option_a'];
    if($row['option_b']) $opts['B'] = $row['option_b'];
    if($row['option_c']) $opts['C'] = $row['option_c'];
    if($row['option_d']) $opts['D'] = $row['option_d'];

    $questions[] = [
        'q' => $row['question_text'],
        'opts' => $opts,
        'points' => $row['points'],
        'question_id' => $row['question_id']
    ];
}
$total_questions = count($questions);

$eventName = $assessment['EventName'];
$orgName = $assessment['OrgName'] ?? 'Organization';
$eventDate = !empty($assessment['EventDateTime']) ? date('M j, Y', strtotime($assessment['EventDateTime'])) : 'TBA';
$testTitle = $assessment['title'];

$attendanceStmt = $conn->prepare("SELECT AttendanceId FROM attendance WHERE EventId = ? AND UserId = ? AND LOWER(AttendanceStatus) = 'present' LIMIT 1");
$attendanceStmt->bind_param('ii', $assessment['event_id'], $student_id);
$attendanceStmt->execute();
$hasAttendance = (bool)$attendanceStmt->get_result()->fetch_assoc();

if (!$hasAttendance) {
    echo "";
    exit;
}

// Fetch student profile photo / initials
$studentQuery = $conn->query("SELECT first_name, last_name, profile_photo FROM user WHERE UserId = $student_id LIMIT 1")->fetch_assoc();
$initials = strtoupper(substr($studentQuery['first_name'],0,1) . substr($studentQuery['last_name'],0,1));
$hasPhoto = !empty($studentQuery['profile_photo']) && file_exists(__DIR__ . '/../../' . $studentQuery['profile_photo']);
$photoUrl = $hasPhoto ? '../../' . $studentQuery['profile_photo'] : '';

$testLabel = 'Pre-Test';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($testLabel) ?> – <?= htmlspecialchars($eventName) ?></title>
    <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>" />
    <link rel="stylesheet" href="../../assets/css/student/pre-test-active.css" />
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
    <button id="hamburger-btn" class="hamburger" aria-label="Open menu"><ion-icon name="menu-outline"></ion-icon></button>
    <div class="nav-mobile"><ul>
        <li><a href="../index.php">Home</a></li>
        <li><a href="organization.php">Organizations</a></li>
        <li><a href="events.php">Events</a></li>
    </ul></div>

    <div class="active-test-container">

        <!-- Header Info Card -->
        <div class="test-card">
            <div class="header-top">
                <div>
                    <div class="ai-badge"><i class='bx bx-brain'></i> AI-Generated Questions</div>
                    <h1 class="test-title"><?= htmlspecialchars($eventName) ?></h1>
                    <p class="test-org"><?= htmlspecialchars($orgName) ?></p>
                </div>
                <div class="badge-pretest"><?= $testLabel ?></div>
            </div>
            <div class="header-bottom">
                <div class="event-time">
                    <i class='bx bx-calendar'></i>
                    <span><?= $eventDate ?></span>
                </div>
                <div class="badge-timer">
                    <i class='bx bx-time-five'></i>
                    <span id="countdown-timer">30:00</span>
                </div>
            </div>
        </div>

        <!-- Progress -->
        <div class="test-card">
            <div class="progress-top">
                <span>Progress</span>
                <span id="progress-text">Question 1 of <?= $total_questions ?></span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="progress-bar" style="width:<?= (1/$total_questions)*100 ?>%;"></div>
            </div>
            <!-- Question dot nav -->
            <div class="question-nav" id="questionNav">
                <?php for ($i = 1; $i <= $total_questions; $i++): ?>
                <div class="q-dot <?= $i === 1 ? 'current' : '' ?>" id="dot-<?= $i ?>" onclick="goToQuestion(<?= $i ?>)"><?= $i ?></div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Question Form -->
        <form action="submit-test.php" method="POST" id="testForm">
            <input type="hidden" name="assessment_id" value="<?= htmlspecialchars($assessment_id) ?>">
            <input type="hidden" name="event_id" value="<?= htmlspecialchars($assessment['event_id']) ?>">
            <input type="hidden" name="type" value="pretest">
            <input type="hidden" name="total" value="<?= $total_questions ?>">
            <input type="hidden" name="tab_switches" id="hiddenTabSwitches" value="0">
            <input type="hidden" name="engagement_score" id="hiddenEngagementScore" value="100">
            <input type="hidden" name="monitoring_flagged" id="hiddenMonitoringFlagged" value="0">

            <div class="test-card">
                <?php foreach ($questions as $index => $q):
                    $qNum = $index + 1;
                ?>
                <div class="question-section <?= $qNum === 1 ? 'active' : '' ?>" id="question-<?= $qNum ?>" data-question="<?= $qNum ?>">
                    <div class="question-header">
                        <div class="question-num"><?= $qNum ?></div>
                        <div class="question-label">Question <?= $qNum ?></div>
                    </div>
                    <h2 class="question-text"><?= nl2br(htmlspecialchars($q['q'])) ?></h2>
                    <div class="options-list">
                        <?php foreach ($q['opts'] as $optKey => $optText):
                            $optId = "q{$qNum}_opt{$optKey}";
                        ?>
                        <label class="option-label" for="<?= $optId ?>">
                            <input type="radio" name="answer_<?= $q['question_id'] ?>" id="<?= $optId ?>" value="<?= htmlspecialchars($optKey) ?>" onchange="markAnswered(<?= $qNum ?>)" required>
                            <span class="radio-custom"></span>
                            <span class="option-text"><b><?= htmlspecialchars($optKey) ?>.</b> <?= htmlspecialchars($optText) ?></span>
                        </label>
                        <?php endforeach; ?>
                        <?php if (empty($q['opts'])): // True / False fallback ?>
                            <label class="option-label" for="q<?= $qNum ?>_True">
                                <input type="radio" name="answer_<?= $q['question_id'] ?>" id="q<?= $qNum ?>_True" value="True" onchange="markAnswered(<?= $qNum ?>)" required>
                                <span class="radio-custom"></span>
                                <span class="option-text">True</span>
                            </label>
                            <label class="option-label" for="q<?= $qNum ?>_False">
                                <input type="radio" name="answer_<?= $q['question_id'] ?>" id="q<?= $qNum ?>_False" value="False" onchange="markAnswered(<?= $qNum ?>)" required>
                                <span class="radio-custom"></span>
                                <span class="option-text">False</span>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if ($total_questions === 0): ?>
                    <p style="text-align: center; color: #64748b; padding: 20px;">No questions found for this assessment.</p>
                <?php endif; ?>
            </div>

            <!-- Footer Actions -->
            <div class="test-actions-footer">
                <button type="button" class="btn-action btn-prev" id="btnPrev" disabled>
                    <i class='bx bx-chevron-left'></i> Previous
                </button>
                <button type="button" class="btn-action btn-next" id="btnNext">
                    Next <i class='bx bx-chevron-right'></i>
                </button>
                <button type="submit" class="btn-action btn-next finish-test" id="btnSubmit" style="display:none;">
                    Submit Test <i class='bx bx-check'></i>
                </button>
            </div>
        </form>
    </div>

    <script>
    const totalQuestions = <?= $total_questions ?>;
    let currentQuestion = 1;
    const answered = new Set();

    const btnPrev    = document.getElementById('btnPrev');
    const btnNext    = document.getElementById('btnNext');
    const btnSubmit  = document.getElementById('btnSubmit');
    const progressBar  = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');

    function goToQuestion(n) {
        document.querySelectorAll('.question-section').forEach(s => s.classList.remove('active'));
        document.getElementById('question-' + n).classList.add('active');
        currentQuestion = n;
        updateView();
    }

    function markAnswered(qNum) {
        answered.add(qNum);
        const dot = document.getElementById('dot-' + qNum);
        dot.classList.remove('current');
        dot.classList.add('answered');
        // Update option label styling
        const container = document.getElementById('question-' + qNum);
        container.querySelectorAll('.option-label').forEach(lbl => lbl.classList.remove('selected'));
        const checked = container.querySelector('input[type="radio"]:checked');
        if (checked) checked.closest('.option-label').classList.add('selected');
    }

    function updateView() {
        const pct = (currentQuestion / totalQuestions) * 100;
        progressBar.style.width = pct + '%';
        progressText.textContent = `Question ${currentQuestion} of ${totalQuestions}`;
        btnPrev.disabled = (currentQuestion === 1);
        if (currentQuestion === totalQuestions) {
            btnNext.style.display = 'none';
            btnSubmit.style.display = 'flex';
        } else {
            btnNext.style.display = 'flex';
            btnSubmit.style.display = 'none';
        }
        // Update dot highlights
        document.querySelectorAll('.q-dot').forEach((d, i) => {
            d.classList.remove('current');
            if ((i + 1) === currentQuestion && !answered.has(i + 1)) d.classList.add('current');
        });
    }

    btnPrev.addEventListener('click', () => { if (currentQuestion > 1) { currentQuestion--; goToQuestion(currentQuestion); } });
    btnNext.addEventListener('click', () => { if (currentQuestion < totalQuestions) { currentQuestion++; goToQuestion(currentQuestion); } });

    // Confirm before submit
    document.getElementById('testForm').addEventListener('submit', function(e) {
        const unanswered = totalQuestions - answered.size;
        if (unanswered > 0) {
            if (!confirm(`You have ${unanswered} unanswered question(s). Submit anyway?`)) e.preventDefault();
        }
    });

    // Auto-submit timer (30 min)
    let timeLeft = 30 * 60;
    const timer = document.getElementById('countdown-timer');
    const timerInterval = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            document.getElementById('testForm').submit();
            return;
        }
        timeLeft--;
        const m = String(Math.floor(timeLeft / 60)).padStart(2,'0');
        const s = String(timeLeft % 60).padStart(2,'0');
        timer.textContent = `${m}:${s}`;
        if (timeLeft <= 60) timer.style.color = '#ef4444';
    }, 1000);
    // Behavior Monitoring & Engagement Tracking
    let tabSwitches = 0;
    const maxTabSwitches = 3;
    let isTestSubmitted = false;

    // Engagement metrics
    let activeSeconds = 0;
    let totalSeconds = 0;
    let idleSeconds = 0;
    let interactionCount = 0;
    let lastInteractionTime = Date.now();

    function recordInteraction() {
        interactionCount++;
        lastInteractionTime = Date.now();
    }
    window.addEventListener('mousemove', recordInteraction);
    window.addEventListener('keypress', recordInteraction);
    window.addEventListener('scroll', recordInteraction);
    window.addEventListener('click', recordInteraction);

    const trackingInterval = setInterval(() => {
        totalSeconds++;
        const secondsSinceLastInteraction = (Date.now() - lastInteractionTime) / 1000;
        if (secondsSinceLastInteraction < 3) {
            activeSeconds++;
            idleSeconds = 0;
        } else {
            idleSeconds++;
        }

        let activeRatio = totalSeconds > 0 ? (activeSeconds / totalSeconds) : 1;
        let interactionDensity = Math.min(1.2, 0.5 + (interactionCount / (totalSeconds * 2 + 1)));
        let rawScore = Math.round(activeRatio * interactionDensity * 100);
        let finalScore = Math.max(0, rawScore - (tabSwitches * 25));
        finalScore = Math.min(100, finalScore);

        const elEngagement = document.getElementById('widgetEngagement');
        if (elEngagement) {
            elEngagement.textContent = `${finalScore}%`;
            if (finalScore >= 80) elEngagement.style.color = '#10b981';
            else if (finalScore >= 50) elEngagement.style.color = '#f59e0b';
            else elEngagement.style.color = '#ef4444';
        }

        document.getElementById('hiddenEngagementScore').value = finalScore;

        if (idleSeconds === 30) {
            showIdleAlert();
        }
    }, 1000);

    function showIdleAlert() {
        if (document.getElementById('warningOverlay')) return;
        const overlay = document.createElement('div');
        overlay.id = 'warningOverlay';
        overlay.className = 'warning-overlay';
        overlay.innerHTML = `
            <div class="warning-box" style="border-color: #f59e0b;">
                <div class="warning-title" style="color: #f59e0b;"><i class='bx bx-alarm'></i> Inactivity Detected</div>
                <div class="warning-text">You have been inactive for over 30 seconds. Please resume answering your questions.</div>
                <button type="button" class="warning-btn" style="background: #f59e0b;" onclick="dismissWarning()">Resume Test</button>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    function recordTabViolation() {
        if (isTestSubmitted) return;
        tabSwitches++;
        document.getElementById('widgetSwitches').textContent = `${tabSwitches} / ${maxTabSwitches}`;
        document.getElementById('hiddenTabSwitches').value = tabSwitches;

        const widgetStatus = document.getElementById('widgetStatus');
        if (widgetStatus) widgetStatus.innerHTML = `<span style="color: #f43f5e; font-weight: bold;">Violation Detected</span>`;

        const widget = document.getElementById('monitoringWidget');
        if (widget) {
            widget.style.borderColor = '#f43f5e';
            widget.style.backgroundColor = 'rgba(244, 63, 94, 0.15)';
            setTimeout(() => {
                widget.style.borderColor = '#0ea5e9';
                widget.style.backgroundColor = 'rgba(15, 23, 42, 0.9)';
            }, 2000);
        }

        if (tabSwitches >= maxTabSwitches) {
            document.getElementById('hiddenMonitoringFlagged').value = 1;
            autoSubmitTest("Proctoring Limit Exceeded", "You have switched tabs / minimized the window 3 or more times. The test is being automatically submitted.");
        } else {
            showTabWarning();
        }
    }

    function showTabWarning() {
        dismissWarning();
        const overlay = document.createElement('div');
        overlay.id = 'warningOverlay';
        overlay.className = 'warning-overlay';
        overlay.innerHTML = `
            <div class="warning-box">
                <div class="warning-title"><i class='bx bx-error-circle'></i> Tab Switch Warning</div>
                <div class="warning-text">
                    Leaving the test screen is a violation. Switching tabs or losing focus is tracked.
                    <br><br>
                    <strong>Violation Count: ${tabSwitches} / ${maxTabSwitches}</strong>
                    <br><br>
                    <span style="color: #f43f5e; font-weight: 700;">Reaching 3 violations will submit your test immediately.</span>
                </div>
                <button type="button" class="warning-btn" onclick="dismissWarning()">I Understand</button>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    function dismissWarning() {
        const overlay = document.getElementById('warningOverlay');
        if (overlay) overlay.remove();
        const widgetStatus = document.getElementById('widgetStatus');
        if (widgetStatus) widgetStatus.innerHTML = `Status: <span style="color: #38bdf8; font-weight: 600;">Active & Focused</span>`;
        idleSeconds = 0;
        lastInteractionTime = Date.now();
    }
    window.dismissWarning = dismissWarning;

    function autoSubmitTest(title, message) {
        isTestSubmitted = true;
        clearInterval(trackingInterval);
        dismissWarning();
        const overlay = document.createElement('div');
        overlay.id = 'warningOverlay';
        overlay.className = 'warning-overlay';
        overlay.innerHTML = `
            <div class="warning-box" style="border-color: #ef4444;">
                <div class="warning-title" style="color: #ef4444;"><i class='bx bx-lock-alt'></i> Test Locked</div>
                <div class="warning-text">${message}<br><br>Submitting answers now...</div>
                <div style="font-size: 0.8rem; color: #94a3b8;">Please wait...</div>
            </div>
        `;
        document.body.appendChild(overlay);
        setTimeout(() => {
            document.getElementById('testForm').submit();
        }, 2500);
    }

    window.addEventListener('blur', recordTabViolation);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) recordTabViolation();
    });

    document.getElementById('testForm').addEventListener('submit', () => {
        isTestSubmitted = true;
        clearInterval(trackingInterval);
    });
    </script>
    <script src="../../assets/js/index.js"></script>

    <!-- Floating Monitoring Widget -->
    <div id="monitoringWidget" style="position: fixed; bottom: 20px; right: 20px; width: 200px; background: rgba(15, 23, 42, 0.9); border: 2px dashed #0ea5e9; border-radius: 12px; padding: 12px; z-index: 9999; box-shadow: 0 4px 20px rgba(0,0,0,0.4); font-family: 'Inter', sans-serif; backdrop-filter: blur(8px); transition: all 0.3s ease;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
            <span style="display: inline-block; width: 8px; height: 8px; background-color: #10b981; border-radius: 50%; animation: pulse 1.5s infinite;" id="pulseDot"></span>
            <span style="font-size: 10px; font-weight: 700; color: #e2e8f0; letter-spacing: 0.5px; text-transform: uppercase;">Engagement Monitor</span>
        </div>
        <div style="font-size: 13px; color: #94a3b8; margin-bottom: 6px;">
            Active Index: <strong style="color: #10b981; font-size: 14px;" id="widgetEngagement">100%</strong>
        </div>
        <div style="font-size: 12px; color: #94a3b8; margin-bottom: 6px;" id="widgetStatus">
            Status: <span style="color: #38bdf8; font-weight: 600;">Active & Focused</span>
        </div>
        <div style="font-size: 11px; color: #64748b;">
            Tab Switches: <span id="widgetSwitches" style="color: #f43f5e; font-weight: bold;">0 / 3</span>
        </div>
    </div>

    
  <script src="../../assets/js/student/pre-test-active.js"></script>
</body>
</html>

