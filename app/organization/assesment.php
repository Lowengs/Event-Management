<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/img_helpers.php';

if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}

$orgId   = (int)$_SESSION['org_id'];
$orgName = $_SESSION['org_name'] ?? 'Organization';
$orgData = ['OrgName' => $orgName, 'OrgPicture' => $_SESSION['org_logo'] ?? ''];
$activePage = 'assesment';

// Handle assessment status and question edits before loading the page data.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $assessmentId = (int)($_POST['assessment_id'] ?? 0);

    if ($action === 'toggle_status' && $assessmentId) {
        $status = $_POST['new_status'] ?? '';
        if (in_array($status, ['draft', 'published', 'closed'], true)) {
            $stmt = $conn->prepare('UPDATE assessments a JOIN event e ON e.EventId = a.event_id SET a.status = ? WHERE a.assessment_id = ? AND e.OrgId = ?');
            $stmt->bind_param('sii', $status, $assessmentId, $orgId);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: assesment.php');
        exit;
    }

    if ($action === 'update_question') {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $questionText = trim($_POST['question_text'] ?? '');
        $points = max(1, (int)($_POST['points'] ?? 1));
        $optionA = trim($_POST['option_a'] ?? '');
        $optionB = trim($_POST['option_b'] ?? '');
        $optionC = trim($_POST['option_c'] ?? '');
        $optionD = trim($_POST['option_d'] ?? '');
        $correctAnswer = strtoupper(trim($_POST['correct_answer'] ?? 'A'));
        if ($questionId && $questionText !== '') {
            $stmt = $conn->prepare('UPDATE assessment_questions aq JOIN assessments a ON a.assessment_id = aq.assessment_id JOIN event e ON e.EventId = a.event_id SET aq.question_text = ?, aq.option_a = ?, aq.option_b = ?, aq.option_c = ?, aq.option_d = ?, aq.correct_answer = ?, aq.points = ? WHERE aq.question_id = ? AND e.OrgId = ?');
            $stmt->bind_param('ssssssiii', $questionText, $optionA, $optionB, $optionC, $optionD, $correctAnswer, $points, $questionId, $orgId);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: assesment.php?assessment_id=' . $assessmentId);
        exit;
    }

    if ($action === 'create_assessment') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $type = strtolower(trim($_POST['test_type'] ?? 'pretest'));
        $title = trim($_POST['title'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        $timeLimit = max(1, min(30, (int)($_POST['time_limit'] ?? 30)));
        $owned = $conn->prepare('SELECT EventId FROM event WHERE EventId = ? AND OrgId = ?');
        $owned->bind_param('ii', $eventId, $orgId); $owned->execute();
        $validEvent = (bool)$owned->get_result()->fetch_assoc(); $owned->close();
        if ($validEvent && $title !== '' && in_array($type, ['pretest', 'posttest'], true)) {
            $conn->query("ALTER TABLE assessments ADD COLUMN time_limit INT NOT NULL DEFAULT 30");
            $conn->query("ALTER TABLE assessments ADD COLUMN test_type VARCHAR(50) DEFAULT 'pretest'");
            $hasTestType = false;
            $chk = $conn->query("SHOW COLUMNS FROM assessments LIKE 'test_type'");
            if ($chk && $chk->num_rows > 0) $hasTestType = true;

            if ($hasTestType) {
                $stmt = $conn->prepare('INSERT INTO assessments (event_id, title, type, test_type, instructions, status, created_by, time_limit) VALUES (?, ?, ?, ?, ?, \'draft\', ?, ?)');
                if ($stmt) { $stmt->bind_param('issssii', $eventId, $title, $type, $type, $instructions, $orgId, $timeLimit); $stmt->execute(); $newAssessmentId = (int)$stmt->insert_id; $stmt->close(); }
            } else {
                $stmt = $conn->prepare('INSERT INTO assessments (event_id, title, type, instructions, status, created_by, time_limit) VALUES (?, ?, ?, ?, \'draft\', ?, ?)');
                if ($stmt) { $stmt->bind_param('isssii', $eventId, $title, $type, $instructions, $orgId, $timeLimit); $stmt->execute(); $newAssessmentId = (int)$stmt->insert_id; $stmt->close(); }
            }
        }
        header('Location: assesment.php' . (!empty($newAssessmentId) ? '?assessment_id=' . $newAssessmentId : '')); exit;
    }

    if ($action === 'add_question' && $assessmentId) {
        $question = trim($_POST['question_text'] ?? '');
        $points = max(1, (int)($_POST['points'] ?? 1));
        $isTrueFalse = ($_POST['q_type'] ?? '') === 'truefalse';
        $a = $isTrueFalse ? 'True' : trim($_POST['option_a'] ?? '');
        $b = $isTrueFalse ? 'False' : trim($_POST['option_b'] ?? '');
        $c = $isTrueFalse ? '' : trim($_POST['option_c'] ?? '');
        $d = $isTrueFalse ? '' : trim($_POST['option_d'] ?? '');
        $answer = $isTrueFalse ? (($_POST['tfOption'] ?? 'True') === 'False' ? 'B' : 'A') : strtoupper($_POST['correctOption'] ?? 'A');
        if ($question !== '' && $a !== '' && $b !== '') {
            $stmt = $conn->prepare('INSERT INTO assessment_questions (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points) SELECT ?, ?, ?, ?, ?, ?, ?, ? FROM assessments a JOIN event e ON e.EventId=a.event_id WHERE a.assessment_id=? AND e.OrgId=?');
            if ($stmt) { $stmt->bind_param('issssssiii', $assessmentId, $question, $a, $b, $c, $d, $answer, $points, $assessmentId, $orgId); $stmt->execute(); $stmt->close(); }
        }
        header('Location: assesment.php?assessment_id=' . $assessmentId); exit;
    }

    if ($action === 'delete_question') {
        $questionId = (int)($_POST['question_id'] ?? 0);
        $stmt = $conn->prepare('DELETE aq FROM assessment_questions aq JOIN assessments a ON a.assessment_id=aq.assessment_id JOIN event e ON e.EventId=a.event_id WHERE aq.question_id=? AND e.OrgId=?');
        if ($stmt) { $stmt->bind_param('ii', $questionId, $orgId); $stmt->execute(); $stmt->close(); }
        header('Location: assesment.php?assessment_id=' . $assessmentId); exit;
    }

    if ($action === 'edit_assessment' && $assessmentId) {
        $type = strtolower(trim($_POST['test_type'] ?? 'pretest'));
        $title = trim($_POST['title'] ?? ''); $instructions = trim($_POST['instructions'] ?? '');
        $status = strtolower(trim($_POST['status'] ?? 'draft')); $timeLimit = max(1, min(30, (int)($_POST['time_limit'] ?? 30)));
        if ($title !== '' && in_array($type, ['pretest', 'posttest'], true) && in_array($status, ['draft', 'published', 'closed'], true)) {
            $conn->query("ALTER TABLE assessments ADD COLUMN time_limit INT NOT NULL DEFAULT 30");
            $conn->query("ALTER TABLE assessments ADD COLUMN test_type VARCHAR(50) DEFAULT 'pretest'");
            $hasTestType = false;
            $chk = $conn->query("SHOW COLUMNS FROM assessments LIKE 'test_type'");
            if ($chk && $chk->num_rows > 0) $hasTestType = true;

            if ($hasTestType) {
                $stmt = $conn->prepare('UPDATE assessments a JOIN event e ON e.EventId=a.event_id SET a.title=?, a.type=?, a.test_type=?, a.instructions=?, a.status=?, a.time_limit=? WHERE a.assessment_id=? AND e.OrgId=?');
                if ($stmt) { $stmt->bind_param('sssssiii', $title, $type, $type, $instructions, $status, $timeLimit, $assessmentId, $orgId); $stmt->execute(); $stmt->close(); }
            } else {
                $stmt = $conn->prepare('UPDATE assessments a JOIN event e ON e.EventId=a.event_id SET a.title=?, a.type=?, a.instructions=?, a.status=?, a.time_limit=? WHERE a.assessment_id=? AND e.OrgId=?');
                if ($stmt) { $stmt->bind_param('ssssiii', $title, $type, $instructions, $status, $timeLimit, $assessmentId, $orgId); $stmt->execute(); $stmt->close(); }
            }
        }
        header('Location: assesment.php'); exit;
    }
}

$_GET['action'] = 'get_assessments';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$assApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

$events        = $assApiRes['events'] ?? [];
$groupedEventsRaw = $assApiRes['grouped_events'] ?? [];
$questionsData = $assApiRes['questions_data'] ?? [];

$groupedEvents = [];
foreach ($groupedEventsRaw as $gEv) {
    if (!empty($gEv['EventId'])) {
        $groupedEvents[$gEv['EventId']] = $gEv;
    }
}
$assessmentPage = max(1, (int)($_GET['page'] ?? 1));
$assessmentPerPage = 4;
$assessmentTotalPages = max(1, (int)ceil(count($groupedEvents) / $assessmentPerPage));
$assessmentPage = min($assessmentPage, $assessmentTotalPages);
$groupedEvents = array_slice($groupedEvents, ($assessmentPage - 1) * $assessmentPerPage, $assessmentPerPage, true);
$jsQuestions = json_encode($questionsData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Assessments</title>
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="stylesheet" href="../../assets/css/organization/assesment.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>

  
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
          <h2>Assessments</h2>
          <p>Manage pre-tests and post-tests for your events</p>
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
      
      <!-- VIEW 1: Assessments List -->
      <section id="listView" class="view-section active">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
          <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">Events & Assessments</h3>
          <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:#eef2ff;border-radius:10px;">
              <input type="text" id="assessSearch" placeholder="Search event or test title..." style="width:200px;height:36px;padding:0 12px;border:1px solid #dbe2ef;border-radius:8px;font-size:13px;outline:none;font-family:inherit;background:#fff;box-sizing:border-box;">
              <select id="assessEventFilter" style="width:205px;height:36px;padding:0 12px;border:1px solid #dbe2ef;border-radius:8px;font-size:13px;outline:none;font-family:inherit;background:#fff;cursor:pointer;box-sizing:border-box;">
              <option value="">All Events</option>
              <?php foreach($groupedEvents as $evId => $ev): ?>
                <option value="<?= htmlspecialchars($ev['EventName']) ?>"><?= htmlspecialchars($ev['EventName']) ?></option>
              <?php endforeach; ?>
            </select>
            </div>
            <button class="primary-btn" onclick="openCreateModal()">
              <ion-icon name="add-outline" style="font-size: 1.1rem;"></ion-icon> Create Test
            </button>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 20px;">
          
          <?php if(empty($groupedEvents)): ?>
            <div style="grid-column: 1/-1; padding: 40px; text-align: center; color: #64748b; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;">
              <ion-icon name="calendar-outline" style="font-size: 3rem; color: #cbd5e1;"></ion-icon>
              <p style="margin-top: 10px; font-weight: 500;">No events found. You need an event to create an assessment.</p>
            </div>
          <?php else: ?>
            <?php foreach($groupedEvents as $evId => $ev): ?>
              <div class="test-card">
                <div class="test-card-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px;">
                  <div>
                    <h4 class="test-card-title"><?= htmlspecialchars($ev['EventName']) ?></h4>
                    <div class="test-event"><ion-icon name="calendar-outline"></ion-icon> <?= date('M j, Y', strtotime($ev['EventDateTime'])) ?></div>
                  </div>
                </div>

                <!-- Pre-test Section -->
                <div style="margin-bottom: 12px;">
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <h6 style="margin: 0; font-size:0.85rem; color:#64748b; text-transform: uppercase;">Pre-Test</h6>
                  </div>
                  <?php if($ev['pretest']): $test = $ev['pretest']; ?>
                    <?php 
                      $statusClass = 'status-draft'; $statusLabel = 'Draft';
                      if ($test['status'] === 'published') { $statusClass = 'status-active'; $statusLabel = 'Active'; }
                      if ($test['status'] === 'closed') { $statusClass = 'status-closed'; $statusLabel = 'Closed'; }
                    ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                        <div style="display:flex; align-items:center; gap:6px; max-width:65%;">
                          <span style="font-weight: 600; font-size: 0.9rem; color:#0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($test['title']) ?>"><?= htmlspecialchars($test['title']) ?></span>
                          <button type="button" class="secondary-btn" style="padding: 2px 6px; font-size: 11px;" onclick='openEditAssessmentModal(<?= json_encode($test, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit Assessment Details"><ion-icon name="create-outline"></ion-icon></button>
                        </div>
                        <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                      </div>
                      <div style="font-size: 0.8rem; color:#64748b; margin-bottom: 12px;">
                        <ion-icon name="help-circle-outline" style="vertical-align: middle;"></ion-icon> <?= $test['q_count'] ?> Questions
                      </div>
                      <div style="display: flex; gap: 8px;">
                        <button class="secondary-btn" style="flex: 1; justify-content: center; padding: 6px 0;" onclick="openQuestionBuilder(<?= $test['assessment_id'] ?>, '<?= htmlspecialchars(addslashes($test['title'])) ?>')" title="Add, edit, or delete assessment questions">
                          <ion-icon name="create-outline"></ion-icon> Edit
                        </button>
                        <a href="test_responses.php?assessment_id=<?= $test['assessment_id'] ?>" class="secondary-btn" style="flex: 1; justify-content: center; padding: 6px 0; text-decoration: none; color: #3b82f6; border-color: #bfdbfe; background: #eff6ff;">
                          <ion-icon name="people-outline"></ion-icon> Responses
                        </a>
                        <form method="POST" action="assesment.php" style="flex: 1; display:flex;">
                          <input type="hidden" name="action" value="toggle_status">
                          <input type="hidden" name="assessment_id" value="<?= $test['assessment_id'] ?>">
                          <?php if($test['status'] === 'published'): ?>
                               <input type="hidden" name="new_status" value="closed">
                               <button type="submit" class="secondary-btn" style="width: 100%; justify-content: center; padding: 6px 0; color: #ef4444; border-color: #fca5a5;">
                                 Close
                               </button>
                          <?php elseif($test['status'] === 'closed'): ?>
                               <input type="hidden" name="new_status" value="draft">
                               <button type="submit" class="secondary-btn" style="width: 100%; justify-content: center; padding: 6px 0; color: #64748b; border-color: #cbd5e1;">
                                 Redraft
                               </button>
                          <?php else: ?>
                               <input type="hidden" name="new_status" value="published">
                               <button type="submit" class="primary-btn" style="width: 100%; justify-content: center; padding: 6px 0; background: #10b981; border: 1px solid #10b981;">
                                 Publish
                               </button>
                          <?php endif; ?>
                        </form>
                      </div>
                    </div>
                  <?php else: ?>
                    <button class="secondary-btn" style="width: 100%; justify-content: center; border-style: dashed; padding: 10px; color:#3b82f6; border-color:#93c5fd; background:#eff6ff;" onclick="openCreateModal(<?= $evId ?>, 'pretest')">
                      <ion-icon name="add-outline"></ion-icon> Create Pre-Test
                    </button>
                  <?php endif; ?>
                </div>

                <!-- Post-test Section -->
                <div>
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <h6 style="margin: 0; font-size:0.85rem; color:#64748b; text-transform: uppercase;">Post-Test</h6>
                  </div>
                  <?php if($ev['posttest']): $test = $ev['posttest']; ?>
                    <?php 
                      $statusClass = 'status-draft'; $statusLabel = 'Draft';
                      if ($test['status'] === 'published') { $statusClass = 'status-active'; $statusLabel = 'Active'; }
                      if ($test['status'] === 'closed') { $statusClass = 'status-closed'; $statusLabel = 'Closed'; }
                    ?>
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px;">
                      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                        <div style="display:flex; align-items:center; gap:6px; max-width:65%;">
                          <span style="font-weight: 600; font-size: 0.9rem; color:#0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($test['title']) ?>"><?= htmlspecialchars($test['title']) ?></span>
                          <button type="button" class="secondary-btn" style="padding: 2px 6px; font-size: 11px;" onclick='openEditAssessmentModal(<?= json_encode($test, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' title="Edit Assessment Details"><ion-icon name="create-outline"></ion-icon></button>
                        </div>
                        <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                      </div>
                      <div style="font-size: 0.8rem; color:#64748b; margin-bottom: 12px;">
                        <ion-icon name="help-circle-outline" style="vertical-align: middle;"></ion-icon> <?= $test['q_count'] ?> Questions
                      </div>
                      <div style="display: flex; gap: 8px;">
                        <button class="secondary-btn" style="flex: 1; justify-content: center; padding: 6px 0;" onclick="openQuestionBuilder(<?= $test['assessment_id'] ?>, '<?= htmlspecialchars(addslashes($test['title'])) ?>')" title="Add, edit, or delete assessment questions">
                          <ion-icon name="create-outline"></ion-icon> Edit 
                        </button>
                        <a href="test_responses.php?assessment_id=<?= $test['assessment_id'] ?>" class="secondary-btn" style="flex: 1; justify-content: center; padding: 6px 0; text-decoration: none; color: #3b82f6; border-color: #bfdbfe; background: #eff6ff;">
                          <ion-icon name="people-outline"></ion-icon> Responses
                        </a>
                        <form method="POST" action="assesment.php" style="flex: 1; display:flex;">
                          <input type="hidden" name="action" value="toggle_status">
                          <input type="hidden" name="assessment_id" value="<?= $test['assessment_id'] ?>">
                          <?php if($test['status'] === 'published'): ?>
                               <input type="hidden" name="new_status" value="closed">
                               <button type="submit" class="secondary-btn" style="width: 100%; justify-content: center; padding: 6px 0; color: #ef4444; border-color: #fca5a5;">
                                 Close
                               </button>
                          <?php elseif($test['status'] === 'closed'): ?>
                               <input type="hidden" name="new_status" value="draft">
                               <button type="submit" class="secondary-btn" style="width: 100%; justify-content: center; padding: 6px 0; color: #64748b; border-color: #cbd5e1;">
                                 Redraft
                               </button>
                          <?php else: ?>
                               <input type="hidden" name="new_status" value="published">
                               <button type="submit" class="primary-btn" style="width: 100%; justify-content: center; padding: 6px 0; background: #10b981; border: 1px solid #10b981;">
                                 Publish
                               </button>
                          <?php endif; ?>
                        </form>
                      </div>
                    </div>
                  <?php else: ?>
                    <button class="secondary-btn" style="width: 100%; justify-content: center; border-style: dashed; padding: 10px; color:#3b82f6; border-color:#93c5fd; background:#eff6ff;" onclick="openCreateModal(<?= $evId ?>, 'posttest')">
                      <ion-icon name="add-outline"></ion-icon> Create Post-Test
                    </button>
                  <?php endif; ?>
                </div>

              </div>
            <?php endforeach; ?>
          <?php endif; ?>

        </div>
        <?php if ($assessmentTotalPages > 1): ?>
        <div style="display:flex;justify-content:center;align-items:center;gap:10px;margin-top:24px;">
          <?php if ($assessmentPage > 1): ?><a class="secondary-btn" href="assesment.php?page=<?= $assessmentPage - 1 ?>">Previous</a><?php endif; ?>
          <span style="font-size:13px;color:#64748b;">Page <?= $assessmentPage ?> of <?= $assessmentTotalPages ?></span>
          <?php if ($assessmentPage < $assessmentTotalPages): ?><a class="secondary-btn" href="assesment.php?page=<?= $assessmentPage + 1 ?>">Next</a><?php endif; ?>
        </div>
        <?php endif; ?>
      </section>

      <!-- VIEW 2: Question Builder -->
      <section id="builderView" class="view-section">
        <button class="secondary-btn" style="margin-bottom:20px;border-color:transparent;" onclick="closeQuestionBuilder()">
          <ion-icon name="arrow-back-outline"></ion-icon> Back to Assessments
        </button>
        <div style="margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;">
          <h3 id="builderTitleDisplay" style="margin:0;font-size:1.2rem;color:#1e293b;">Assessment Builder</h3>
          <button class="primary-btn" onclick="openModal('addQuestionModal')">
            <ion-icon name="add-outline"></ion-icon> Add Question
          </button>
        </div>
        <div id="questionsContainer"></div>
      </section>

    </div>
  </div>
</div>

<!-- Edit Individual Question Modal -->
<div class="modal-overlay" id="editQuestionModal">
  <div class="modal-content" style="max-width:650px;">
    <div class="modal-header"><h3>Edit</h3><button class="btn-close" type="button" onclick="closeModal('editQuestionModal')"><ion-icon name="close-outline"></ion-icon></button></div>
    <div class="modal-body">
      <form id="editQuestionForm" method="POST" action="assesment.php">
        <input type="hidden" name="action" value="update_question">
        <input type="hidden" name="assessment_id" id="editQuestionAssessmentId">
        <input type="hidden" name="question_id" id="editQuestionId">
        <div class="form-group"><label>Question Text *</label><textarea class="form-control" name="question_text" id="editQuestionText" rows="3" required></textarea></div>
        <div class="form-group"><label>Choices *</label>
          <input class="form-control" name="option_a" id="editOptionA" placeholder="Choice A" required style="margin-bottom:8px;">
          <input class="form-control" name="option_b" id="editOptionB" placeholder="Choice B" required style="margin-bottom:8px;">
          <input class="form-control" name="option_c" id="editOptionC" placeholder="Choice C" required style="margin-bottom:8px;">
          <input class="form-control" name="option_d" id="editOptionD" placeholder="Choice D" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group"><label>Correct Answer *</label><select class="form-control" name="correct_answer" id="editCorrectAnswer"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>
          <div class="form-group"><label>Points *</label><input class="form-control" type="number" min="1" name="points" id="editQuestionPoints" required></div>
        </div>
      </form>
    </div>
    <div class="modal-footer"><button class="secondary-btn" type="button" onclick="closeModal('editQuestionModal')">Cancel</button><button class="primary-btn" type="button" onclick="document.getElementById('editQuestionForm').submit()">Save Question</button></div>
  </div>
</div>

<!-- Modal 1: Create Test -->
<div class="modal-overlay" id="createTestModal">
  <div class="modal-content" style="max-width:550px;">
    <div class="modal-header">
      <h3>Create New Assessment</h3>
      <button class="btn-close" onclick="closeModal('createTestModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <form id="createTestForm" method="POST" action="assesment.php">
        <input type="hidden" name="action" value="create_assessment">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
          <div class="form-group"><label>Event *</label>
            <select class="form-control" name="event_id" required>
              <option value="">-- Select Event --</option>
              <?php foreach($events as $ev): ?>
                <option value="<?= $ev['EventId'] ?>"><?= htmlspecialchars($ev['EventName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Test Type *</label>
            <select class="form-control" name="test_type" required>
              <option value="pretest">Pre-Test</option>
              <option value="posttest">Post-Test</option>
            </select>
          </div>
          <div class="form-group"><label>Time Limit (Mins) *</label>
            <input type="number" name="time_limit" class="form-control" value="30" min="1" max="30" required>
          </div>
        </div>
        <div class="form-group"><label>Test Title *</label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Pre-Test: General Knowledge" required>
        </div>
        <div class="form-group"><label>Instructions</label>
          <textarea name="instructions" class="form-control" rows="3" placeholder="Instructions for students..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="secondary-btn" onclick="closeModal('createTestModal')">Cancel</button>
      <button class="primary-btn" type="button" onclick="document.getElementById('createTestForm').submit()"><ion-icon name="save-outline"></ion-icon> Save Test Details</button>
    </div>
  </div>
</div>

<!-- Modal 2: Add Question -->
<div class="modal-overlay" id="addQuestionModal">
  <div class="modal-content" style="max-width:650px;">
    <div class="modal-header">
      <h3>Add New Question</h3>
      <button class="btn-close" onclick="closeModal('addQuestionModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <form id="addQuestionForm" method="POST" action="assesment.php">
        <input type="hidden" name="action" value="add_question">
        <input type="hidden" name="assessment_id" id="hiddenAssessmentId" value="">
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;">
          <div class="form-group"><label>Question Type *</label>
            <select class="form-control" name="q_type" id="qTypeSelect" onchange="toggleOptionFields()">
              <option value="multiple">Multiple Choice</option>
              <option value="truefalse">True/False</option>
            </select>
          </div>
          <div class="form-group"><label>Points *</label>
            <input type="number" name="points" class="form-control" value="1" min="1" required>
          </div>
        </div>
        <div class="form-group"><label>Question Text *</label>
          <textarea class="form-control" name="question_text" rows="3" placeholder="Type your question here..." required></textarea>
        </div>
        <div id="multipleChoiceContainer" style="background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;">
          <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:12px;">Answer Options (Select the correct one)</label>
          <div style="display:flex;gap:12px;margin-bottom:10px;align-items:center;"><input type="radio" name="correctOption" value="A" style="width:18px;height:18px;" checked><span style="font-weight:600;color:#64748b;">A.</span><input type="text" name="option_a" class="form-control" placeholder="Option A" style="flex:1;"></div>
          <div style="display:flex;gap:12px;margin-bottom:10px;align-items:center;"><input type="radio" name="correctOption" value="B" style="width:18px;height:18px;"><span style="font-weight:600;color:#64748b;">B.</span><input type="text" name="option_b" class="form-control" placeholder="Option B" style="flex:1;"></div>
          <div style="display:flex;gap:12px;margin-bottom:10px;align-items:center;"><input type="radio" name="correctOption" value="C" style="width:18px;height:18px;"><span style="font-weight:600;color:#64748b;">C.</span><input type="text" name="option_c" class="form-control" placeholder="Option C" style="flex:1;"></div>
          <div style="display:flex;gap:12px;align-items:center;"><input type="radio" name="correctOption" value="D" style="width:18px;height:18px;"><span style="font-weight:600;color:#64748b;">D.</span><input type="text" name="option_d" class="form-control" placeholder="Option D" style="flex:1;"></div>
        </div>
        <div id="trueFalseContainer" style="display:none;background:#f8fafc;padding:16px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;">
          <label style="display:block;font-size:0.85rem;font-weight:600;margin-bottom:12px;">Select Correct Answer</label>
          <div style="display:flex;gap:24px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="radio" name="tfOption" value="True" checked style="width:18px;height:18px;"> <span style="font-size:0.9rem;font-weight:600;">True</span></label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="radio" name="tfOption" value="False" style="width:18px;height:18px;"> <span style="font-size:0.9rem;font-weight:600;">False</span></label>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="secondary-btn" onclick="closeModal('addQuestionModal')">Cancel</button>
      <button class="primary-btn" type="button" onclick="document.getElementById('addQuestionForm').submit()"><ion-icon name="save-outline"></ion-icon> Save Question</button>
    </div>
  </div>
</div>

<!-- Modal 3: Edit Assessment -->
<div class="modal-overlay" id="editAssessmentModal">
  <div class="modal-content" style="max-width:550px;">
    <div class="modal-header">
      <h3>Edit Assessment Details</h3>
      <button class="btn-close" onclick="closeModal('editAssessmentModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <form id="editAssessmentForm" method="POST" action="assesment.php">
        <input type="hidden" name="action" value="edit_assessment">
        <input type="hidden" name="assessment_id" id="editAssessId">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
          <div class="form-group"><label>Test Type *</label>
            <select class="form-control" name="test_type" id="editAssessType" required>
              <option value="pretest">Pre-Test</option>
              <option value="posttest">Post-Test</option>
            </select>
          </div>
          <div class="form-group"><label>Time Limit (Mins, max 30) *</label>
            <input type="number" name="time_limit" id="editAssessTimeLimit" class="form-control" value="30" min="1" max="30" required>
          </div>
          <div class="form-group"><label>Status</label>
            <select class="form-control" name="status" id="editAssessStatus">
              <option value="draft">Draft</option>
              <option value="published">Active</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Test Title *</label>
          <input type="text" name="title" id="editAssessTitle" class="form-control" placeholder="Test title..." required>
        </div>
        <div class="form-group"><label>Instructions</label>
          <textarea name="instructions" id="editAssessInstructions" class="form-control" rows="3" placeholder="Enter instructions..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="secondary-btn" onclick="closeModal('editAssessmentModal')">Cancel</button>
      <button class="primary-btn" type="button" onclick="document.getElementById('editAssessmentForm').submit()"><ion-icon name="save-outline"></ion-icon> Save Changes</button>
    </div>
  </div>
</div>

<script>
  var questionsData = <?= $jsQuestions ?: '{}' ?>;
</script>
<script src="../../assets/js/org/assesment.js?v=<?= time() ?>"></script>
<script src="../../assets/js/org/org.js"></script>
</body>
</html>
