<?php
session_start();

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

                    <label for="loginPassword">
                        <span>Password</span>
                        <input type="password" id="loginPassword" name="password"
                               placeholder="yourpassword"
                               autocomplete="current-password" required>
                        <span class="field-error" id="passwordError"></span>
                    </label>

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

            
            <div class="fp-panel active" id="fpPanel1">
                <h3 id="fpTitle">Reset Password</h3>
                <p class="fp-sub" style="font-size:0.95rem;color:#e2e8f0;line-height:1.6;margin-top:12px;">
                    Please contact your school administrator or the Office of Student Affairs (OSA) to reset your account password.
                </p>
                <div style="background:#1e293b;border:1px solid #334155;border-radius:10px;padding:14px;margin:16px 0;text-align:left;">
                    <p style="margin:0 0 6px;font-size:0.8rem;color:#94a3b8;font-weight:600;text-transform:uppercase;">Contact Information</p>
                    <p style="margin:0;font-size:0.9rem;color:#f8fafc;font-weight:600;"><ion-icon name="mail-outline" style="vertical-align:middle;"></ion-icon> Email: <a href="mailto:naaporganization@gmail.com" style="color:#60a5fa;text-decoration:none;">naaporganization@gmail.com</a></p>
                </div>
                <button class="fp-btn" onclick="document.getElementById('forgotModal').classList.remove('active')">Close</button>
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

            
            <div class="fp-panel" id="fpPanel3">
                <h3>Set a new password</h3>
                <p class="fp-sub">Choose a strong password with at least 8 characters.</p>

                <div class="fp-field">
                    <label for="fpNewPass">New Password</label>
                    <input type="password" id="fpNewPass" placeholder="Create a strong password" autocomplete="new-password">
                    <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                    <span class="strength-label" id="strengthLabel"></span>
                    <span class="field-error" id="fpNewPassError"></span>
                </div>
                <div class="fp-field">
                    <label for="fpConfirmPass">Confirm Password</label>
                    <input type="password" id="fpConfirmPass" placeholder="Re-type password" autocomplete="new-password">
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

    <script src="../../assets/js/student/login.js" defer></script>
</body>

</html>
