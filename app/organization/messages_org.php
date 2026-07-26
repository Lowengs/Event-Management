<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgId   = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();
$orgName = $orgData['OrgName'] ?? 'Organization';
$activePage = 'messages';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Messages</title>
  <link rel="stylesheet" href="../../assets/css/organization/messages.css">
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../assets/css/organization/messages_org.css?<?= time() ?>" />
</head><body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title"><h2>Messages</h2><p>Communication center – chat with OSA</p></div>
      </div>
    </header>
    <div class="maincontent"><div class="divider"></div>
      <section class="messages-grid" aria-label="Messages">
        <article class="panel messages-panel">
          <header class="panel-header"><h3>Conversations</h3></header>
          <div class="message-item active-thread">
            <div class="message-avatar"><ion-icon name="business-outline"></ion-icon></div>
            <div class="message-content">
              <div class="message-meta-row">
                <h4>Office of Student Affairs (OSA)</h4>
                <div class="time-wrap"><span class="message-time" id="lastMsgTime"></span><span class="unread-count" id="unreadBadge" style="display:none;"></span></div>
              </div>
              <p id="lastMsgPreview">Click to load conversation…</p>
            </div>
          </div>
        </article>

        <article class="panel notifications-panel">
          <header class="panel-header"><h3>Recent Announcements</h3></header>
          <div id="notifList"><p style="padding:16px;color:#94a3b8;font-size:13px;">Loading…</p></div>
        </article>
      </section>

      <section class="panel chat-panel" aria-label="Chat with OSA">
        <header class="panel-header">
          <div style="display:flex;align-items:center;gap:10px;">
            <div style="width:36px;height:36px;background:#1e3a8a;border-radius:50%;display:flex;align-items:center;justify-content:center;">
              <ion-icon name="business-outline" style="color:#fff;font-size:18px;"></ion-icon>
            </div>
            <div><h3 style="margin:0;font-size:15px;">Office of Student Affairs</h3><p style="margin:0;font-size:12px;color:#94a3b8;">OSA Admin</p></div>
          </div>
        </header>

        <div class="chat-window" id="chatWindow">
          <p style="text-align:center;color:#94a3b8;font-size:13px;">Loading messages…</p>
        </div>

        <div class="composer-row chat-composer">
          <input type="text" id="msgInput" placeholder="Type your message to OSA…" autocomplete="off">
          <button class="send-btn" id="sendMsgBtn" type="button"><ion-icon name="paper-plane-outline"></ion-icon></button>
        </div>
      </section>
    </div>
  </div>
</div>

<script>
let lastCount = 0;
const orgName = <?= json_encode($orgName) ?>;

function renderMessages(messages){
  const win = document.getElementById('chatWindow');
  if(!messages.length){ win.innerHTML='<p style="text-align:center;color:#94a3b8;font-size:13px;padding:20px;">No messages yet. Say hello to OSA!</p>'; return; }
  win.innerHTML = messages.map(m=>{
    const isOrg = m.SenderType==='org';
    const dt = new Date(m.SentAt).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
    return `<div class="bubble-row ${isOrg?'outgoing':'incoming'}">
      ${!isOrg?'<div class="message-avatar small"><ion-icon name="business-outline"></ion-icon></div>':''}
      <div class="chat-bubble ${isOrg?'outgoing-bubble':'incoming-bubble'}">
        <p>${m.Message.replace(/</g,'&lt;')}</p>
        <small>${dt}${isOrg?' · You':' · OSA'}</small>
      </div>
    </div>`;
  }).join('');
  win.scrollTop=win.scrollHeight;
  const last=messages[messages.length-1];
  document.getElementById('lastMsgPreview').textContent=last.Message.substring(0,50)+(last.Message.length>50?'…':'');
  document.getElementById('lastMsgTime').textContent=new Date(last.SentAt).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
}

function loadMessages(){
  fetch('../../config/API/get_org_messages.php').then(r=>r.json()).then(data=>{
    if(!data.success) return;
    renderMessages(data.messages||[]);
    const badge=document.getElementById('unreadBadge');
    if(data.unread>0){ badge.textContent=data.unread; badge.style.display='flex'; } else badge.style.display='none';
  });
}

function loadNotifs(){
  fetch('../../config/API/get_org_announcements.php').then(r=>r.json()).then(data=>{
    const el=document.getElementById('notifList');
    if(!data.success||!data.announcements.length){ el.innerHTML='<p style="padding:16px;color:#94a3b8;font-size:13px;">No recent announcements.</p>'; return; }
    el.innerHTML=data.announcements.slice(0,5).map(a=>`
      <div class="notification-item">
        <ion-icon name="megaphone-outline"></ion-icon>
        <div><p>${a.Title}</p><span>${a.DatePosted||'—'}</span></div>
      </div>`).join('');
  });
}

document.getElementById('sendMsgBtn').addEventListener('click', sendMsg);
document.getElementById('msgInput').addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); sendMsg(); }});

function sendMsg(){
  const input=document.getElementById('msgInput');
  const msg=input.value.trim();
  if(!msg) return;
  input.value='';
  const fd=new FormData(); fd.append('message',msg);
  fetch('../../config/API/send_org_message.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success) loadMessages();
  });
}

loadMessages(); loadNotifs();
// Poll every 5 seconds
setInterval(loadMessages, 5000);
</script>
<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/org.js"></script>
</body></html>