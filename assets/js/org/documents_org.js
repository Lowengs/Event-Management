/* ── Extracted from organization/documents_org.php ── */
/* ── Toast ─────────────────────────────────────────────── */
function showToast(msg,ok=true){
  const t=document.getElementById('toast');
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
  fetch('../../config/API/get_org_documents.php').then(r=>r.json()).then(data=>{
    allDocs = data.documents || [];
    applyFilter();
  });
}

/* ── Render a page of docs ─────────────────────────────── */
function renderDocsPage() {
  const el = document.getElementById('docList');
  const total = filteredDocs.length;
  const totalPages = Math.max(1, Math.ceil(total / DOC_PAGE_SIZE));
  const start = (docCurrentPage - 1) * DOC_PAGE_SIZE;
  const end   = Math.min(start + DOC_PAGE_SIZE, total);
  const pageDocs = filteredDocs.slice(start, end);

  if (!total) {
    el.innerHTML = '<p style="text-align:center;padding:40px;color:#94a3b8;font-family:Inter,sans-serif;">No documents found.</p>';
    document.getElementById('docPaginationBar').style.display = 'none';
    return;
  }

  el.innerHTML = pageDocs.map(d => {
    const dt = new Date(d.UploadedAt).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
    return `<article class="document-item">
      <div class="document-left">
        <div class="doc-icon-wrap"><ion-icon name="document-text-outline"></ion-icon></div>
        <div><h4>${d.Title}</h4><p>${d.DocType}${d.EventName?' &bull; '+d.EventName:''} &bull; ${dt}${d.FileSize?' &bull; '+d.FileSize:''}</p></div>
      </div>
      <div class="document-actions">
        <a href="../../${d.FilePath}" target="_blank" class="view-btn view-doc-btn">View</a>
        <a href="../../${d.FilePath}" download class="icon-btn"><ion-icon name="download-outline"></ion-icon></a>
      </div>
    </article>`;
  }).join('');

  // Pagination bar
  const bar = document.getElementById('docPaginationBar');
  bar.style.display = 'flex';
  document.getElementById('docPageInfo').innerHTML =
    `Showing <strong>${start+1}–${end}</strong> of <strong>${total}</strong> document${total!==1?'s':''}`;

  // Controls
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

/* ── Filter ────────────────────────────────────────────── */
function applyFilter(){
  const q = document.getElementById('docSearch').value.toLowerCase();
  const t = document.getElementById('docTypeFilter').value;
  filteredDocs = allDocs.filter(d => (!q||d.Title.toLowerCase().includes(q)) && (!t||d.DocType===t));
  docCurrentPage = 1;
  renderDocsPage();
}

/* ── Upload ────────────────────────────────────────────── */
document.getElementById('openUploadModalBtn').addEventListener('click',()=>document.getElementById('uploadDocModal').classList.add('active'));
document.getElementById('closeUploadModal').addEventListener('click',()=>document.getElementById('uploadDocModal').classList.remove('active'));
document.getElementById('cancelUploadBtn').addEventListener('click',()=>document.getElementById('uploadDocModal').classList.remove('active'));
document.getElementById('submitDocBtn').addEventListener('click',()=>{
  const fd=new FormData(document.getElementById('uploadDocForm'));
  fetch('../../config/API/upload_org_document.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    showToast(d.message,d.success);
    if(d.success){
      document.getElementById('uploadDocModal').classList.remove('active');
      resetUploadForm();
      loadDocs();
    }
  });
});

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
      selectedFileBanner.querySelector('.file-name-text').textContent = file.name;
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
  document.getElementById('uploadDocForm').reset();
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

// Filters
document.getElementById('docSearch').addEventListener('input', applyFilter);
document.getElementById('docTypeFilter').addEventListener('change', applyFilter);

window.addEventListener('click',e=>{const m=document.getElementById('uploadDocModal');if(e.target===m)m.classList.remove('active');});
loadDocs();