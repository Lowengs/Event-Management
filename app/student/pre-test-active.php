<?php
/**
 * Active Student Assessment Page (Pre-Test & Post-Test)
 * Uses GETassessment_questions API endpoint for questions and info
 */
session_start();

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id    = (int)$_SESSION['student_id'];
$assessment_id = (int)($_GET['assessment_id'] ?? 0);
$event_id      = (int)($_GET['event_id'] ?? 0);
$type          = strtolower($_GET['type'] ?? 'pretest');
$isPre         = (strpos($type, 'pre') !== false);
$typeStr       = $isPre ? 'pretest' : 'posttest';

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

// 2. Fetch Assessment & Questions via API
ob_start();
$_GET['assessment_id'] = $assessment_id;
$_GET['event_id']      = $event_id;
$_GET['type']          = $typeStr;
$_GET['action']        = 'get_assessment_questions';
require __DIR__ . '/../../config/API/endpoints/index.php';
$assessApi = json_decode(ob_get_clean() ?: '[]', true) ?: [];

if (!($assessApi['success'] ?? true) && empty($assessApi['questions']) && empty($assessApi['assessment'])) {
    header("Location: assessment_error.php?reason=not_found&event_id={$event_id}&type={$typeStr}");
    exit;
}

$assessment = $assessApi['assessment'] ?? [];
$questions  = $assessApi['questions'] ?? [];

if (empty($questions)) {
    // Generate default questions if none exist in DB yet
    $questions = [
        ['question_id' => 1, 'question_text' => 'What is the primary objective of this event topic?', 'option_a' => 'Gain knowledge and industry skills', 'option_b' => 'Skip classes', 'option_c' => 'Ignore aviation guidelines', 'option_d' => 'None of the above', 'correct_answer' => 'A'],
        ['question_id' => 2, 'question_text' => 'Which safety standard is most critical in flight operations?', 'option_a' => 'Standard Operating Procedures (SOP)', 'option_b' => 'Guesswork', 'option_c' => 'Speed over safety', 'option_d' => 'Unchecked checklists', 'correct_answer' => 'A'],
        ['question_id' => 3, 'question_text' => 'What role does communication play in air traffic management?', 'option_a' => 'Ensures clear, error-free instructions', 'option_b' => 'Creates noise', 'option_c' => 'Optional step', 'option_d' => 'Delayed feedback', 'correct_answer' => 'A'],
        ['question_id' => 4, 'question_text' => 'How does pre-test assessment benefit student evaluation?', 'option_a' => 'Measures baseline understanding prior to event', 'option_b' => 'Assigns final grades', 'option_c' => 'Replaces attendance', 'option_d' => 'No purpose', 'correct_answer' => 'A'],
        ['question_id' => 5, 'question_text' => 'What is expected of attendees after completing the session?', 'option_a' => 'Apply learned principles and complete post-test', 'option_b' => 'Forget course material', 'option_c' => 'Disregard feedback', 'option_d' => 'None of the above', 'correct_answer' => 'A']
    ];
}

$timeLimit = min(30, max(1, (int)($assessment['time_limit'] ?? 30)));
$testTitle = $isPre ? 'Pre-Test Assessment' : 'Post-Test Assessment';
$testBadge = $isPre ? 'Pre-Test' : 'Post-Test';
$assessId  = $assessment['assessment_id'] ?? $assessment_id;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="icon" href="../../assets/img/philsca.png">
</head>
<body style="background:#0b1120;color:#f8fafc;font-family:'Inter',sans-serif;min-height:100vh;display:flex;flex-direction:column;">
    <nav style="background:rgba(15,23,42,0.95);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,0.08);padding:16px 32px;position:sticky;top:0;z-index:100;height:auto !important;">
        <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;width:100%;">
            <div style="display:flex;align-items:center;gap:14px;">
                <img src="../../assets/img/naap logo.png" alt="NAAP Logo" style="height:36px;margin:0;">
                <span style="font-weight:700;font-size:1.1rem;letter-spacing:-0.3px;color:#fff;">NAAP Student Portal</span>
            </div>
            <div style="display:flex;align-items:center;gap:16px;">
                <span style="background:rgba(245,158,11,0.15);color:#fbbf24;border:1px solid rgba(245,158,11,0.35);font-weight:700;font-size:0.9rem;padding:8px 18px;border-radius:30px;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 12px rgba(245,158,11,0.15);">
                    <i class='bx bx-timer' style="font-size:1.2rem;color:#f59e0b;"></i> 
                    <span style="letter-spacing:0.5px;">Time Left:</span> 
                    <span id="timer" style="font-family:Consolas,Monaco,'Courier New',monospace;font-size:1.05rem;font-weight:800;letter-spacing:2px;margin-left:6px;color:#ffffff;background:rgba(0,0,0,0.35);padding:3px 10px;border-radius:6px;border:1px solid rgba(245,158,11,0.3);"><?= sprintf('%02d:00', $timeLimit) ?></span>
                </span>
            </div>
        </div>
    </nav>

    <main style="flex:1;max-width:860px;width:100%;margin:30px auto;padding:0 20px;background:transparent !important;">
        <form action="submit-test.php" method="POST" id="testForm">
            <input type="hidden" name="assessment_id" value="<?= $assessId ?>">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <input type="hidden" name="type" value="<?= htmlspecialchars($typeStr) ?>">
            <input type="hidden" name="total" value="<?= count($questions) ?>">

            <div style="background:rgba(30,41,59,0.7);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:32px;box-shadow:0 20px 40px rgba(0,0,0,0.3);backdrop-filter:blur(16px);margin-bottom:24px;">
                <h2 style="font-size:1.5rem;font-weight:800;color:#fff;margin:0 0 8px;"><?= htmlspecialchars($assessment['title'] ?? $testTitle) ?></h2>
                <p style="color:#94a3b8;margin:0;font-size:0.9rem;">Answer all <?= count($questions) ?> questions carefully before submitting.</p>
            </div>

            <?php foreach ($questions as $idx => $q): ?>
                <?php $qId = $q['question_id'] ?? ($idx + 1); ?>
                <div style="background:rgba(30,41,59,0.7);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:24px;margin-bottom:20px;backdrop-filter:blur(12px);">
                    <div style="font-size:0.85rem;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Question <?= $idx + 1 ?> of <?= count($questions) ?></div>
                    <h3 style="font-size:1.05rem;font-weight:700;color:#f8fafc;margin:0 0 18px;line-height:1.5;"><?= htmlspecialchars($q['question_text']) ?></h3>

                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <?php foreach (['A' => $q['option_a'], 'B' => $q['option_b'], 'C' => $q['option_c'], 'D' => $q['option_d']] as $key => $opt): ?>
                            <?php if (!empty($opt)): ?>
                                <label style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.06);border-radius:10px;cursor:pointer;transition:all 0.2s;">
                                    <input type="radio" name="answer_<?= $qId ?>" value="<?= $key ?>" required style="accent-color:#2563eb;">
                                    <span style="font-weight:700;color:#60a5fa;min-width:20px;"><?= $key ?>.</span>
                                    <span style="color:#cbd5e1;font-size:0.95rem;"><?= htmlspecialchars($opt) ?></span>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div style="display:flex;justify-content:flex-end;margin-top:30px;margin-bottom:60px;">
                <button type="submit" style="padding:14px 36px;border-radius:12px;border:none;font-weight:800;font-size:1rem;color:#fff;background:linear-gradient(135deg,#16a34a,#15803d);box-shadow:0 4px 14px rgba(22,163,74,0.4);cursor:pointer;transition:all 0.2s;">
                    Submit Assessment <i class='bx bx-check-circle'></i>
                </button>
            </div>
        </form>
    </main>

    <script>
        let timeLeft = <?= $timeLimit ?> * 60;
        const timerEl = document.getElementById('timer');
        const timerInterval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                document.getElementById('testForm').submit();
                return;
            }
            timeLeft--;
            const mins = Math.floor(timeLeft / 60);
            const secs = timeLeft % 60;
            timerEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }, 1000);
    </script>
</body>
</html>
