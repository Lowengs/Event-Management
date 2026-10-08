<?php
/**
 * Shared tab bar for the org Events / Pre-Registered / On-Site / Online pages.
 * Same tabs, order, labels and style on every page; the current page is
 * highlighted automatically. Styles are self-contained (org-tabs-*) so they do
 * not depend on each page's own CSS.
 */
$__orgTabs = [
    'events_org.php'            => ['calendar-outline',  'Events List'],
    'preregistrations_org.php'  => ['clipboard-outline', 'Pre-Registered Students'],
    'attendance_org.php'        => ['qr-code-outline',   'On-Site Attendance'],
    'online_attendance_org.php' => ['videocam-outline',  'Online Attendance'],
];
$__orgTabCurrent = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<style>
    .org-tabs-bar { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px; margin-bottom: 20px;
                    background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 12px; }
    .org-tabs-bar a { display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px; border-radius: 9px;
                      font-size: 13px; font-weight: 700; line-height: 1.2; text-decoration: none; color: #64748b;
                      background: transparent; transition: background .15s, color .15s; white-space: nowrap; }
    .org-tabs-bar a:hover { color: #0f172a; background: rgba(255,255,255,.6); }
    .org-tabs-bar a.active { color: #2563eb; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .org-tabs-bar ion-icon { font-size: 16px; flex-shrink: 0; }
</style>
<nav class="org-tabs-bar" aria-label="Events and attendance sections">
<?php foreach ($__orgTabs as $__file => [$__icon, $__label]): $__on = ($__file === $__orgTabCurrent); ?>
    <a href="<?= $__file ?>" class="<?= $__on ? 'active' : '' ?>"<?= $__on ? ' aria-current="page"' : '' ?>>
        <ion-icon name="<?= $__icon ?>"></ion-icon> <?= htmlspecialchars($__label) ?>
    </a>
<?php endforeach; ?>
</nav>
