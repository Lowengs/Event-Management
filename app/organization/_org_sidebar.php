<?php
require_once __DIR__ . '/../../config/img_helpers.php';
require_once __DIR__ . '/../../config/db.php';

if (!empty($_SESSION['org_id'])) {
    $orgId = (int)$_SESSION['org_id'];
    if (empty($_SESSION['org_logo']) || empty($_SESSION['org_name'])) {
        global $conn;
        if (isset($conn) && $conn) {
            $q = $conn->query("SELECT OrgName, OrgPicture FROM organization WHERE OrgId = $orgId LIMIT 1");
            if ($q && ($r = $q->fetch_assoc())) {
                if (!empty($r['OrgPicture'])) $_SESSION['org_logo'] = $r['OrgPicture'];
                if (!empty($r['OrgName'])) $_SESSION['org_name'] = $r['OrgName'];
            }
        }
    }
}

if (!isset($orgData) && !empty($_SESSION['org_id'])) {
    $orgData = [
        'OrgName' => $_SESSION['org_name'] ?? 'Organization',
        'OrgPicture' => $_SESSION['org_logo'] ?? ''
    ];
}
$orgName   = !empty($orgData['OrgName']) ? $orgData['OrgName'] : ($_SESSION['org_name'] ?? 'Organization');
$orgPic    = !empty($orgData['OrgPicture']) ? $orgData['OrgPicture'] : (!empty($orgData['OrgLogo']) ? $orgData['OrgLogo'] : ($_SESSION['org_logo'] ?? ''));
$logoSrc   = imgPathForDepth($orgPic, 2, '../../assets/img/philsca.png');

$nav = [
    'dashboard'   => ['icon'=>'grid-outline',              'label'=>'Dashboard',     'href'=>'dashboard_org.php'],
    'officers'    => ['icon'=>'people-outline',            'label'=>'Officers',      'href'=>'officers_org.php'],
    'members'     => ['icon'=>'person-outline',            'label'=>'Members',       'href'=>'members_org.php'],
    'events'      => ['icon'=>'calendar-outline',          'label'=>'Events',        'href'=>'events_org.php'],
    'announcement'=> ['icon'=>'megaphone-outline',         'label'=>'Announcement',  'href'=>'announcement.php'],
    'attendance'  => ['icon'=>'calendar-number-outline',   'label'=>'Attendance',    'href'=>'attendance_org.php'],
    'documents'   => ['icon'=>'document-text-outline',     'label'=>'Documents',     'href'=>'documents_org.php'],
    'messages'    => ['icon'=>'chatbox-outline',           'label'=>'Messages',      'href'=>'messages_org.php'],
    'reports'     => ['icon'=>'bar-chart-outline',         'label'=>'Reports',       'href'=>'reports_org.php'],
    'assesment'    => ['icon'=>'document-text-outline',     'label'=>'Assessments',   'href'=>'assesment.php'],
    'certificates' => ['icon'=>'ribbon-outline',            'label'=>'Certificates',  'href'=>'certificate-templates.php'],
    'audit'        => ['icon'=>'swap-vertical-outline',     'label'=>'Audit Trail',   'href'=>'audit-trail_org.php'],
    'settings'    => ['icon'=>'cog-outline',               'label'=>'Settings',      'href'=>'settings_org.php'],
];
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-logo-wrap" style="width:46px;height:46px;min-width:46px;border-radius:12px;overflow:hidden;background:#ffffff;display:flex;align-items:center;justify-content:center;border:1.5px solid rgba(255,255,255,0.2);box-shadow:0 4px 12px rgba(0,0,0,0.15);">
      <img src="<?= $logoSrc ?>" alt="Org logo" class="brand-logo" style="width:100%;height:100%;object-fit:cover;display:block;" onerror="this.src='../../assets/img/philsca.png'" />
    </div>
    <div class="brand-text" style="overflow:hidden;">
      <h1 style="font-size:15px;font-weight:800;white-space:nowrap;text-overflow:ellipsis;overflow:hidden;"><?= htmlspecialchars($orgName) ?></h1>
      <p style="font-size:11.5px;color:rgba(255,255,255,0.65);margin:2px 0 0;">ORG Portal</p>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($nav as $key => $item): ?>
    <a href="<?= $item['href'] ?>" class="nav-link <?= ($activePage === $key) ? 'active' : '' ?>">
      <ion-icon name="<?= $item['icon'] ?>"></ion-icon>
      <span><?= $item['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>

  <!-- Logout button triggers modal -->
  <button type="button" class="logout-link" id="logoutBtn"
    style="background:none;border:none;cursor:pointer;width:100%;text-align:left;"
    onclick="(function(){var m=document.getElementById('logoutModal');if(m)m.style.display='flex';})()">
    <ion-icon name="log-out-outline"></ion-icon> Logout
  </button>
</aside>

<?php
// Compute unread count and fetch notifications for the active organization
$orgUnreadCount = 0;
$orgNotifications = [];
if (!empty($_SESSION['org_id'])) {
    $oid = (int)$_SESSION['org_id'];

    // If currently viewing messages page, automatically mark incoming messages as read in DB
    if (($activePage ?? '') === 'messages') {
        $conn->query("UPDATE org_messages SET IsRead = 1 WHERE OrgId = $oid AND LOWER(SenderType) != 'org' AND IsRead = 0");
    }
    
    // 1. Incoming Messages ONLY (from OSA, Admin, or Student - EXCLUDE messages sent by the organization itself)
    $mQ = $conn->query("
        SELECT MessageId, SenderType, SenderId, Subject, Message, SentAt, IsRead 
        FROM org_messages 
        WHERE OrgId = $oid AND LOWER(SenderType) != 'org' AND LOWER(SenderType) != 'organization'
        ORDER BY IsRead ASC, SentAt DESC 
        LIMIT 15
    ");
    if ($mQ) {
        while ($mr = $mQ->fetch_assoc()) {
            $isUnread = empty($mr['IsRead']);
            if ($isUnread) $orgUnreadCount++;

            $st = strtolower($mr['SenderType'] ?? '');
            if ($st === 'osa') {
                $senderName = 'Office of Student Affairs (OSA)';
                $badgeTitle = 'OSA Message';
                $badgeColor = '#3b82f6';
            } elseif ($st === 'student') {
                $senderName = 'Student';
                $badgeTitle = 'Student Message';
                $badgeColor = '#059669';
            } else {
                $senderName = 'System Administrator';
                $badgeTitle = 'Admin Notice';
                $badgeColor = '#7c3aed';
            }

            $orgNotifications[] = [
                'id' => 'msg_' . $mr['MessageId'],
                'type' => 'message',
                'title' => $mr['Subject'] ?: 'Message from ' . ($st === 'osa' ? 'OSA' : 'Student'),
                'desc' => $mr['Message'] ?: 'You received a message.',
                'sender' => $senderName,
                'time' => $mr['SentAt'] ? date('M d, g:i A', strtotime($mr['SentAt'])) : 'Recently',
                'link' => 'messages_org.php?id=' . $mr['MessageId'],
                'is_read' => !$isUnread,
                'icon' => 'chatbox-ellipses-outline',
                'badge' => $badgeTitle,
                'badge_color' => $badgeColor
            ];
        }
    }

    // 2. Organization Announcements
    $aQ = $conn->query("
        SELECT AnnouncementId, Title, Body, Category, DatePosted, CreatedAt, Status 
        FROM announcement 
        WHERE (OrgId = $oid OR LOWER(Audience) = 'all') 
        ORDER BY CreatedAt DESC 
        LIMIT 10
    ");
    if ($aQ) {
        while ($ar = $aQ->fetch_assoc()) {
            $orgNotifications[] = [
                'id' => 'ann_' . $ar['AnnouncementId'],
                'type' => 'announcement',
                'title' => $ar['Title'] ?: 'Announcement',
                'desc' => $ar['Body'] ?: 'New announcement posted.',
                'sender' => $ar['Category'] ?: 'General',
                'time' => $ar['DatePosted'] ? date('M d, Y', strtotime($ar['DatePosted'])) : 'Recently',
                'link' => 'announcement.php',
                'is_read' => true,
                'icon' => 'megaphone-outline',
                'badge' => $ar['Category'] ?: 'Announcement',
                'badge_color' => '#8b5cf6'
            ];
        }
    }
}
?>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;"
  onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:16px;padding:32px;max-width:380px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);animation:modalPop 0.25s ease;">
    <div style="width:64px;height:64px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <ion-icon name="log-out-outline" style="font-size:28px;color:#ef4444;"></ion-icon>
    </div>
    <h3 style="margin:0 0 8px;font-family:'Inter',sans-serif;font-size:1.2rem;color:#111827;">Log Out?</h3>
    <p style="margin:0 0 24px;color:#6b7280;font-family:'Inter',sans-serif;font-size:0.95rem;">Are you sure you want to log out of the <?= htmlspecialchars($orgName) ?> portal?</p>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button id="logoutCancelBtn"
        onclick="document.getElementById('logoutModal').style.display='none'"
        style="padding:10px 28px;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;cursor:pointer;font-family:'Inter',sans-serif;font-weight:600;color:#374151;transition:all 0.2s;">Cancel</button>
      <a href="../../config/API/endpoints/index.php?action=org_logout" style="padding:10px 28px;border-radius:8px;border:none;background:#ef4444;color:#fff;cursor:pointer;font-family:'Inter',sans-serif;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;">Log Out</a>
    </div>
  </div>
</div>

<!-- ══ All Organization Notifications Modal ══ -->
<div id="allOrgNotifsModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#ffffff;border-radius:18px;max-width:520px;width:100%;max-height:85vh;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,0.25);overflow:hidden;font-family:'Inter',sans-serif;">
    <div style="padding:18px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:10px;background:#dbeafe;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:20px;">
          <ion-icon name="notifications-outline"></ion-icon>
        </div>
        <div>
          <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a;">Notifications</h3>
          <p id="orgNotifSubtext" style="margin:0;font-size:12px;color:#64748b;"><?= $orgUnreadCount > 0 ? "$orgUnreadCount unread incoming message(s)" : "All caught up" ?></p>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <?php if ($orgUnreadCount > 0): ?>
        <button type="button" id="markAllReadBtn" onclick="markAllOrgNotifsAsRead(event)" style="font-size:11.5px;font-weight:600;color:#2563eb;background:#eff6ff;border:1px solid #bfdbfe;padding:4px 10px;border-radius:6px;cursor:pointer;transition:all 0.2s;">Mark all read</button>
        <?php endif; ?>
        <button type="button" onclick="document.getElementById('allOrgNotifsModal').style.display='none'" style="background:none;border:none;color:#94a3b8;font-size:24px;cursor:pointer;line-height:1;">&times;</button>
      </div>
    </div>

    <div style="padding:16px 20px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:10px;">
      <?php if (empty($orgNotifications)): ?>
        <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
          <ion-icon name="notifications-off-outline" style="font-size:42px;margin-bottom:8px;display:block;margin-inline:auto;"></ion-icon>
          <p style="margin:0;font-size:14px;font-weight:500;">No incoming notifications.</p>
        </div>
      <?php else: ?>
        <?php foreach ($orgNotifications as $n): ?>
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;cursor:pointer;transition:all 0.2s;position:relative;"
             onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.06)';this.style.borderColor='#93c5fd';"
             onmouseout="this.style.boxShadow='none';this.style.borderColor='#e2e8f0';"
             data-title="<?= htmlspecialchars($n['title']) ?>"
             data-sender="<?= htmlspecialchars($n['sender']) ?>"
             data-date="<?= htmlspecialchars($n['time']) ?>"
             data-desc="<?= htmlspecialchars($n['desc']) ?>"
             data-type="<?= htmlspecialchars($n['type']) ?>"
             data-link="<?= htmlspecialchars($n['link']) ?>"
             onclick="showOrgNotifDetail(this)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;gap:8px;">
            <h4 style="margin:0;font-size:13.5px;color:#0f172a;font-weight:700;display:flex;align-items:center;gap:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;">
              <ion-icon name="<?= $n['icon'] ?>" style="color:<?= $n['badge_color'] ?>;font-size:18px;flex-shrink:0;"></ion-icon>
              <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($n['title']) ?></span>
            </h4>
            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
              <?php if (empty($n['is_read'])): ?>
                <span style="width:8px;height:8px;border-radius:50%;background:#ef4444;" title="Unread"></span>
              <?php endif; ?>
              <span style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars($n['time']) ?></span>
            </div>
          </div>
          <p style="margin:0 0 8px 0;font-size:12px;color:#64748b;line-height:1.45;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;text-overflow:ellipsis;">
            <?= htmlspecialchars($n['desc']) ?>
          </p>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:11.5px;color:#64748b;font-weight:500;"><ion-icon name="person-outline" style="vertical-align:middle;margin-right:3px;"></ion-icon><?= htmlspecialchars($n['sender']) ?></span>
            <span style="font-size:11px;padding:2px 8px;border-radius:6px;font-weight:700;background:<?= $n['type'] === 'message' ? '#eff6ff' : '#f5f3ff' ?>;color:<?= $n['type'] === 'message' ? '#2563eb' : '#7c3aed' ?>;">
              <?= htmlspecialchars($n['badge']) ?>
            </span>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;">
      <a href="messages_org.php" style="font-size:13px;color:#2563eb;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;">
        <ion-icon name="chatbubbles-outline"></ion-icon> Open Messages
      </a>
      <a href="announcement.php" style="font-size:13px;color:#7c3aed;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:4px;">
        <ion-icon name="megaphone-outline"></ion-icon> Announcements
      </a>
    </div>
  </div>
</div>

<!-- ══ Single Notification Detail Modal ══ -->
<div id="singleOrgNotifModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:99999;align-items:center;justify-content:center;padding:20px;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#ffffff;border-radius:18px;max-width:460px;width:100%;box-shadow:0 25px 60px rgba(0,0,0,0.25);overflow:hidden;font-family:'Inter',sans-serif;">
    <div style="padding:18px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc;">
      <div style="display:flex;align-items:center;gap:8px;">
        <ion-icon id="orgModalIcon" name="notifications-outline" style="font-size:20px;color:#2563eb;"></ion-icon>
        <span id="orgModalBadge" style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;background:#eff6ff;color:#2563eb;">Notification</span>
      </div>
      <button type="button" onclick="document.getElementById('singleOrgNotifModal').style.display='none'" style="background:none;border:none;color:#94a3b8;font-size:24px;cursor:pointer;line-height:1;">&times;</button>
    </div>
    <div style="padding:22px;">
      <h3 id="orgModalTitle" style="margin:0 0 6px 0;font-size:16px;font-weight:700;color:#0f172a;">Notification Title</h3>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;font-size:12px;color:#64748b;">
        <span id="orgModalSender" style="font-weight:500;">Sender</span>
        <span>•</span>
        <span id="orgModalDate">Date</span>
      </div>
      <div id="orgModalBody" style="font-size:13.5px;color:#334155;line-height:1.6;white-space:pre-wrap;background:#f8fafc;padding:14px;border-radius:10px;border:1px solid #e2e8f0;max-height:220px;overflow-y:auto;">
        Notification content details...
      </div>
    </div>
    <div style="padding:14px 22px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;gap:10px;justify-content:flex-end;">
      <button type="button" onclick="document.getElementById('singleOrgNotifModal').style.display='none'" style="padding:9px 16px;border-radius:8px;border:1.5px solid #cbd5e1;background:#fff;color:#334155;font-weight:600;font-size:13px;cursor:pointer;">Close</button>
      <a id="orgModalActionBtn" href="messages_org.php" style="padding:9px 18px;border-radius:8px;border:none;background:#2563eb;color:#fff;font-weight:600;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">Open</a>
    </div>
  </div>
</div>

<script>
// Bulletproof Mobile Sidebar Toggle Handler for all Organization views
(function() {
  function getSidebar() { return document.getElementById('sidebar') || document.querySelector('.sidebar'); }
  function getOverlay() {
    let overlay = document.getElementById('sidebarOverlay') || document.querySelector('.overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'sidebarOverlay';
      overlay.className = 'overlay';
      document.body.appendChild(overlay);
    }
    return overlay;
  }

  function toggleOrgSidebar(e) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    const sidebar = getSidebar();
    const overlay = getOverlay();
    if (!sidebar) return;

    const isOpen = sidebar.classList.contains('open') || sidebar.classList.contains('active');
    if (isOpen) {
      sidebar.classList.remove('open', 'active');
      if (overlay) overlay.classList.remove('show', 'active');
    } else {
      sidebar.classList.add('open', 'active');
      if (overlay) overlay.classList.add('show', 'active');
    }
  }

  function closeOrgSidebar(e) {
    if (e && e.preventDefault) e.preventDefault();
    const sidebar = getSidebar();
    const overlay = getOverlay();
    if (sidebar) sidebar.classList.remove('open', 'active');
    if (overlay) overlay.classList.remove('show', 'active');
  }

  // Delegated click capture on document - handles any hamburger button on all pages cleanly
  document.addEventListener('click', function(e) {
    const hamburger = e.target.closest('.hamburger, #hamburgerBtn, [aria-label="Open menu"], [aria-label="Toggle Sidebar"]');
    if (hamburger) {
      toggleOrgSidebar(e);
      return;
    }
    const overlay = e.target.closest('#sidebarOverlay, .overlay');
    if (overlay) {
      closeOrgSidebar(e);
      return;
    }
  }, true);

  window.addEventListener('resize', function() {
    if (window.innerWidth > 900) {
      closeOrgSidebar();
    }
  });

  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeOrgSidebar();
    }
  });

  window.toggleOrgSidebar = toggleOrgSidebar;
  window.closeOrgSidebar = closeOrgSidebar;
})();

// Organization Notifications Handler
function showAllOrgNotifsModal(e) {
  if (e) { e.preventDefault(); e.stopPropagation(); }
  const m = document.getElementById('allOrgNotifsModal');
  if (m) m.style.display = 'flex';
}
window.showAllOrgNotifsModal = showAllOrgNotifsModal;

function showOrgNotifDetail(el) {
  if (!el) return;
  const title = el.getAttribute('data-title') || 'Notification';
  const sender = el.getAttribute('data-sender') || 'System';
  const date = el.getAttribute('data-date') || '';
  const desc = el.getAttribute('data-desc') || '';
  const type = el.getAttribute('data-type') || 'general';
  const link = el.getAttribute('data-link') || 'messages_org.php';

  const tEl = document.getElementById('orgModalTitle');
  const sEl = document.getElementById('orgModalSender');
  const dEl = document.getElementById('orgModalDate');
  const bEl = document.getElementById('orgModalBody');
  const actBtn = document.getElementById('orgModalActionBtn');
  const badgeEl = document.getElementById('orgModalBadge');
  const iconEl = document.getElementById('orgModalIcon');

  if (tEl) tEl.textContent = title;
  if (sEl) sEl.textContent = sender;
  if (dEl) dEl.textContent = date;
  if (bEl) bEl.textContent = desc;

  if (badgeEl) {
    badgeEl.textContent = type === 'message' ? 'Message' : 'Announcement';
    badgeEl.style.background = type === 'message' ? '#eff6ff' : '#f5f3ff';
    badgeEl.style.color = type === 'message' ? '#2563eb' : '#7c3aed';
  }
  if (iconEl) {
    iconEl.setAttribute('name', type === 'message' ? 'chatbox-ellipses-outline' : 'megaphone-outline');
    iconEl.style.color = type === 'message' ? '#2563eb' : '#7c3aed';
  }
  if (actBtn) {
    actBtn.href = link;
    actBtn.textContent = type === 'message' ? 'Open Messages' : 'Open Announcement';
    actBtn.style.background = type === 'message' ? '#2563eb' : '#7c3aed';
  }

  const allModal = document.getElementById('allOrgNotifsModal');
  if (allModal) allModal.style.display = 'none';

  const singleModal = document.getElementById('singleOrgNotifModal');
  if (singleModal) singleModal.style.display = 'flex';
}
window.showOrgNotifDetail = showOrgNotifDetail;

function markAllOrgNotifsAsRead(e) {
  if (e) { e.preventDefault(); e.stopPropagation(); }
  fetch('../../config/API/endpoints/index.php?action=mark_org_messages_read', { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        const badges = document.querySelectorAll('#orgUnreadBadge, #unreadBadge');
        badges.forEach(b => { b.style.display = 'none'; b.textContent = '0'; });
        const redDots = document.querySelectorAll('#allOrgNotifsModal span[title="Unread"]');
        redDots.forEach(dot => dot.remove());
        const markBtn = document.getElementById('markAllReadBtn');
        if (markBtn) markBtn.remove();
        const unreadSub = document.getElementById('orgNotifSubtext');
        if (unreadSub) unreadSub.textContent = 'All caught up';
      }
    })
    .catch(() => {});
}
window.markAllOrgNotifsAsRead = markAllOrgNotifsAsRead;

// Auto-inject Notification Bell into topbar-right if not present in markup
document.addEventListener('DOMContentLoaded', function() {
  const topbarRight = document.querySelector('.topbar-right');
  if (topbarRight && !document.getElementById('orgNotifBtn')) {
    const notifBtn = document.createElement('a');
    notifBtn.href = '#';
    notifBtn.id = 'orgNotifBtn';
    notifBtn.setAttribute('aria-label', 'Notifications');
    notifBtn.setAttribute('onclick', 'showAllOrgNotifsModal(event)');
    notifBtn.style.cssText = 'position:relative;display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:#ffffff;border:1px solid #e2e8f0;color:#1e293b;box-shadow:0 2px 8px rgba(0,0,0,0.04);cursor:pointer;text-decoration:none;transition:all 0.2s;margin-right:4px;';
    
    const unread = <?= (int)$orgUnreadCount ?>;
    notifBtn.innerHTML = `
      <ion-icon name="notifications-outline" style="font-size:22px;color:#1e293b;"></ion-icon>
      <span id="orgUnreadBadge" style="position:absolute;top:-4px;right:-4px;background:#ef4444;color:#fff;border-radius:50%;width:18px;height:18px;font-size:11px;font-weight:700;display:${unread > 0 ? 'flex' : 'none'};align-items:center;justify-content:center;box-shadow:0 0 0 2px #fff;">
        ${unread > 99 ? '99+' : unread}
      </span>
    `;
    topbarRight.insertBefore(notifBtn, topbarRight.firstChild);
  }
});
</script>
