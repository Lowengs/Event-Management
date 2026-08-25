<?php
session_start();

// Redirect logged-in users to their respective dashboards
if (!empty($_SESSION['student_id'])) {
    header('Location: profile-dashboard.php');
    exit;
} elseif (!empty($_SESSION['admin_id'])) {
    header('Location: ../admin/dashboard.php');
    exit;
} elseif (!empty($_SESSION['osa_id'])) {
    header('Location: ../osa/dashboard_final.php');
    exit;
} elseif (!empty($_SESSION['org_id'])) {
    header('Location: ../organization/dashboard_org.php');
    exit;
}

$rememberedEmail = htmlspecialchars($_COOKIE['student_remember_email'] ?? '');
$isRemembered    = !empty($_COOKIE['student_remember']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Login</title>
    <link rel="stylesheet" href="../../assets/css/student/login.css?v=<?= time() ?>">
    <link rel="icon" href="../../assets/img/philsca.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>
    <a href="../../index.php" class="back-link" id="backToDashboard">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Back to Index
    </a>

    <main>
        <section class="hero">
            <div class="hero__intro">
                <p class="eyebrow">Welcome back</p>
                <h1>Smarter Event Participation Starts Here.</h1>
                <p class="subtitle">
                    Sign in to access campus events, monitor your attendance, and stay engaged with university activities. Our system helps students and the Office of Student Affairs track participation and improve event experiences.
                </p>
                <div class="hero__stats">
                    <div>
                        <strong>10+</strong>
                        <span>New events Monthly</span>
                    </div>
                </div>
            </div>

            <div class="hero__card">
                <h2>Student login</h2>
                <p class="card-subtitle">Enter your credentials to continue.</p>

                <form class="login-form" id="loginForm" novalidate>
                    <label for="loginEmail">
                        <span>Email address</span>
                        <input type="email" id="loginEmail" name="email"
                               placeholder="yourname@email.com"
                               value="<?= $rememberedEmail ?>"
                               autocomplete="email" required>
                        <span class="field-error" id="emailError"></span>
                    </label>

                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        <label for="loginPassword" style="font-size:0.88rem;color:#cbd5e1;font-weight:500;margin:0;">Password</label>
                        <div class="pw-input-wrap">
                            <input type="password" id="loginPassword" name="password"
                                   placeholder="yourpassword"
                                   autocomplete="current-password" required>
                            <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('loginPassword', this); return false;" aria-label="Toggle password visibility">
                                <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                            </button>
                        </div>
                        <span class="field-error" id="passwordError"></span>
                    </div>

                    <div class="form-actions">
                        <label class="remember" for="rememberMe">
                            <input type="checkbox" id="rememberMe" name="remember"
                                   <?= $isRemembered ? 'checked' : '' ?>>
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot" id="openForgotModal">Forgot password?</a>
                    </div>

                    <button type="submit" class="primary" id="loginBtn">Sign in</button>
                </form>

                <p class="form-footer">
                    Need an account? <a href="register.php">Register Now</a>
                </p>
            </div>
        </section>
    </main>

    
    <div id="forgotModal" role="dialog" aria-modal="true" aria-labelledby="fpTitle">
        <div class="fp-card">
            <button class="fp-close" id="closeForgotModal" onclick="document.getElementById('forgotModal').classList.remove('active')" aria-label="Close" style="position:absolute;top:16px;right:20px;background:none;border:none;color:#94a3b8;font-size:28px;cursor:pointer;z-index:9999;">&times;</button>

            <!-- ── PANEL 1: Request OTP ───────────────────────────── -->
            <div class="fp-panel active" id="fpPanel1">
                <h3 id="fpTitle">Forgot Password?</h3>
                <p class="fp-sub">Enter the email address registered to your student account, and we'll send you an automated 6-digit verification code to reset your password.</p>

                <div class="fp-field">
                    <label for="fpEmail">Email Address</label>
                    <input type="email" id="fpEmail" placeholder="e.g. student@school.edu" autocomplete="email" required>
                    <span class="field-error" id="fpEmailError"></span>
                </div>

                <button class="fp-btn" id="fpSendBtn" type="button">Send Verification Code</button>
            </div>

            
            <div class="fp-panel" id="fpPanel2">
                <h3>Enter the code</h3>
                <p class="fp-sub" id="fpOtpHint">We sent a 6-digit code to <strong id="fpEmailDisplay"></strong>. It expires in 15 minutes.</p>

                <div class="otp-row" id="otpRow">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="otp1" aria-label="Digit 1">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="otp2" aria-label="Digit 2">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="otp3" aria-label="Digit 3">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="otp4" aria-label="Digit 4">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="otp5" aria-label="Digit 5">
                    <input class="otp-box" type="text" inputmode="numeric" maxlength="1" id="otp6" aria-label="Digit 6">
                </div>
                <span class="field-error" id="fpOtpError" style="text-align:center;display:block;margin-bottom:0.5rem;"></span>

                <div class="resend-row">
                    Didn't receive the code?
                    <button id="resendBtn">Resend</button>
                    <span id="resendTimer"></span>
                </div>

                <button class="fp-btn" id="fpVerifyBtn">Verify Code</button>
            </div>

            <!-- ── PANEL 3: Set New Password ───────────────────── -->
            <div class="fp-panel" id="fpPanel3">
                <h3>Set a new password</h3>
                <p class="fp-sub">Choose a strong password with at least 8 characters.</p>

                <div class="fp-field">
                    <label for="fpNewPass">New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="fpNewPass" placeholder="Create a strong password" autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('fpNewPass', this); return false;" aria-label="Toggle password visibility">
                            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <span class="strength-label" id="strengthLabel"></span>
                    <span class="field-error" id="fpNewPassError"></span>
                </div>
                <div class="fp-field">
                    <label for="fpConfirmPass">Confirm Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="fpConfirmPass" placeholder="Re-type password" autocomplete="new-password">
                        <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('fpConfirmPass', this); return false;" aria-label="Toggle password visibility">
                            <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                        </button>
                    </div>
                    <span class="field-error" id="fpConfirmPassError"></span>
                </div>

                <button class="fp-btn" id="fpResetBtn">Reset Password</button>
            </div>

            
            <div class="fp-panel" id="fpPanel4">
                <div class="fp-success-icon"><ion-icon name="checkmark-circle-outline" style="font-size:48px;color:#10b981;"></ion-icon></div>
                <p class="fp-success-title">Password Reset!</p>
                <p class="fp-success-text">Your password has been updated. You can now sign in with your new credentials.</p>
                <button class="fp-btn" id="fpDoneBtn" style="margin-top:1.5rem;">Go to Login</button>
            </div>
        </div>
    </div>

    
    <div id="toast" role="alert" aria-live="polite">
        <span class="toast-icon" id="toastIcon"></span>
        <span id="toastMsg"></span>
    </div>

    <script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
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
        }
    }
    </script>
    <script src="../../assets/js/student/login.js?v=<?= time() ?>"></script>
</body>
</html>
