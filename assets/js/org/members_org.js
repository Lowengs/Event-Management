/* ── Extracted from organization/members_org.php ── */
let viewMemberCorUrl = '';
  let viewMemberBodyOverflow = '';

  function renderMemberCorPreview(corUrl) {
    const previewEl = document.getElementById('viewMemberCorPreview');
    const buttonEl = document.getElementById('viewMemberCorButton');

    viewMemberCorUrl = corUrl || '';

    if (!previewEl || !buttonEl) {
      return;
    }

    if (!viewMemberCorUrl) {
      previewEl.innerHTML = '<div style="padding: 20px; color: #64748b; font-size: 13px;">No COR document available.</div>';
      buttonEl.disabled = true;
      return;
    }

    const cleanUrl = viewMemberCorUrl.split('?')[0].toLowerCase();
    const isPdf = cleanUrl.endsWith('.pdf');

    previewEl.innerHTML = isPdf
      ? '<iframe src="' + viewMemberCorUrl + '" title="COR Document" style="display:block;width:100%;height:320px;border:0;background:#fff;"></iframe>'
      : '<img src="' + viewMemberCorUrl + '" alt="COR Document" style="display:block;width:100%;height:auto;max-height:320px;object-fit:contain;background:#fff;" />';

    buttonEl.disabled = false;
  }

  function openMemberCorFullScreen() {
    if (viewMemberCorUrl) {
      window.open(viewMemberCorUrl, '_blank', 'noopener');
    }
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
    statusEl.innerText = status;
    statusEl.className = 'status-badge ' + (status.toLowerCase() === 'active' ? 'active' : 'pending');

    // Avatar handling
    const avatarEl = document.getElementById('viewMemberAvatar');
    avatarEl.innerText = initials;
    avatarEl.className = 'avatar'; // reset
    if (colorClass) {
      avatarEl.classList.add(colorClass); // e.g. blue, purple
    } else {
      avatarEl.style.background = '#8b5cf6'; // default fallback logic
    }

    renderMemberCorPreview(corDocumentUrl);

    document.getElementById('viewMemberModal').classList.add('show');
    viewMemberBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
  }

  function closeViewMemberModal() {
    document.getElementById('viewMemberModal').classList.remove('show');
    document.body.style.overflow = viewMemberBodyOverflow;
  }

  // Close when clicking outside modal
  window.addEventListener('click', function(e) {
    const modal = document.getElementById('viewMemberModal');
    if (e.target === modal) {
      closeViewMemberModal();
    }
  });