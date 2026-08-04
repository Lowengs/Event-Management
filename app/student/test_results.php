<?php
/**
 * test_results.php — Displays the result of a pre-test or post-test submission.
 * Query params: event_id, type (pre|post), score, total, redirect (optional)
 */
session_start();
require_once '../../config/gemini.php';

if (empty($_SESSION['student_id'])) { header('Location: login.php'); exit; }

$studentId = (int)$_SESSION['student_id'];
$eventId   = (int)($_GET['event_id'] ?? 0);
$type      = ($_GET['type'] ?? 'pre') === 'post' ? 'post' : 'pre';
$assessmentId = (int)($_GET['assessment_id'] ?? 0);

if (!$eventId) { header('Location: events.php'); exit; }

// Load test results via API
$_GET['event_id'] = $eventId;
$_GET['type'] = $type;
$_GET['assessment_id'] = $assessmentId;
ob_start();
$_GET['action'] = 'get_student_test_results'; require __DIR__ . '/../../config/API/endpoints/index.php';
$apiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

if (!($apiRes['success'] ?? false)) { header('Location: events.php'); exit; }

$ev          = is_array($apiRes['event'] ?? null) ? $apiRes['event'] : [];
$score       = (int)($apiRes['score'] ?? 0);
$total       = max(1, (int)($apiRes['total'] ?? 1));
$submittedAt = $apiRes['submitted_at'] ?? date('Y-m-d H:i:s');
$pct         = round(($score / $total) * 100);

$preResult   = $apiRes['pre_result'] ?? null;
$postResult  = $apiRes['post_result'] ?? null;
$attLogIn    = $apiRes['att_login'] ?? null;
$attLogOut   = $apiRes['att_logout'] ?? null;

$preScore       = $preResult ? (int)$preResult['Score'] : null;
$preSubmittedAt = $preResult ? $preResult['SubmittedAt'] : null;
$prePct         = ($preScore !== null && $total > 0) ? round(($preScore / $total) * 100) : null;

$postScore       = $postResult ? (int)$postResult['Score'] : null;
$postSubmittedAt = $postResult ? $postResult['SubmittedAt'] : null;
$postPct         = ($postScore !== null && $total > 0) ? round(($postScore / $total) * 100) : null;

// Participation combines the completed presence confirmations and liveness
// (anti-spoofing) challenges for this event. Missed presence checks are kept
// on the attendance record, so they reduce the displayed rate.
$conn->query("CREATE TABLE IF NOT EXISTS student_verification_checks (
  VerificationId INT AUTO_INCREMENT PRIMARY KEY, EventId INT NOT NULL, UserId INT NOT NULL,
  CheckType VARCHAR(20) NOT NULL, TriggeredAt DATETIME NOT NULL, CompletedAt DATETIME NOT NULL,
  UNIQUE KEY verification_once (EventId, UserId, CheckType, TriggeredAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$verificationCounts = ['presence_completed' => 0, 'antispoof_completed' => 0];
$verificationStmt = $conn->prepare("SELECT
    SUM(CheckType = 'presence') AS presence_completed,
    SUM(CheckType = 'antispoof') AS antispoof_completed
  FROM student_verification_checks WHERE EventId = ? AND UserId = ?");
if ($verificationStmt) {
  $verificationStmt->bind_param('ii', $eventId, $studentId);
  $verificationStmt->execute();
  $verificationCounts = array_merge($verificationCounts, $verificationStmt->get_result()->fetch_assoc() ?: []);
  $verificationStmt->close();
}
$attendanceVerification = $conn->query("SELECT COALESCE(SUM(PresenceChecksPassed),0) AS passed, COALESCE(SUM(PresenceChecksMissed),0) AS missed FROM attendance WHERE EventId = $eventId AND UserId = $studentId")->fetch_assoc() ?: [];
$presenceCompleted = (int)($verificationCounts['presence_completed'] ?? 0);
$antiSpoofCompleted = (int)($verificationCounts['antispoof_completed'] ?? 0);
$completedChecks = max($presenceCompleted + $antiSpoofCompleted, (int)($attendanceVerification['passed'] ?? 0));
$missedChecks = (int)($attendanceVerification['missed'] ?? 0);
$totalChecks = $completedChecks + $missedChecks;
$participationRate = $totalChecks > 0 ? (int)round(($completedChecks / $totalChecks) * 100) : null;

// Grade label
if ($pct >= 80)      { $grade = 'Excellent'; $gradeColor = '#4ade80'; }
elseif ($pct >= 60)  { $grade = 'Good';      $gradeColor = '#60a5fa'; }
elseif ($pct >= 40)  { $grade = 'Fair';      $gradeColor = '#fbbf24'; }
else                  { $grade = 'Needs Improvement'; $gradeColor = '#ef4444'; }

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

if ($type === 'post' && $preScore !== null) {
  $aiFeedback = generateComparisonInsight($eventName, $preScore, $score, $total);
}
if (!empty($ev['AntiSpoofActive']) || !empty($ev['PresenceCheckActive'])) {
  $aiFeedback .= "\n\nLive verification is active for this event: " . (!empty($ev['AntiSpoofActive']) ? 'anti-spoofing' : '') . (!empty($ev['AntiSpoofActive']) && !empty($ev['PresenceCheckActive']) ? ' and ' : '') . (!empty($ev['PresenceCheckActive']) ? 'periodic presence check' : '') . '.';
}

// Format event details
$dt      = !empty($ev['EventDateTime']) ? new DateTime($ev['EventDateTime']) : null;
$dateStr = $dt ? $dt->format('n/j/Y') : 'TBA';
$timeStr = '';
if ($dt) {
    $end   = !empty($ev['EndDateTime']) ? new DateTime($ev['EndDateTime']) : null;
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
  <script src="../../assets/js/student/test_results.js?v=<?= time() ?>"></script>
</head>
<body style="padding-top: 30px;">

<div class="shell">

  <!-- Top Action Navigation Buttons -->
  <div class="actions" style="margin-top:0;margin-bottom:24px;gap:12px;flex-wrap:wrap;"> 
    <a href="profile-dashboard.php" class="btn-secondary-lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Dashboard
    </a>
    <a href="profile-dashboard.php?tab=registrations" class="btn-primary-lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      My Registrations
    </a>
   
    <a href="events.php" class="btn-secondary-lg">
      Browse Events
    </a>
  </div>

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
    <div class="score-pct"><?= $pct . '%' ?></div>
  
    <div class="grade-badge"><?= htmlspecialchars($grade) ?></div>
    <div class="score-stats">
      <div class="stat-box">
        <span class="val"><?= $score ?>/<?= $total ?></span>
        <span class="lbl">Questions Answered</span>
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

  <!-- Participation Verification Summary -->
  <div class="ai-card" style="margin-bottom:24px;">
    <div class="ai-header">
      <div class="ai-icon">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
      </div>
      <div><div class="ai-title">Participation Verification</div></div>
    </div>
    <div class="score-stats" style="margin-top:16px;">
      <div class="stat-box highlight" style="flex:1;">
        <span class="val"><?= $participationRate === null ? '—' : $participationRate . '%' ?></span>
        <span class="lbl">Participation Rate</span>
      </div>
      <div class="stat-box" style="flex:1;">
        <span class="val"><?= $presenceCompleted ?></span>
        <span class="lbl">Presence Checks Completed</span>
      </div>
      <div class="stat-box" style="flex:1;">
        <span class="val"><?= $antiSpoofCompleted ?></span>
        <span class="lbl">Anti-Spoofing Completed</span>
      </div>
      <div class="stat-box" style="flex:1;">
        <span class="val"><?= $missedChecks ?></span>
        <span class="lbl">Checks Missed</span>
      </div>
    </div>
    <p class="ai-body" style="margin-top:14px;"><?= $totalChecks > 0 ? $completedChecks . ' completed out of ' . $totalChecks . ' verification check' . ($totalChecks === 1 ? '' : 's') . '.' : 'No presence or anti-spoofing checks have been recorded for this event yet.' ?></p>
  </div>

  <!-- Pre-Test & Post-Test Overall Summary Card -->
  <div class="ai-card" style="margin-bottom:24px;">
    <div class="ai-header">
      <div class="ai-icon">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      </div>
      <div>
        <div class="ai-title">Pre-Test &amp; Post-Test Performance Summary</div>
      </div>
    </div>
    <div class="score-stats" style="margin-top:16px;">
      <!-- Pre-Test Box -->
      <div class="stat-box" style="flex:1;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);padding:16px;border-radius:12px;">
        <span class="lbl" style="color:#a78bfa;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">PRE-TEST RESULT</span>
        <div style="font-size:1.6rem;font-weight:800;color:#f8fafc;margin:6px 0;">
          <?= $preScore !== null ? $preScore . '/' . $total . ' (' . $prePct . '%)' : 'Not Taken' ?>
        </div>
        <span class="lbl" style="font-size:11px;color:#94a3b8;">
          <?= $preSubmittedAt ? 'Taken on ' . date('M j, g:i A', strtotime($preSubmittedAt)) : 'No pre-test recorded' ?>
        </span>
      </div>

      <!-- Post-Test Box -->
      <div class="stat-box highlight" style="flex:1;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);padding:16px;border-radius:12px;">
        <span class="lbl" style="color:#60a5fa;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">POST-TEST RESULT</span>
        <div style="font-size:1.6rem;font-weight:800;color:#38bdf8;margin:6px 0;">
          <?= $postScore !== null ? $postScore . '/' . $total . ' (' . $postPct . '%)' : 'Not Taken' ?>
        </div>
        <span class="lbl" style="font-size:11px;color:#94a3b8;">
          <?= $postSubmittedAt ? 'Taken on ' . date('M j, g:i A', strtotime($postSubmittedAt)) : 'No post-test recorded' ?>
        </span>
      </div>
    </div>
  </div>

  <!-- AI Insights -->
  <?php
    $logInTime  = $attLogIn  ? date('g:i A', strtotime($attLogIn['Timestamp']))  : 'Not Recorded';
    $logOutTime = $attLogOut ? date('g:i A', strtotime($attLogOut['Timestamp'])) : 'Not Recorded';
  ?>
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
    
    <div style="margin-top:14px;padding:12px 16px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
        <div>
            <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;display:block;">Check-In (Log In) Time</span>
            <strong style="color:#10b981;font-size:14px;"><?= $logInTime ?></strong>
        </div>
        <div>
            <span style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;display:block;">Check-Out (Log Out) Time</span>
            <strong style="color:#38bdf8;font-size:14px;"><?= $logOutTime ?></strong>
        </div>
    </div>
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



</div>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
