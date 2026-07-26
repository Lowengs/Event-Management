<?php
/**
 * test_results.php — Displays the result of a pre-test or post-test submission.
 * Query params: event_id, type (pre|post), score, total, redirect (optional)
 */
session_start();
require_once '../../config/db.php';
require_once '../../config/gemini.php';

if (empty($_SESSION['student_id'])) { header('Location: login.php'); exit; }

$studentId = (int)$_SESSION['student_id'];
$eventId   = (int)($_GET['event_id'] ?? 0);
$type      = ($_GET['type'] ?? 'pre') === 'post' ? 'post' : 'pre';

if (!$eventId) { header('Location: events.php'); exit; }

// Load event
$ev = $conn->query("SELECT e.*, o.OrgName FROM event e LEFT JOIN organization o ON e.OrgId=o.OrgId WHERE e.EventId=$eventId")->fetch_assoc();
if (!$ev) { header('Location: events.php'); exit; }

// Load score from DB (most recent for this student/event)
$table  = $type === 'pre' ? 'event_pretest' : 'event_posttest';
$result = $conn->query("SELECT Score, SubmittedAt FROM $table WHERE EventId=$eventId AND UserId=$studentId ORDER BY SubmittedAt DESC LIMIT 1")->fetch_assoc();
$score  = $result ? (int)$result['Score'] : 0;

// Determine total questions — post-test is a feedback survey (always 5 qs)
$total  = 5;
$pct    = $total > 0 ? round(($score / $total) * 100) : 0;
$submittedAt = $result ? $result['SubmittedAt'] : date('Y-m-d H:i:s');

$preScore = null;
$preSubmittedAt = null;
$prePct = null;
if ($type === 'post') {
  $preResult = $conn->query("SELECT Score, SubmittedAt FROM event_pretest WHERE EventId=$eventId AND UserId=$studentId ORDER BY SubmittedAt DESC LIMIT 1")->fetch_assoc();
  if ($preResult) {
    $preScore = (int)$preResult['Score'];
    $preSubmittedAt = $preResult['SubmittedAt'];
    $prePct = $total > 0 ? round(($preScore / $total) * 100) : 0;
  }
}

// Grade label
if ($pct >= 80)      { $grade = 'Excellent'; $gradeColor = '#4ade80'; }
elseif ($pct >= 60)  { $grade = 'Good';      $gradeColor = '#60a5fa'; }
elseif ($pct >= 40)  { $grade = 'Fair';      $gradeColor = '#fbbf24'; }
else                  { $grade = 'Needs Improvement'; $gradeColor = '#ef4444'; }

// For post-test, keep the actual score so it can be compared against the pre-test.
if ($type === 'post') { $grade = 'Completed'; $gradeColor = '#4ade80'; }

// AI Feedback
$eventName   = $ev['EventName'] ?? 'this event';
$eventDesc   = $ev['EventDescription'] ?? $ev['EventDetails'] ?? '';
$aiFeedback  = generateAIFeedback(
    $eventName,
    $score,
    $total,
    [],
    $type
);

if ($type === 'post') {
  $aiFeedback = generateComparisonInsight($eventName, $preScore, $score, $total);
}

// Format event details
$dt      = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : null;
$dateStr = $dt ? $dt->format('n/j/Y') : 'TBA';
$timeStr = '';
if ($dt) {
    $end   = $ev['EndDateTime'] ? new DateTime($ev['EndDateTime']) : null;
    $timeStr = $dt->format('g:i A') . ($end ? ' – ' . $end->format('g:i A') : '');
}
$testLabel = $type === 'pre' ? 'Pre-Test' : 'Post-Test';
$nextUrl   = 'event_detail.php?id=' . $eventId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Test Completed – <?= htmlspecialchars($eventName) ?></title>
  <link rel="stylesheet" href="../../assets/css/index.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="icon" href="../../assets/img/philsca.png">
  
  <link rel="stylesheet" href="../../assets/css/student/test_results.css?<?= time() ?>" />
</head>
<body>
<nav>
  <div class="nav-logo">
    <img src="../../assets/img/naap logo.png" alt="NAAP">
    <span>NAAP Student Portal</span>
  </div>
  <div class="nav-links">
    <a href="../index.php">Home</a>
    <a href="events.php">Events</a>
    <a href="profile-dashboard.php">Dashboard</a>
  </div>
  <a href="<?= htmlspecialchars($nextUrl) ?>" class="nav-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    Back to Event
  </a>
</nav>

<div class="shell">

  <!-- Hero -->
  <div class="hero">
    <div class="check-circle">
      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h1>Test Completed!</h1>
    <p>Your <?= $testLabel ?> results for <strong style="color:#94a3b8;"><?= htmlspecialchars($eventName) ?></strong></p>
  </div>

  <!-- Score Card -->
  <div class="score-card" style="text-align:center;">
    <div class="score-label" style="justify-content:center;">
      <span></span>Your Score
    </div>
    <div class="score-pct"><?= $type === 'post' ? '✓' : $pct . '%' ?></div>
    <div class="grade-badge"><?= htmlspecialchars($grade) ?></div>
    <div class="score-stats">
      <div class="stat-box">
        <span class="val"><?= $type === 'post' ? '5' : $score ?>/<?= $total ?></span>
        <span class="lbl"><?= $type === 'post' ? 'Questions Answered' : 'Correct Answers' ?></span>
      </div>
      <div class="stat-box">
        <span class="val"><?= $total ?></span>
        <span class="lbl">Total Questions</span>
      </div>
      <div class="stat-box highlight">
        <span class="val"><?= date('g:i A', strtotime($submittedAt)) ?></span>
        <span class="lbl">Submitted At</span>
      </div>
    </div>
  </div>

  <?php if ($type === 'post'): ?>
  <div class="ai-card" style="margin-bottom:24px;">
    <div class="ai-header">
      <div class="ai-icon">
        <svg viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 14l3-3 4 4 7-7"/></svg>
      </div>
      <div>
        <div class="ai-title">Before Seminar vs After Seminar</div>
      </div>
    </div>
    <?php if ($preScore !== null): ?>
      <div class="score-stats" style="margin-top:12px;">
        <div class="stat-box">
          <span class="val"><?= $preScore ?>/<?= $total ?></span>
          <span class="lbl">Before Seminar</span>
        </div>
        <div class="stat-box highlight">
          <span class="val"><?= $score - $preScore >= 0 ? '+' : '' ?><?= $score - $preScore ?></span>
          <span class="lbl">Score Change</span>
        </div>
        <div class="stat-box">
          <span class="val"><?= $prePct ?>% → <?= $pct ?>%</span>
          <span class="lbl">After Seminar</span>
        </div>
      </div>
      <p class="ai-body" style="margin-top:14px;">
        <?php if ($preSubmittedAt): ?>
          Pre-test submitted on <?= htmlspecialchars(date('M j, Y g:i A', strtotime($preSubmittedAt))) ?>.
        <?php else: ?>
          Pre-test score used as the comparison baseline.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p class="ai-body" style="margin-top:6px;">No pre-test result was found for this event, so comparison is unavailable.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- AI Insights -->
    <div class="ai-card">
    <div class="ai-header">
      <div class="ai-icon">
        <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      </div>
      <div>
        <div class="ai-title"><?= $type === 'post' ? 'AI Insight' : 'AI Analysis &amp; Insights' ?></div>
      </div>
    </div>
    <p class="ai-body"><?= nl2br(htmlspecialchars($aiFeedback)) ?></p>
  </div>

  <!-- Event Details -->
  <div class="event-card">
    <div class="event-card-header">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Event Details
    </div>
    <div class="event-details-grid">
      <div class="event-detail-item">
        <label>Event Name</label>
        <strong><?= htmlspecialchars($eventName) ?></strong>
      </div>
      <div class="event-detail-item">
        <label>Organization</label>
        <strong><?= htmlspecialchars($ev['OrgName'] ?? 'NAAP') ?></strong>
      </div>
      <div class="event-detail-item">
        <label>Date</label>
        <strong><?= $dateStr ?></strong>
      </div>
      <?php if ($timeStr): ?>
      <div class="event-detail-item">
        <label>Time</label>
        <strong><?= htmlspecialchars($timeStr) ?></strong>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Actions -->
  <div class="actions">
    <a href="profile-dashboard.php?tab=registrations" class="btn-primary-lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      My Registrations
    </a>
    <a href="<?= htmlspecialchars($nextUrl) ?>" class="btn-secondary-lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Event
    </a>
    <a href="events.php" class="btn-secondary-lg">
      Browse Events
    </a>
  </div>

</div>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
