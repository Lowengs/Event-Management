/* ── Extracted from organization/members_org.php ── */
let viewMemberCorUrl = '';
let viewMemberBodyOverflow = '';

function renderMemberCorPreview(corUrl) {
  const previewEl = document.getElementById('viewMemberCorPreview');
  const buttonEl = document.getElementById('viewMemberCorButton');

  viewMemberCorUrl = corUrl || '';

  if (!previewEl || !buttonEl) return;

  if (!viewMemberCorUrl) {
    previewEl.innerHTML = '<div style="padding: 24px; color: #64748b; font-size: 13px; text-align: center;">No COR document available.</div>';
    buttonEl.disabled = true;
    return;
  }

  let fullCorPath = viewMemberCorUrl;
  if (!fullCorPath.startsWith('http') && !fullCorPath.startsWith('/') && !fullCorPath.startsWith('../')) {
    fullCorPath = '../../' + fullCorPath.replace(/^\/+/, '');
  }

  const cleanUrl = fullCorPath.split('?')[0].toLowerCase();
  const ext = cleanUrl.split('.').pop();

  buttonEl.disabled = false;

  if (ext === 'pdf') {
    previewEl.innerHTML = '<iframe src="' + fullCorPath + '" title="COR Document" style="display:block;width:100%;height:320px;border:0;background:#fff;"></iframe>';
  } else if (ext === 'docx' || ext === 'doc') {
    previewEl.innerHTML = `
      <div id="corDocxTarget" style="padding:16px;background:#fff;min-height:280px;text-align:left;overflow:auto;">
        <div style="display:flex;align-items:center;justify-content:center;padding:40px;color:#64748b;gap:10px;">
          <ion-icon name="sync-outline" style="font-size:24px;color:#2563eb;animation:spin 1s linear infinite;"></ion-icon>
          <span style="font-size:13px;font-weight:600;">Rendering COR Word document...</span>
        </div>
      </div>`;
    const targetEl = document.getElementById('corDocxTarget');
    if (typeof docx !== 'undefined' && docx.renderAsync) {
      fetch(fullCorPath)
        .then(r => r.arrayBuffer())
        .then(buf => {
          targetEl.innerHTML = '';
          docx.renderAsync(buf, targetEl, null, { inWrapper: false, breakPages: true }).catch(err => {
            console.error('COR Docx render error:', err);
            targetEl.innerHTML = '<div style="padding:24px;color:#64748b;font-size:13px;text-align:center;">COR Word Document (.docx) available. Click "View Full Screen" to download/open.</div>';
          });
        })
        .catch(err => {
          console.error('Fetch COR error:', err);
          targetEl.innerHTML = '<div style="padding:24px;color:#64748b;font-size:13px;text-align:center;">COR Word Document (.docx) available.</div>';
        });
    } else {
      targetEl.innerHTML = '<div style="padding:24px;color:#64748b;font-size:13px;text-align:center;">COR Word Document (.docx) available.</div>';
    }
  } else {
    previewEl.innerHTML = '<img src="' + fullCorPath + '" alt="COR Document" style="display:block;width:100%;height:auto;max-height:320px;object-fit:contain;background:#fff;" />';
  }
}

function openMemberCorFullScreen() {
  if (!viewMemberCorUrl) return;

  let fullCorPath = viewMemberCorUrl;
  if (!fullCorPath.startsWith('http') && !fullCorPath.startsWith('/') && !fullCorPath.startsWith('../')) {
    fullCorPath = '../../' + fullCorPath.replace(/^\/+/, '');
  }

  window.open(fullCorPath, '_blank', 'noopener');
}

function openViewMemberModal(name, studentId, email, yearLevel, section, joinDate, status, initials, colorClass, phoneNumber, corDocumentUrl) {
  document.getElementById('viewMemberName').innerText = name;
  document.getElementById('viewMemberId').innerText = studentId;
  document.getElementById('viewMemberEmail').innerText = email;
  document.getElementById('viewMemberPhone').innerText = phoneNumber || 'No phone number available';
  document.getElementById('viewMemberYearSection').innerText = yearLevel + ' - ' + section;
  document.getElementById('viewMemberJoin').innerText = joinDate;

  // Status handling
  const statusEl = document.getElementById('viewMemberStatus');
  if (statusEl) {
    statusEl.innerText = status;
    statusEl.className = 'status-badge ' + (status.toLowerCase() === 'active' ? 'active' : 'pending');
  }

  // Avatar handling
  const avatarEl = document.getElementById('viewMemberAvatar');
  if (avatarEl) {
    avatarEl.innerText = initials;
    avatarEl.className = 'avatar'; // reset
    if (colorClass) {
      avatarEl.classList.add(colorClass); // e.g. blue, purple
    } else {
      avatarEl.style.background = '#8b5cf6'; // default fallback logic
    }
  }

  renderMemberCorPreview(corDocumentUrl);

  const modal = document.getElementById('viewMemberModal');
  if (modal) {
    modal.classList.add('show');
    viewMemberBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
  }
}

function closeViewMemberModal() {
  const modal = document.getElementById('viewMemberModal');
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = viewMemberBodyOverflow;
  }
}

// Close when clicking outside modal
window.addEventListener('click', function(e) {
  const modal = document.getElementById('viewMemberModal');
  if (e.target === modal) {
    closeViewMemberModal();
  }
});