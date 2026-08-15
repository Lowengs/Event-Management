<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgId   = (int)$_SESSION['org_id'];

ob_start();
$_GET['action'] = 'get_org_announcements'; require __DIR__ . '/../../config/API/endpoints/index.php';
$annApiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$announcementsList = $annApiRes['data'] ?? [];

$orgName = $_SESSION['org_name'] ?? 'Organization';
$activePage = 'announcement';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Announcements</title>
  <link rel="stylesheet" href="../../assets/css/organization/announcement.css">
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
</head><body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title"><h2>Announcements</h2><p>Post and manage organization announcements</p></div>
      </div>
    </header>
    <div class="maincontent"><div class="divider"></div>
      <div class="page-actions" style="padding:16px 24px 0;">
        <button class="add-announcement-btn" type="button" id="openCreateAnnBtn">
          <ion-icon name="add-outline"></ion-icon> Add Announcement
        </button>
      </div>
      <section class="summary-grid" style="padding:16px 24px;">
        <article class="summary-card"><p class="summary-label">Total</p><p class="summary-value text-blue" id="annTotal">0</p></article>
        <article class="summary-card"><p class="summary-label">Approved</p><p class="summary-value text-green" id="annApproved">0</p></article>
        <article class="summary-card"><p class="summary-label">Pending OSA</p><p class="summary-value text-gold" id="annPending">0</p></article>
        <article class="summary-card"><p class="summary-label">Draft</p><p class="summary-value text-slate" id="annDraft">0</p></article>
      </section>
      <section class="announcements-panel" style="padding:0 24px 24px;">
        <header class="panel-header">
          <h3>All Announcements</h3>
          <div class="panel-filter-wrap">
            <label for="annFilter">Filter</label>
            <select id="annFilter" class="panel-filter">
              <option value="all">All</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Failed</option>
            </select>
          </div>
        </header>
        <div id="annList"><p style="text-align:center;padding:40px;color:#94a3b8;">No announcements yet. Click "Add Announcement" to create one.</p></div>
      </section>
    </div>
  </div>
</div>

<!-- Create/Edit Announcement Modal (fixed with inline styles) -->
<div id="annFormModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="annFormTitle">Create Announcement</h2>
      <button class="close-modal" onclick="closeM('annFormModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="annFormId">
      <div class="section-label">Announcement Information</div>
      <div class="form-group"><label>Title *</label>
        <input type="text" id="annTitle" class="form-input" placeholder="Enter announcement title"></div>
      <div class="form-group"><label>Message *</label>
        <textarea id="annBody" class="form-input" placeholder="Write the announcement content here..."></textarea></div>
      <div class="form-grid-2">
        <div class="form-group"><label>Category</label>
          <select id="annCategory" class="form-input">
            <option>General Notice</option><option>Event Announcement</option><option>Reminder</option><option>Meeting</option>
          </select></div>
        <div class="form-group"><label>Audience</label>
          <select id="annAudience" class="form-input">
            <option value="All Members">All Members</option>
            <option value="Officers Only">Officers Only</option>
            <option value="Public">Public</option>
          </select></div>
      </div>
      <div class="section-label">Schedule</div>
      <div class="form-grid-2">
        <div class="form-group"><label>Date Posted</label>
          <input type="date" id="annDate" class="form-input" min="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>Expiry Date (Optional)</label>
          <input type="date" id="annExpiry" class="form-input" min="<?= date('Y-m-d') ?>"></div>
      </div>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;font-size:13px;color:#92400e;font-family:'Inter',sans-serif;">
        <strong>Note:</strong> After submission, the announcement will be sent to OSA for approval before it goes live.
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-cancel" onclick="closeM('annFormModal')">Cancel</button>
      <button type="button" class="btn-save" id="saveAnnBtn">Submit for Approval</button>
    </div>
  </div>
</div>

<!-- View Modal -->
<div id="viewAnnModal" class="modal-overlay">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="vAnnTitle">Announcement</h2>
      <button class="close-modal" onclick="closeM('viewAnnModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body">
      <p style="font-size:12px;color:#94a3b8;margin-bottom:8px;font-family:'Inter',sans-serif;" id="vAnnMeta"></p>
      <p id="vAnnBody" style="line-height:1.7;color:#374151;font-family:'Inter',sans-serif;"></p>
      <div id="vAnnTags" style="margin-top:16px;"></div>
    </div>
    <div class="modal-footer"><button class="btn-cancel" onclick="closeM('viewAnnModal')">Close</button></div>
  </div>
</div>

<div id="toast"></div>

<script>
  const currentOrgId = <?= (int)$orgId ?>;
</script>
<script src="../../assets/js/org/org.js?v=<?= time() ?>"></script>
<script src="../../assets/js/org/announcement.js?v=<?= time() ?>"></script>
<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body></html>