/* ── OSA Events page modal & filter JS ── */

// ── View Event Modal ──────────────────────────────────────────────────
function openViewModal(id, name, desc, org, date, time, place, loc, speaker, cap, status, pic) {
  document.getElementById('modalEventTitle').textContent = name  || '—';
  document.getElementById('modalEventSub').textContent   = org   || 'General';
  document.getElementById('modalOrgName').textContent    = org   || '—';
  document.getElementById('modalDate').textContent       = date  || '—';
  document.getElementById('modalTime').textContent       = time  || '—';
  document.getElementById('modalLocation').textContent   = place ? place + (loc ? ' — ' + loc : '') : (loc || '—');
  document.getElementById('modalStatus').textContent     = status || '—';

  const descEl = document.querySelector('#eventModal .item-value[data-field="desc"]');
  if (descEl) descEl.textContent = desc || 'No description provided.';
  const spkrEl = document.querySelector('#eventModal .item-value[data-field="speaker"]');
  if (spkrEl) spkrEl.textContent = speaker || '—';
  const capEl  = document.querySelector('#eventModal .item-value[data-field="cap"]');
  if (capEl)  capEl.textContent  = cap || '—';

  const posterImg = document.getElementById('modalPosterImg');
  if (posterImg) posterImg.src = pic ? '../../' + pic : '../../assets/img/philsca.png';

  document.getElementById('eventModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

// ── Docs Modal ────────────────────────────────────────────────────────
function openDocsModal(eventName, docs) {
  document.getElementById('docsModalTitle').textContent = 'Documents — ' + eventName;
  document.getElementById('docsModalSub').textContent   = eventName;
  const listContainer = document.getElementById('docsAttachmentList');
  listContainer.innerHTML = '';

  if (!Array.isArray(docs)) { try { docs = JSON.parse(docs || '[]'); } catch(e) { docs = []; } }

  const typeLabels = {
    'EventProposal':      'Event Proposal / OPLAN',
    'EventProgramFlow':   'Program Flow',
    'PostActivityReport': 'Post Activity Report',
    'FinancialReport':    'Financial Report',
    'Supporting Document':'Supporting Document'
  };
  const typeIcons = {
    'EventProposal':      'document-text-outline',
    'EventProgramFlow':   'list-outline',
    'PostActivityReport': 'clipboard-outline',
    'FinancialReport':    'cash-outline',
    'Supporting Document':'attach-outline'
  };

  if (docs.length === 0) {
    listContainer.innerHTML = '<p style="text-align:center;color:#666;font-size:0.9rem;padding:1rem 0;">No documents submitted for this event.</p>';
  } else {
    docs.forEach(doc => {
      const rawType = String(doc.DocType || '');
      const label   = typeLabels[rawType] || rawType || 'Document';
      const icon    = typeIcons[rawType]  || 'document-outline';
      const fname   = doc.Title    || 'Document';
      const fpath   = doc.FilePath || '';
      const orgName = (doc.OrgName || doc.Org || eventName || 'Organization').replace(/[^a-zA-Z0-9_-]/g, '_');
      const cleanLabel = label.replace(/[^a-zA-Z0-9_-]/g, '_');
      const downloadFilename = `${cleanLabel}_${orgName}.pdf`;

      const item = document.createElement('div');
      item.className  = 'attachment-item docs-item';
      item.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-top:10px;padding:10px 12px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;';
      item.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;overflow:hidden;">
          <ion-icon name="${icon}" style="font-size:1.3rem;color:#3b82f6;flex-shrink:0;"></ion-icon>
          <div style="overflow:hidden;">
            <div style="font-size:0.82rem;font-weight:700;color:#334155;">${label}</div>
            <div style="font-size:0.75rem;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;" title="${fname}">${fname}</div>
          </div>
        </div>
        <a class="modal-btn outline" href="../../${fpath}" download="${downloadFilename}"
           style="text-decoration:none;padding:5px 12px;font-size:0.78rem;display:flex;align-items:center;gap:5px;white-space:nowrap;"
           title="Download ${label}">
          <ion-icon name="download-outline"></ion-icon> Download ${label}
        </a>`;
      listContainer.appendChild(item);
    });
  }
  document.getElementById('docsModal').style.display = 'flex';
}

// ── Modal close & filter ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const evModal   = document.getElementById('eventModal');
  const docsModal = document.getElementById('docsModal');

  [document.getElementById('closeEventModal'), document.getElementById('modalCloseBtn')].forEach(btn => {
    if (btn) btn.addEventListener('click', () => { evModal.style.display = 'none'; document.body.style.overflow = ''; });
  });
  [document.getElementById('closeDocsModal'), document.getElementById('docsModalCloseBtn')].forEach(btn => {
    if (btn) btn.addEventListener('click', () => { docsModal.style.display = 'none'; document.body.style.overflow = ''; });
  });
  window.addEventListener('click', e => {
    if (e.target === evModal)   { evModal.style.display   = 'none'; document.body.style.overflow = ''; }
    if (e.target === docsModal) { docsModal.style.display = 'none'; document.body.style.overflow = ''; }
  });

  // Filter logic
  const filterOrg    = document.getElementById('filterOrg');
  const filterSearch = document.getElementById('filterSearch');
  const tableBody    = document.getElementById('eventsTableBody');

  function applyFilters() {
    const orgVal    = filterOrg    ? filterOrg.value.toLowerCase()    : 'all';
    const searchVal = filterSearch ? filterSearch.value.toLowerCase() : '';
    if (!tableBody) return;
    tableBody.querySelectorAll('tr').forEach(row => {
      if (row.children.length === 1) return;
      const orgCell  = row.querySelector('.orgCell span:last-child');
      const nameCell = row.querySelector('.eventName');
      if (!orgCell || !nameCell) return;
      const orgText  = orgCell.textContent.toLowerCase();
      const nameText = nameCell.textContent.toLowerCase();
      row.style.display = ((orgVal === 'all' || orgText === orgVal) && (searchVal === '' || nameText.includes(searchVal) || orgText.includes(searchVal))) ? '' : 'none';
    });
  }

  if (filterOrg)    filterOrg.addEventListener('change', applyFilters);
  if (filterSearch) filterSearch.addEventListener('input',  applyFilters);
});
