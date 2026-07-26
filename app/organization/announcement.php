<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgId   = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();
$activePage = 'announcement';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Announcements</title>
  <link rel="stylesheet" href="../../assets/css/organization/announcement.css">
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
</head><body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title"><h2>Announcements</h2><p>Post and manage organization announcements</p></div>
      </div>
    </header>
    <div class="maincontent"><div class="divider"></div>
      <div class="page-actions" style="padding:16px 24px 0;">
        <button class="add-announcement-btn" type="button" id="openCreateAnnBtn">
          <ion-icon name="add-outline"></ion-icon> Add Announcement
        </button>
      </div>
      <section class="summary-grid" style="padding:16px 24px;">
        <article class="summary-card"><p class="summary-label">Total</p><p class="summary-value text-blue" id="annTotal">0</p></article>
        <article class="summary-card"><p class="summary-label">Approved</p><p class="summary-value text-green" id="annApproved">0</p></article>
        <article class="summary-card"><p class="summary-label">Pending OSA</p><p class="summary-value text-gold" id="annPending">0</p></article>
        <article class="summary-card"><p class="summary-label">Draft</p><p class="summary-value text-slate" id="annDraft">0</p></article>
      </section>
      <section class="announcements-panel" style="padding:0 24px 24px;">
        <header class="panel-header">
          <h3>All Announcements</h3>
          <div class="panel-filter-wrap">
            <label for="annFilter">Filter</label>
            <select id="annFilter" class="panel-filter">
              <option value="all">All</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Failed</option>
            </select>
          </div>
        </header>
        <div id="annList"><p style="text-align:center;padding:40px;color:#94a3b8;">No announcements yet. Click "Add Announcement" to create one.</p></div>
      </section>
    </div>
  </div>
</div>

<!-- Create/Edit Announcement Modal (fixed with inline styles) -->
<div id="annFormModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="annFormTitle">Create Announcement</h2>
      <button class="close-modal" onclick="closeM('annFormModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="annFormId">
      <div class="section-label">Announcement Information</div>
      <div class="form-group"><label>Title *</label>
        <input type="text" id="annTitle" class="form-input" placeholder="Enter announcement title"></div>
      <div class="form-group"><label>Message *</label>
        <textarea id="annBody" class="form-input" placeholder="Write the announcement content here..."></textarea></div>
      <div class="form-grid-2">
        <div class="form-group"><label>Category</label>
          <select id="annCategory" class="form-input">
            <option>General Notice</option><option>Event Announcement</option><option>Reminder</option><option>Meeting</option>
          </select></div>
        <div class="form-group"><label>Audience</label>
          <select id="annAudience" class="form-input">
            <option value="All Members">All Members</option>
            <option value="Officers Only">Officers Only</option>
            <option value="Public">Public</option>
          </select></div>
      </div>
      <div class="section-label">Schedule</div>
      <div class="form-grid-2">
        <div class="form-group"><label>Date Posted</label>
          <input type="date" id="annDate" class="form-input" min="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>Expiry Date (Optional)</label>
          <input type="date" id="annExpiry" class="form-input" min="<?= date('Y-m-d') ?>"></div>
      </div>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:13px;color:#92400e;font-family:'Inter',sans-serif;">
        <strong>Note:</strong> After submission, the announcement will be sent to OSA for approval before it goes live.
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" onclick="closeM('annFormModal')">Cancel</button>
      <button type="button" class="btn-save" id="saveAnnBtn">Submit for Approval</button>
    </div>
  </div>
</div>

<!-- View Modal -->
<div id="viewAnnModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="vAnnTitle">Announcement</h2>
      <button class="close-modal" onclick="closeM('viewAnnModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:#94a3b8;margin-bottom:8px;font-family:'Inter',sans-serif;" id="vAnnMeta"></p>
      <p id="vAnnBody" style="line-height:1.7;color:#374151;font-family:'Inter',sans-serif;"></p>
      <div id="vAnnTags" style="margin-top:16px;"></div>
    </div>
    <div class="modal-footer"><button class="btn-cancel" onclick="closeM('viewAnnModal')">Close</button></div>
  </div>
</div>

<div id="toast"></div>

<script>
let allAnn=[], annMode='create';
function openM(id){ document.getElementById(id).classList.add('active'); }
function closeM(id){ document.getElementById(id).classList.remove('active'); }
function showToast(msg,ok=true){ const t=document.getElementById('toast'); t.textContent=msg; t.style.background=ok?'#16a34a':'#dc2626'; t.style.display='block'; setTimeout(()=>t.style.display='none',3500); }

const statusMap={pending:{label:'Pending OSA Approval',cls:'pending',icon:'time-outline'},approved:{label:'Approved by OSA',cls:'approved',icon:'checkmark-circle-outline'},rejected:{label:'Failed OSA Review',cls:'rejected',icon:'close-circle-outline'},draft:{label:'Draft',cls:'pending',icon:'document-outline'}};
const currentOrgId = <?= (int)$orgId ?>;

function renderAnn(items){
  const el=document.getElementById('annList');
  if(!items.length){ el.innerHTML='<p style="text-align:center;padding:40px;color:#94a3b8;font-family:Inter,sans-serif;">No announcements found.</p>'; return; }
  el.innerHTML=items.map(a=>{
    const sm=statusMap[a.Status]||statusMap.pending;
    const dt=a.DatePosted?new Date(a.DatePosted).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}):'';
    const canManage=a.OrgId && Number(a.OrgId)===currentOrgId;
    const audienceLabel=a.AudienceLabel||a.Audience||'All Members';
    return `<article class="announce-card" data-status="${a.Status}">
      <div class="announce-head">
        <div class="announce-title-wrap"><div>
          <h4>${a.Title}</h4>
          <p class="announce-meta">${a.Category||''} &bull; ${dt}</p>
        </div></div>
        <div class="announce-actions">
          <span class="status-pill ${sm.cls}"><ion-icon name="${sm.icon}"></ion-icon>${sm.label}</span>
          <button class="icon-action" onclick='viewAnn(${JSON.stringify(a).replace(/'/g,"&#39;")})' title="View"><ion-icon name="eye-outline"></ion-icon></button>
          ${canManage?`<button class="icon-action" onclick='editAnn(${JSON.stringify(a).replace(/'/g,"&#39;")})' title="Edit"><ion-icon name="create-outline"></ion-icon></button>`:''}
          ${canManage?`<button class="icon-action delete" onclick="deleteAnn(${a.AnnouncementId})" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>`:''}
        </div>
      </div>
      <p class="announce-body">${a.Body.substring(0,160)}${a.Body.length>160?'...':''}</p>
      <div class="announce-foot">
        <span class="audience-chip all"><ion-icon name="people-outline"></ion-icon>${audienceLabel}</span>
      </div>
    </article>`;
  }).join('');
}

function loadAnn(){
  fetch('../../config/API/get_org_announcements.php').then(r=>r.json()).then(data=>{
    if(!data.success) return;
    document.getElementById('annTotal').textContent=data.stats.total;
    document.getElementById('annApproved').textContent=data.stats.approved;
    document.getElementById('annPending').textContent=data.stats.pending;
    document.getElementById('annDraft').textContent=data.stats.draft;
    allAnn=data.announcements;
    applyAnnFilter();
  });
}
function applyAnnFilter(){
  const f=document.getElementById('annFilter').value;
  renderAnn(f==='all'?allAnn:allAnn.filter(a=>a.Status===f));
}
function viewAnn(a){
  document.getElementById('vAnnTitle').textContent=a.Title;
  document.getElementById('vAnnMeta').textContent=`${a.Category||'General'} | ${a.AudienceLabel||a.Audience||'All Members'} | ${a.DatePosted||''}`;
  document.getElementById('vAnnBody').textContent=a.Body;
  const sm=statusMap[a.Status]||statusMap.pending;
  document.getElementById('vAnnTags').innerHTML=`<span class="status-pill ${sm.cls}"><ion-icon name="${sm.icon}"></ion-icon>${sm.label}</span>`;
  openM('viewAnnModal');
}
function editAnn(a){
  document.getElementById('annFormTitle').textContent='Edit Announcement';
  document.getElementById('annFormId').value=a.AnnouncementId;
  document.getElementById('annTitle').value=a.Title;
  document.getElementById('annBody').value=a.Body;
  document.getElementById('annCategory').value=a.Category||'General Notice';
  document.getElementById('annAudience').value=a.Audience||'All Members';
  document.getElementById('annDate').value=a.DatePosted||'';
  document.getElementById('annExpiry').value=a.ExpirationDate||'';
  annMode='edit'; openM('annFormModal');
}
function deleteAnn(id){
  if(!confirm('Delete this announcement?')) return;
  const fd=new FormData(); fd.append('AnnouncementId',id);
  fetch('../../config/API/delete_org_announcement.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{showToast(d.message,d.success);if(d.success)loadAnn();});
}
document.getElementById('openCreateAnnBtn').addEventListener('click',()=>{
  document.getElementById('annFormTitle').textContent='Create Announcement';
  document.getElementById('annFormId').value='';
  document.getElementById('annTitle').value='';
  document.getElementById('annBody').value='';
  document.getElementById('annDate').value=new Date().toISOString().split('T')[0];
  document.getElementById('annExpiry').value='';
  annMode='create'; openM('annFormModal');
});
document.getElementById('saveAnnBtn').addEventListener('click',()=>{
  const title=document.getElementById('annTitle').value.trim();
  const body=document.getElementById('annBody').value.trim();
  if(!title||!body){showToast('Title and message required',false);return;}
  const fd=new FormData();
  fd.append('Title',title);fd.append('Body',body);
  fd.append('Category',document.getElementById('annCategory').value);
  fd.append('Audience',document.getElementById('annAudience').value);
  fd.append('DatePosted',document.getElementById('annDate').value);
  fd.append('ExpirationDate',document.getElementById('annExpiry').value);
  const id=document.getElementById('annFormId').value;
  if(annMode==='edit') fd.append('AnnouncementId',id);
  const url=annMode==='edit'?'../../config/API/update_org_announcement.php':'../../config/API/create_org_announcement.php';
  fetch(url,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{showToast(d.message,d.success);if(d.success){closeM('annFormModal');loadAnn();}});
});
document.getElementById('annFilter').addEventListener('change',applyAnnFilter);
window.addEventListener('click',e=>['annFormModal','viewAnnModal'].forEach(id=>{const m=document.getElementById(id);if(e.target===m)closeM(id);}));
loadAnn();
</script>
<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/org.js"></script>
</body></html>