<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgId   = (int)$_SESSION['org_id'];
$orgData = ['OrgName' => $_SESSION['org_name'] ?? 'Organization', 'OrgPicture' => $_SESSION['org_logo'] ?? ''];
$activePage = 'documents';

$_GET['action'] = 'get_org_events';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$evApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$events = $evApiRes['data'] ?? [];
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Documents</title>
  <link rel="stylesheet" href="../../assets/css/organization/documents.css?v=5">
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../assets/css/organization/documents_org.css?<?= time() ?>" />
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head><body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title"><h2>Document Management</h2><p>Store, organize, and share organization files</p></div>
      </div>
    </header>
    <div class="maincontent"><div class="divider"></div>
      <div class="page-actions" aria-label="Document page actions">
        <button class="add-doc-btn" id="openUploadModalBtn" type="button">
          <ion-icon name="add-outline"></ion-icon> Upload Document
        </button>
      </div>
      <section class="search-filter-panel">
        <label class="search-field">
          <ion-icon name="search-outline"></ion-icon>
          <input type="search" id="docSearch" placeholder="Search documents...">
        </label>
        <select class="filter-btn" id="docEventFilter" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-family:'Inter',sans-serif;font-size:13px;">
          <option value="">All Events</option>
          <?php foreach($events as $ev): ?>
          <option value="<?= $ev['EventId'] ?>"><?= htmlspecialchars($ev['EventName']) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="filter-btn" id="docTypeFilter" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-family:'Inter',sans-serif;font-size:13px;">
          <option value="">All Types</option>
          <option>MOA / Letters</option><option>Proposal</option><option>Budget Plan</option>
          <option>Activity Permit</option><option>Report</option><option>Certificate</option><option>Other</option>
        </select>
      </section>

      <section class="panel" aria-label="Documents list" style="padding:0 24px 0;">
        <h3 style="padding:16px 0 12px;">Organization Documents</h3>
        <div id="docList"><p style="text-align:center;padding:40px;color:#94a3b8;font-family:'Inter',sans-serif;">No documents uploaded yet. Click "Upload Document" to add files.</p></div>

        <!-- Pagination bar -->
        <div id="docPaginationBar" style="display:none;align-items:center;justify-content:space-between;gap:12px;padding:14px 0 20px;flex-wrap:wrap;">
          <span id="docPageInfo" style="font-size:13px;color:#64748b;font-weight:500;font-family:'Inter',sans-serif;"></span>
          <div id="docPageControls" style="display:flex;align-items:center;gap:6px;"></div>
        </div>
      </section>
    </div>
  </div>
</div>

<!-- Upload Document Modal -->
<div id="uploadDocModal" class="doc-modal">
  <div class="doc-modal-content" style="max-width:640px;">
    <div class="doc-modal-header">
      <h2>Upload Document</h2>
      <button class="doc-modal-close" id="closeUploadModal">&times;</button>
    </div>
    <div class="doc-modal-body">
      <form class="upload-doc-form" id="uploadDocForm" enctype="multipart/form-data">
        <div class="form-group"><label>Document Title <span class="required">*</span></label>
          <input type="text" name="Title" placeholder="e.g. Budget Plan 2026" required class="form-input"></div>
        <div class="form-group half-group">
          <div style="flex:1"><label>Document Type <span class="required">*</span></label>
            <select name="DocType" required class="form-input">
              <option value="" disabled selected>Select Type...</option>
              <option>MOA / Letters</option><option>Proposal</option><option>Budget Plan</option>
              <option>Activity Permit</option><option>Report</option><option>Certificate</option><option>Other</option>
            </select></div>
          <div style="flex:1"><label>Related Event <span>(if applicable)</span></label>
            <select name="EventId" class="form-input">
              <option value="">None / Not Applicable</option>
              <?php foreach($events as $ev): ?>
              <option value="<?= $ev['EventId'] ?>"><?= htmlspecialchars($ev['EventName']) ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <div class="form-group"><label>Description / Purpose <span class="required">*</span></label>
          <textarea name="Description" rows="3" class="form-input" placeholder="Briefly describe the purpose of this document..." required></textarea></div>
        <div class="form-group"><label>Attach File <span class="required">*</span></label>
          <div class="file-upload-box" id="fileDropZone">
            <div class="dropzone-default">
              <ion-icon name="cloud-upload-outline"></ion-icon>
              <p>Drag and drop file here or <span class="browse-link">browse</span></p>
              <small>Accepted formats: PDF, DOCX, JPG, PNG (Max size: 10MB)</small>
            </div>
            <div class="dropzone-success" style="display:none;">
              <ion-icon name="checkmark-circle-outline" style="font-size:38px;color:#10b981;margin-bottom:6px;"></ion-icon>
              <p class="dropzone-filename" style="font-weight:700;font-size:15px;color:#1e293b;margin:2px 0;"></p>
              <small style="color:#64748b;font-size:12px;font-weight:500;">Click to change file</small>
            </div>
            <input type="file" name="DocFile" class="docFileInput" id="docFileInput" accept=".pdf,.doc,.docx,image/*" required>
          </div>
          <div id="selectedFileName" class="selected-file" style="display:none;">
            <ion-icon name="document-text-outline"></ion-icon>
            <span class="file-name-text">No file selected</span>
          </div>
        </div>
      </form>
    </div>
    <div class="doc-modal-footer">
      <div class="footer-right">
        <button class="ghost-btn" type="button" id="cancelUploadBtn">Cancel</button>
        <button class="primary-btn" type="button" id="submitDocBtn"><ion-icon name="cloud-upload-outline"></ion-icon> Upload Document</button>
      </div>
    </div>
  </div>
</div>

<div id="toast"></div>


<script src="../../assets/js/org/org.js"></script>
<script>window.orgEvents = <?= json_encode($events) ?>;</script>
<script src="../../assets/js/org/documents_org.js?v=<?= time() ?>"></script>
</body></html>