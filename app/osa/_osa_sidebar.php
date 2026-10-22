<?php
/**
 * _osa_sidebar.php — Interactive Dropdown Navigation Sidebar for NAAP OSA Portal
 * 
 * Connected Groupings with Dropdown Selection:
 *  - Main (Dashboard, Calendar)
 *  - Operations (Organization, Events, Students, Announcements)
 *  - Records & Comms (Reports, Audit Trail, Messages)
 *  - System (Settings, Logout)
 */
$currentOsaPage = $currentOsaPage ?? basename($_SERVER['PHP_SELF'], '.php');

// Detect active group to keep it open by default
$isMainActive       = in_array($currentOsaPage, ['dashboard_final', 'dashboard', 'calendar']);
$isOperationsActive = in_array($currentOsaPage, ['organization', 'events', 'students', 'announcement']);
$isRecordsActive    = in_array($currentOsaPage, ['reports', 'audit-trail', 'messages']);
$isSystemActive     = in_array($currentOsaPage, ['settings']);
?>
<nav class="navigation" id="sidebar">
  <ul>
    <li>
      <div class="span">
        <div class="logo-border">
          <img src="../../assets/img/philsca.png" alt="NAAP Logo">
        </div>
        <div class="text">
          <h1>NAAP</h1>
          <p>OSA Portal</p>
        </div>
      </div>
    </li>

    <!-- Group 1: Main (Dropdown Selection) -->
    <li class="nav-dropdown <?= $isMainActive ? 'open active-group' : '' ?>" id="grp-main">
      <button type="button" class="nav-dropdown-btn" onclick="toggleOsaSidebarDropdown('grp-main')" aria-expanded="<?= $isMainActive ? 'true' : 'false' ?>">
        <span class="btn-left">
          <ion-icon name="grid-outline" class="group-icon"></ion-icon>
          <span>Main</span>
        </span>
        <ion-icon name="chevron-down-outline" class="chevron-icon"></ion-icon>
      </button>
      <ul class="nav-dropdown-menu">
        <li>
          <a href="dashboard_final.php" class="nav <?= in_array($currentOsaPage, ['dashboard_final', 'dashboard']) ? 'active' : '' ?>">
            <ion-icon name="speedometer-outline"></ion-icon>
            <span>Dashboard</span>
          </a>
        </li>
        <li>
          <a href="calendar.php" class="nav <?= $currentOsaPage === 'calendar' ? 'active' : '' ?>">
            <ion-icon name="calendar-number-outline"></ion-icon>
            <span>Calendar</span>
          </a>
        </li>
      </ul>
    </li>

    <!-- Group 2: Operations (Dropdown Selection) -->
    <li class="nav-dropdown <?= $isOperationsActive ? 'open active-group' : '' ?>" id="grp-operations">
      <button type="button" class="nav-dropdown-btn" onclick="toggleOsaSidebarDropdown('grp-operations')" aria-expanded="<?= $isOperationsActive ? 'true' : 'false' ?>">
        <span class="btn-left">
          <ion-icon name="layers-outline" class="group-icon"></ion-icon>
          <span>Operations</span>
        </span>
        <ion-icon name="chevron-down-outline" class="chevron-icon"></ion-icon>
      </button>
      <ul class="nav-dropdown-menu">
        <li>
          <a href="organization.php" class="nav <?= $currentOsaPage === 'organization' ? 'active' : '' ?>">
            <ion-icon name="business-outline"></ion-icon>
            <span>Organization</span>
          </a>
        </li>
        <li>
          <a href="events.php" class="nav <?= $currentOsaPage === 'events' ? 'active' : '' ?>">
            <ion-icon name="calendar-outline"></ion-icon>
            <span>Events</span>
          </a>
        </li>
        <li>
          <a href="students.php" class="nav <?= $currentOsaPage === 'students' ? 'active' : '' ?>">
            <ion-icon name="people-outline"></ion-icon>
            <span>Students</span>
          </a>
        </li>
        <li>
          <a href="announcement.php" class="nav <?= $currentOsaPage === 'announcement' ? 'active' : '' ?>">
            <ion-icon name="megaphone-outline"></ion-icon>
            <span>Announcements</span>
          </a>
        </li>
      </ul>
    </li>

    <!-- Group 3: Records & Comms (Dropdown Selection) -->
    <li class="nav-dropdown <?= $isRecordsActive ? 'open active-group' : '' ?>" id="grp-records">
      <button type="button" class="nav-dropdown-btn" onclick="toggleOsaSidebarDropdown('grp-records')" aria-expanded="<?= $isRecordsActive ? 'true' : 'false' ?>">
        <span class="btn-left">
          <ion-icon name="folder-open-outline" class="group-icon"></ion-icon>
          <span>Records &amp; Comms</span>
        </span>
        <ion-icon name="chevron-down-outline" class="chevron-icon"></ion-icon>
      </button>
      <ul class="nav-dropdown-menu">
        <li>
          <a href="reports.php" class="nav <?= $currentOsaPage === 'reports' ? 'active' : '' ?>">
            <ion-icon name="document-text-outline"></ion-icon>
            <span>Reports</span>
          </a>
        </li>
        <li>
          <a href="audit-trail.php" class="nav <?= $currentOsaPage === 'audit-trail' ? 'active' : '' ?>">
            <ion-icon name="analytics-outline"></ion-icon>
            <span>Audit Trail</span>
          </a>
        </li>
        <li>
          <a href="messages.php" class="nav <?= $currentOsaPage === 'messages' ? 'active' : '' ?>">
            <ion-icon name="chatbox-outline"></ion-icon>
            <span>Messages</span>
          </a>
        </li>
      </ul>
    </li>

    <!-- Group 4: System (Dropdown Selection) -->
    <li class="nav-dropdown <?= $isSystemActive ? 'open active-group' : '' ?>" id="grp-system">
      <button type="button" class="nav-dropdown-btn" onclick="toggleOsaSidebarDropdown('grp-system')" aria-expanded="<?= $isSystemActive ? 'true' : 'false' ?>">
        <span class="btn-left">
          <ion-icon name="settings-outline" class="group-icon"></ion-icon>
          <span>System</span>
        </span>
        <ion-icon name="chevron-down-outline" class="chevron-icon"></ion-icon>
      </button>
      <ul class="nav-dropdown-menu">
        <li>
          <a href="settings.php" class="nav <?= $currentOsaPage === 'settings' ? 'active' : '' ?>">
            <ion-icon name="cog-outline"></ion-icon>
            <span>Settings</span>
          </a>
        </li>
        <li>
          <a href="../../config/API/endpoints/index.php?action=osa_logout" class="nav nav-logout">
            <ion-icon name="log-out-outline"></ion-icon>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </li>
  </ul>
</nav>

<script>
if (typeof window.toggleOsaSidebarDropdown !== 'function') {
  window.toggleOsaSidebarDropdown = function(groupId) {
    const el = document.getElementById(groupId);
    if (!el) return;
    const isOpen = el.classList.contains('open');
    el.classList.toggle('open', !isOpen);
    const btn = el.querySelector('.nav-dropdown-btn');
    if (btn) btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
    try {
      sessionStorage.setItem('osa_grp_' + groupId, !isOpen ? 'open' : 'closed');
    } catch(e) {}
  };

  document.addEventListener('DOMContentLoaded', function() {
    ['grp-main', 'grp-operations', 'grp-records', 'grp-system'].forEach(function(gid) {
      try {
        const saved = sessionStorage.getItem('osa_grp_' + gid);
        const el = document.getElementById(gid);
        if (el && !el.classList.contains('active-group') && saved) {
          if (saved === 'open') {
            el.classList.add('open');
            const btn = el.querySelector('.nav-dropdown-btn');
            if (btn) btn.setAttribute('aria-expanded', 'true');
          } else if (saved === 'closed') {
            el.classList.remove('open');
            const btn = el.querySelector('.nav-dropdown-btn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
          }
        }
      } catch(e) {}
    });
  });
}
</script>
