<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/img_helpers.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }
ob_start();
$_GET['action'] = 'get_org_settings'; require __DIR__ . '/../../config/API/endpoints/index.php';
$settApiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$orgData = $settApiRes['data'] ?? [];

$bannerSrc = imgPathForDepth($orgData['OrgBanner'] ?? '', 2, '../../assets/img/registrar.jpg');
$logoSrc   = imgPathForDepth($orgData['OrgPicture'] ?? '', 2, '../../assets/img/philsca.png');
$emailVal  = $orgData['Email'] ?? ($orgData['email'] ?? '');
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
<script src="../../assets/js/security.js"></script>
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
            <img id="bannerPreview" class="banner-img" src="<?= $bannerSrc ?>" alt="Banner">
            <button type="button" class="change-banner-btn" onclick="document.getElementById('bannerInput').click()">
              <ion-icon name="camera-outline"></ion-icon> Change Banner
            </button>
            <input type="file" id="bannerInput" name="OrgBanner" form="profileForm" accept="image/*" style="display:none;">
          </div>
          
          <div class="profile-header-info">
            <div class="logo-wrapper">
              <img id="logoPreview" class="logo-img" src="<?= $logoSrc ?>" alt="Logo">
              <button type="button" class="logo-camera-btn" onclick="document.getElementById('logoInput').click()" title="Change Logo">
                <ion-icon name="camera-outline"></ion-icon>
              </button>
              <input type="file" id="logoInput" name="OrgPicture" form="profileForm" accept="image/*" style="display:none;">
            </div>
            <div class="profile-meta">
              <h2><?= htmlspecialchars($orgData['OrgName'] ?? ($_SESSION['org_name'] ?? 'Organization Name')) ?></h2>
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
                  <input type="text" name="OrgName" id="settOrgName" value="<?= htmlspecialchars($orgData['OrgName']??($_SESSION['org_name']??'')) ?>" placeholder="Enter organization name" required>
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
                <input type="email" name="Email" id="settEmail" value="<?= htmlspecialchars($emailVal) ?>" data-current="<?= htmlspecialchars($emailVal) ?>" placeholder="org@philsca.edu.ph">
              </div>
            </div>

            <div class="form-group">
              <label for="settDesc">Organization Description</label>
              <textarea name="Description" id="settDesc" rows="4" placeholder="Briefly describe your organization's mission, goals, and activities..."><?= htmlspecialchars($orgData['Description']??'') ?></textarea>
            </div>

            <div class="form-actions">
              <button type="submit" id="saveOrgProfileBtn" class="primary-btn"><ion-icon name="checkmark-circle-outline"></ion-icon> Save Profile Changes</button>
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
                <input type="password" id="curPass" name="current_password" class="has-pw-toggle" placeholder="Enter current password" autocomplete="new-password" value="" required>
                <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility(this, 'curPass'); return false;" aria-label="Toggle password visibility">
                  <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                </button>
              </div>
            </div>
            <div class="form-grid-2">
              <div class="form-group">
                <label for="newPass">New Password *</label>
                <div class="input-wrapper">
                  <ion-icon name="key-outline"></ion-icon>
                  <input type="password" id="newPass" name="new_password" class="has-pw-toggle" placeholder="Minimum 8 characters" autocomplete="new-password" required minlength="8">
                  <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility(this, 'newPass'); return false;" aria-label="Toggle password visibility">
                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label for="conPass">Confirm New Password *</label>
                <div class="input-wrapper">
                  <ion-icon name="shield-checkmark-outline"></ion-icon>
                  <input type="password" id="conPass" name="confirm_password" class="has-pw-toggle" placeholder="Re-enter new password" autocomplete="new-password" required minlength="8">
                  <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility(this, 'conPass'); return false;" aria-label="Toggle password visibility">
                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                  </button>
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

<!-- Email Change OTP Verification Modal -->
<div id="orgEmailOtpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
  <div style="background:#fff;border-radius:14px;width:90%;max-width:440px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.3);">
    <div style="background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:1.25rem 1.5rem;color:#fff;">
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:8px;">
          <ion-icon name="mail-unread-outline"></ion-icon> Verify New Email
        </h3>
        <button type="button" onclick="closeOrgEmailOtpModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;">&times;</button>
      </div>
      <p style="margin:6px 0 0;font-size:0.82rem;opacity:0.9;">A 6-digit verification code has been sent to your new email address.</p>
    </div>
    <div style="padding:1.5rem;">
      <p style="margin:0 0 12px;font-size:0.88rem;color:#334155;">New Email: <strong id="orgOtpTargetEmail"></strong></p>
      <div class="form-group" style="margin-bottom:12px;">
        <label for="orgEmailOtpInput" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:#64748b;display:block;margin-bottom:6px;">Enter 6-Digit OTP Code</label>
        <input type="text" id="orgEmailOtpInput" maxlength="6" placeholder="000000" style="width:100%;font-size:1.5rem;text-align:center;letter-spacing:6px;font-weight:700;padding:10px;border:2px solid #cbd5e1;border-radius:10px;box-sizing:border-box;">
      </div>
      <div id="orgOtpErrorMsg" style="display:none;color:#dc2626;font-size:0.82rem;margin-bottom:12px;font-weight:600;"></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
        <button type="button" onclick="closeOrgEmailOtpModal()" style="padding:9px 16px;border:1px solid #cbd5e1;background:#fff;border-radius:8px;font-weight:600;cursor:pointer;color:#475569;">Cancel</button>
        <button type="button" id="orgVerifyOtpBtn" onclick="confirmOrgEmailChangeOtp()" style="padding:9px 20px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Verify & Save</button>
      </div>
    </div>
  </div>
</div>

<div id="toast" style="display:none;position:fixed;bottom:24px;right:24px;background:#1e293b;color:#fff;padding:12px 24px;border-radius:10px;z-index:99999;font-family:'Inter',sans-serif;font-size:14px;"></div>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/org.js?v=<?= time() ?>"></script>
<script src="../../assets/js/org/settings_org.js?v=<?= time() ?>"></script>
<script>
  // Ensure current password field starts clean and empty on page load
  window.addEventListener('DOMContentLoaded', () => {
    const cp = document.getElementById('curPass');
    if (cp) cp.value = '';
  });

  // Global bulletproof password visibility toggle
  function togglePasswordVisibility(a, b) {
    let inputId, btn;
    if (typeof a === 'string') {
      inputId = a;
      btn = b;
    } else {
      btn = a;
      inputId = b;
    }
    const input = typeof inputId === 'string' ? document.getElementById(inputId) : (btn ? btn.parentElement.querySelector('input') : null);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    if (btn) {
      const openSvg = btn.querySelector('.eye-open');
      const closedSvg = btn.querySelector('.eye-closed');
      if (openSvg && closedSvg) {
        openSvg.style.display = isPassword ? 'none' : 'block';
        closedSvg.style.display = isPassword ? 'block' : 'none';
      }
      const icon = btn.querySelector('ion-icon');
      if (icon) {
        icon.setAttribute('name', isPassword ? 'eye-off-outline' : 'eye-outline');
      }
    }
  }
  window.togglePasswordVisibility = togglePasswordVisibility;

  // Email Change OTP Flow for Organization
  const orgProfileForm = document.getElementById('profileForm');
  const orgEmailInput = document.getElementById('settEmail');
  let pendingOrgEmailSubmit = false;

  if (orgProfileForm && orgEmailInput) {
    orgProfileForm.addEventListener('submit', async function(e) {
      const curEmail = orgEmailInput.getAttribute('data-current') || '';
      const newEmail = orgEmailInput.value.trim();

      if (newEmail && newEmail.toLowerCase() !== curEmail.toLowerCase() && !pendingOrgEmailSubmit) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const btn = document.getElementById('saveOrgProfileBtn');
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<ion-icon name="sync-outline" style="animation:spin 1s linear infinite;"></ion-icon> Sending OTP...';

        try {
          const fd = new FormData();
          fd.append('new_email', newEmail);
          const res = await fetch('../../config/API/endpoints/index.php?action=send_email_change_otp', {
            method: 'POST',
            body: fd
          });
          const d = await res.json();
          btn.disabled = false;
          btn.innerHTML = origHtml;

          if (d.success) {
            document.getElementById('orgOtpTargetEmail').textContent = newEmail;
            document.getElementById('orgEmailOtpInput').value = '';
            document.getElementById('orgOtpErrorMsg').style.display = 'none';
            document.getElementById('orgEmailOtpModal').style.display = 'flex';
          } else {
            alert(d.message || 'Failed to send OTP code.');
          }
        } catch(err) {
          btn.disabled = false;
          btn.innerHTML = origHtml;
          alert('Network error while requesting verification OTP.');
        }
      }
    }, true);
  }

  function closeOrgEmailOtpModal() {
    document.getElementById('orgEmailOtpModal').style.display = 'none';
  }

  async function confirmOrgEmailChangeOtp() {
    const code = document.getElementById('orgEmailOtpInput').value.trim();
    const errBox = document.getElementById('orgOtpErrorMsg');
    const vBtn = document.getElementById('orgVerifyOtpBtn');

    if (!code || code.length !== 6) {
      errBox.textContent = 'Please enter a valid 6-digit verification code.';
      errBox.style.display = 'block';
      return;
    }

    vBtn.disabled = true;
    vBtn.textContent = 'Verifying...';
    errBox.style.display = 'none';

    try {
      const fd = new FormData();
      fd.append('otp_code', code);
      const res = await fetch('../../config/API/endpoints/index.php?action=verify_email_change_otp', {
        method: 'POST',
        body: fd
      });
      const d = await res.json();
      vBtn.disabled = false;
      vBtn.textContent = 'Verify & Save';

      if (d.success) {
        orgEmailInput.setAttribute('data-current', d.new_email);
        pendingOrgEmailSubmit = true;
        closeOrgEmailOtpModal();
        orgProfileForm.requestSubmit();
      } else {
        errBox.textContent = d.message || 'Verification failed.';
        errBox.style.display = 'block';
      }
    } catch(err) {
      vBtn.disabled = false;
      vBtn.textContent = 'Verify & Save';
      errBox.textContent = 'Network error while verifying OTP.';
      errBox.style.display = 'block';
    }
  }
</script>
</body>
</html>
