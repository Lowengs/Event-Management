/**
 * messages_org.js — Organization Portal Messaging Handler
 */

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
    return `<div class="bubble-row ${isOrg ? 'outgoing' : 'incoming'}">
      ${!isOrg ? '<div class="message-avatar small"><ion-icon name="business-outline"></ion-icon></div>' : ''}
      <div class="chat-bubble ${isOrg ? 'outgoing-bubble' : 'incoming-bubble'}">
        <span style="font-size:10px;font-weight:700;display:block;margin-bottom:2px;opacity:0.85;">${(senderName).replace(/</g, '&lt;')}</span>
        <p style="margin:0;">${(m.Message || '').replace(/</g, '&lt;')}</p>
        <small style="display:block;margin-top:4px;font-size:10px;opacity:0.75;">${dt} · ${senderName}</small>
      </div>
    </div>`;
  }).join('');
  win.scrollTop = win.scrollHeight;
  const last = messages[messages.length - 1];
  const prev = document.getElementById('lastMsgPreview');
  const timeEl = document.getElementById('lastMsgTime');
  if (prev) prev.textContent = last.Message.substring(0, 50) + (last.Message.length > 50 ? '…' : '');
  if (timeEl) {
    const sentAt = new Date(last.SentAt);
    timeEl.textContent = isNaN(sentAt) ? '—' : sentAt.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }
}

function loadMessages() {
  fetch('../../config/API/endpoints/index.php?action=get_org_messages').then(r => r.json()).then(data => {
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
  }).catch(() => {});
}

function loadNotifs() {
  fetch('../../config/API/endpoints/index.php?action=get_org_announcements').then(r => r.json()).then(data => {
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
      </div>`).join('');
  }).catch(() => {});
}

function sendMsg() {
  const input = document.getElementById('msgInput');
  if (!input) return;
  const msg = input.value.trim();
  if (!msg) return;
  input.value = '';
  const fd = new FormData(); 
  fd.append('message', msg);
  fetch('../../config/API/endpoints/index.php?action=send_org_message', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
    if (d.success) loadMessages();
  }).catch(() => {});
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

  loadMessages(); 
  loadNotifs();
  setInterval(loadMessages, 5000);
});
