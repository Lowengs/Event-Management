<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/img_helpers.php';

if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}

$orgId   = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();
$orgName = $orgData['OrgName'] ?? 'Organization';
$activePage = 'assesment'; // Matches sidebar

// Fetch events for dropdown
$events = [];
$evQuery = $conn->query("SELECT EventId, EventName FROM event WHERE OrgId=$orgId ORDER BY EventDateTime DESC");
if ($evQuery) {
    while ($row = $evQuery->fetch_assoc()) {
        $events[] = $row;
    }
}

// ── Handle Post Requests ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Create Assessment
    if ($_POST['action'] === 'create_assessment') {
        $eventId = (int)$_POST['event_id'];
        $type = $conn->real_escape_string(strtolower($_POST['test_type']));
        if ($type === 'pre-test') $type = 'pretest';
        if ($type === 'post-test') $type = 'posttest';

        $status = strtolower($_POST['status']);
        if ($status === 'active') $status = 'published';

        $title = $conn->real_escape_string(trim($_POST['title']));
        $instructions = $conn->real_escape_string(trim($_POST['instructions'] ?? ''));

        $sql = "INSERT INTO assessments (event_id, title, type, instructions, status, created_by) 
                VALUES ($eventId, '$title', '$type', '$instructions', '$status', $orgId)";
        $conn->query($sql);
        
        header("Location: assesment.php");
        exit;
    }

    // Add Question
    if ($_POST['action'] === 'add_question') {
        $assessId = (int)$_POST['assessment_id'];
        $qType = $_POST['q_type'];
        $points = (int)$_POST['points'];
        $qText = $conn->real_escape_string(trim($_POST['question_text']));
        
        if ($qType === 'truefalse') {
            $optA = 'True';
            $optB = 'False';
            $optC = '';
            $optD = '';
            $correct = ($_POST['tfOption'] === 'True') ? 'A' : 'B';
        } else {
            $optA = $conn->real_escape_string(trim($_POST['option_a'] ?? ''));
            $optB = $conn->real_escape_string(trim($_POST['option_b'] ?? ''));
            $optC = $conn->real_escape_string(trim($_POST['option_c'] ?? ''));
            $optD = $conn->real_escape_string(trim($_POST['option_d'] ?? ''));
            $correct = $_POST['correctOption'] ?? 'A';
        }

        $sql = "INSERT INTO assessment_questions 
                (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points) 
                VALUES ($assessId, '$qText', '$optA', '$optB', '$optC', '$optD', '$correct', $points)";
        $conn->query($sql);
        
        header("Location: assesment.php?assessment_id=$assessId");
        exit;
    }

    // Toggle Status
    if ($_POST['action'] === 'toggle_status') {
        $assessId = (int)$_POST['assessment_id'];
        $newStatus = trim($_POST['new_status']);
        // Must be drafted, published, or closed
        if (in_array($newStatus, ['draft', 'published', 'closed'])) {
            $sql = "UPDATE assessments SET status = '$newStatus' WHERE assessment_id = $assessId AND created_by = $orgId";
            $conn->query($sql);
        }
        header("Location: assesment.php");
        exit;
    }
}

// ── Fetch events and assessments grouped by event ────────────────────────────────────────────────
$groupedEvents = [];

// Fetch events
$evQueryList = $conn->query("SELECT EventId, EventName, EventDateTime FROM event WHERE OrgId=$orgId ORDER BY EventDateTime DESC");
if ($evQueryList) {
    while ($row = $evQueryList->fetch_assoc()) {
        $row['pretest'] = null;
        $row['posttest'] = null;
        $groupedEvents[$row['EventId']] = $row;
    }
}

// Fetch assessments
$qT = "
    SELECT a.*,
           (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.assessment_id) as q_count
    FROM assessments a
    JOIN event e ON a.event_id = e.EventId
    WHERE e.OrgId = $orgId
";
$resT = $conn->query($qT);
if ($resT) {
    while ($row = $resT->fetch_assoc()) {
        $eid = $row['event_id'];
        $type = strtolower($row['type']); // 'pretest' or 'posttest'
        if (isset($groupedEvents[$eid]) && ($type === 'pretest' || $type === 'posttest')) {
            $groupedEvents[$eid][$type] = $row;
        }
    }
}

$questionsData = [];
$qQ = "
    SELECT aq.* 
    FROM assessment_questions aq
    JOIN assessments a ON aq.assessment_id = a.assessment_id
    JOIN event e ON a.event_id = e.EventId
    WHERE e.OrgId = $orgId
    ORDER BY aq.question_id ASC
";
$resQ = $conn->query($qQ);
if ($resQ) {
    while ($row = $resQ->fetch_assoc()) {
        $questionsData[$row['assessment_id']][] = $row;
    }
}
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
            <input type="text" id="assessSearch" placeholder="Search event or test title..." style="padding: 8px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; font-family: inherit; min-width: 200px;">
            <select id="assessEventFilter" style="padding: 8px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; font-family: inherit; background: #fff; cursor: pointer;">
              <option value="">All Events</option>
              <?php foreach($groupedEvents as $evId => $ev): ?>
                <option value="<?= htmlspecialchars($ev['EventName']) ?>"><?= htmlspecialchars($ev['EventName']) ?></option>
              <?php endforeach; ?>
            </select>
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
                        <span style="font-weight: 600; font-size: 0.9rem; color:#0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%;" title="<?= htmlspecialchars($test['title']) ?>"><?= htmlspecialchars($test['title']) ?></span>
                        <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                      </div>
                      <div style="font-size: 0.8rem; color:#64748b; margin-bottom: 12px;">
                        <ion-icon name="help-circle-outline" style="vertical-align: middle;"></ion-icon> <?= $test['q_count'] ?> Questions
                      </div>
                      <div style="display: flex; gap: 8px;">
                        <button class="secondary-btn" style="flex: 1; justify-content: center; padding: 6px 0;" onclick="openQuestionBuilder(<?= $test['assessment_id'] ?>, '<?= htmlspecialchars(addslashes($test['title'])) ?>')">
                          <ion-icon name="create-outline"></ion-icon> <?= ($test['q_count'] > 0) ? 'Edit' : 'Add' ?>
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
                        <span style="font-weight: 600; font-size: 0.9rem; color:#0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 60%;" title="<?= htmlspecialchars($test['title']) ?>"><?= htmlspecialchars($test['title']) ?></span>
                        <span class="status-badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                      </div>
                      <div style="font-size: 0.8rem; color:#64748b; margin-bottom: 12px;">
                        <ion-icon name="help-circle-outline" style="vertical-align: middle;"></ion-icon> <?= $test['q_count'] ?> Questions
                      </div>
                      <div style="display: flex; gap: 8px;">
                        <button class="secondary-btn" style="flex: 1; justify-content: center; padding: 6px 0;" onclick="openQuestionBuilder(<?= $test['assessment_id'] ?>, '<?= htmlspecialchars(addslashes($test['title'])) ?>')">
                          <ion-icon name="create-outline"></ion-icon> <?= ($test['q_count'] > 0) ? 'Edit' : 'Add' ?>
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
      </section>

      <!-- VIEW 2: Question Builder -->
      <section id="builderView" class="view-section">
        <button class="secondary-btn" style="margin-bottom: 20px; border-color: transparent;" onclick="closeQuestionBuilder()">
          <ion-icon name="arrow-back-outline"></ion-icon> Back to Assessments
        </button>

        <div class="builder-header">
          <div class="builder-title">
            <p>Assessment Builder</p>
            <h3 id="builderTitleDisplay">Assessment: [Title]</h3>
          </div>
          <button class="primary-btn" onclick="openModal('addQuestionModal')">
            <ion-icon name="add-outline"></ion-icon> Add Question
          </button>
        </div>

        <div id="questionsContainer">
            <!-- Questions rendered via JS -->
        </div>

      </section>

    </div>
  </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Create Test Modal -->
<div class="modal-overlay" id="createTestModal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Create New Assessment Test</h3>
      <button class="btn-close" onclick="closeModal('createTestModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <form id="createTestForm" method="POST" action="assesment.php">
        <input type="hidden" name="action" value="create_assessment">
        <div class="form-group">
          <label>Event *</label>
          <select class="form-control" name="event_id" required>
            <option value="" disabled selected>Select an Event</option>
            <?php foreach($events as $ev): ?>
              <option value="<?= $ev['EventId'] ?>"><?= htmlspecialchars($ev['EventName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="form-group">
            <label>Test Type *</label>
            <select class="form-control" name="test_type" required>
              <option value="pretest">Pre-Test</option>
              <option value="posttest">Post-Test</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status">
              <option value="draft">Draft</option>
              <option value="published">Active</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label>Test Title *</label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Aviation Safety pre-test" required>
        </div>

        <div class="form-group">
          <label>Instructions</label>
          <textarea name="instructions" class="form-control" rows="3" placeholder="Enter instructions for the student..."></textarea>
        </div>

      </form>
    </div>
    <div class="modal-footer">
      <button class="secondary-btn" onclick="closeModal('createTestModal')">Cancel</button>
      <button class="primary-btn" type="button" onclick="document.getElementById('createTestForm').submit()">Save Test Details</button>
    </div>
  </div>
</div>

<!-- Add Question Modal -->
<div class="modal-overlay" id="addQuestionModal">
  <div class="modal-content" style="max-width: 650px;">
    <div class="modal-header">
      <h3>Add New Question</h3>
      <button class="btn-close" onclick="closeModal('addQuestionModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <form id="addQuestionForm" method="POST" action="assesment.php">
        <input type="hidden" name="action" value="add_question">
        <input type="hidden" name="assessment_id" id="hiddenAssessmentId" value="">
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
          <div class="form-group">
            <label>Question Type *</label>
            <select class="form-control" name="q_type" id="qTypeSelect" onchange="toggleOptionFields()">
              <option value="multiple">Multiple Choice</option>
              <option value="truefalse">True/False</option>
            </select>
          </div>
          <div class="form-group">
            <label>Points *</label>
            <input type="number" name="points" class="form-control" value="1" min="1" required>
          </div>
        </div>

        <div class="form-group">
          <label>Question Text *</label>
          <textarea class="form-control" name="question_text" rows="3" placeholder="Type your question here..." required></textarea>
        </div>

        <!-- Options Container for Multiple Choice -->
        <div id="multipleChoiceContainer" style="background:#f8fafc; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px;">
          <label style="display:block; font-size: 0.85rem; font-weight: 600; margin-bottom: 12px;">Answer Options (Select the correct one)</label>
          
          <div style="display: flex; gap: 12px; margin-bottom: 10px; align-items: center;">
            <input type="radio" name="correctOption" value="A" style="width: 18px; height: 18px;" checked>
            <span style="font-weight: 600; color: #64748b;">A.</span>
            <input type="text" name="option_a" class="form-control" placeholder="Option A" style="flex:1;">
          </div>
          <div style="display: flex; gap: 12px; margin-bottom: 10px; align-items: center;">
            <input type="radio" name="correctOption" value="B" style="width: 18px; height: 18px;">
            <span style="font-weight: 600; color: #64748b;">B.</span>
            <input type="text" name="option_b" class="form-control" placeholder="Option B" style="flex:1;">
          </div>
          <div style="display: flex; gap: 12px; margin-bottom: 10px; align-items: center;">
            <input type="radio" name="correctOption" value="C" style="width: 18px; height: 18px;">
            <span style="font-weight: 600; color: #64748b;">C.</span>
            <input type="text" name="option_c" class="form-control" placeholder="Option C" style="flex:1;">
          </div>
          <div style="display: flex; gap: 12px; align-items: center;">
            <input type="radio" name="correctOption" value="D" style="width: 18px; height: 18px;">
            <span style="font-weight: 600; color: #64748b;">D.</span>
            <input type="text" name="option_d" class="form-control" placeholder="Option D" style="flex:1;">
          </div>
        </div>

        <!-- Options Container for True/False -->
        <div id="trueFalseContainer" style="display:none; background:#f8fafc; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px;">
          <label style="display:block; font-size: 0.85rem; font-weight: 600; margin-bottom: 12px;">Select Correct Answer</label>
          <div style="display: flex; gap: 24px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input type="radio" name="tfOption" value="True" checked style="width: 18px; height: 18px;"> <span style="font-size: 0.9rem; font-weight: 600;">True</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input type="radio" name="tfOption" value="False" style="width: 18px; height: 18px;"> <span style="font-size: 0.9rem; font-weight: 600;">False</span>
            </label>
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

<script>
  const questionsData = <?= $jsQuestions ?: '{}' ?>;

  // UI logic for views
  const listView = document.getElementById('listView');
  const builderView = document.getElementById('builderView');
  const builderTitleDisplay = document.getElementById('builderTitleDisplay');

  function openQuestionBuilder(assessmentId, title) {
    document.getElementById('hiddenAssessmentId').value = assessmentId;
    builderTitleDisplay.textContent = `Assessment: ${title}`;
    listView.classList.remove('active');
    builderView.classList.add('active');
    renderQuestions(assessmentId);
  }

  function closeQuestionBuilder() {
    builderView.classList.remove('active');
    listView.classList.add('active');
  }

  // UI logic for modals
  function openModal(id) {
    document.getElementById(id).classList.add('active');
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove('active');
  }
  
  function openCreateModal(eventId = '', testType = 'pretest') {
    const modal = document.getElementById('createTestModal');
    const evSelect = modal.querySelector('select[name="event_id"]');
    const typeSelect = modal.querySelector('select[name="test_type"]');
    
    if(evSelect && eventId) evSelect.value = eventId;
    if(typeSelect && testType) typeSelect.value = testType;
    
    openModal('createTestModal');
  }

  // Toggle between Multiple Choice and True/False
  function toggleOptionFields() {
    const type = document.getElementById('qTypeSelect').value;
    const mcContainer = document.getElementById('multipleChoiceContainer');
    const tfContainer = document.getElementById('trueFalseContainer');

    if (type === 'truefalse') {
      mcContainer.style.display = 'none';
      tfContainer.style.display = 'block';
    } else {
      mcContainer.style.display = 'block';
      tfContainer.style.display = 'none';
    }
  }

  function renderQuestions(assessmentId) {
    const container = document.getElementById('questionsContainer');
    const questions = questionsData[assessmentId] || [];
    
    if (questions.length === 0) {
      container.innerHTML = `
        <div style="text-align: center; color: #64748b; padding: 40px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
          No questions yet for this test. Click "Add Question" to build your assessment.
        </div>
      `;
      return;
    }

    let html = '';
    questions.forEach((q, index) => {
      let optionsHtml = '';
      if (q.question_type === 'multiple') {
        const isCorrectA = q.correct_answer === 'A' ? 'color: #10b981; font-weight: 600;' : '';
        const isCorrectB = q.correct_answer === 'B' ? 'color: #10b981; font-weight: 600;' : '';
        const isCorrectC = q.correct_answer === 'C' ? 'color: #10b981; font-weight: 600;' : '';
        const isCorrectD = q.correct_answer === 'D' ? 'color: #10b981; font-weight: 600;' : '';
        optionsHtml = `
          <ul class="option-list" style="list-style: none; padding: 0;">
            <li class="option-item" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; ${isCorrectA}">A. ${q.option_a || ''} ${q.correct_answer === 'A' ? '&#10003;' : ''}</li>
            <li class="option-item" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; ${isCorrectB}">B. ${q.option_b || ''} ${q.correct_answer === 'B' ? '&#10003;' : ''}</li>
            <li class="option-item" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; ${isCorrectC}">C. ${q.option_c || ''} ${q.correct_answer === 'C' ? '&#10003;' : ''}</li>
            <li class="option-item" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; ${isCorrectD}">D. ${q.option_d || ''} ${q.correct_answer === 'D' ? '&#10003;' : ''}</li>
          </ul>`;
      } else {
        optionsHtml = `<div style="font-weight: 600; color: #10b981; margin-top: 10px;">Answer: ${q.correct_answer}</div>`;
      }

      html += `
        <div class="test-card" style="margin-bottom: 16px; align-items: flex-start; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px;">
            <div style="font-weight: 700; color: #334155;">Question ${index + 1}</div>
            <div style="color: #64748b; font-size: 0.85rem; font-weight: 600;">${q.points} Points</div>
          </div>
          <p style="color: #1e293b; margin-bottom: 16px;">${q.question_text}</p>
          ${optionsHtml}
        </div>
      `;
    });

    container.innerHTML = html;
  }

  // Restore the assessment builder view if we just added a question
  window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const activeAssessmentId = urlParams.get('assessment_id');
    
    if (activeAssessmentId) {
      // Find the assessment button that matches this ID so we can trigger it and get the title
      const editBtns = document.querySelectorAll(`button[onclick*="openQuestionBuilder(${activeAssessmentId}"]`);
      if (editBtns.length > 0) {
      <button class="primary-btn" type="button" onclick="document.getElementById('createTestForm').submit()">Save Test Details</button>
    </div>
  </div>
</div>

<!-- Add Question Modal -->
<div class="modal-overlay" id="addQuestionModal">
  <div class="modal-content" style="max-width: 650px;">
    <div class="modal-header">
      <h3>Add New Question</h3>
      <button class="btn-close" onclick="closeModal('addQuestionModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <form id="addQuestionForm" method="POST" action="assesment.php">
        <input type="hidden" name="action" value="add_question">
        <input type="hidden" name="assessment_id" id="hiddenAssessmentId" value="">
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
          <div class="form-group">
            <label>Question Type *</label>
            <select class="form-control" name="q_type" id="qTypeSelect" onchange="toggleOptionFields()">
              <option value="multiple">Multiple Choice</option>
              <option value="truefalse">True/False</option>
            </select>
          </div>
          <div class="form-group">
            <label>Points *</label>
            <input type="number" name="points" class="form-control" value="1" min="1" required>
          </div>
        </div>

        <div class="form-group">
          <label>Question Text *</label>
          <textarea class="form-control" name="question_text" rows="3" placeholder="Type your question here..." required></textarea>
        </div>

        <!-- Options Container for Multiple Choice -->
        <div id="multipleChoiceContainer" style="background:#f8fafc; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px;">
          <label style="display:block; font-size: 0.85rem; font-weight: 600; margin-bottom: 12px;">Answer Options (Select the correct one)</label>
          
          <div style="display: flex; gap: 12px; margin-bottom: 10px; align-items: center;">
            <input type="radio" name="correctOption" value="A" style="width: 18px; height: 18px;" checked>
            <span style="font-weight: 600; color: #64748b;">A.</span>
            <input type="text" name="option_a" class="form-control" placeholder="Option A" style="flex:1;">
          </div>
          <div style="display: flex; gap: 12px; margin-bottom: 10px; align-items: center;">
            <input type="radio" name="correctOption" value="B" style="width: 18px; height: 18px;">
            <span style="font-weight: 600; color: #64748b;">B.</span>
            <input type="text" name="option_b" class="form-control" placeholder="Option B" style="flex:1;">
          </div>
          <div style="display: flex; gap: 12px; margin-bottom: 10px; align-items: center;">
            <input type="radio" name="correctOption" value="C" style="width: 18px; height: 18px;">
            <span style="font-weight: 600; color: #64748b;">C.</span>
            <input type="text" name="option_c" class="form-control" placeholder="Option C" style="flex:1;">
          </div>
          <div style="display: flex; gap: 12px; align-items: center;">
            <input type="radio" name="correctOption" value="D" style="width: 18px; height: 18px;">
            <span style="font-weight: 600; color: #64748b;">D.</span>
            <input type="text" name="option_d" class="form-control" placeholder="Option D" style="flex:1;">
          </div>
        </div>

        <!-- Options Container for True/False -->
        <div id="trueFalseContainer" style="display:none; background:#f8fafc; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 16px;">
          <label style="display:block; font-size: 0.85rem; font-weight: 600; margin-bottom: 12px;">Select Correct Answer</label>
          <div style="display: flex; gap: 24px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input type="radio" name="tfOption" value="True" checked style="width: 18px; height: 18px;"> <span style="font-size: 0.9rem; font-weight: 600;">True</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
              <input type="radio" name="tfOption" value="False" style="width: 18px; height: 18px;"> <span style="font-size: 0.9rem; font-weight: 600;">False</span>
            </label>
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

<script>
  const questionsData = <?= $jsQuestions ?: '{}' ?>;

  // UI logic for views
  const listView = document.getElementById('listView');
  const builderView = document.getElementById('builderView');
  const builderTitleDisplay = document.getElementById('builderTitleDisplay');

  function openQuestionBuilder(assessmentId, title) {
    document.getElementById('hiddenAssessmentId').value = assessmentId;
    builderTitleDisplay.textContent = `Assessment: ${title}`;
    listView.classList.remove('active');
    builderView.classList.add('active');
    renderQuestions(assessmentId);
  }

  function closeQuestionBuilder() {
    builderView.classList.remove('active');
    listView.classList.add('active');
  }

  // UI logic for modals
  function openModal(id) {
    document.getElementById(id).classList.add('active');
  }
  function closeModal(id) {
    document.getElementById(id).classList.remove('active');
  }
  
  function openCreateModal(eventId = '', testType = 'pretest') {
    const modal = document.getElementById('createTestModal');
    const evSelect = modal.querySelector('select[name="event_id"]');
    const typeSelect = modal.querySelector('select[name="test_type"]');
    
    if(evSelect && eventId) evSelect.value = eventId;
    if(typeSelect && testType) typeSelect.value = testType;
    
    openModal('createTestModal');
  }

  // Toggle between Multiple Choice and True/False
  function toggleOptionFields() {
    const type = document.getElementById('qTypeSelect').value;
    const mcContainer = document.getElementById('multipleChoiceContainer');
    const tfContainer = document.getElementById('trueFalseContainer');

    if (type === 'truefalse') {
      mcContainer.style.display = 'none';
      tfContainer.style.display = 'block';
    } else {
      mcContainer.style.display = 'block';
      tfContainer.style.display = 'none';
    }
  }

  function renderQuestions(assessmentId) {
    const container = document.getElementById('questionsContainer');
    const questions = questionsData[assessmentId] || [];
    
    if (questions.length === 0) {
      container.innerHTML = `
        <div style="text-align: center; color: #64748b; padding: 40px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
          No questions yet for this test. Click "Add Question" to build your assessment.
        </div>
      `;
      return;
    }

    let html = '';
    questions.forEach((q, index) => {
      let optionsHtml = '';
      if (q.question_type === 'multiple') {
        const isCorrectA = q.correct_answer === 'A' ? 'color: #10b981; font-weight: 600;' : '';
        const isCorrectB = q.correct_answer === 'B' ? 'color: #10b981; font-weight: 600;' : '';
        const isCorrectC = q.correct_answer === 'C' ? 'color: #10b981; font-weight: 600;' : '';
        const isCorrectD = q.correct_answer === 'D' ? 'color: #10b981; font-weight: 600;' : '';
        optionsHtml = `
          <ul class="option-list" style="list-style: none; padding: 0;">
            <li class="option-item" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; ${isCorrectA}">A. ${q.option_a || ''} ${q.correct_answer === 'A' ? '&#10003;' : ''}</li>
            <li class="option-item" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; ${isCorrectB}">B. ${q.option_b || ''} ${q.correct_answer === 'B' ? '&#10003;' : ''}</li>
            <li class="option-item" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; ${isCorrectC}">C. ${q.option_c || ''} ${q.correct_answer === 'C' ? '&#10003;' : ''}</li>
            <li class="option-item" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; ${isCorrectD}">D. ${q.option_d || ''} ${q.correct_answer === 'D' ? '&#10003;' : ''}</li>
          </ul>`;
      } else {
        optionsHtml = `<div style="font-weight: 600; color: #10b981; margin-top: 10px;">Answer: ${q.correct_answer}</div>`;
      }

      html += `
        <div class="test-card" style="margin-bottom: 16px; align-items: flex-start; flex-direction: column;">
          <div style="display: flex; justify-content: space-between; width: 100%; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 12px;">
            <div style="font-weight: 700; color: #334155;">Question ${index + 1}</div>
            <div style="color: #64748b; font-size: 0.85rem; font-weight: 600;">${q.points} Points</div>
          </div>
          <p style="color: #1e293b; margin-bottom: 16px;">${q.question_text}</p>
          ${optionsHtml}
        </div>
      `;
    });

    container.innerHTML = html;
  }

  // Restore the assessment builder view if we just added a question
  window.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const activeAssessmentId = urlParams.get('assessment_id');
    
    if (activeAssessmentId) {
      // Find the assessment button that matches this ID so we can trigger it and get the title
      const editBtns = document.querySelectorAll(`button[onclick*="openQuestionBuilder(${activeAssessmentId}"]`);
      if (editBtns.length > 0) {
        editBtns[0].click();
        
        // Remove the query param from the URL without refreshing, to clean up
        const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({path: newUrl}, '', newUrl);
      }
    }

    // Filter test cards by search input and event select
    function filterAssessCards() {
      const q = (document.getElementById('assessSearch')?.value || '').toLowerCase().trim();
      const evFilter = (document.getElementById('assessEventFilter')?.value || '').toLowerCase().trim();
      const cards = document.querySelectorAll('.test-card');

      cards.forEach(c => {
        const text = c.textContent.toLowerCase();
        const evTitle = (c.querySelector('.test-card-title')?.textContent || '').toLowerCase();
        
        const matchQ = !q || text.includes(q);
        const matchEv = !evFilter || evTitle.includes(evFilter);

        if (matchQ && matchEv) {
          c.style.display = '';
        } else {
          c.style.display = 'none';
        }
      });
    }

    document.getElementById('assessSearch')?.addEventListener('input', filterAssessCards);
    document.getElementById('assessEventFilter')?.addEventListener('change', filterAssessCards);
  });

</script>

<script src="../../assets/js/org/org.js"></script>
</body>
</html>
