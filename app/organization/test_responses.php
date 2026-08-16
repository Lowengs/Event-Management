<?php
session_start();
require_once '../../config/img_helpers.php';
require_once '../../config/gemini.php';

if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}

$orgId   = (int)$_SESSION['org_id'];
$orgName = $_SESSION['org_name'] ?? 'Organization';
$orgData = ['OrgName' => $orgName, 'OrgPicture' => $_SESSION['org_logo'] ?? ''];
$activePage = 'assesment'; 

$assessmentId = (int)($_GET['assessment_id'] ?? 0);
if ($assessmentId === 0) {
    header('Location: assesment.php');
    exit;
}

$_GET['action'] = 'get_org_test_responses';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$trApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

$assessment    = $trApiRes['assessment'] ?? null;
$responses     = $trApiRes['responses']  ?? [];
$questionsRaw  = $trApiRes['questions']  ?? [];

if (!$assessment) {
    header('Location: assesment.php');
    exit;
}

$eventId = $assessment['event_id'];
$type = strtolower($assessment['type']); 

// AI effectiveness context
$totalResponses = count($responses);
$qCount = max(1, (int)$assessment['q_count']);
$avgScore = 0;
$highCount = 0;
$lowCount = 0;
foreach ($responses as $row) {
    $pct = round(($row['Score'] / $qCount) * 100);
    if ($pct >= 75) {
        $highCount++;
    } elseif ($pct < 50) {
        $lowCount++;
    }
    $avgScore += $row['Score'];
}
if ($totalResponses > 0) {
    $avgScore = round($avgScore / $totalResponses, 1);
}
$avgPct = round(($avgScore / $qCount) * 100);
$highRatio = $totalResponses > 0 ? $highCount / $totalResponses : 0;
$aiEffectiveness = min(100, round($avgPct * 0.7 + $highRatio * 30));
$aiNarrative = 'AI review suggests reinforcing the current lessons.';
if ($avgPct >= 75) {
    $aiNarrative = 'AI notes that the group is demonstrating strong mastery; highlight advanced challenges to keep the momentum going.';
} elseif ($avgPct < 50) {
    $aiNarrative = 'AI flagged noticeable knowledge gaps; prioritize targeted coaching for the weakest segments to raise mastery quickly.';
}

$questionsList = [];
foreach ($questionsRaw as $r) {
    $answered = (int)$r['total_answered'];
    $correct = (int)$r['total_correct'];
    $pct = $answered > 0 ? round(($correct / $answered) * 100) : 0;
    $r['success_rate'] = $pct;
    $questionsList[] = $r;
}

$questionInsights = [];
if (count($questionsList) > 0) {
    if (isset($_GET['refresh_insights'])) {
        unset($_SESSION['ai_q_insights_' . $assessmentId]);
    }
    
    $sessionKey = 'ai_q_insights_' . $assessmentId;
    if (isset($_SESSION[$sessionKey]) && count($_SESSION[$sessionKey]) === count($questionsList)) {
        $questionInsights = $_SESSION[$sessionKey];
    } else {
        $count = count($questionsList);
        $prompt = "Analyze the difficulty of these {$count} multiple choice questions. You are given the question and the percentage of students who got it right (Success Rate). For each, write ONE short sentence (max 15 words) stating if the difficulty is effective, too hard, or too easy, and why.\n\nRespond ONLY with a valid JSON array of exactly {$count} strings. Do NOT wrap the response in markdown code blocks like ```json. Just return the raw JSON array `[\"insight 1\", \"insight 2\"]`.\n\nQuestions:\n";
        foreach ($questionsList as $i => $qData) {
            $scoreText = $qData['total_answered'] > 0 ? "{$qData['success_rate']}% Correct" : "No student data yet";
            $prompt .= ($i+1) . ". [Score: $scoreText] " . $qData['question_text'] . "\n";
        }
        $rawAi = geminiAsk($prompt, 1024);
        
        $decoded = null;
        if ($rawAi) {
            $rawAi = trim($rawAi);
            $rawAi = preg_replace('/^```(?:json)?\s*/i', '', $rawAi);
            $rawAi = preg_replace('/```\s*$/i', '', $rawAi);
            $decoded = json_decode(trim($rawAi), true);
        }
        
        if (is_array($decoded) && count($decoded) === count($questionsList)) {
            $questionInsights = $decoded;
            $_SESSION[$sessionKey] = $questionInsights;
        } else {
            if ($rawAi && !is_array($decoded) && preg_match_all('/^\d+\.\s*(.+)$/m', trim($rawAi), $matches)) {
                if (count($matches[1]) === count($questionsList)) {
                    $questionInsights = $matches[1];
                    $_SESSION[$sessionKey] = $questionInsights;
                }
            }
            
            if (empty($questionInsights) || count($questionInsights) !== count($questionsList)) {
                $questionInsights = [];
                $errorMsg = empty($rawAi) ? "(Gemini API Error: Invalid API Key or offline)" : "(Failed to parse AI output)";
                foreach ($questionsList as $qData) {
                    $questionInsights[] = "This question tests core conceptual understanding $errorMsg";
                }
                $_SESSION[$sessionKey] = $questionInsights;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assessment Responses - <?= htmlspecialchars($assessment['title']) ?></title>
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="stylesheet" href="../../assets/css/organization/dashboard.css">
  <link rel="stylesheet" href="../../assets/css/organization/test_responses.css?v=<?= time() ?>">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left" style="display:flex;align-items:center;gap:12px;">
        <button class="hamburger" id="hamburgerBtn" aria-label="Open menu">
          <ion-icon name="menu-outline"></ion-icon>
        </button>
        <a class="back-btn" href="assesment.php" aria-label="Back to Assessments" title="Back to Assessments">
          <ion-icon name="arrow-back-outline"></ion-icon>
        </a>
        <div class="page-title">
          <h2><?= htmlspecialchars($assessment['title']) ?></h2>
          <p>
            Event: <strong><?= htmlspecialchars($assessment['EventName']) ?></strong> &bull; 
            Type: <span class="badge <?= $type ?>"><?= strtoupper($type) ?></span>
          </p>
        </div>
      </div>
      <div class="topbar-right">
        <a href="test_responses.php?assessment_id=<?= $assessmentId ?>&refresh_insights=1" class="btn-refresh" title="Re-analyze with Gemini AI">
          <ion-icon name="sparkles-outline"></ion-icon> Refresh AI Analysis
        </a>
      </div>
    </header>

    <div class="divider"></div>

    <div class="maincontent">
      <!-- AI Learning Impact Banner -->
      <div class="ai-insight-banner">
        <div class="ai-banner-left">
          <div class="ai-banner-icon">
            <ion-icon name="sparkles"></ion-icon>
          </div>
          <div class="ai-banner-content">
            <h3>AI Learning Impact Score: <span class="ai-score-pill"><?= $aiEffectiveness ?>/100</span></h3>
            <p><?= htmlspecialchars($aiNarrative) ?></p>
          </div>
        </div>
        <div class="ai-banner-stats">
          <div class="stat-pill">
            <span class="label">Avg Score</span>
            <span class="value"><?= $avgScore ?> / <?= $qCount ?> (<?= $avgPct ?>%)</span>
          </div>
          <div class="stat-pill">
            <span class="label">Submissions</span>
            <span class="value"><?= $totalResponses ?></span>
          </div>
        </div>
      </div>

      <div class="responses-grid">
        <!-- Left Column: Individual Student Results -->
        <div class="card responses-table-card">
          <div class="card-header">
            <h3><ion-icon name="people-outline"></ion-icon> Student Submissions</h3>
            <span style="font-size:12px;font-weight:700;color:#64748b;background:#f1f5f9;padding:3px 10px;border-radius:20px;">
              <?= $totalResponses ?> <?= $totalResponses === 1 ? 'Student' : 'Students' ?>
            </span>
          </div>
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Student Name</th>
                  <th>Score</th>
                  <th>Tab Switches</th>
                  <th>Submitted At</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($responses)): ?>
                  <tr><td colspan="4" class="empty-cell">No student responses recorded yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($responses as $r): 
                    $pct = round(($r['Score'] / $qCount) * 100);
                    $scoreClass = ($pct >= 75) ? 'high' : (($pct < 50) ? 'low' : 'mid');
                  ?>
                    <tr>
                      <td>
                        <div class="user-cell">
                          <strong><?= htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: ($r['Email'] ?? 'Student')) ?></strong>
                          <small><?= htmlspecialchars($r['Email'] ?? '') ?></small>
                        </div>
                      </td>
                      <td>
                        <span class="score-badge <?= $scoreClass ?>">
                          <?= $r['Score'] ?> / <?= $qCount ?> (<?= $pct ?>%)
                        </span>
                      </td>
                      <td>
                        <span class="switch-count <?= ($r['tab_switches'] > 2) ? 'warning' : '' ?>" title="<?= (int)$r['tab_switches'] ?> tab switch violations">
                          <ion-icon name="warning-outline"></ion-icon> <?= (int)$r['tab_switches'] ?>
                        </span>
                      </td>
                      <td><?= date('M j, Y &bull; g:i A', strtotime($r['SubmittedAt'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right Column: Question Difficulty Insights -->
        <div class="card questions-analytics-card">
          <div class="card-header">
            <h3><ion-icon name="analytics-outline"></ion-icon> Question Performance Insights</h3>
          </div>
          <div class="questions-list">
            <?php if (empty($questionsList)): ?>
              <p style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;margin:0;">No questions found in this assessment.</p>
            <?php else: ?>
              <?php foreach ($questionsList as $idx => $q): 
                $rateClass = ($q['success_rate'] >= 75) ? 'high' : (($q['success_rate'] < 50) ? 'low' : 'mid');
              ?>
                <div class="question-insight-item">
                  <div class="q-header">
                    <span class="q-num">Q<?= $idx + 1 ?></span>
                    <span class="q-text"><?= htmlspecialchars($q['question_text']) ?></span>
                    <span class="q-rate <?= $rateClass ?>"><?= $q['success_rate'] ?>% Correct</span>
                  </div>
                  <div class="q-ai-feedback">
                    <ion-icon name="hardware-chip-outline"></ion-icon>
                    <span><?= htmlspecialchars($questionInsights[$idx] ?? 'AI analysis ready.') ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
