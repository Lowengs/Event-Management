/**
 * announcement.js — Organization Portal Announcement Handler
 */

let allAnn = [], annMode = 'create';

function openM(id) { 
  const el = document.getElementById(id);
  if (el) el.classList.add('active'); 
}

function closeM(id) { 
  const el = document.getElementById(id);
  if (el) el.classList.remove('active'); 
}

function showToast(msg, ok = true) { 
  const t = document.getElementById('toast'); 
  if (!t) return;
  t.textContent = msg; 
  t.style.background = ok ? '#16a34a' : '#dc2626'; 
  t.style.display = 'block'; 
  setTimeout(() => t.style.display = 'none', 3500); 
}

const statusMap = {
  pending: { label: 'Pending OSA Approval', cls: 'pending', icon: 'time-outline' },
  approved: { label: 'Approved by OSA', cls: 'approved', icon: 'checkmark-circle-outline' },
  rejected: { label: 'Failed OSA Review', cls: 'rejected', icon: 'close-circle-outline' },
  draft: { label: 'Draft', cls: 'pending', icon: 'document-outline' }
};

function renderAnn(items) {
  const el = document.getElementById('annList');
  if (!el) return;
  if (!items || !items.length) { 
    el.innerHTML = '<p style="text-align:center;padding:40px;color:#94a3b8;font-family:Inter,sans-serif;">No announcements found.</p>'; 
    return; 
  }
  const currOrgId = typeof currentOrgId !== 'undefined' ? currentOrgId : 0;
  el.innerHTML = items.map(a => {
    const sm = statusMap[a.Status] || statusMap.pending;
    const dt = a.DatePosted ? new Date(a.DatePosted).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
    const canManage = a.OrgId && Number(a.OrgId) === currOrgId;
    const audienceLabel = a.AudienceLabel || a.Audience || 'All Members';
    return `<article class="announce-card" data-status="${a.Status}">
      <div class="announce-head">
        <div class="announce-title-wrap"><div>
          <h4>${a.Title}</h4>
          <p class="announce-meta">${a.Category || ''} &bull; ${dt}</p>
        </div></div>
        <div class="announce-actions">
          <span class="status-pill ${sm.cls}"><ion-icon name="${sm.icon}"></ion-icon>${sm.label}</span>
          <button class="icon-action" onclick='viewAnn(${JSON.stringify(a).replace(/'/g, "&#39;")})' title="View"><ion-icon name="eye-outline"></ion-icon></button>
          ${canManage ? `<button class="icon-action" onclick='editAnn(${JSON.stringify(a).replace(/'/g, "&#39;")})' title="Edit"><ion-icon name="create-outline"></ion-icon></button>` : ''}
          ${canManage ? `<button class="icon-action delete" onclick="deleteAnn(${a.AnnouncementId})" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>` : ''}
        </div>
      </div>
      <p class="announce-body">${a.Body.substring(0, 160)}${a.Body.length > 160 ? '...' : ''}</p>
      <div class="announce-foot">
        <span class="audience-chip all"><ion-icon name="people-outline"></ion-icon>${audienceLabel}</span>
      </div>
    </article>`;
  }).join('');
}

function loadAnn() {
  fetch('../../config/API/endpoints/index.php?action=get_org_announcements').then(r => r.json()).then(data => {
    if (!data.success) return;
    const tot = document.getElementById('annTotal');
    const app = document.getElementById('annApproved');
    const pen = document.getElementById('annPending');
    const dra = document.getElementById('annDraft');
    if (tot) tot.textContent = data.stats.total;
    if (app) app.textContent = data.stats.approved;
    if (pen) pen.textContent = data.stats.pending;
    if (dra) dra.textContent = data.stats.draft;
    allAnn = data.announcements || [];
    applyAnnFilter();
  }).catch(() => {});
}

function applyAnnFilter() {
  const fEl = document.getElementById('annFilter');
  const f = fEl ? fEl.value : 'all';
  renderAnn(f === 'all' ? allAnn : allAnn.filter(a => a.Status === f));
}

function viewAnn(a) {
  const t = document.getElementById('vAnnTitle');
  const m = document.getElementById('vAnnMeta');
  const b = document.getElementById('vAnnBody');
  const tg = document.getElementById('vAnnTags');
  if (t) t.textContent = a.Title;
  if (m) m.textContent = `${a.Category || 'General'} | ${a.AudienceLabel || a.Audience || 'All Members'} | ${a.DatePosted || ''}`;
  if (b) b.textContent = a.Body;
  const sm = statusMap[a.Status] || statusMap.pending;
  if (tg) tg.innerHTML = `<span class="status-pill ${sm.cls}"><ion-icon name="${sm.icon}"></ion-icon>${sm.label}</span>`;
  openM('viewAnnModal');
}

function editAnn(a) {
  const title = document.getElementById('annFormTitle');
  if (title) title.textContent = 'Edit Announcement';
  document.getElementById('annFormId').value = a.AnnouncementId;
  document.getElementById('annTitle').value = a.Title;
  document.getElementById('annBody').value = a.Body;
  document.getElementById('annCategory').value = a.Category || 'General Notice';
  document.getElementById('annAudience').value = a.Audience || 'All Members';
  document.getElementById('annDate').value = a.DatePosted || '';
  document.getElementById('annExpiry').value = a.ExpirationDate || '';
  annMode = 'edit'; 
  openM('annFormModal');
}

function deleteAnn(id) {
  showConfirmModal('Delete this announcement? This action cannot be undone.', function() {
    const fd = new FormData(); 
    fd.append('AnnouncementId', id);
    fetch('../../config/API/endpoints/index.php?action=delete_org_announcement', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        showToast(d.message, d.success);
        if (d.success) loadAnn();
      })
      .catch(() => {});
  }, 'Delete Announcement', 'danger');
}

window.addEventListener('DOMContentLoaded', () => {
  const openBtn = document.getElementById('openCreateAnnBtn');
  if (openBtn) {
    openBtn.addEventListener('click', () => {
      const title = document.getElementById('annFormTitle');
      if (title) title.textContent = 'Create Announcement';
      document.getElementById('annFormId').value = '';
      document.getElementById('annTitle').value = '';
      document.getElementById('annBody').value = '';
      document.getElementById('annDate').value = new Date().toISOString().split('T')[0];
      document.getElementById('annExpiry').value = '';
      annMode = 'create'; 
      openM('annFormModal');
    });
  }

  const saveBtn = document.getElementById('saveAnnBtn');
  if (saveBtn) {
    saveBtn.addEventListener('click', () => {
      const title = document.getElementById('annTitle').value.trim();
      const body = document.getElementById('annBody').value.trim();
      if (!title || !body) { showToast('Title and message required', false); return; }
      const fd = new FormData();
      fd.append('Title', title);
      fd.append('Body', body);
      fd.append('Category', document.getElementById('annCategory').value);
      fd.append('Audience', document.getElementById('annAudience').value);
      fd.append('DatePosted', document.getElementById('annDate').value);
      fd.append('ExpirationDate', document.getElementById('annExpiry').value);
      const id = document.getElementById('annFormId').value;
      if (annMode === 'edit') fd.append('AnnouncementId', id);
      const url = annMode === 'edit' ? '../../config/API/endpoints/index.php?action=update_org_announcement' : '../../config/API/endpoints/index.php?action=create_org_announcement';
      fetch(url, { method: 'POST', body: fd }).then(async r => {
        const raw = await r.text();
        try { return JSON.parse(raw); } catch (_) { throw new Error(raw || 'The server returned an invalid response'); }
      }).then(d => {
        showToast(d.message, d.success);
        if (d.success) { closeM('annFormModal'); loadAnn(); }
      }).catch(err => showToast(err.message || 'Unable to save announcement', false));
    });
  }

  const filterEl = document.getElementById('annFilter');
  if (filterEl) filterEl.addEventListener('change', applyAnnFilter);

  window.addEventListener('click', e => {
    ['annFormModal', 'viewAnnModal'].forEach(id => {
      const m = document.getElementById(id);
      if (e.target === m) closeM(id);
    });
  });

  loadAnn();
});
