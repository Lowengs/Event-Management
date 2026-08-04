<?php

require_once '../../config/img_helpers.php';
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
    <div class="brand-logo-wrap">
      <img src="<?= $logoSrc ?>" alt="Org logo" class="brand-logo" />
    </div>
    <div class="brand-text">
      <h1><?= htmlspecialchars($orgName) ?></h1>
      <p>ORG Portal</p>
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
