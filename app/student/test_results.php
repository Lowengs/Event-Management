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

// Live Verification & Continuous Monitoring Stats from API
$presenceCompleted  = (int)($apiRes['presence_completed'] ?? 0);
$antiSpoofCompleted = (int)($apiRes['antispoof_completed'] ?? 0);
$missedChecks       = (int)($apiRes['checks_missed'] ?? 0);
$completedChecks    = (int)($apiRes['completed_checks'] ?? ($presenceCompleted + $antiSpoofCompleted));
$participationRate  = isset($apiRes['participation_rate']) ? (int)$apiRes['participation_rate'] : null;

// Fallback if needed
if ($presenceCompleted === 0 && $antiSpoofCompleted === 0 && isset($conn)) {
    $vCheck = $conn->query("SELECT 
        COALESCE(SUM(CASE WHEN LOWER(CheckType) LIKE '%anti%' OR LOWER(CheckType) LIKE '%spoof%' THEN 1 ELSE 0 END), 0) AS antispoof,
        COALESCE(SUM(CASE WHEN LOWER(CheckType) LIKE '%presence%' OR LOWER(CheckType) LIKE '%continuous%' THEN 1 ELSE 0 END), 0) AS presence
      FROM student_verification_checks WHERE EventId = $eventId AND UserId = $studentId");
    if ($vCheck && $vRow = $vCheck->fetch_assoc()) {
        $antiSpoofCompleted = (int)$vRow['antispoof'];
        $presenceCompleted  = (int)$vRow['presence'];
    }
    $attCheck = $conn->query("SELECT COALESCE(SUM(PresenceChecksPassed),0) AS passed, COALESCE(SUM(PresenceChecksMissed),0) AS missed FROM attendance WHERE EventId = $eventId AND UserId = $studentId")->fetch_assoc() ?: [];
    if ((int)($attCheck['passed'] ?? 0) > $presenceCompleted && $presenceCompleted === 0) {
        $presenceCompleted = (int)$attCheck['passed'];
    }
    $missedChecks = (int)($attCheck['missed'] ?? 0);
    $completedChecks = max($presenceCompleted + $antiSpoofCompleted, (int)($attCheck['passed'] ?? 0));
    $totalChecks = $completedChecks + $missedChecks;
    $participationRate = $totalChecks > 0 ? (int)round(($completedChecks / $totalChecks) * 100) : 100;
}
if ($participationRate === null) $participationRate = 100;


// Determine if event is online
$eventMode  = strtolower(trim($ev['EventMode'] ?? ''));
$eventPlace = strtolower(trim(($ev['EventPlace'] ?? '') . ' ' . ($ev['EventLocation'] ?? '')));
$isOnlineEvent = ($eventMode === 'online' || strpos($eventPlace, 'online') !== false || strpos($eventPlace, 'zoom') !== false || strpos($eventPlace, 'teams') !== false);

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
if (!$isOnlineEvent && (!empty($ev['AntiSpoofActive']) || !empty($ev['PresenceCheckActive']))) {
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
<body style="padding-top: 40px; padding-bottom: 60px;">

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

  <!-- Anti-Spoofing & Continuous Monitoring Performance Summary (Always visible) -->
  <div class="ai-card" style="margin-bottom:24px;">
    <div class="ai-header">
      <div class="ai-icon" style="background:rgba(56,189,248,0.15);color:#38bdf8;border:1px solid rgba(56,189,248,0.3);">
        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
      </div>
      <div>
        <div class="ai-title">Live Verification &amp; Continuous Monitoring Stats</div>
        <p style="margin:2px 0 0;font-size:12px;color:#94a3b8;">Anti-spoofing facial scans and periodic presence checks</p>
      </div>
    </div>
    <div class="score-stats" style="margin-top:16px;">
      <div class="stat-box highlight" style="flex:1;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.25);">
        <span class="val" style="color:#34d399;"><?= $participationRate === null ? ($completedChecks > 0 ? '100%' : '100%') : $participationRate . '%' ?></span>
        <span class="lbl" style="color:#a7f3d0;">Participation Rate</span>
      </div>
      <div class="stat-box" style="flex:1;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);">
        <span class="val" style="color:#38bdf8;"><ion-icon name="camera-outline"></ion-icon> <?= $antiSpoofCompleted ?></span>
        <span class="lbl">Anti-Spoofing Completed</span>
      </div>
      <div class="stat-box" style="flex:1;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);">
        <span class="val" style="color:#818cf8;"><ion-icon name="timer-outline"></ion-icon> <?= $presenceCompleted ?></span>
        <span class="lbl">Continuous Checks Completed</span>
      </div>
      <div class="stat-box" style="flex:1;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);">
        <span class="val" style="color:<?= $missedChecks > 0 ? '#f87171' : '#94a3b8' ?>;"><?= $missedChecks ?></span>
        <span class="lbl">Checks Missed</span>
      </div>
    </div>
    <div style="margin-top:14px;padding:10px 14px;background:rgba(255,255,255,0.03);border-radius:10px;font-size:12.5px;color:#cbd5e1;line-height:1.5;">
      <ion-icon name="bulb-outline" style="color:#fbbf24;"></ion-icon> <strong>Verification Breakdown:</strong> <?= $antiSpoofCompleted ?> Anti-Spoofing facial verification challenge<?= $antiSpoofCompleted === 1 ? '' : 's' ?> (30-min intervals) and <?= $presenceCompleted ?> Continuous Monitoring presence check<?= $presenceCompleted === 1 ? '' : 's' ?> (5-min intervals) successfully recorded.
    </div>
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
      Event Details &amp; Overview
    </div>
    <?php if (!empty($ev['EventPicture'])):
      $eventPicSrc = (strpos($ev['EventPicture'], 'http') === 0 || strpos($ev['EventPicture'], '../../') === 0) ? $ev['EventPicture'] : '../../' . ltrim($ev['EventPicture'], '/');
    ?>
    <div style="width:100%;height:180px;border-radius:12px;overflow:hidden;margin-bottom:16px;background:#0f172a;">
      <img src="<?= htmlspecialchars($eventPicSrc) ?>" alt="<?= htmlspecialchars($eventName) ?> Banner" style="width:100%;height:100%;object-fit:cover;">
    </div>
    <?php endif; ?>

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

    <?php if (!empty($ev['EventDescription'])): ?>
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,0.08);">
      <label style="font-size:11px;color:#94a3b8;font-weight:600;text-transform:uppercase;display:block;margin-bottom:4px;">Event Description</label>
      <p style="margin:0;font-size:0.92rem;color:#cbd5e1;line-height:1.5;"><?= nl2br(htmlspecialchars($ev['EventDescription'])) ?></p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Bottom Navigation Buttons -->
  <div class="actions" style="margin-top:28px;margin-bottom:20px;gap:14px;display:flex;justify-content:center;flex-wrap:wrap;"> 
    <a href="profile-dashboard.php" class="btn-secondary-lg">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      Back to Dashboard
    </a>
    <a href="profile-dashboard.php?tab=registrations" class="btn-primary-lg">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      My Registrations
    </a>
    <a href="events.php" class="btn-secondary-lg">
      Browse Events
    </a>
  </div>

</div>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/student/verification_notifier.js?v=<?= time() ?>"></script>
</body>
</html>
