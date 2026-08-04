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

<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="dashboard.php" class="sidebar-brand">
            <img src="../../assets/img/philsca.png" alt="NAAP">
            <div class="sidebar-brand-text">
                <span>NAAP System</span>
                <span>Administrator</span>
            </div>
        </a>
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
