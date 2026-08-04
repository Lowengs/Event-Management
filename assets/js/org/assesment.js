/**
 * assesment.js — Assessment Builder & Filter Logic for Organization Portal
 */

var listView = null;
var builderView = null;
var builderTitleDisplay = null;

function initAssessmentPage() {
  listView = document.getElementById('listView');
  builderView = document.getElementById('builderView');
  builderTitleDisplay = document.getElementById('builderTitleDisplay');

  var urlParams = new URLSearchParams(window.location.search);
  var activeId = urlParams.get('assessment_id');
  if (activeId) {
    var btns = document.querySelectorAll('button[onclick*="openQuestionBuilder(' + activeId + '"]');
    if (btns.length > 0) { 
      btns[0].click(); 
      window.history.replaceState({}, '', window.location.pathname); 
    }
  }

  function filterCards() {
    var q = (document.getElementById('assessSearch') || { value: '' }).value.toLowerCase().trim();
    var ev = (document.getElementById('assessEventFilter') || { value: '' }).value.toLowerCase().trim();
    var df = (document.getElementById('assessDateFilter') || { value: '' }).value;
    var fds = '';
    if (df) { 
      var p = df.split('-'); 
      if (p.length === 3) {
        var d = new Date(p[0], p[1] - 1, p[2]);
        fds = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).toLowerCase();
      }
    }
    document.querySelectorAll('.test-card').forEach(function(c) {
      var t = c.textContent.toLowerCase();
      var et = (c.querySelector('.test-card-title') || { textContent: '' }).textContent.toLowerCase();
      var cd = (c.querySelector('.test-event') || { textContent: '' }).textContent.toLowerCase();
      c.style.display = (!q || t.includes(q)) && (!ev || et.includes(ev)) && (!df || cd.includes(fds) || cd.includes(df)) ? '' : 'none';
    });
  }

  ['assessSearch', 'assessEventFilter', 'assessDateFilter'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener(id === 'assessSearch' ? 'input' : 'change', filterCards);
  });

  document.querySelectorAll('form').forEach(function(f) {
    f.addEventListener('submit', function() {
      var btns = f.querySelectorAll('button[type="submit"], input[type="submit"]');
      btns.forEach(function(b) { b.disabled = true; });
    });
  });
}

function openQuestionBuilder(assessmentId, title) {
  if (!builderView || !listView) initAssessmentPage();
  document.getElementById('hiddenAssessmentId').value = assessmentId;
  if (builderTitleDisplay) builderTitleDisplay.textContent = 'Assessment: ' + title;
  if (listView) listView.classList.remove('active');
  if (builderView) builderView.classList.add('active');
  renderQuestions(assessmentId);
}

function closeQuestionBuilder() {
  if (builderView) builderView.classList.remove('active');
  if (listView) listView.classList.add('active');
}

function openModal(id) { 
  var m = document.getElementById(id);
  if (m) m.classList.add('active'); 
}

function closeModal(id) { 
  var m = document.getElementById(id);
  if (m) m.classList.remove('active'); 
}

function openCreateModal(eventId, testType) {
  eventId = eventId || '';
  testType = testType || 'pretest';
  var modal = document.getElementById('createTestModal');
  if (!modal) return;
  var evSelect = modal.querySelector('select[name="event_id"]');
  var typeSelect = modal.querySelector('select[name="test_type"]');
  if (evSelect && eventId) evSelect.value = eventId;
  if (typeSelect && testType) typeSelect.value = testType;
  openModal('createTestModal');
}

function toggleOptionFields() {
  var isTF = document.getElementById('qTypeSelect').value === 'truefalse';
  var mc = document.getElementById('multipleChoiceContainer');
  var tf = document.getElementById('trueFalseContainer');
  if (mc) mc.style.display = isTF ? 'none' : 'block';
  if (tf) tf.style.display = isTF ? 'block' : 'none';
}

function renderQuestions(assessmentId) {
  var container = document.getElementById('questionsContainer');
  if (!container) return;
  var questions = (typeof questionsData !== 'undefined' ? questionsData[assessmentId] : []) || [];
  if (questions.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:#64748b;padding:40px;background:#f8fafc;border-radius:8px;border:1px dashed #cbd5e1;">No questions yet. Click "Add Question" to build your assessment.</div>';
    return;
  }
  var html = '';
  questions.forEach(function(q, index) {
    var optionsHtml = '';
    if (q.question_type === 'multiple') {
      ['A', 'B', 'C', 'D'].forEach(function(l) {
        var ok = q.correct_answer === l;
        optionsHtml += '<li style="padding:8px;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:8px;' + (ok ? 'color:#10b981;font-weight:600;' : '') + '">' + l + '. ' + (q['option_' + l.toLowerCase()] || '') + (ok ? ' &#10003;' : '') + '</li>';
      });
      optionsHtml = '<ul style="list-style:none;padding:0;">' + optionsHtml + '</ul>';
    } else {
      optionsHtml = '<div style="font-weight:600;color:#10b981;margin-top:10px;">Answer: ' + q.correct_answer + '</div>';
    }
    var deleteBtn = '<form method="POST" action="assesment.php" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this question?\');">' +
      '<input type="hidden" name="action" value="delete_question">' +
      '<input type="hidden" name="question_id" value="' + q.question_id + '">' +
      '<input type="hidden" name="assessment_id" value="' + assessmentId + '">' +
      '<button type="submit" class="secondary-btn" style="padding:4px 8px;font-size:11px;color:#ef4444;border-color:#fca5a5;" title="Delete Question"><ion-icon name="trash-outline"></ion-icon> Delete</button>' +
    '</form>';

    var editBtn = '<button type="button" class="secondary-btn" style="padding:4px 8px;font-size:11px;" onclick="openEditQuestionModal(' + assessmentId + ', ' + q.question_id + ')"><ion-icon name="create-outline"></ion-icon> Edit</button>';

    html += '<div class="test-card" style="margin-bottom:16px;align-items:flex-start;flex-direction:column;"><div style="display:flex;justify-content:space-between;align-items:center;width:100%;border-bottom:1px solid #e2e8f0;padding-bottom:12px;margin-bottom:12px;"><div style="font-weight:700;color:#334155;">Question ' + (index + 1) + '</div><div style="display:flex;align-items:center;gap:10px;"><span style="color:#64748b;font-size:0.85rem;font-weight:600;">' + q.points + ' Points</span>' + editBtn + deleteBtn + '</div></div><p style="color:#1e293b;margin-bottom:16px;">' + q.question_text + '</p>' + optionsHtml + '</div>';
  });
  container.innerHTML = html;
}

function openEditQuestionModal(assessmentId, questionId) {
  var questions = (typeof questionsData !== 'undefined' ? questionsData[assessmentId] : []) || [];
  var q = questions.find(function(item) { return String(item.question_id) === String(questionId); });
  if (!q) return;
  document.getElementById('editQuestionAssessmentId').value = assessmentId;
  document.getElementById('editQuestionId').value = questionId;
  document.getElementById('editQuestionText').value = q.question_text || '';
  document.getElementById('editOptionA').value = q.option_a || '';
  document.getElementById('editOptionB').value = q.option_b || '';
  document.getElementById('editOptionC').value = q.option_c || '';
  document.getElementById('editOptionD').value = q.option_d || '';
  document.getElementById('editCorrectAnswer').value = q.correct_answer || 'A';
  document.getElementById('editQuestionPoints').value = q.points || 1;
  openModal('editQuestionModal');
}

function openEditAssessmentModal(testObj) {
  if (!testObj) return;
  document.getElementById('editAssessId').value = testObj.assessment_id;
  document.getElementById('editAssessType').value = testObj.type;
  document.getElementById('editAssessStatus').value = testObj.status;
  document.getElementById('editAssessTitle').value = testObj.title;
  document.getElementById('editAssessInstructions').value = testObj.instructions || '';
  var elTime = document.getElementById('editAssessTimeLimit');
  if (elTime) elTime.value = testObj.time_limit || 30;
  openModal('editAssessmentModal');
}

window.addEventListener('DOMContentLoaded', initAssessmentPage);
