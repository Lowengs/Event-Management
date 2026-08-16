<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgId   = (int)$_SESSION['org_id'];

ob_start();
$_GET['action'] = 'get_org_officers'; require __DIR__ . '/../../config/API/endpoints/index.php';
$offApiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$officersList = $offApiRes['data'] ?? [];

$orgName = $_SESSION['org_name'] ?? 'Organization';
$activePage = 'officers';
?>
<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Officers</title>
  <link rel="stylesheet" href="../../assets/css/organization/members.css">
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../assets/css/organization/officers_org.css?<?= time() ?>" />
</head><body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn" aria-label="Open menu"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title"><h2>Officers</h2><p>Manage organization officers</p></div>
      </div>
    </header>
    <div class="maincontent"><div class="divider"></div>
      <section style="padding:0 24px 24px;">
        <!-- Action bar -->
        <div class="officers-action-bar" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
          <button class="primary-btn" id="openAssignOfficerBtn" type="button">
            <ion-icon name="person-add-outline"></ion-icon> Assign Officer from Members
          </button>
        </div>

        <div class="stats-grid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
          <article class="stat-card"><p>Total Officers</p><strong class="text-blue" id="statOfficersTotal">—</strong></article>
        </div>

        <div class="members-table-wrap">
          <table class="members-table">
            <thead><tr>
              <th>Officer</th><th>Student ID</th><th>Email</th>
              <th>Year Level</th><th>Role/Position</th><th>Actions</th>
            </tr></thead>
            <tbody id="officersTableBody">
              <tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Loading officers…</td></tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</div>

<!-- View Officer Modal -->
<div class="modal-overlay" id="viewOfficerModal">
  <div class="modal-content" style="max-width:480px;">
    <div class="modal-header">
      <h3>Officer Details</h3>
      <button class="close-modal" onclick="closeM('viewOfficerModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body" style="padding:20px;">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
        <div class="avatar" id="voAvatar" style="width:64px;height:64px;font-size:22px;"></div>
        <div><h3 id="voName" style="margin:0;"></h3><p id="voRole" style="margin:4px 0 0;color:#6b7280;font-size:14px;"></p></div>
      </div>
      <table style="width:100%;border-collapse:collapse;">
        <tr><td style="padding:8px 0;color:#6b7280;font-size:13px;width:140px;">Student ID</td><td id="voSid" style="font-weight:600;"></td></tr>
        <tr><td style="padding:8px 0;color:#6b7280;font-size:13px;">Email</td><td id="voEmail"></td></tr>
        <tr><td style="padding:8px 0;color:#6b7280;font-size:13px;">Year Level</td><td id="voYear"></td></tr>
      </table>
    </div>
    <div class="modal-footer"><button class="close-btn-bottom" onclick="closeM('viewOfficerModal')">Close</button></div>
  </div>
</div>

<!-- Edit Officer Role Modal -->
<div class="modal-overlay" id="editOfficerModal">
  <div class="modal-content" style="max-width:420px;">
    <div class="modal-header">
      <h3>Edit Officer Role</h3>
      <button class="close-modal" onclick="closeM('editOfficerModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body" style="padding:20px;">
      <input type="hidden" id="editOfficerUserId">
      <div style="margin-bottom:16px;"><label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Position / Role</label>
        <select id="editOfficerRole" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;margin-bottom:8px;" onchange="toggleOtherInput('editOfficerRole', 'editOfficerRoleOther')">
          <option value="">Select a role…</option>
          <option value="President">President</option>
          <option value="Vice President - Internal affairs">Vice President - Internal affairs</option>
          <option value="Vice President - External affairs">Vice President - External affairs</option>
          <option value="Secretary">Secretary</option>
          <option value="Treasurer">Treasurer</option>
          <option value="Auditor">Auditor</option>
          <option value="PIO">PIO</option>
          <option value="Peace Officer">Peace Officer</option>
          <option value="Others">Others (specify below)</option>
        </select>
        <input type="text" id="editOfficerRoleOther" placeholder="Specify role here…" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;display:none;">
      </div>
      <p style="font-size:12px;color:#94a3b8;">Leave blank or select none to remove officer status.</p>
    </div>
    <div class="modal-footer">
      <button class="close-btn-bottom" onclick="closeM('editOfficerModal')">Cancel</button>
      <button class="primary-btn" id="saveOfficerRoleBtn">Save</button>
    </div>
  </div>
</div>

<!-- Assign Officer Modal -->
<div class="modal-overlay" id="assignOfficerModal">
  <div class="modal-content" style="max-width:500px;">
    <div class="modal-header">
      <h3 style="margin:0;font-size:1.15rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:10px;">
        <ion-icon name="person-add-outline" style="color:#2563eb;font-size:22px;"></ion-icon>
        Assign Officer from Members
      </h3>
      <button class="close-modal" onclick="closeM('assignOfficerModal')"><ion-icon name="close-outline"></ion-icon></button>
    </div>
    <div class="modal-body" style="padding:22px;">
      <div style="margin-bottom:18px;">
        <label style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:8px;">Select Member</label>
        <div style="position:relative;margin-bottom:10px;">
          <ion-icon name="search-outline" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:18px;pointer-events:none;"></ion-icon>
          <input type="text" id="assignMemberSearch" placeholder="Search members by name or ID…" style="width:100%;padding:11px 14px 11px 40px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;outline:none;background:#f8fafc;box-sizing:border-box;" onkeyup="filterAssignMembers()">
        </div>
        <div class="member-list-box" style="border:1px solid #cbd5e1;border-radius:12px;background:#ffffff;height:180px;overflow-y:auto;padding:6px;box-sizing:border-box;box-shadow:inset 0 1px 3px rgba(0,0,0,0.03);">
          <div id="assignMemberList">
            <div style="padding:14px;text-align:center;color:#94a3b8;font-size:13px;">Loading members…</div>
          </div>
        </div>
        <input type="hidden" id="assignMemberSelect" name="assignMemberSelect">
      </div>
      <div>
        <label style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:8px;">Position / Role</label>
        <select id="assignOfficerRole" style="width:100%;padding:11px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;color:#1e293b;background:#f8fafc;outline:none;box-sizing:border-box;" onchange="toggleOtherInput('assignOfficerRole', 'assignOfficerRoleOther')">
          <option value="">Select a role…</option>
          <option value="President">President</option>
          <option value="Vice President - Internal affairs">Vice President - Internal affairs</option>
          <option value="Vice President - External affairs">Vice President - External affairs</option>
          <option value="Secretary">Secretary</option>
          <option value="Treasurer">Treasurer</option>
          <option value="Auditor">Auditor</option>
          <option value="PIO">PIO</option>
          <option value="Peace Officer">Peace Officer</option>
          <option value="Others">Others (specify below)</option>
        </select>
        <input type="text" id="assignOfficerRoleOther" placeholder="Specify role here…" style="width:100%;padding:11px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px;margin-top:10px;display:none;outline:none;box-sizing:border-box;">
      </div>
    </div>
    <div class="modal-footer">
      <button class="close-btn-bottom" onclick="closeM('assignOfficerModal')">Cancel</button>
      <button class="primary-btn" id="saveAssignOfficerBtn">Assign Officer</button>
    </div>
  </div>
</div>



<div id="toast" style="display:none;position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:12px 24px;border-radius:10px;z-index:99999;font-family:'Inter',sans-serif;font-size:14px;"></div>


<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/org.js"></script>
  <script src="../../assets/js/org/officers_org.js?v=<?= time() ?>"></script>
</body></html>