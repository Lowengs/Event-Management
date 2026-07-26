<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
$orgId   = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId=$orgId")->fetch_assoc();
$activePage = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>NAAP ORG Portal – Settings</title>
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../../assets/css/organization/settings_org.css?<?= time() ?>" />
</head>
<body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>

  <div class="content-shell">
    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
        <div class="page-title">
          <h2>Settings</h2>
          <p>Manage your organization profile and preferences</p>
        </div>
      </div>
    </header>

    <div class="maincontent">
      <div class="divider"></div>
      <section class="settings-section">

        <!-- Profile Hero Header Card -->
        <div class="settings-card profile-hero-card">
          <div class="banner-wrapper">
            <img id="bannerPreview" class="banner-img" src="<?= $orgData['OrgBanner'] ? '../../'.ltrim($orgData['OrgBanner'],'/') : '../../assets/img/registrar.jpg' ?>" alt="Banner">
            <button type="button" class="change-banner-btn" onclick="document.getElementById('bannerInput').click()">
              <ion-icon name="camera-outline"></ion-icon> Change Banner
            </button>
            <input type="file" id="bannerInput" name="OrgBanner" form="profileForm" accept="image/*" style="display:none;">
          </div>
          
          <div class="profile-header-info">
            <div class="logo-wrapper">
              <img id="logoPreview" class="logo-img" src="<?= $orgData['OrgPicture'] ? '../../'.ltrim($orgData['OrgPicture'],'/') : '../../assets/img/philsca.png' ?>" alt="Logo">
              <button type="button" class="logo-camera-btn" onclick="document.getElementById('logoInput').click()" title="Change Logo">
                <ion-icon name="camera-outline"></ion-icon>
              </button>
              <input type="file" id="logoInput" name="OrgPicture" form="profileForm" accept="image/*" style="display:none;">
            </div>
            <div class="profile-meta">
              <h2><?= htmlspecialchars($orgData['OrgName'] ?? 'Organization Name') ?></h2>
              <p><ion-icon name="shield-checkmark-outline"></ion-icon> Official Student Organization</p>
            </div>
          </div>
        </div>

        <!-- Organization Profile Details Card -->
        <div class="settings-card">
          <div class="card-header">
            <div class="card-icon blue-gradient"><ion-icon name="business-outline"></ion-icon></div>
            <div>
              <h3>Organization Profile</h3>
              <p class="settings-description">Update your organization's public information and contact details.</p>
            </div>
          </div>
          
          <form id="profileForm" enctype="multipart/form-data">
            <div class="form-grid-2">
              <div class="form-group">
                <label for="settOrgName">Organization Name *</label>
                <div class="input-wrapper">
                  <ion-icon name="business-outline"></ion-icon>
                  <input type="text" name="OrgName" id="settOrgName" value="<?= htmlspecialchars($orgData['OrgName']??'') ?>" placeholder="Enter organization name" required>
                </div>
              </div>
              <div class="form-group">
                <label for="settAdviser">Faculty Adviser</label>
                <div class="input-wrapper">
                  <ion-icon name="person-outline"></ion-icon>
                  <input type="text" name="Adviser" id="settAdviser" value="<?= htmlspecialchars($orgData['Adviser']??'') ?>" placeholder="Faculty adviser name">
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="settEmail">Contact Email</label>
              <div class="input-wrapper">
                <ion-icon name="mail-outline"></ion-icon>
                <input type="email" name="Email" id="settEmail" value="<?= htmlspecialchars($orgData['Email']??'') ?>" placeholder="org@philsca.edu.ph">
              </div>
            </div>

            <div class="form-group">
              <label for="settDesc">Organization Description</label>
              <textarea name="Description" id="settDesc" rows="4" placeholder="Briefly describe your organization's mission, goals, and activities..."><?= htmlspecialchars($orgData['Description']??'') ?></textarea>
            </div>

            <div class="form-actions">
              <button type="submit" class="primary-btn"><ion-icon name="checkmark-circle-outline"></ion-icon> Save Profile Changes</button>
            </div>
          </form>
        </div>

        <!-- Password Settings Card -->
        <div class="settings-card">
          <div class="card-header">
            <div class="card-icon purple-gradient"><ion-icon name="lock-closed-outline"></ion-icon></div>
            <div>
              <h3>Security &amp; Password</h3>
              <p class="settings-description">Ensure your organization account stays secure with a strong password.</p>
            </div>
          </div>
          
          <form id="passwordForm">
            <div class="form-group">
              <label for="curPass">Current Password *</label>
              <div class="input-wrapper">
                <ion-icon name="lock-closed-outline"></ion-icon>
                <input type="password" id="curPass" name="current_password" placeholder="Enter current password" required>
              </div>
            </div>
            <div class="form-grid-2">
              <div class="form-group">
                <label for="newPass">New Password *</label>
                <div class="input-wrapper">
                  <ion-icon name="key-outline"></ion-icon>
                  <input type="password" id="newPass" name="new_password" placeholder="Minimum 8 characters" required>
                </div>
              </div>
              <div class="form-group">
                <label for="conPass">Confirm New Password *</label>
                <div class="input-wrapper">
                  <ion-icon name="shield-checkmark-outline"></ion-icon>
                  <input type="password" id="conPass" name="confirm_password" placeholder="Re-enter new password" required>
                </div>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="primary-btn purple-btn"><ion-icon name="key-outline"></ion-icon> Update Password</button>
            </div>
          </form>
        </div>

      </section>
    </div>
  </div>
</div>

<div id="toast" style="display:none;position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:12px 24px;border-radius:10px;z-index:99999;font-family:'Inter',sans-serif;font-size:14px;"></div>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/org.js"></script>
<script src="../../assets/js/org/settings_org.js"></script>
</body>
</html>