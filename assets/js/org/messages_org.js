function renderMessages(messages) {
  const win = document.getElementById('chatWindow');
  if (!win) return;
  if (!messages || !messages.length) {
    win.innerHTML = '<p style="text-align:center;color:#94a3b8;font-size:13px;padding:20px;">No messages yet. Say hello to OSA!</p>';
    return;
  }
  win.innerHTML = messages.map(m => {
    const isOrg = m.SenderType === 'org';
    const senderName = isOrg ? (m.SenderName || 'Student Organization') : (m.SenderName || 'Office of Student Affairs (OSA)');
    const sentAt = new Date(m.SentAt);
    const dt = isNaN(sentAt) ? '—' : sentAt.toLocaleString([], { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

    let attHtml = '';
    if (m.AttachmentPath) {
      const attPath = '../../' + m.AttachmentPath.replace(/^\/+/, '');
      const attName = (m.AttachmentName || 'Attachment').replace(/</g, '&lt;');
      const attType = (m.AttachmentType || '').toLowerCase();
      if (attType === 'image') {
        attHtml = `<div style="margin-top:8px;"><a href="${attPath}" target="_blank" style="display:block;border-radius:8px;overflow:hidden;max-width:240px;border:1px solid rgba(0,0,0,0.1);"><img src="${attPath}" alt="${attName}" style="width:100%;max-height:180px;object-fit:cover;display:block;"></a></div>`;
      } else {
        const iconName = attType === 'pdf' ? 'document-text-outline' : 'document-outline';
        const iconColor = attType === 'pdf' ? '#ef4444' : '#2563eb';
        attHtml = `<div style="margin-top:8px;"><a href="${attPath}" target="_blank" download style="display:inline-flex;align-items:center;gap:8px;padding:8px 12px;background:${isOrg ? 'rgba(255,255,255,0.18)' : '#ffffff'};border:1px solid ${isOrg ? 'rgba(255,255,255,0.3)' : '#cbd5e1'};border-radius:8px;text-decoration:none;color:inherit;font-size:12px;font-weight:600;"><ion-icon name="${iconName}" style="font-size:18px;color:${iconColor};"></ion-icon><span style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${attName}</span><ion-icon name="download-outline" style="font-size:16px;margin-left:4px;"></ion-icon></a></div>`;
      }
    }

    return `<div class="bubble-row ${isOrg ? 'outgoing' : 'incoming'}">
      ${!isOrg ? '<div class="message-avatar small"><ion-icon name="business-outline"></ion-icon></div>' : ''}
      <div class="chat-bubble ${isOrg ? 'outgoing-bubble' : 'incoming-bubble'}">
        <span style="font-size:10px;font-weight:700;display:block;margin-bottom:2px;opacity:0.85;">${(senderName).replace(/</g, '&lt;')}</span>
        ${m.Message ? `<p style="margin:0;">${(m.Message).replace(/</g, '&lt;')}</p>` : ''}
        ${attHtml}
        <small style="display:block;margin-top:4px;font-size:10px;opacity:0.75;">${dt} · ${(senderName).replace(/</g, '&lt;')}</small>
      </div>
    </div>`;
  }).join('');
  win.scrollTop = win.scrollHeight;
  const last = messages[messages.length - 1];
  const prev = document.getElementById('lastMsgPreview');
  const timeEl = document.getElementById('lastMsgTime');
  if (prev && last) {
    const textPreview = last.Message || (last.AttachmentName ? '📎 ' + last.AttachmentName : '');
    prev.textContent = textPreview.substring(0, 50) + (textPreview.length > 50 ? '…' : '');
  }
  if (timeEl && last) {
    const sentAt = new Date(last.SentAt);
    timeEl.textContent = isNaN(sentAt) ? '—' : sentAt.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
}

function loadMessages() {
  fetch('../../config/API/endpoints/index.php?action=get_org_messages')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      renderMessages(data.messages || []);
      const badge = document.getElementById('unreadBadge');
      if (badge) {
        if (data.unread > 0) {
          badge.textContent = data.unread;
          badge.style.display = 'flex';
        } else {
          badge.style.display = 'none';
        }
      }
      const orgBadge = document.getElementById('orgUnreadBadge');
      if (orgBadge) {
        if (data.unread > 0) {
          orgBadge.textContent = data.unread > 99 ? '99+' : data.unread;
          orgBadge.style.display = 'flex';
        } else {
          orgBadge.style.display = 'none';
        }
      }
    })
    .catch(() => {});
}

function loadNotifs() {
  fetch('../../config/API/endpoints/index.php?action=get_org_announcements')
    .then(r => r.json())
    .then(data => {
      const el = document.getElementById('notifList');
      if (!el) return;
      if (!data.success || !data.announcements.length) {
        el.innerHTML = '<p style="padding:16px;color:#94a3b8;font-size:13px;">No recent announcements.</p>';
        return;
      }
      el.innerHTML = data.announcements.slice(0, 5).map(a => `
        <div class="notification-item">
          <ion-icon name="megaphone-outline"></ion-icon>
          <div><p>${a.Title}</p><span>${a.DatePosted || '—'}</span></div>
        </div>
      `).join('');
    })
    .catch(() => {});
}

let selectedAttachmentFile = null;

function handleFileSelect(e) {
  const file = e.target.files && e.target.files[0];
  const bar = document.getElementById('msgAttachmentBar');
  const nameEl = document.getElementById('msgAttName');
  const sizeEl = document.getElementById('msgAttSize');
  const iconEl = document.getElementById('msgAttIcon');
  const attachBtn = document.getElementById('attachBtn');

  if (!file) {
    clearAttachment();
    return;
  }

  // Validate 15MB max
  if (file.size > 15 * 1024 * 1024) {
    alert('File size exceeds the 15MB limit.');
    clearAttachment();
    return;
  }

  selectedAttachmentFile = file;

  if (nameEl) nameEl.textContent = file.name;
  if (sizeEl) {
    const kb = (file.size / 1024).toFixed(1);
    const mb = (file.size / (1024 * 1024)).toFixed(2);
    sizeEl.textContent = file.size > 1024 * 1024 ? `${mb} MB` : `${kb} KB`;
  }

  const ext = file.name.split('.').pop().toLowerCase();
  if (iconEl) {
    if (['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(ext)) {
      iconEl.setAttribute('name', 'image-outline');
      iconEl.style.color = '#10b981';
    } else if (ext === 'pdf') {
      iconEl.setAttribute('name', 'document-text-outline');
      iconEl.style.color = '#ef4444';
    } else {
      iconEl.setAttribute('name', 'document-outline');
      iconEl.style.color = '#2563eb';
    }
  }

  if (bar) bar.style.display = 'flex';
  if (attachBtn) attachBtn.classList.add('has-file');
}

function clearAttachment() {
  selectedAttachmentFile = null;
  const fileInput = document.getElementById('msgFileInput');
  if (fileInput) fileInput.value = '';
  const bar = document.getElementById('msgAttachmentBar');
  if (bar) bar.style.display = 'none';
  const attachBtn = document.getElementById('attachBtn');
  if (attachBtn) attachBtn.classList.remove('has-file');
}

function sendMsg() {
  const input = document.getElementById('msgInput');
  const sendBtn = document.getElementById('sendMsgBtn');
  if (!input) return;
  const msg = input.value.trim();
  if (!msg && !selectedAttachmentFile) return;

  const fd = new FormData();
  if (msg) fd.append('message', msg);
  if (selectedAttachmentFile) {
    fd.append('attachment', selectedAttachmentFile);
  }

  if (sendBtn) sendBtn.disabled = true;
  input.value = '';
  clearAttachment();

  fetch('../../config/API/endpoints/index.php?action=send_org_message', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (sendBtn) sendBtn.disabled = false;
      if (d.success) {
        loadMessages();
      } else {
        alert(d.message || 'Failed to send message');
      }
    })
    .catch(() => {
      if (sendBtn) sendBtn.disabled = false;
    });
}

window.addEventListener('DOMContentLoaded', () => {
  const sendBtn = document.getElementById('sendMsgBtn');
  if (sendBtn) sendBtn.addEventListener('click', sendMsg);
  const input = document.getElementById('msgInput');
  if (input) {
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMsg();
      }
    });
  }
  const fileInput = document.getElementById('msgFileInput');
  if (fileInput) fileInput.addEventListener('change', handleFileSelect);
  const clearBtn = document.getElementById('clearAttBtn');
  if (clearBtn) clearBtn.addEventListener('click', clearAttachment);

  loadMessages();
  loadNotifs();
  setInterval(loadMessages, 5000);
});