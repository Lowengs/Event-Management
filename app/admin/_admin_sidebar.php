<?php
/**
 * _admin_sidebar.php — Reusable sidebar for Admin pages.
 * Include from any app/admin/*.php page.
 *
 * Expects $adminName (string) and $currentPage (string) to be defined before include.
 */
$adminName    = $adminName    ?? ($_SESSION['admin_name'] ?? 'Admin');
$currentPage  = $currentPage  ?? '';
$adminInitial = strtoupper(substr($adminName, 0, 1));
?>

<!-- Mobile Top Navbar -->
<header class="admin-mobile-topbar">
    <div class="mobile-topbar-left">
        <button class="mobile-menu-toggle" id="adminSidebarToggle" aria-label="Toggle Navigation">
            <ion-icon name="menu-outline"></ion-icon>
        </button>
        <a href="dashboard.php" class="mobile-brand">
            <img src="../../assets/img/philsca.png" alt="NAAP">
            <span>NAAP Admin</span>
        </a>
    </div>
    <div class="mobile-topbar-right">
        <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:0.75rem;"><?= $adminInitial ?></div>
    </div>
</header>

<!-- Backdrop Overlay for Mobile Sidebar -->
<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <img src="../../assets/img/philsca.png" alt="NAAP">
            <div class="sidebar-brand-text">
                <span>NAAP System</span>
                <span>Administrator</span>
            </div>
        </a>
        <button class="sidebar-close-btn" id="adminSidebarClose" aria-label="Close Sidebar">
            <ion-icon name="close-outline"></ion-icon>
        </button>
    </div>

    <?php
    $isAdminOpsActive = in_array($currentPage, ['users', 'audit']);
    $isAdminSysActive = in_array($currentPage, ['settings']);
    ?>
    <nav class="sidebar-nav">
        <!-- Direct Overview Link -->
        <a href="dashboard.php" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <ion-icon name="grid-outline"></ion-icon> Dashboard
        </a>

        <!-- Group 1: Administration (Dropdown Selection) -->
        <div class="nav-dropdown <?= $isAdminOpsActive ? 'open active-group' : '' ?>" id="admin-grp-ops">
            <button type="button" class="nav-dropdown-btn" onclick="toggleAdminNavDropdown('admin-grp-ops')" aria-expanded="<?= $isAdminOpsActive ? 'true' : 'false' ?>">
                <span class="btn-left">
                    <ion-icon name="shield-outline" class="group-icon"></ion-icon>
                    <span>Administration</span>
                </span>
                <ion-icon name="chevron-down-outline" class="chevron-icon"></ion-icon>
            </button>
            <div class="nav-dropdown-menu">
                <a href="users.php" class="sidebar-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                    <ion-icon name="people-outline"></ion-icon> User Management
                </a>
                <a href="audit-trail.php" class="sidebar-link <?= $currentPage === 'audit' ? 'active' : '' ?>">
                    <ion-icon name="document-text-outline"></ion-icon> Audit Trail
                </a>
            </div>
        </div>

        <!-- Group 2: System & Account (Dropdown Selection) -->
        <div class="nav-dropdown <?= $isAdminSysActive ? 'open active-group' : '' ?>" id="admin-grp-sys">
            <button type="button" class="nav-dropdown-btn" onclick="toggleAdminNavDropdown('admin-grp-sys')" aria-expanded="<?= $isAdminSysActive ? 'true' : 'false' ?>">
                <span class="btn-left">
                    <ion-icon name="settings-outline" class="group-icon"></ion-icon>
                    <span>System &amp; Account</span>
                </span>
                <ion-icon name="chevron-down-outline" class="chevron-icon"></ion-icon>
            </button>
            <div class="nav-dropdown-menu">
                <a href="settings.php" class="sidebar-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                    <ion-icon name="key-outline"></ion-icon> Settings
                </a>
                <a href="../../config/API/endpoints/index.php?action=admin_logout" class="sidebar-link nav-logout" style="color: #ef4444;">
                    <ion-icon name="log-out-outline"></ion-icon> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= $adminInitial ?></div>
            <div class="sidebar-user-info">
                <span><?= htmlspecialchars($adminName) ?></span>
                <span>Super Administrator</span>
            </div>
        </div>
    </div>
</aside>
<script src="../../assets/js/logout_confirm.js"></script>
<script src="../../assets/js/inactivity_timer.js?v=<?= time() ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('adminSidebarToggle');
    const closeBtn  = document.getElementById('adminSidebarClose');
    const sidebar   = document.getElementById('adminSidebar');
    const overlay   = document.getElementById('adminSidebarOverlay');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);
});

if (typeof window.toggleAdminNavDropdown !== 'function') {
    window.toggleAdminNavDropdown = function(groupId) {
        const el = document.getElementById(groupId);
        if (!el) return;
        const isOpen = el.classList.contains('open');
        el.classList.toggle('open', !isOpen);
        const btn = el.querySelector('.nav-dropdown-btn');
        if (btn) btn.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
        try {
            sessionStorage.setItem('admin_grp_' + groupId, !isOpen ? 'open' : 'closed');
        } catch(e) {}
    };

    document.addEventListener('DOMContentLoaded', function() {
        ['admin-grp-ops', 'admin-grp-sys'].forEach(function(gid) {
            try {
                const saved = sessionStorage.getItem('admin_grp_' + gid);
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
