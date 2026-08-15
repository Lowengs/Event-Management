<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}
$orgId   = (int)$_SESSION['org_id'];
$org_id  = $orgId; // backward compat

ob_start();
$_GET['action'] = 'get_org_members'; require __DIR__ . '/../../config/API/endpoints/index.php';
$memApiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$membersList = $memApiRes['data'] ?? [];

$orgName = $_SESSION['org_name'] ?? 'Organization';
$activePage = 'members';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP ORG Portal - Members</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="../../assets/css/organization/members.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/organization/nav.css" />
  <link rel="icon" href="../../assets/img/philsca.png" />
</head>
<body>
  <div class="dashboard-layout">
    <?php include '_org_sidebar.php'; ?>
    <div class="overlay" id="sidebarOverlay"></div>

    <div class="content-shell">
      <header class="topbar">
        <div class="topbar-left">
          <button class="hamburger" id="hamburgerBtn" aria-label="Open menu">
            <ion-icon name="menu-outline"></ion-icon>
          </button>
          <a class="back-btn" href="dashboard.php" aria-label="Back to dashboard">
            <ion-icon name="arrow-back-outline"></ion-icon>
          </a>
          <div class="page-title">
            <h2>Members</h2>
            <p>Manage organization members and approvals</p>
          </div>
        </div>
      </header>

      

      <div class="maincontent">
        <div class="divider"></div>

        <div class="pagebar-actions">
          <button class="ghost-btn" onclick="window.location.href='../../config/API/endpoints/index.php?action=export_org_members'">
            <ion-icon name="download-outline"></ion-icon>
            Export List
          </button>
          <button class="primary-btn" id="openAddMemberModal">
            <ion-icon name="person-add-outline"></ion-icon>
            Add Member
          </button>
        </div>

        <div class="summary-row">
          <article class="summary-card">
            <p class="summary-label">Total Members</p>
            <p class="summary-value text-blue" id="statMembersTotal">-</p>
          </article>
          <article class="summary-card">
            <p class="summary-label">Active Members</p>
            <p class="summary-value text-green" id="statMembersActive">-</p>
          </article>
          <article class="summary-card">
            <p class="summary-label">Pending Approval</p>
            <p class="summary-value text-gold" id="statMembersPending">-</p>
          </article>
          <article class="summary-card" style="border-left: 4px solid #8b5cf6;">
            <p class="summary-label">AI Approved (Active)</p>
            <p class="summary-value" id="statMembersAIApproved" style="color: #8b5cf6;">-</p>
          </article>
          <article class="summary-card" style="border-left: 4px solid #f43f5e;">
            <p class="summary-label">Manual Review</p>
            <p class="summary-value" id="statMembersManualReview" style="color: #f43f5e;">-</p>
          </article>
        </div>

        <section class="filter-panel">
          <label class="search-field">
            <ion-icon name="search-outline"></ion-icon>
            <input type="search" id="searchMember" placeholder="Search members..." />
          </label>
          <label class="select-field">
            <ion-icon name="funnel-outline"></ion-icon>
            <select id="filterStatus" aria-label="Filter by Status">
              <option value="all">All Statuses</option>
              <option value="active">Active Members</option>
              <option value="ai_approved">Active: AI Approved</option>
              <option value="manual_review">Pending: Manual Review</option>
            </select>
          </label>
          <label class="select-field">
            <ion-icon name="school-outline"></ion-icon>
            <select id="filterYearLevel" aria-label="Filter by year level">
              <option value="all">All Year Levels</option>
              <option value="1">1st Year</option>
              <option value="2">2nd Year</option>
              <option value="3">3rd Year</option>
              <option value="4">4th Year</option>
            </select>
          </label>
        </section>

        <section class="members-table-panel">
          <header>
            <h3>Members List (AI Verified & Active)</h3>
            <p style="font-size:12px; color:#64748b;">Members who are active or have been passed automatically by AI.</p>
          </header>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Student ID</th>
                  <th>Email</th>
                  <th>Year Level</th>
                  <th>Section</th>
                  <th>Join Date</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="membersTableBody"><tr><td colspan="8" style="text-align:center; padding: 20px;">Loading members...</td></tr></tbody>
            </table>
          </div>
        </section>

        <!-- New Manual Review Table -->
        <section class="members-table-panel" style="margin-top: 24px; border-top: 4px solid #f43f5e;">
          <header>
            <h3>Requires Manual Review</h3>
            <p style="font-size:12px; color:#64748b;">Members rejected or flagged by the AI verification process.</p>
          </header>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Student ID</th>
                  <th>Program</th>
                  <th>Join Date</th>
                  <th>AI Score</th>
                  <th>Reason Flagged / Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="manualReviewTableBody"><tr><td colspan="7" style="text-align:center; padding: 20px;">Loading...</td></tr></tbody>
            </table>
          </div>
          <footer class="table-footer" aria-label="Members pagination" style="display:none;">
            <!-- Keeping footer structure if we want pagination later -->
          </footer>
        </section>
      </div>
    </div>
  </div>

  <!-- Add Member Modal -->
  <div id="addMemberModal" class="add-member-modal">
    <div class="add-member-modal-content">
      <div class="add-member-modal-header">
        <h2>Add Organization Member</h2>
        <button class="add-member-close" id="closeAddMemberModal">&times;</button>
      </div>
      <div class="add-member-modal-body">
        <form class="add-member-form">
          <div class="form-section">
            <h3>Basic Student Information</h3>
            <div class="form-grid">
              <label>
                <span>Student ID Number</span>
                <input type="text" name="student_id" placeholder="1234MN-000123" required>
              </label>
              <label>
                <span>First Name</span>
                <input type="text" name="first_name" placeholder="Jack Michael" required>
              </label>
              <label>
                <span>Middle Name</span>
                <input type="text" name="middle_name" placeholder="Limbaga" required>
              </label>
              <label>
                <span>Last Name</span>
                <input type="text" name="last_name" placeholder="Aragota" required>
              </label>
              <label>
                <span>Email Address</span>
                <input type="email" name="email" placeholder="you@school.edu" required>
              </label>
              <label>
                <span>Course / Program</span>
                <select name="course" required>
                  <option value="" disabled selected>Select Course / Program</option>
                  <option value="BSAIT">BSAIT</option>
                  <option value="BSAIS">BSAIS</option>
                  <option value="AAMT">AAMT</option>
                  <option value="AAET">AAET</option>
                  <option value="BSAMT">BSAMT</option>
                  <option value="BSAEE">BSAEE</option>
                  <option value="BSAT">BSAT</option>
                  <option value="BSAVTOUR">BSAVTOUR</option>
                  <option value="BSAVCOMM">BSAVCOMM</option>
                  <option value="BSAET">BSAET</option>
                  <option value="BSAVLOG">BSAVLOG</option>
                  <option value="BSAVSEC">BSAVSEC</option>
                </select>
              </label>
              <label>
                <span>Year Level</span>
                <select name="year_level" required>
                  <option value="" disabled selected>Select Year Level</option>
                  <option value="1st Year">1st Year</option>
                  <option value="2nd Year">2nd Year</option>
                  <option value="3rd Year">3rd Year</option>
                  <option value="4th Year">4th Year</option>
                </select>
              </label>
              <label>
                <span>Section</span>
                <select name="section">
                    <option value="" disabled selected>Select Section</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">6</option>
                    <option value="7">7</option>
                    <option value="8">8</option>
                    <option value="9">9</option>
                    <option value="10">10</option>
                </select>
              </label>
            </div>
          </div>
          
          <div class="form-section">
            <h3>Account Information</h3>
            <div class="form-grid">
              <label>
                <span>Username</span>
                <input type="text" name="username" placeholder="student123" required>
              </label>
              <label>
                <span>Password</span>
                <input type="password" name="password" placeholder="Create a password" required>
              </label>
              <label>
                <span>Confirm Password</span>
                <input type="password" name="confirm_password" placeholder="Re-type password" required>
              </label>
            </div>
          </div>

          <div class="form-section">
            <h3>Verification</h3>
            <div class="form-grid">
              <label>
                <span>Profile Photo</span>
                <input type="file" name="profile_photo" accept="image/*">
              </label>
              <label>
                  <span>Phone Number</span>
                  <input type="tel" name="phone" placeholder="+63 912 345 6789" required>
              </label>
            </div>
          </div>
          
          <div class="add-member-modal-footer">
            <button type="button" class="ghost-btn" id="cancelAddMemberBtn">Cancel</button>
            <button type="submit" class="primary-btn">Add Member</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Member Modal -->
  <div id="viewMemberModal" class="add-member-modal">
    <div class="add-member-modal-content" style="max-width: 720px;">
      <div class="add-member-modal-header">
        <h2>Member Details</h2>
        <button class="add-member-close" onclick="closeViewMemberModal()">&times;</button>
      </div>
      <div class="add-member-modal-body">
        <div style="text-align: center; margin-bottom: 24px;">
            <div class="avatar" id="viewMemberAvatar" style="width: 80px; height: 80px; font-size: 28px; margin: 0 auto 12px; background: #e2e8f0; color: #fff;"></div>
            <h3 id="viewMemberName" style="margin: 0; font-size: 20px; color: #0f172a; font-weight: 700;"></h3>
            <p id="viewMemberId" style="margin: 4px 0 0; color: #64748b; font-size: 14px;"></p>
            <div style="margin-top: 10px;">
                <span id="viewMemberStatus" class="status-badge" style="font-size: 12px;"></span>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Academic Information</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 8px;">
                <div>
                    <span style="display:block; font-size: 12px; color: #64748b; margin-bottom: 4px;">Year Level / Section</span>
                    <strong id="viewMemberYearSection" style="font-size: 14px; color: #0f172a;"></strong>
                </div>
                <div>
                    <span style="display:block; font-size: 12px; color: #64748b; margin-bottom: 4px;">Join Date</span>
                    <strong id="viewMemberJoin" style="font-size: 14px; color: #0f172a;"></strong>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h3>Contact Information</h3>
            <div style="display: grid; grid-template-columns: 1fr; gap: 16px; background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                <div>
                    <span style="display:block; font-size: 12px; color: #64748b; margin-bottom: 4px;">Email Address</span>
                    <strong id="viewMemberEmail" style="font-size: 14px; color: #0f172a;"></strong>
                </div>
                <div>
                  <span style="display:block; font-size: 12px; color: #64748b; margin-bottom: 4px;">Phone Number</span>
                  <strong id="viewMemberPhone" style="font-size: 14px; color: #0f172a;"></strong>
                </div>
            </div>
        </div>

        <div class="form-section" style="border: none;">
            <h3>Uploaded Documents</h3>
            <div style="background: #f8fafc; padding: 16px; border-radius: 8px; text-align: center;">
                <span style="display:block; font-size: 12px; color: #64748b; margin-bottom: 12px; text-align: left;">Certificate of Registration (COR)</span>
                <div id="viewMemberCorPreview" style="width: 100%; max-height: 320px; overflow: auto; border-radius: 4px; border: 1px solid #cbd5e1; background: #fff;">
                  <div style="padding: 20px; color: #64748b; font-size: 13px;">No COR document available.</div>
                </div>
                <button type="button" id="viewMemberCorButton" class="ghost-btn" style="margin-top: 12px; font-size: 12px; padding: 6px 12px;" onclick="openMemberCorFullScreen()" disabled>
                  <ion-icon name="scan-outline"></ion-icon>
                  View Full Screen
                </button>
            </div>
        </div>
      </div>
      <div class="add-member-modal-footer">
        <button type="button" class="ghost-btn" style="width: 100%; justify-content: center; border-color: #cbd5e1;" onclick="closeViewMemberModal()">Close Details</button>
      </div>
    </div>
  </div>

<script src="../../assets/js/org/org.js?v=<?= time() ?>"></script>
<script src="../../assets/js/org/members.js"></script>
<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  
  <script src="../../assets/js/org/api_loader.js?v=<?= time() ?>"></script>
  <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.15/dist/docx-preview.min.js"></script>
  <script src="../../assets/js/org/members_org.js?v=<?= time() ?>"></script>
</body>
</html>