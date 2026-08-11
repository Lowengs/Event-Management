/* ── Extracted from organization/documents_org.php ── */
/* ── Toast ─────────────────────────────────────────────── */
function showToast(msg,ok=true){
  const t=document.getElementById('toast');
  if(!t) return;
  t.textContent=msg; t.style.background=ok?'#16a34a':'#dc2626'; t.style.display='block';
  setTimeout(()=>t.style.display='none',3500);
}

/* ── State ─────────────────────────────────────────────── */
const DOC_PAGE_SIZE = 25;
let allDocs = [];
let filteredDocs = [];
let docCurrentPage = 1;

/* ── Load docs from API ────────────────────────────────── */
function loadDocs(){
  // Render immediately with window.orgEvents
  renderDocsPage();
  fetch('../../config/API/endpoints/index.php?action=get_org_documents')
    .then(r => r.json())
    .then(data => {
      allDocs = data.documents || [];
      applyFilter();
    })
    .catch(err => {
      console.error('Error loading documents:', err);
    });
}

/* ── Helper to open Upload Modal pre-selecting a specific event ── */
function openUploadForEvent(eventId) {
  const modal = document.getElementById('uploadDocModal');
  if (!modal) return;
  const evSelect = modal.querySelector('select[name="EventId"]');
  if (evSelect && eventId) {
    evSelect.value = String(eventId);
  }
  modal.classList.add('active');
}

/* ── Render a page of docs in Event Accordions ─────────────────────────────── */
function renderDocsPage() {
  const el = document.getElementById('docList');
  if (!el) return;

  const searchEl = document.getElementById('docSearch');
  const typeEl = document.getElementById('docTypeFilter');
  const evEl = document.getElementById('docEventFilter');

  const q = searchEl ? searchEl.value.toLowerCase().trim() : '';
  const t = typeEl ? typeEl.value : '';
  const evFilter = evEl ? evEl.value : '';

  // 1. Group documents by event
  const groups = {};

  // Seed with all organization events from window.orgEvents
  const orgEvents = window.orgEvents || [];
  orgEvents.forEach(ev => {
    if (!ev || !ev.EventId) return;
    const key = `event-${ev.EventId}`;
    groups[key] = {
      id: ev.EventId,
      name: ev.EventName || 'Untitled Event',
      date: ev.EventDateTime,
      docs: []
    };
  });

  // Always seed general organization documents group
  groups['organization-files'] = {
    id: null,
    name: 'General Organization Documents',
    date: null,
    docs: []
  };

  // Add all fetched documents to their group
  allDocs.forEach(d => {
    const key = d.EventId ? `event-${d.EventId}` : 'organization-files';
    if (!groups[key]) {
      groups[key] = { id: d.EventId, name: d.EventName || 'General Organization Documents', date: d.EventDateTime, docs: [] };
    }
    groups[key].docs.push(d);
  });

  let groupList = Object.values(groups);

  // 2. Filter groups based on search & drop downs
  if (evFilter) {
    groupList = groupList.filter(g => String(g.id) === String(evFilter) || (!g.id && evFilter === 'general'));
  }

  if (q || t) {
    groupList = groupList.map(g => {
      const matchedDocs = g.docs.filter(d => {
        const matchSearch = !q || (d.Title || d.DocumentName || '').toLowerCase().includes(q) || (g.name && g.name.toLowerCase().includes(q));
        const matchType = !t || (d.DocType || d.DocumentType) === t;
        return matchSearch && matchType;
      });
      return { ...g, docs: matchedDocs };
    }).filter(g => g.docs.length > 0 || (q && g.name && g.name.toLowerCase().includes(q)));
  }

  // Hide general group if it has 0 docs
  groupList = groupList.filter(g => (g.id !== null && g.id !== undefined) || g.docs.length > 0);

  // Sort groups: newest events first, General Organization Documents at the bottom
  groupList.sort((a, b) => {
    if (!a.id) return 1;
    if (!b.id) return -1;
    const dA = a.date ? new Date(a.date).getTime() : 0;
    const dB = b.date ? new Date(b.date).getTime() : 0;
    return dB - dA;
  });

  const total = groupList.length;
  if (!total) {
    el.innerHTML = '<p style="text-align:center;padding:40px;color:#94a3b8;font-family:Inter,sans-serif;">No documents or matching events found.</p>';
    const bar = document.getElementById('docPaginationBar');
    if (bar) bar.style.display = 'none';
    return;
  }

  const totalPages = Math.max(1, Math.ceil(total / DOC_PAGE_SIZE));
  const start = (docCurrentPage - 1) * DOC_PAGE_SIZE;
  const end   = Math.min(start + DOC_PAGE_SIZE, total);
  const pageGroups = groupList.slice(start, end);

  el.innerHTML = pageGroups.map((group, idx) => {
    const eventDate = group.date ? new Date(group.date).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : 'General';
    
    let itemsHtml = '';
    if (group.docs.length === 0) {
      itemsHtml = `
        <div style="padding:20px;text-align:center;background:#f8fafc;border-radius:10px;border:1px dashed #cbd5e1;margin-top:10px;">
          <p style="margin:0 0 10px;font-size:13px;color:#64748b;">No documents uploaded for this event yet.</p>
          <button type="button" onclick="openUploadForEvent(${group.id})" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">
            <ion-icon name="cloud-upload-outline"></ion-icon> Upload Document
          </button>
        </div>`;
    } else {
      const docTypeLabels = {
        'EventProposal':      'Project Proposal / OPlan',
        'EventProgramFlow':   'Program Flow',
        'PostActivityReport': 'Post Activity Report',
        'FinancialReport':    'Financial Report',
        'Supporting Document':'Supporting File',
        'Proposal':           'Project Proposal / OPlan',
        'ProgramFlow':        'Program Flow'
      };

      itemsHtml = group.docs.map(d => {
        const dt = new Date(d.UploadedAt || d.DateUploaded || Date.now()).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
        const rawType = String(d.DocType || d.DocumentType || 'File').trim();
        const displayType = docTypeLabels[rawType] || rawType;
        const displayTitle = d.Title || d.DocumentName || 'Document';

        return `
          <div class="report-card">
            <div class="report-card-header">
              <div class="report-card-title-block">
                <ion-icon name="document-text-outline" class="doc-icon"></ion-icon>
                <div>
                  <h5>${displayTitle}</h5>
                  <p><strong>${displayType}</strong> &bull; Uploaded: ${dt} ${d.FileSize ? '&bull; ' + d.FileSize : ''}</p>
                </div>
              </div>
              <div class="report-actions">
                <a href="../../${d.FilePath}" target="_blank" class="icon-action-btn" title="View Document"><ion-icon name="eye-outline"></ion-icon></a>
                <a href="../../${d.FilePath}" download="${displayTitle}" class="icon-action-btn" title="Download Document"><ion-icon name="download-outline"></ion-icon></a>
              </div>
            </div>
          </div>`;
      }).join('');
    }

    const isFirstExpanded = idx === 0 ? 'expanded' : '';
    return `
      <div class="event-accordion-item ${isFirstExpanded}">
        <div class="event-summary" onclick="this.parentElement.classList.toggle('expanded')">
          <div class="event-summary-left">
            <ion-icon name="chevron-forward-outline" class="chevron-icon"></ion-icon>
            <ion-icon name="calendar-outline" class="calendar-icon"></ion-icon>
            <div class="event-title-date">
              <h4>${group.name}</h4>
              <p>${eventDate} &bull; ${group.docs.length} document(s)</p>
            </div>
          </div>
          <span style="font-size:12px;background:${group.docs.length ? '#e0f2fe' : '#f1f5f9'};color:${group.docs.length ? '#0284c7' : '#64748b'};padding:4px 12px;border-radius:20px;font-weight:700;">
            ${group.docs.length} File(s)
          </span>
        </div>
        <div class="event-details">
          ${itemsHtml}
        </div>
      </div>`;
  }).join('');

  // Pagination bar
  const bar = document.getElementById('docPaginationBar');
  if (bar) {
    bar.style.display = 'flex';
    document.getElementById('docPageInfo').innerHTML =
      `Showing <strong>${start+1}–${end}</strong> of <strong>${total}</strong> event section${total!==1?'s':''}`;

    const ctrl = document.getElementById('docPageControls');
    ctrl.innerHTML = '';
    const mk = (label, page, active, disabled) => {
      const btn = document.createElement('button');
      btn.style.cssText = `display:inline-flex;align-items:center;justify-content:center;height:34px;min-width:34px;padding:0 10px;border-radius:8px;border:1px solid ${active?'#6366f1':'#e2e8f0'};background:${active?'#6366f1':'#fff'};color:${active?'#fff':'#374151'};font-size:13px;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;opacity:${disabled?'.4':'1'};`;
      btn.disabled = disabled;
      btn.textContent = label;
      if (!disabled) btn.addEventListener('click', () => { docCurrentPage = page; renderDocsPage(); });
      return btn;
    };
    ctrl.appendChild(mk('‹ Prev', docCurrentPage - 1, false, docCurrentPage === 1));
    const pw = 2;
    for (let p = Math.max(1, docCurrentPage - pw); p <= Math.min(totalPages, docCurrentPage + pw); p++) {
      ctrl.appendChild(mk(p, p, p === docCurrentPage, false));
    }
    ctrl.appendChild(mk('Next ›', docCurrentPage + 1, false, docCurrentPage >= totalPages));
  }
}

/* ── Filter ────────────────────────────────────────────── */
function applyFilter(){
  docCurrentPage = 1;
  renderDocsPage();
}

/* ── Upload Modal & Handlers ────────────────────────────── */
const openBtn = document.getElementById('openUploadModalBtn');
const closeBtn = document.getElementById('closeUploadModal');
const cancelBtn = document.getElementById('cancelUploadBtn');
const submitBtn = document.getElementById('submitDocBtn');

if (openBtn) openBtn.addEventListener('click',()=>document.getElementById('uploadDocModal').classList.add('active'));
if (closeBtn) closeBtn.addEventListener('click',()=>document.getElementById('uploadDocModal').classList.remove('active'));
if (cancelBtn) cancelBtn.addEventListener('click',()=>document.getElementById('uploadDocModal').classList.remove('active'));

if (submitBtn) {
  submitBtn.addEventListener('click',()=>{
    const form = document.getElementById('uploadDocForm');
    if (!form) return;
    const fd = new FormData(form);
    fetch('../../config/API/endpoints/index.php?action=upload_org_document',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      showToast(d.message,d.success);
      if(d.success){
        document.getElementById('uploadDocModal').classList.remove('active');
        resetUploadForm();
        loadDocs();
      }
    });
  });
}

// File input & Drag and Drop Handling
const fileInput = document.getElementById('docFileInput');
const dropZone = document.getElementById('fileDropZone');
const defaultUI = dropZone ? dropZone.querySelector('.dropzone-default') : null;
const successUI = dropZone ? dropZone.querySelector('.dropzone-success') : null;
const filenameEl = dropZone ? dropZone.querySelector('.dropzone-filename') : null;
const selectedFileBanner = document.getElementById('selectedFileName');

function handleFileSelection(file) {
  if (file) {
    if (filenameEl) filenameEl.textContent = file.name;
    if (selectedFileBanner) {
      const txt = selectedFileBanner.querySelector('.file-name-text');
      if (txt) txt.textContent = file.name;
      selectedFileBanner.style.display = 'flex';
    }
    if (dropZone) dropZone.classList.add('has-file');
    if (defaultUI) defaultUI.style.display = 'none';
    if (successUI) successUI.style.display = 'block';
  } else {
    resetUploadFormState();
  }
}

function resetUploadFormState() {
  if (dropZone) dropZone.classList.remove('has-file', 'dragover');
  if (defaultUI) defaultUI.style.display = 'block';
  if (successUI) successUI.style.display = 'none';
  if (selectedFileBanner) selectedFileBanner.style.display = 'none';
}

function resetUploadForm() {
  const form = document.getElementById('uploadDocForm');
  if (form) form.reset();
  resetUploadFormState();
}

if (fileInput) {
  fileInput.addEventListener('change', function() {
    handleFileSelection(this.files[0]);
  });
}

if (dropZone) {
  ['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.add('dragover');
    }, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropZone.classList.remove('dragover');
    }, false);
  });

  dropZone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    if (files && files.length > 0) {
      fileInput.files = files;
      handleFileSelection(files[0]);
    }
  });
}

// Filters listeners
const searchInput = document.getElementById('docSearch');
const typeSelect = document.getElementById('docTypeFilter');
const eventSelect = document.getElementById('docEventFilter');

if (searchInput) searchInput.addEventListener('input', applyFilter);
if (typeSelect) typeSelect.addEventListener('change', applyFilter);
if (eventSelect) eventSelect.addEventListener('change', applyFilter);

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', loadDocs);
} else {
  loadDocs();
}
