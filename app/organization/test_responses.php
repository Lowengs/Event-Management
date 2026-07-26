<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/img_helpers.php';
require_once '../../config/gemini.php';

if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}

$orgId   = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();
$orgName = $orgData['OrgName'] ?? 'Organization';
$activePage = 'assesment'; 

$assessmentId = (int)($_GET['assessment_id'] ?? 0);
if ($assessmentId === 0) {
    header('Location: assesment.php');
    exit;
}

// 1. Get Assessment Info
$stmt = $conn->prepare("
    SELECT a.*, e.EventName, e.EventDateTime,
           (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.assessment_id) as q_count
    FROM assessments a
    JOIN event e ON a.event_id = e.EventId
    WHERE a.assessment_id = ? AND e.OrgId = ?
");
$stmt->bind_param('ii', $assessmentId, $orgId);
$stmt->execute();
$assessment = $stmt->get_result()->fetch_assoc();

if (!$assessment) {
    header('Location: assesment.php');
    exit;
}

$eventId = $assessment['event_id'];
$type = strtolower($assessment['type']); 

// 2. Fetch Responses
 $responses = [];

// Because test_results.php references event_pretest / event_posttest, we will use that as the primary source
$tableName = ($type === 'pretest' || $type === 'pre') ? 'event_pretest' : 'event_posttest';

// Use a safe query to check if table exists and has records
$resQuery = $conn->query("
    SELECT r.Score, r.SubmittedAt, u.first_name, u.last_name, u.Email,
           r.tab_switches, r.engagement_score, r.monitoring_flagged
    FROM $tableName r 
    JOIN user u ON r.UserId = u.UserId 
    WHERE r.EventId = $eventId 
    ORDER BY r.Score DESC, r.SubmittedAt ASC
");

if ($resQuery) {
    while ($row = $resQuery->fetch_assoc()) {
        $responses[] = $row;
    }
}

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

// 3. Fetch Questions, compute average scores & Ask Gemini for Per-Question Insights
$questionsList = [];
// This query calculates how many students answered each question, and how many got it correct
$qStmt = $conn->prepare("
    SELECT 
        q.question_id, 
        q.question_text, 
        q.correct_answer,
        COUNT(sqr.id) AS total_answered,
        SUM(sqr.is_correct) AS total_correct
    FROM assessment_questions q
    LEFT JOIN student_question_responses sqr ON q.question_id = sqr.question_id
    WHERE q.assessment_id = ?
    GROUP BY q.question_id
    ORDER BY q.question_id ASC
");
$qStmt->bind_param('i', $assessmentId);
$qStmt->execute();
$qRes = $qStmt->get_result();

while ($r = $qRes->fetch_assoc()) {
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
            // Fallback: If JSON is invalid, try extracting numbered lists if Gemini ignores the JSON command
            if ($rawAi && !is_array($decoded) && preg_match_all('/^\d+\.\s*(.+)$/m', trim($rawAi), $matches)) {
                if (count($matches[1]) === count($questionsList)) {
                    $questionInsights = $matches[1];
                    $_SESSION[$sessionKey] = $questionInsights;
                }
            }
            
            // Absolute fallback
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
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Assessment Responses – NAAP ORG Portal</title>
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>

  
  <link rel="stylesheet" href="../../assets/css/organization/test_responses.css?<?= time() ?>" />
</head>
<body>

<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title">
          <h2>Student Responses</h2>
          <p>Review student performance for this assessment</p>
        </div>
      </div>
      <div class="topbar-right">
        <div class="user-box">
          <img src="<?= imgPathForDepth($orgData['OrgPicture'] ?? '', 2, '../../assets/img/philsca.png') ?>" alt="Org logo" class="org-logo">
          <div>
            <strong><?= htmlspecialchars($orgName) ?></strong>
            <span>ORG Admin</span>
          </div>
        </div>
      </div>
    </header>

    <div class="maincontent">
      <div class="divider"></div>
      
      <div style="margin-bottom: 20px;">
        <a href="assesment.php" class="secondary-btn" style="border-color: transparent;">
          <ion-icon name="arrow-back-outline"></ion-icon> Back to Assessments
        </a>
      </div>

      <!-- Overview -->
      <div class="overview-card">
        <div class="overview-info">
          <h3><?= htmlspecialchars($assessment['title']) ?></h3>
          <p style="margin-bottom: 6px;"><ion-icon name="calendar-outline"></ion-icon> <?= htmlspecialchars($assessment['EventName']) ?> (<?= date('M j, Y', strtotime($assessment['EventDateTime'])) ?>)</p>
          <p><ion-icon name="document-text-outline"></ion-icon> <?= ucfirst($assessment['type']) ?> • <?= $assessment['q_count'] ?> Questions Total</p>
        </div>
        <div class="stat-boxes">
          <div class="stat-box">
            <h4><?= count($responses) ?></h4>
            <span>Total Submissions</span>
          </div>
          <div class="stat-box">
            <h4 style="color: #10b981;"><?php echo $avgScore; ?></h4>
            <span>Average Score</span>
          </div>
        </div>
      </div>

      <!-- Responses Table -->
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Email</th>
              <th>Score</th>
              <th>Percentage</th>
              <th>Proctoring & Engagement</th>
              <th>Submitted At</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($responses)): ?>
              <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: #64748b;">
                  <ion-icon name="people-outline" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></ion-icon>
                  <p>No student reponses recorded yet.</p>
                  <p style="font-size: 0.8rem;">(Make sure the test is published and students have submitted their answers)</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($responses as $r): 
                $initials = strtoupper(substr($r['first_name'],0,1) . substr($r['last_name'],0,1));
                $fullName = trim($r['first_name'] . ' ' . $r['last_name']);
                
                $pct = $assessment['q_count'] > 0 ? round(($r['Score'] / $assessment['q_count']) * 100) : 0;
                
                $badgeClass = 'score-med';
                if ($pct >= 75) $badgeClass = 'score-high';
                if ($pct < 50) $badgeClass = 'score-low';
              ?>
              <tr>
                <td>
                  <div class="avatar-circle"><?= $initials ?></div>
                  <strong><?= htmlspecialchars($fullName) ?></strong>
                </td>
                <td style="color: #64748b;"><?= htmlspecialchars($r['Email']) ?></td>
                <td>
                  <strong style="color: #0f172a; font-size: 1rem;"><?= $r['Score'] ?></strong> <span style="color: #94a3b8; font-size: 0.8rem;">/ <?= $assessment['q_count'] ?></span>
                </td>
                <td>
                  <span class="score-badge <?= $badgeClass ?>"><?= $pct ?>%</span>
                </td>
                <td>
                  <?php 
                    $switches = (int)($r['tab_switches'] ?? 0);
                    $engagement = (int)($r['engagement_score'] ?? 100);
                    $flagged = (int)($r['monitoring_flagged'] ?? 0);
                    
                    // Determine Engagement color and label
                    $engColor = '#10b981'; // green
                    $engText = 'Highly Engaged';
                    if ($engagement < 50) {
                        $engColor = '#ef4444'; // red
                        $engText = 'Unengaged';
                    } elseif ($engagement < 80) {
                        $engColor = '#f59e0b'; // orange
                        $engText = 'Distracted';
                    }
                    
                    // Display Badge
                    if ($flagged) {
                        echo '<span class="score-badge score-low" style="background:#fecaca; color:#dc2626; border:1px solid #fca5a5; display:inline-flex; align-items:center; gap:4px; font-weight:700;" title="Test auto-submitted due to focus violations."><ion-icon name="alert-circle-outline"></ion-icon> FLAGGED (' . $switches . ' tabs)</span>';
                    } else if ($switches > 0) {
                        echo '<span class="score-badge score-med" style="background:#fef3c7; color:#d97706; border:1px solid #fcd34d; display:inline-flex; align-items:center; gap:4px;" title="Left test window ' . $switches . ' time(s)."><ion-icon name="warning-outline"></ion-icon> ' . $switches . ' Tab Warning' . ($switches > 1 ? 's' : '') . '</span>';
                    } else {
                        echo '<span class="score-badge score-high" style="background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; display:inline-flex; align-items:center; gap:4px;"><ion-icon name="checkmark-circle-outline"></ion-icon> Verified</span>';
                    }
                    echo '<div style="font-size:11px; margin-top:4px; color:#64748b;">Index: <strong style="color: ' . $engColor . ';">' . $engagement . '%</strong> (' . $engText . ')</div>';
                  ?>
                </td>
                <td style="color: #64748b; font-size: 0.85rem;">
                  <?= date('M j, Y h:i A', strtotime($r['SubmittedAt'])) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>

        </table>
      </div>

      <!-- AI Insights / Analysis Box -->
      <div class="ai-insight-box">
        <div class="ai-header">
          <div class="ai-icon-wrap">
            <ion-icon name="bulb-outline"></ion-icon>
          </div>
          <div>
            <h3 style="display: flex; align-items: center; gap: 8px;">
              AI Analysis &amp; Insights
              <span class="badge"><ion-icon name="sparkles"></ion-icon> Gemini AI</span>
            </h3>
          </div>
          <form method="GET" style="margin-left: auto;">
              <input type="hidden" name="assessment_id" value="<?= $assessmentId ?>">
              <input type="hidden" name="refresh_insights" value="1">
              <button type="submit" class="primary-btn" style="border: 1px solid #bae6fd; background: #e0f2fe; color: #0284c7;">
                <ion-icon name="analytics-outline"></ion-icon> Refresh Insights
              </button>
          </form>
        </div>
      <div class="ai-body">
          <div style="background: #f8fafc; border-radius: 8px; padding: 20px; border: 1px dashed #cbd5e1; display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <span class="ai-pulse"></span>
              <strong style="font-size:0.95rem;">AI Effectiveness Score:</strong>
              <span style="font-size:1.1rem; color:#0f172a; font-weight:700;"><?= $aiEffectiveness ?>/100</span>
            </div>
            <p style="margin:0; color:#475569;"><strong>Insight:</strong> <?= htmlspecialchars($aiNarrative) ?></p>
            <p style="margin:0; color:#475569; font-size:0.9rem;">High-performing submissions (≥75%): <?= $highCount ?> (<?= round($highRatio*100) ?>%) • Low-performing (<50%): <?= $lowCount ?>. AI recommends tailoring follow-up tasks accordingly.</p>
          </div>

          <?php if (!empty($questionsList)): ?>
          <div style="background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column;">
            <div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 8px;">
              <ion-icon name="list-outline"></ion-icon> Per-Question Analysis
            </div>
            <div style="padding: 20px; display: flex; flex-direction: column; gap: 16px;">
              <?php foreach ($questionsList as $i => $q): 
                 $insight = $questionInsights[$i] ?? 'No insight available for this question.';
                   $color = '#94a3b8'; // default gray for no data
                   $badgeLabel = "No data";
                   if ($q['total_answered'] > 0) {
                       $badgeLabel = $q['success_rate'] . "% Average";
                       if ($q['success_rate'] >= 75) $color = '#10b981'; // Green
                       elseif ($q['success_rate'] >= 50) $color = '#f59e0b'; // Orange
                       else $color = '#ef4444'; // Red
                   }
                ?>
                  <div style="padding-bottom: 16px; border-bottom: 1px solid #f1f5f9;">
                    <div style="display: flex; justify-content: space-between; gap: 12px; margin-bottom: 8px;">
                      <strong style="color: #0f172a; line-height: 1.4;">Q<?= $i+1 ?>: <?= htmlspecialchars($q['question_text']) ?></strong>
                      <span style="white-space: nowrap; font-size: 0.8rem; font-weight: 700; padding: 4px 10px; border-radius: 20px; background: color-mix(in srgb, <?= $color ?> 15%, white); color: <?= $color ?>; display: inline-flex; align-items: center; justify-content: center; height: fit-content;">
                        <?= $badgeLabel ?>
                      </span>
                    </div>
                    <p style="margin: 0; color: #475569; font-size: 0.9rem; line-height: 1.5; padding-left: 12px; border-left: 3px solid #0ea5e9;">
                       <strong style="color: #0284c7;">AI Insight:</strong> <?= htmlspecialchars($insight) ?>
                  </p>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <div style="margin-top:18px; display:flex; gap:12px; flex-wrap:wrap;">
            <span style="font-size:0.8rem; color:#64748b; align-self:center;">Powered by Gemini AI</span>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>



  <script src="../../assets/js/org/test_responses.js"></script>
</body>
</html>
