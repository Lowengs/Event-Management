<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// ── Read "Remember Me" cookies to pre-fill fields ─────────────────
$rememberedOsaEmail   = htmlspecialchars($_COOKIE['osa_remember_email']   ?? '');
$rememberedOrgId      = (int)($_COOKIE['org_remember_id']                 ?? 0);
$rememberedOrgUsername= htmlspecialchars($_COOKIE['org_remember_username'] ?? '');

define('ALLOW_PUBLIC_ORG_LIST', true);

$_GET['action'] = 'get_osa_organizations';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$orgApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$orgs = $orgApiRes['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP – OSA &amp; Organization Login</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/admin/login.css?v=<?= time() ?>">

    <!-- FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="icon" href="../../assets/img/philsca.png">

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>
    <main>
        <div class="login-container" style="flex-direction: column; gap: 16px; padding: 20px 0;">
            <div class="login-card">
                <div class="login-header">
                    <div class="img-border">
                        <img src="../../assets/img/philsca.png" alt="NAAP logo">
                    </div>

                    <p>National Aviation Academy of the Philippines<br>
                        <span>Student Organization Management</span>
                    </p>

                    <h1 class="schooltitle" id="titleOrg">Organization</h1>
                    <h1 class="schooltitle active" id="titleOsa">OSA</h1>

                    <div class="toggle-option">
                        <button class="tab active" id="btnOSA">Login as OSA</button>
                        <button class="tab"        id="btnORG">Login as Organization</button>
                    </div>
                </div><!-- /.login-header -->

                <div class="login-forms">

                    <!-- ══ OSA FORM ══════════════════════════════════ -->
                    <form id="formOSA" class="form active" novalidate>
                        <label for="osaEmail">Email</label>
                        <div class="input-icon-wrap">
                            <ion-icon name="mail-outline"></ion-icon>
                            <input type="email" id="osaEmail" placeholder="OSA Email Address"
                                   class="username-input" autocomplete="email"
                                   value="<?= $rememberedOsaEmail ?>">
                        </div>
                        <span class="field-err" id="osaEmailErr"></span>

                        <label for="osaPassword">Password</label>
                        <div class="input-icon-wrap">
                            <ion-icon name="lock-closed-outline"></ion-icon>
                            <input type="password" id="osaPassword" placeholder="Password"
                                   class="password-input" autocomplete="current-password">
                        </div>
                        <span class="field-err" id="osaPassErr"></span>

                        <div class="flex-items">
                            <label><input type="checkbox" id="osaRemember" name="remember"
                                <?= $rememberedOsaEmail ? 'checked' : '' ?>> Remember me</label>
                            <a href="#" id="osaForgotLink" style="font-size:0.85rem;color:#60a5fa;">Forgot Password?</a>
                        </div>

                        <button type="submit" id="osaSbmBtn">Sign In</button>
                    </form>

                    <!-- ══ ORG OFFICER FORM ══════════════════════════ -->
                    <form id="formORG" class="form" novalidate>

                        <label for="orgSelect">Organization</label>
                        <div class="input-icon-wrap">
                            <ion-icon name="business-outline"></ion-icon>
                            <select id="orgSelect" name="org_id">
                                <option value="" disabled <?= $rememberedOrgId ? '' : 'selected' ?>>Select Organization</option>
                                <?php foreach ($orgs as $o): ?>
                                    <option value="<?= (int)$o['OrgId'] ?>"
                                        <?= ($rememberedOrgId === (int)$o['OrgId']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($o['OrgName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <span class="field-err" id="orgSelectErr"></span>

                        <label for="orgUsername">Username</label>
                        <div class="input-icon-wrap">
                            <ion-icon name="person-outline"></ion-icon>
                            <input type="text" id="orgUsername" placeholder="Officer Username"
                                   class="username-input" autocomplete="username"
                                   value="<?= $rememberedOrgUsername ?>">
                        </div>
                        <span class="field-err" id="orgUserErr"></span>

                        <label for="orgPassword">Password</label>
                        <div class="input-icon-wrap">
                            <ion-icon name="lock-closed-outline"></ion-icon>
                            <input type="password" id="orgPassword" placeholder="Password"
                                   class="password-input" autocomplete="current-password">
                        </div>
                        <span class="field-err" id="orgPassErr"></span>

                        <div class="flex-items">
                            <label><input type="checkbox" id="orgRemember" name="remember"
                                <?= $rememberedOrgId ? 'checked' : '' ?>> Remember me</label>
                            <a href="#">Forgot Password?</a>
                        </div>

                        <button type="submit" id="orgSbmBtn">Sign In</button>
                    </form>

                </div>
                
                <div style="text-align:center;margin-top:20px;padding:16px 20px;border-top:1px solid #e2e8f0;">
                    <a href="index.php" style="color:#1e40af;font-size:0.88rem;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:6px;">← Back to Index</a>
                </div>
            </div>
        </div>
    </main>


    <div id="adminToast" role="alert" aria-live="polite">
        <span id="adminToastIcon"></span>
        <span id="adminToastMsg"></span>
    </div>

    <script src="../../assets/js/osa/login.js?v=<?= time() ?>" defer></script>


</body>

<div id="forgotModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#1e2a3a;border:1px solid #334155;border-radius:16px;padding:2rem;width:92%;max-width:400px;font-family:'Inter',sans-serif;box-shadow:0 24px 64px rgba(0,0,0,0.5);">
    <div id="forgotStep1">
      <h3 style="margin:0 0 0.5rem;color:#f1f5f9;font-size:1.1rem;">Reset Password</h3>
      <p style="margin:0 0 1.25rem;color:#94a3b8;font-size:0.85rem;line-height:1.5;">Please contact your school system administrator to reset your OSA or Organization account password.</p>
      <div style="background:#0f172a;border:1px solid #334155;border-radius:8px;padding:12px;margin-bottom:1rem;">
        <p style="margin:0;font-size:0.83rem;color:#f1f5f9;font-weight:600;"><ion-icon name="mail-outline" style="vertical-align:middle;"></ion-icon> System Admin: <a href="mailto:naaporganization@gmail.com" style="color:#60a5fa;text-decoration:none;">naaporganization@gmail.com</a></p>
      </div>
      <div style="display:flex;gap:.75rem;">
        <button onclick="closeForgotModal()" style="flex:1;padding:.65rem;background:#2563eb;border:none;border-radius:8px;color:#fff;font-size:0.9rem;font-weight:600;cursor:pointer;transition:background 0.2s;">Ok</button>
      </div>
    </div>
  
    <div id="forgotStep2" style="display:none;">
      <h3 style="margin:0 0 0.5rem;color:#f1f5f9;font-size:1.1rem;">Enter Reset Code</h3>
      <p style="margin:0 0 1.25rem;color:#94a3b8;font-size:0.85rem;">Check your email for a 6-digit code (or see the dev message above).</p>
      <input type="text" id="resetPin" placeholder="6-digit Code" maxlength="6"
             style="width:100%;padding:.65rem .85rem;border:1.5px solid #334155;border-radius:8px;background:#0f172a;color:#f1f5f9;font-size:.9rem;box-sizing:border-box;margin-bottom:.75rem;outline:none;letter-spacing:.2em;">
      <input type="password" id="resetNewPass" placeholder="New Password (min 8 chars)"
             style="width:100%;padding:.65rem .85rem;border:1.5px solid #334155;border-radius:8px;background:#0f172a;color:#f1f5f9;font-size:.9rem;box-sizing:border-box;margin-bottom:.75rem;outline:none;">
      <input type="password" id="resetConfPass" placeholder="Confirm New Password"
             style="width:100%;padding:.65rem .85rem;border:1.5px solid #334155;border-radius:8px;background:#0f172a;color:#f1f5f9;font-size:.9rem;box-sizing:border-box;margin-bottom:.75rem;outline:none;">
      <div id="resetMsg" style="font-size:0.82rem;margin-bottom:.75rem;"></div>
      <div style="display:flex;gap:.75rem;">
        <button onclick="document.getElementById('forgotStep2').style.display='none';document.getElementById('forgotStep1').style.display='block';" style="flex:1;padding:.6rem;background:transparent;border:1px solid #475569;border-radius:8px;color:#94a3b8;cursor:pointer;">Back</button>
        <button id="resetSaveBtn" style="flex:2;padding:.6rem;background:#16a34a;border:none;border-radius:8px;color:#fff;font-weight:600;cursor:pointer;">Reset Password</button>
      </div>
    </div>
  </div>
</div>


</html>
