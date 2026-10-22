<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';

$osa_name  = htmlspecialchars($_SESSION['osa_name']  ?? 'Administrator');
$osa_email = htmlspecialchars($_SESSION['osa_email'] ?? '');

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/../../config/audit.php';
    auditTrackPageForm('osa/settings.php', 'osa'); // logs a 'failed' row if the update is rejected
    ob_start();
    $_GET['action'] = 'update_osa_settings';
    require __DIR__ . '/../../config/API/endpoints/index.php';
    $apiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
    header('Content-Type: text/html; charset=UTF-8');

    if ($apiRes['success'] ?? false) {
        $success_msg = $apiRes['message'] ?? 'Settings updated successfully.';
        $osa_name  = htmlspecialchars($_SESSION['osa_name']  ?? 'Administrator');
        $osa_email = htmlspecialchars($_SESSION['osa_email'] ?? '');
    } else {
        $error_msg = $apiRes['message'] ?? 'Failed to update settings.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Settings</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/settings.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/osa/settings.css?v=<?= time() ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="icon" href="../../assets/img/philsca.png" />
<script src="../../assets/js/security.js"></script>
</head>

<body>
  <header>
    <button id="hamburger-btn" class="hamburger" aria-label="Open menu">
      <ion-icon name="menu-outline"></ion-icon>
    </button>
    <h1>NAAP OSA PORTAL</h1>
  </header>

  <main>
    <?php include '_osa_sidebar.php'; ?>

    <div class="maincontent">
      <div class="pagebar">
        <a class="back-btn" href="dashboard_final.php" aria-label="Back to dashboard">
          <ion-icon name="arrow-back-outline"></ion-icon>
        </a>
        <div class="pagebar-text">
          <h2>Settings</h2>
          <p>Manage your OSA account preferences, security credentials, and system environment</p>
        </div>
      </div>

      <div class="divider"></div>

      <?php if ($success_msg): ?>
        <div class="alert alert-success"><ion-icon name="checkmark-circle-outline"></ion-icon> <?= htmlspecialchars($success_msg) ?></div>
      <?php endif; ?>
      <?php if ($error_msg): ?>
        <div class="alert alert-error"><ion-icon name="alert-circle-outline"></ion-icon> <?= htmlspecialchars($error_msg) ?></div>
      <?php endif; ?>

      <!-- Connected Settings Section Dropdown Selector -->
      <div class="settings-dropdown-bar">
        <label for="settingsSectionSelect" class="settings-dropdown-label">
          <ion-icon name="options-outline"></ion-icon>
          <span>Select Group:</span>
        </label>
        <div class="settings-select-wrapper">
          <select id="settingsSectionSelect" class="settings-dropdown-select" onchange="onSettingsDropdownChange(this.value)">
            <option value="section-profile">👤 Identity &amp; Profile (Profile &amp; Account)</option>
            <option value="section-security">🔒 Security &amp; Access (Security &amp; Password)</option>
            <option value="section-system">⚙️ Platform &amp; Infrastructure (System &amp; Database)</option>
          </select>
          <ion-icon name="chevron-down-outline" class="select-chevron"></ion-icon>
        </div>
      </div>

      <div class="settings-layout-wrap">
        <!-- Settings Sub-Sidebar: Grouping connected functions -->
        <aside class="settings-subnav" aria-label="Settings Navigation">
          <div class="settings-subnav-group">
            <span class="settings-subnav-title">Identity &amp; Profile</span>
            <a href="#section-profile" class="settings-subnav-link active" onclick="activateSettingsNav(this)">
              <ion-icon name="person-circle-outline"></ion-icon>
              <span>Profile &amp; Account</span>
            </a>
          </div>

          <div class="settings-subnav-group">
            <span class="settings-subnav-title">Security &amp; Access</span>
            <a href="#section-security" class="settings-subnav-link" onclick="activateSettingsNav(this)">
              <ion-icon name="shield-checkmark-outline"></ion-icon>
              <span>Security &amp; Password</span>
            </a>
          </div>

          <div class="settings-subnav-group">
            <span class="settings-subnav-title">Platform</span>
            <a href="#section-system" class="settings-subnav-link" onclick="activateSettingsNav(this)">
              <ion-icon name="server-outline"></ion-icon>
              <span>System &amp; Database</span>
            </a>
          </div>
        </aside>

        <!-- Connected Settings Panels -->
        <div class="settings-main-panels">
          <!-- Connected Group 1: Profile & Identity -->
          <section id="section-profile" class="settings-group-section">
            <div class="settings-group-header">
              <div class="group-icon-wrap"><ion-icon name="person-circle-outline"></ion-icon></div>
              <div class="group-header-text">
                <h3>Profile &amp; Account Management</h3>
                <p>Manage administrative identity, display name, and authorized contact email</p>
              </div>
            </div>

            <div class="settings-subgrid">
              <!-- Account Overview Card -->
              <div class="settings-card">
                <div class="settings-card-header">
                  <ion-icon name="id-card-outline"></ion-icon>
                  <h3>Account Overview</h3>
                </div>
                <div class="settings-card-body">
                  <div class="profile-avatar-row">
                    <div class="avatar-circle"><?= strtoupper(substr($osa_name, 0, 1)) ?></div>
                    <div class="avatar-info">
                      <h4><?= $osa_name ?></h4>
                      <p><?= $osa_email ?></p>
                    </div>
                  </div>
                  <div class="info-tile">
                    <span class="it-label">Role</span>
                    <span class="it-value">OSA Administrator</span>
                  </div>
                  <div class="info-tile">
                    <span class="it-label">Portal</span>
                    <span class="it-value">NAAP OSA Portal</span>
                  </div>
                  <div class="info-tile">
                    <span class="it-label">Status</span>
                    <span class="it-value"><span class="badge-active">Active</span></span>
                  </div>
                </div>
              </div>

              <!-- Edit Profile Card -->
              <div class="settings-card">
                <div class="settings-card-header">
                  <ion-icon name="create-outline"></ion-icon>
                  <h3>Edit Profile</h3>
                </div>
                <div class="settings-card-body">
                  <form id="osaProfileForm" method="POST" action="settings.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                      <label for="settingName">Full Name</label>
                      <input type="text" id="settingName" name="name" value="<?= $osa_name ?>" placeholder="Your full name" required>
                    </div>
                    <div class="form-group">
                      <label for="settingEmail">Email Address</label>
                      <input type="email" id="settingEmail" name="email" value="<?= $osa_email ?>" data-current="<?= $osa_email ?>" placeholder="your@email.com" required>
                    </div>
                    <button type="submit" id="saveProfileBtn" class="save-btn">
                      <ion-icon name="save-outline" class="btn-icon-prefix"></ion-icon> Save Changes
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </section>

          <!-- Connected Group 2: Security & Credentials -->
          <section id="section-security" class="settings-group-section">
            <div class="settings-group-header">
              <div class="group-icon-wrap" style="background:#fef2f2;color:#dc2626;"><ion-icon name="shield-checkmark-outline"></ion-icon></div>
              <div class="group-header-text">
                <h3>Security &amp; Authentication</h3>
                <p>Maintain your account security with a robust password and OTP-verified updates</p>
              </div>
            </div>

            <div class="settings-card">
              <div class="settings-card-header">
                <ion-icon name="lock-closed-outline" style="color:#dc2626;"></ion-icon>
                <h3>Change Password</h3>
              </div>
              <div class="settings-card-body">
                <form method="POST" action="settings.php" id="osaChangePwForm">
                  <input type="hidden" name="action" value="change_password">
                  <div class="form-group">
                    <label for="currentPwd">Current Password</label>
                    <div class="password-input-wrap">
                      <input type="password" id="currentPwd" name="current_password" placeholder="Enter current password" autocomplete="new-password" value="" required>
                      <button type="button" class="pw-toggle-btn" data-target="currentPwd" aria-label="Toggle password visibility">
                        <ion-icon name="eye-outline"></ion-icon>
                      </button>
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="newPwd">New Password</label>
                    <div class="password-input-wrap">
                      <input type="password" id="newPwd" name="new_password" placeholder="Min. 8 characters" required minlength="8" autocomplete="new-password">
                      <button type="button" class="pw-toggle-btn" data-target="newPwd" aria-label="Toggle password visibility">
                        <ion-icon name="eye-outline"></ion-icon>
                      </button>
                    </div>
                    <!-- Password Strength Checklist -->
                    <div class="password-strength-container" id="osaPwStrengthContainer" style="margin-top:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:12px;">
                      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:12px;font-weight:700;color:#64748b;">Password Strength:</span>
                        <span id="osaPwStrengthLabel" style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;background:#fee2e2;color:#dc2626;">Too Short</span>
                      </div>
                      <div style="background:#e2e8f0;height:5px;border-radius:4px;overflow:hidden;margin-bottom:8px;">
                        <div id="osaPwStrengthFill" style="width:0%;height:100%;background:#ef4444;transition:all 0.3s ease;"></div>
                      </div>
                      <ul style="list-style:none;padding:0;margin:0;display:grid;grid-template-columns:1fr 1fr;gap:4px;font-size:11.5px;color:#64748b;">
                        <li id="osaCritLength" style="display:flex;align-items:center;gap:4px;"><ion-icon name="close-circle-outline" style="color:#ef4444;font-size:14px;"></ion-icon> 8–12+ characters</li>
                        <li id="osaCritUpper" style="display:flex;align-items:center;gap:4px;"><ion-icon name="close-circle-outline" style="color:#ef4444;font-size:14px;"></ion-icon> Uppercase letter</li>
                        <li id="osaCritLower" style="display:flex;align-items:center;gap:4px;"><ion-icon name="close-circle-outline" style="color:#ef4444;font-size:14px;"></ion-icon> Lowercase letter</li>
                        <li id="osaCritNumber" style="display:flex;align-items:center;gap:4px;"><ion-icon name="close-circle-outline" style="color:#ef4444;font-size:14px;"></ion-icon> Number</li>
                        <li id="osaCritSpecial" style="display:flex;align-items:center;gap:4px;"><ion-icon name="close-circle-outline" style="color:#ef4444;font-size:14px;"></ion-icon> Special character</li>
                      </ul>
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="confirmPwd">Confirm New Password</label>
                    <div class="password-input-wrap">
                      <input type="password" id="confirmPwd" name="confirm_password" placeholder="Re-type new password" required autocomplete="new-password">
                      <button type="button" class="pw-toggle-btn" data-target="confirmPwd" aria-label="Toggle password visibility">
                        <ion-icon name="eye-outline"></ion-icon>
                      </button>
                    </div>
                  </div>
                  <button type="submit" class="save-btn" style="background:#dc2626;">
                    <ion-icon name="key-outline" class="btn-icon-prefix"></ion-icon> Update Password
                  </button>
                </form>
              </div>
            </div>
          </section>

          <!-- Connected Group 3: System & Platform Specs -->
          <section id="section-system" class="settings-group-section">
            <div class="settings-group-header">
              <div class="group-icon-wrap" style="background:#f0fdf4;color:#16a34a;"><ion-icon name="server-outline"></ion-icon></div>
              <div class="group-header-text">
                <h3>System &amp; Server Specifications</h3>
                <p>Runtime parameters, hosting environment, and database engine diagnostics</p>
              </div>
            </div>

            <div class="settings-card">
              <div class="settings-card-header">
                <ion-icon name="information-circle-outline" style="color:#16a34a;"></ion-icon>
                <h3>System Information</h3>
              </div>
              <div class="settings-card-body">
                <div class="info-tile">
                  <span class="it-label">System Architecture</span>
                  <span class="it-value">NAAP Org Management (OSA Subsystem)</span>
                </div>
                <div class="info-tile">
                  <span class="it-label">PHP Engine</span>
                  <span class="it-value"><?= phpversion() ?></span>
                </div>
                <div class="info-tile">
                  <span class="it-label">Database Management</span>
                  <span class="it-value">MariaDB / MySQL (utf8mb4)</span>
                </div>
                <div class="info-tile">
                  <span class="it-label">Server Timestamp</span>
                  <span class="it-value"><?= date('M j, Y  h:i A') ?></span>
                </div>
                <div class="info-tile">
                  <span class="it-label">Timezone Configuration</span>
                  <span class="it-value"><?= date_default_timezone_get() ?></span>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </main>

  <!-- Email Change OTP Verification Modal -->
  <div id="emailOtpModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
    <div style="background:#fff;border-radius:14px;width:90%;max-width:440px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.3);">
      <div style="background:linear-gradient(135deg,#003366,#0a5eb0);padding:1.25rem 1.5rem;color:#fff;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:8px;">
            <ion-icon name="mail-unread-outline"></ion-icon> Verify New Email
          </h3>
          <button type="button" onclick="closeEmailOtpModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>
        <p style="margin:6px 0 0;font-size:0.82rem;opacity:0.9;">A 6-digit confirmation code has been sent to your new email address.</p>
      </div>
      <div style="padding:1.5rem;">
        <p style="margin:0 0 12px;font-size:0.88rem;color:#334155;">New Email: <strong id="otpTargetEmail"></strong></p>
        <div class="form-group" style="margin-bottom:12px;">
          <label for="emailOtpInput" style="font-size:0.75rem;font-weight:700;text-transform:uppercase;color:#64748b;display:block;margin-bottom:6px;">Enter 6-Digit OTP Code</label>
          <input type="text" id="emailOtpInput" maxlength="6" placeholder="000000" style="width:100%;font-size:1.5rem;text-align:center;letter-spacing:6px;font-weight:700;padding:10px;border:2px solid #cbd5e1;border-radius:10px;box-sizing:border-box;">
        </div>
        <div id="otpErrorMsg" style="display:none;color:#dc2626;font-size:0.82rem;margin-bottom:12px;font-weight:600;"></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
          <button type="button" onclick="closeEmailOtpModal()" style="padding:9px 16px;border:1px solid #cbd5e1;background:#fff;border-radius:8px;font-weight:600;cursor:pointer;color:#475569;">Cancel</button>
          <button type="button" id="verifyOtpBtn" onclick="confirmEmailChangeOtp()" style="padding:9px 20px;background:#003366;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;">Verify & Save</button>
        </div>
      </div>
    </div>
  </div>

  <script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
  <script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  <script>
    // Ensure current password field starts clean and empty on page load
    window.addEventListener('DOMContentLoaded', () => {
      const cur = document.getElementById('currentPwd');
      if (cur) cur.value = '';
    });

    // Password Eye Toggles
    document.querySelectorAll('.pw-toggle-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = btn.dataset.target;
        const input = targetId ? document.getElementById(targetId) : btn.parentElement.querySelector('input');
        if (!input) return;
        const isPw = (input.type === 'password');
        input.type = isPw ? 'text' : 'password';
        const icon = btn.querySelector('ion-icon');
        if (icon) {
          icon.setAttribute('name', isPw ? 'eye-off-outline' : 'eye-outline');
        }
      });
    });

    // Password Strength & Criteria Evaluator
    const osaNewPw = document.getElementById('newPwd');
    const osaFill = document.getElementById('osaPwStrengthFill');
    const osaLabel = document.getElementById('osaPwStrengthLabel');
    const osaCritLength = document.getElementById('osaCritLength');
    const osaCritUpper = document.getElementById('osaCritUpper');
    const osaCritLower = document.getElementById('osaCritLower');
    const osaCritNumber = document.getElementById('osaCritNumber');
    const osaCritSpecial = document.getElementById('osaCritSpecial');

    function updateOsaCrit(el, ok) {
      if (!el) return;
      const icon = el.querySelector('ion-icon');
      if (ok) {
        el.style.color = '#10b981';
        el.style.fontWeight = '600';
        if (icon) { icon.setAttribute('name', 'checkmark-circle'); icon.style.color = '#10b981'; }
      } else {
        el.style.color = '#64748b';
        el.style.fontWeight = '400';
        if (icon) { icon.setAttribute('name', 'close-circle-outline'); icon.style.color = '#ef4444'; }
      }
    }

    if (osaNewPw) {
      osaNewPw.addEventListener('input', () => {
        const val = osaNewPw.value || '';
        const hasLen = val.length >= 8;
        const hasUp = /[A-Z]/.test(val);
        const hasLow = /[a-z]/.test(val);
        const hasNum = /[0-9]/.test(val);
        const hasSpec = /[^A-Za-z0-9]/.test(val);

        updateOsaCrit(osaCritLength, hasLen);
        updateOsaCrit(osaCritUpper, hasUp);
        updateOsaCrit(osaCritLower, hasLow);
        updateOsaCrit(osaCritNumber, hasNum);
        updateOsaCrit(osaCritSpecial, hasSpec);

        let score = (hasLen ? 1 : 0) + (hasUp ? 1 : 0) + (hasLow ? 1 : 0) + (hasNum ? 1 : 0) + (hasSpec ? 1 : 0);
        if (val.length >= 12) score++;

        if (!val) {
          osaFill.style.width = '0%';
          osaLabel.textContent = 'Too Short';
          osaLabel.style.background = '#fee2e2';
          osaLabel.style.color = '#dc2626';
        } else if (!hasLen || score <= 2) {
          osaFill.style.width = '25%';
          osaFill.style.background = '#ef4444';
          osaLabel.textContent = 'Weak';
          osaLabel.style.background = '#fee2e2';
          osaLabel.style.color = '#dc2626';
        } else if (score === 3 || score === 4) {
          osaFill.style.width = '60%';
          osaFill.style.background = '#f59e0b';
          osaLabel.textContent = 'Moderate';
          osaLabel.style.background = '#fef3c7';
          osaLabel.style.color = '#d97706';
        } else {
          osaFill.style.width = '100%';
          osaFill.style.background = '#10b981';
          osaLabel.textContent = 'Strong';
          osaLabel.style.background = '#d1fae5';
          osaLabel.style.color = '#059669';
        }
      });
    }

    // Email Change OTP Flow
    const profileForm = document.getElementById('osaProfileForm');
    const emailInput = document.getElementById('settingEmail');
    let pendingEmailFormSubmit = false;

    if (profileForm && emailInput) {
      profileForm.addEventListener('submit', async function(e) {
        const curEmail = emailInput.getAttribute('data-current') || '';
        const newEmail = emailInput.value.trim();

        if (newEmail.toLowerCase() !== curEmail.toLowerCase() && !pendingEmailFormSubmit) {
          e.preventDefault();
          const btn = document.getElementById('saveProfileBtn');
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
              document.getElementById('otpTargetEmail').textContent = newEmail;
              document.getElementById('emailOtpInput').value = '';
              document.getElementById('otpErrorMsg').style.display = 'none';
              document.getElementById('emailOtpModal').style.display = 'flex';
            } else {
              alert(d.message || 'Failed to send OTP code.');
            }
          } catch(err) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            alert('Network error while requesting verification OTP.');
          }
        }
      });
    }

    function closeEmailOtpModal() {
      document.getElementById('emailOtpModal').style.display = 'none';
    }

    async function confirmEmailChangeOtp() {
      const code = document.getElementById('emailOtpInput').value.trim();
      const errBox = document.getElementById('otpErrorMsg');
      const vBtn = document.getElementById('verifyOtpBtn');

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
          emailInput.setAttribute('data-current', d.new_email);
          pendingEmailFormSubmit = true;
          closeEmailOtpModal();
          // Submit remaining profile fields to update name
          profileForm.submit();
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

    // Settings Sub-Sidebar Navigation Handler
    function activateSettingsNav(link) {
      document.querySelectorAll('.settings-subnav-link').forEach(l => l.classList.remove('active'));
      link.classList.add('active');
      const href = link.getAttribute('href') || '';
      const targetId = href.replace('#', '');
      const selectEl = document.getElementById('settingsSectionSelect');
      if (selectEl && targetId) selectEl.value = targetId;
    }

    function onSettingsDropdownChange(sectionId) {
      const sec = document.getElementById(sectionId);
      if (sec) {
        const top = sec.getBoundingClientRect().top + window.pageYOffset - 90;
        window.scrollTo({ top, behavior: 'smooth' });
        document.querySelectorAll('.settings-subnav-link').forEach(link => {
          link.classList.remove('active');
          if (link.getAttribute('href') === '#' + sectionId) {
            link.classList.add('active');
          }
        });
      }
    }

    // Highlight sub-sidebar links and dropdown automatically as user scrolls
    window.addEventListener('DOMContentLoaded', () => {
      const sections = document.querySelectorAll('.settings-group-section');
      const navLinks = document.querySelectorAll('.settings-subnav-link');
      const selectEl = document.getElementById('settingsSectionSelect');

      window.addEventListener('scroll', () => {
        let current = '';
        sections.forEach(sec => {
          const top = sec.offsetTop - 140;
          if (window.pageYOffset >= top) {
            current = sec.getAttribute('id');
          }
        });
        if (current) {
          navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) {
              link.classList.add('active');
            }
          });
          if (selectEl && selectEl.value !== current) {
            selectEl.value = current;
          }
        }
      });
    });
  </script>
</body>
</html>
