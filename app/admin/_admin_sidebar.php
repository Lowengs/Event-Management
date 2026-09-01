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

    <nav class="sidebar-nav">
        <div class="sidebar-nav-label">Main</div>
        <a href="dashboard.php" class="sidebar-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <ion-icon name="grid-outline"></ion-icon> Dashboard
        </a>
        <a href="users.php" class="sidebar-link <?= $currentPage === 'users' ? 'active' : '' ?>">
            <ion-icon name="people-outline"></ion-icon> User Management
        </a>
        <a href="audit-trail.php" class="sidebar-link <?= $currentPage === 'audit' ? 'active' : '' ?>">
            <ion-icon name="document-text-outline"></ion-icon> Audit Trail
        </a>

        <div class="sidebar-nav-label" style="margin-top:16px;">Account</div>
        <a href="settings.php" class="sidebar-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
            <ion-icon name="settings-outline"></ion-icon> Settings
        </a>
        <a href="../../config/API/endpoints/index.php?action=admin_logout" class="sidebar-link" style="color: #dc2626;">
            <ion-icon name="log-out-outline"></ion-icon> Logout
        </a>
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
</script>
