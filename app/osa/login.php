<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// ── Read "Remember Me" cookies to pre-fill fields ─────────────────
$rememberedOsaEmail   = htmlspecialchars($_COOKIE['osa_remember_email']   ?? '');
$rememberedOrgId      = (int)($_COOKIE['org_remember_id']                 ?? 0);
$rememberedOrgUsername= htmlspecialchars($_COOKIE['org_remember_username'] ?? '');

// ── Load organizations for dropdown ──────────────────────────────
$orgs = [];
if ($conn) {
    $r = $conn->query("SELECT OrgId, OrgName FROM `organization` ORDER BY OrgName ASC");
    if ($r) {
        while ($row = $r->fetch_assoc()) $orgs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP – OSA &amp; Organization Login</title>

    <!-- CSS -->
    <link rel="stylesheet" href="../../assets/css/admin/login.css">

    <!-- FONTS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="icon" href="../../assets/img/philsca.png">

    
</head>
<body>
    <main>
        <div class="login-container">
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
                        <input type="email" id="osaEmail" placeholder="OSA Email Address"
                               class="username-input" autocomplete="email"
                               value="<?= $rememberedOsaEmail ?>">
                        <span class="field-err" id="osaEmailErr"></span>

                        <label for="osaPassword">Password</label>
                        <input type="password" id="osaPassword" placeholder="Password"
                               class="password-input" autocomplete="current-password">
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
                        <select id="orgSelect" name="org_id">
                            <option value="" disabled <?= $rememberedOrgId ? '' : 'selected' ?>>Select Organization</option>
                            <?php foreach ($orgs as $o): ?>
                                <option value="<?= (int)$o['OrgId'] ?>"
                                    <?= ($rememberedOrgId === (int)$o['OrgId']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($o['OrgName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-err" id="orgSelectErr"></span>

                        <label for="orgUsername">Username</label>
                        <input type="text" id="orgUsername" placeholder="Officer Username"
                               class="username-input" autocomplete="username"
                               value="<?= $rememberedOrgUsername ?>">
                        <span class="field-err" id="orgUserErr"></span>

                        <label for="orgPassword">Password</label>
                        <input type="password" id="orgPassword" placeholder="Password"
                               class="password-input" autocomplete="current-password">
                        <span class="field-err" id="orgPassErr"></span>

                        <div class="flex-items">
                            <label><input type="checkbox" id="orgRemember" name="remember"
                                <?= $rememberedOrgId ? 'checked' : '' ?>> Remember me</label>
                            <a href="#">Forgot Password?</a>
                        </div>

                        <button type="submit" id="orgSbmBtn">Sign In</button>
                    </form>

                </div>
            </div>
        </div>
    </main>


    <div id="adminToast" role="alert" aria-live="polite">
        <span id="adminToastIcon"></span>
        <span id="adminToastMsg"></span>
    </div>

    <script src="../../assets/js/admin/login.js" defer></script>


</body>

<div id="forgotModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#1e2a3a;border:1px solid #334155;border-radius:16px;padding:2rem;width:92%;max-width:400px;font-family:'Inter',sans-serif;box-shadow:0 24px 64px rgba(0,0,0,0.5);">
    <div id="forgotStep1">
      <h3 style="margin:0 0 0.5rem;color:#f1f5f9;font-size:1.1rem;">Reset OSA Password</h3>
      <p style="margin:0 0 1.25rem;color:#94a3b8;font-size:0.85rem;">Enter your OSA email and we'll send a reset code.</p>
      <input type="email" id="forgotEmail" placeholder="OSA Email Address"
             style="width:100%;padding:.65rem .85rem;border:1.5px solid #334155;border-radius:8px;background:#0f172a;color:#f1f5f9;font-size:.9rem;box-sizing:border-box;margin-bottom:.75rem;outline:none;">
      <div id="forgotMsg" style="font-size:0.82rem;margin-bottom:.75rem;"></div>
      <div style="display:flex;gap:.75rem;">
        <button onclick="document.getElementById('forgotModal').style.display='none'" style="flex:1;padding:.6rem;background:transparent;border:1px solid #475569;border-radius:8px;color:#94a3b8;cursor:pointer;">Cancel</button>
        <button id="forgotSendBtn" style="flex:2;padding:.6rem;background:#2563eb;border:none;border-radius:8px;color:#fff;font-weight:600;cursor:pointer;">Send Reset Code</button>
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
