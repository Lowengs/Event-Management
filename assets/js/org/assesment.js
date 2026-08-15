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
    var title = (typeof assessmentsMap !== 'undefined' && assessmentsMap[activeId])
      ? assessmentsMap[activeId].title
      : ('Assessment #' + activeId);

    openQuestionBuilder(activeId, title);
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
  var hiddenAssId = document.getElementById('hiddenAssessmentId');
  if (hiddenAssId) hiddenAssId.value = assessmentId;
  
  if (!title && typeof assessmentsMap !== 'undefined' && assessmentsMap[assessmentId]) {
    title = assessmentsMap[assessmentId].title;
  }

  if (builderTitleDisplay) builderTitleDisplay.textContent = 'Assessment: ' + (title || ('#' + assessmentId));
  if (listView) listView.classList.remove('active');
  if (builderView) builderView.classList.add('active');
  renderQuestions(assessmentId);

  if (window.history && window.history.replaceState) {
    window.history.replaceState({}, '', window.location.pathname + '?assessment_id=' + assessmentId);
  }
}

function closeQuestionBuilder() {
  if (builderView) builderView.classList.remove('active');
  if (listView) listView.classList.add('active');
  if (window.history && window.history.replaceState) {
    window.history.replaceState({}, '', window.location.pathname);
  }
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
  var type = (document.getElementById('qTypeSelect') || {}).value || 'multiple';
  var mc = document.getElementById('multipleChoiceContainer');
  var tf = document.getElementById('trueFalseContainer');
  var essay = document.getElementById('essayContainer');
  if (mc) mc.style.display = (type === 'multiple') ? 'block' : 'none';
  if (tf) tf.style.display = (type === 'truefalse') ? 'block' : 'none';
  if (essay) essay.style.display = (type === 'essay') ? 'block' : 'none';
}

function toggleEditOptionFields() {
  var type = (document.getElementById('editQTypeSelect') || {}).value || 'multiple';
  var mc = document.getElementById('editMultipleChoiceContainer');
  var tf = document.getElementById('editTrueFalseContainer');
  var essay = document.getElementById('editEssayContainer');
  if (mc) mc.style.display = (type === 'multiple') ? 'block' : 'none';
  if (tf) tf.style.display = (type === 'truefalse') ? 'block' : 'none';
  if (essay) essay.style.display = (type === 'essay') ? 'block' : 'none';
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
    var qType = q.question_type || (q.option_a || q.option_b ? 'multiple' : 'essay');
    var isEssay = (qType === 'essay') || (q.correct_answer === 'ESSAY') || (!q.option_a && !q.option_b && !q.option_c && !q.option_d);
    var isTF = (qType === 'truefalse') || (q.option_a === 'True' && q.option_b === 'False');

    var typeBadge = isEssay 
      ? '<span style="background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;">Essay</span>'
      : (isTF 
        ? '<span style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;">True/False</span>'
        : '<span style="background:#f8fafc;color:#475569;border:1px solid #cbd5e1;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;">Multiple Choice</span>');

    var optionsHtml = '';
    if (isEssay) {
      optionsHtml = '<div style="background:#f8fafc;border-left:4px solid #10b981;padding:12px 14px;border-radius:8px;font-size:0.88rem;color:#475569;margin-top:10px;"><strong style="color:#0f172a;display:block;margin-bottom:2px;">Essay / Freeform Response</strong><span style="color:#64748b;">Students submit a written response in an open text box.</span></div>';
    } else if (isTF) {
      var ansText = (q.correct_answer === 'B' || q.correct_answer === 'False') ? 'False' : 'True';
      optionsHtml = '<div style="font-weight:700;color:#10b981;margin-top:10px;font-size:0.9rem;">Correct Answer: ' + ansText + '</div>';
    } else {
      ['A', 'B', 'C', 'D'].forEach(function(l) {
        var optVal = q['option_' + l.toLowerCase()] || '';
        if (optVal) {
          var ok = q.correct_answer === l;
          optionsHtml += '<li style="padding:8px 12px;border:1.5px solid ' + (ok ? '#86efac' : '#e2e8f0') + ';background:' + (ok ? '#f0fdf4' : '#ffffff') + ';border-radius:8px;margin-bottom:8px;' + (ok ? 'color:#15803d;font-weight:700;' : 'color:#334155;') + '">' + l + '. ' + optVal + (ok ? ' &#10004;' : '') + '</li>';
        }
      });
      optionsHtml = '<ul style="list-style:none;padding:0;margin:8px 0 0;">' + optionsHtml + '</ul>';
    }
    var deleteBtn = '<button type="button" class="secondary-btn" style="padding:4px 8px;font-size:11px;color:#ef4444;border-color:#fca5a5;" title="Delete Question" onclick="confirmDeleteQuestion(' + assessmentId + ', ' + q.question_id + ')"><ion-icon name="trash-outline"></ion-icon> Delete</button>';

    var editBtn = '<button type="button" class="secondary-btn" style="padding:4px 8px;font-size:11px;" onclick="openEditQuestionModal(' + assessmentId + ', ' + q.question_id + ')"><ion-icon name="create-outline"></ion-icon> Edit</button>';

    html += '<div class="test-card" style="margin-bottom:16px;align-items:flex-start;flex-direction:column;"><div style="display:flex;justify-content:space-between;align-items:center;width:100%;border-bottom:1px solid #e2e8f0;padding-bottom:12px;margin-bottom:12px;"><div style="display:flex;align-items:center;gap:8px;"><span style="font-weight:700;color:#334155;">Question ' + (index + 1) + '</span> ' + typeBadge + '</div><div style="display:flex;align-items:center;gap:10px;"><span style="color:#64748b;font-size:0.85rem;font-weight:600;">' + q.points + ' Point' + (q.points > 1 ? 's' : '') + '</span>' + editBtn + deleteBtn + '</div></div><p style="color:#1e293b;font-size:0.95rem;font-weight:600;margin-bottom:12px;line-height:1.5;">' + q.question_text + '</p>' + optionsHtml + '</div>';
  });
  container.innerHTML = html;
}

window.confirmDeleteQuestion = function(assessmentId, questionId) {
  showConfirmModal('Are you sure you want to delete this question?', function() {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'assesment.php';
    form.innerHTML = '<input type="hidden" name="action" value="delete_question">' +
      '<input type="hidden" name="question_id" value="' + questionId + '">' +
      '<input type="hidden" name="assessment_id" value="' + assessmentId + '">';
    document.body.appendChild(form);
    form.submit();
  }, 'Delete Question', 'danger');
};

function openEditQuestionModal(assessmentId, questionId) {
  var questions = (typeof questionsData !== 'undefined' ? questionsData[assessmentId] : []) || [];
  var q = questions.find(function(item) { return String(item.question_id) === String(questionId); });
  if (!q) return;

  var qType = q.question_type || (q.option_a || q.option_b ? ((q.option_a === 'True' && q.option_b === 'False') ? 'truefalse' : 'multiple') : 'essay');
  if (q.correct_answer === 'ESSAY') qType = 'essay';

  document.getElementById('editQuestionAssessmentId').value = assessmentId;
  document.getElementById('editQuestionId').value = questionId;
  document.getElementById('editQTypeSelect').value = qType;
  document.getElementById('editQuestionText').value = q.question_text || '';
  document.getElementById('editOptionA').value = q.option_a || '';
  document.getElementById('editOptionB').value = q.option_b || '';
  document.getElementById('editOptionC').value = q.option_c || '';
  document.getElementById('editOptionD').value = q.option_d || '';
  document.getElementById('editQuestionPoints').value = q.points || 1;

  var ans = (q.correct_answer || 'A').toUpperCase();
  if (['A', 'B', 'C', 'D'].includes(ans)) {
    var rad = document.getElementById('editCorrect' + ans);
    if (rad) rad.checked = true;
    var tfRad = document.getElementById(ans === 'B' ? 'editTfB' : 'editTfA');
    if (tfRad) tfRad.checked = true;
  }

  toggleEditOptionFields();
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
