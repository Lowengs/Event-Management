
(() => {
    'use strict';

    // ── Helpers ───────────────────────────────────────────────────
    const $ = id => document.getElementById(id);
    let toastTimer;

    function showToast(msg, type = 'info') {
        const icons = { success: '', error: '', info: 'ℹ' };
        const el = $('toast');
        el.className = `toast-${type}`;
        $('toastIcon').textContent = icons[type] ?? 'ℹ';
        $('toastMsg').textContent = msg;
        el.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 4500);
    }

    function setLoading(btn, loading, label = 'Sign in') {
        btn.disabled = loading;
        btn.innerHTML = loading ? `<span class="btn-spinner"></span> Please wait…` : label;
    }

    function clearFieldError(input, errorId) {
        input.classList.remove('input-error');
        $(errorId).textContent = '';
    }

    function setFieldError(input, errorId, msg) {
        input.classList.add('input-error');
        $(errorId).textContent = msg;
    }

    // ══════════════════════════════════════════════════════════════
    //  LOGIN FORM
    // ══════════════════════════════════════════════════════════════
    const loginForm = $('loginForm');

    loginForm.addEventListener('input', e => {
        if (e.target.id === 'loginEmail') clearFieldError(e.target, 'emailError');
        if (e.target.id === 'loginPassword') clearFieldError(e.target, 'passwordError');
    });

    loginForm.addEventListener('submit', async e => {
        e.preventDefault();
        const emailEl = $('loginEmail');
        const passwordEl = $('loginPassword');
        const rememberEl = $('rememberMe');
        let valid = true;

        if (!emailEl.value.trim()) {
            setFieldError(emailEl, 'emailError', 'Email address is required.');
            valid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
            setFieldError(emailEl, 'emailError', 'Please enter a valid email address.');
            valid = false;
        } else {
            clearFieldError(emailEl, 'emailError');
        }

        if (!passwordEl.value) {
            setFieldError(passwordEl, 'passwordError', 'Password is required.');
            valid = false;
        } else if (passwordEl.value.length < 6) {
            setFieldError(passwordEl, 'passwordError', 'Password must be at least 6 characters.');
            valid = false;
        } else {
            clearFieldError(passwordEl, 'passwordError');
        }
        if (!valid) return;

        const btn = $('loginBtn');
        setLoading(btn, true);

        const body = new FormData();
        body.append('email', emailEl.value.trim());
        body.append('password', passwordEl.value);
        body.append('remember', rememberEl.checked ? '1' : '0');

        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=student_login', { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => { window.location.href = data.redirect; }, 1200);
            } else if (data.locked) {
                // ── Account Locked: show countdown ───────────────
                setLoading(btn, false, 'Sign in');
                btn.disabled = true;
                let secs = data.cooldown_seconds || data.remaining || 180;
                const tick = () => {
                    const m = Math.floor(secs / 60);
                    const s = secs % 60;
                    btn.innerHTML = `<ion-icon name="timer-outline"></ion-icon> Locked — ${m}:${String(s).padStart(2, '0')} remaining`;
                    if (secs <= 0) {
                        btn.disabled = false;
                        btn.innerHTML = 'Sign in';
                        clearInterval(lockTimer);
                    }
                    secs--;
                };
                tick();
                const lockTimer = setInterval(tick, 1000);
                setFieldError(passwordEl, 'passwordError', data.message);
                showToast(data.message, 'error');
            } else {
                showToast(data.message, 'error');
                setLoading(btn, false, 'Sign in');
                if (data.message.toLowerCase().includes('email') ||
                    data.message.toLowerCase().includes('account')) {
                    setFieldError(emailEl, 'emailError', data.message);
                } else {
                    // show wrong-password message with attempts left
                    const msg = data.attempts_left !== undefined
                        ? `Incorrect password. ${data.attempts_left} attempt(s) left.`
                        : data.message;
                    setFieldError(passwordEl, 'passwordError', msg);
                }
            }
        } catch {
            showToast('Network error. Please check your connection.', 'error');
            setLoading(btn, false, 'Sign in');
        }
    });

    // ══════════════════════════════════════════════════════════════
    //  FORGOT PASSWORD MODAL  (3-step: email → OTP → new password)
    // ══════════════════════════════════════════════════════════════
    const modal = $('forgotModal');
    const steps = [$('fpStep1'), $('fpStep2'), $('fpStep3')];
    const panels = [$('fpPanel1'), $('fpPanel2'), $('fpPanel3'), $('fpPanel4')];
    let currentPanel = 0;
    let resendInterval;

    function showPanel(index) {
        panels.forEach((p, i) => { if (p) p.classList.toggle('active', i === index); });
        steps.forEach((s, i) => { if (s) s.classList.toggle('done', i <= Math.min(index, 2)); });
        currentPanel = index;
    }

    function openModal() { showPanel(0); modal.classList.add('open'); modal.classList.add('active'); if ($('fpEmail')) $('fpEmail').focus(); }
    function closeModal() { modal.classList.remove('open'); modal.classList.remove('active'); clearInterval(resendInterval); }

    if ($('openForgotModal')) $('openForgotModal').addEventListener('click', e => { e.preventDefault(); openModal(); });
    if ($('closeForgotModal')) $('closeForgotModal').addEventListener('click', closeModal);
    if (modal) modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // ── Panel 1: Send OTP ─────────────────────────────────────────
    $('fpSendBtn').addEventListener('click', async () => {
        const emailInput = $('fpEmail');
        const email = emailInput.value.trim();
        const errEl = $('fpEmailError');
        errEl.textContent = '';
        errEl.innerHTML = '';
        emailInput.classList.remove('input-error');

        if (!email) {
            emailInput.classList.add('input-error');
            errEl.textContent = 'Email address is required.';
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            emailInput.classList.add('input-error');
            errEl.textContent = 'Please enter a valid email address.';
            return;
        }

        const btn = $('fpSendBtn');
        setLoading(btn, true, 'Send Code');

        const body = new FormData();
        body.append('email', email);

        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=student_forgot_password', { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                $('fpEmailDisplay').textContent = email;
                showPanel(1);
                startResendTimer();
                showToast(data.message, 'success');
            } else {
                emailInput.classList.add('input-error');
                if (data.not_registered) {
                    // Show error + Register link
                    errEl.innerHTML = `No account found with that email. &nbsp;<a href="register.php" style="color:#38bdf8;font-weight:600;text-decoration:underline;">Register Now →</a>`;
                    showToast('No account found. Please register first.', 'error');
                } else {
                    errEl.textContent = data.message;
                    showToast(data.message, 'error');
                }
            }
        } catch {
            showToast('Network error. Please try again.', 'error');
        } finally {
            setLoading(btn, false, 'Send Code');
        }
    });

    // Clear register link when user retypes email
    $('fpEmail').addEventListener('input', () => {
        const errEl = $('fpEmailError');
        errEl.innerHTML = '';
        $('fpEmail').classList.remove('input-error');
    });


    // ── OTP digit inputs: auto-advance & backspace ────────────────
    const otpBoxes = Array.from({ length: 6 }, (_, i) => $(`otp${i + 1}`));

    otpBoxes.forEach((box, i) => {
        box.addEventListener('input', () => {
            box.value = box.value.replace(/\D/g, '').slice(0, 1);
            if (box.value && i < 5) otpBoxes[i + 1].focus();
        });
        box.addEventListener('keydown', e => {
            if (e.key === 'Backspace' && !box.value && i > 0) otpBoxes[i - 1].focus();
        });
        box.addEventListener('paste', e => {
            e.preventDefault();
            const pasted = (e.clipboardData.getData('text')).replace(/\D/g, '').slice(0, 6);
            [...pasted].forEach((ch, j) => { if (otpBoxes[j]) otpBoxes[j].value = ch; });
            otpBoxes[Math.min(pasted.length, 5)].focus();
        });
    });

    // ── Resend countdown timer ────────────────────────────────────
    function startResendTimer() {
        let secs = 60;
        const resendBtn = $('resendBtn');
        const timerEl = $('resendTimer');
        resendBtn.disabled = true;
        clearInterval(resendInterval);
        resendInterval = setInterval(() => {
            timerEl.textContent = ` (${secs}s)`;
            secs--;
            if (secs < 0) {
                clearInterval(resendInterval);
                resendBtn.disabled = false;
                timerEl.textContent = '';
            }
        }, 1000);
    }

    $('resendBtn').addEventListener('click', async () => {
        const email = $('fpEmail').value.trim();
        if (!email) return;
        const body = new FormData();
        body.append('email', email);
        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=student_forgot_password', { method: 'POST', body });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) startResendTimer();
        } catch {
            showToast('Network error.', 'error');
        }
    });

    // ── Panel 2: Verify OTP ───────────────────────────────────────
    $('fpVerifyBtn').addEventListener('click', async () => {
        const otp = otpBoxes.map(b => b.value).join('');
        $('fpOtpError').textContent = '';

        if (otp.length !== 6) { $('fpOtpError').textContent = 'Please enter all 6 digits.'; return; }

        const btn = $('fpVerifyBtn');
        setLoading(btn, true, 'Verify Code');

        const body = new FormData();
        body.append('action', 'verify_otp');
        body.append('otp', otp);

        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=student_verify_otp', { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                showPanel(2);
                showToast(data.message, 'success');
            } else {
                $('fpOtpError').textContent = data.message;
                otpBoxes.forEach(b => b.classList.add('input-error'));
                setTimeout(() => otpBoxes.forEach(b => b.classList.remove('input-error')), 1400);
            }
        } catch {
            showToast('Network error.', 'error');
        } finally {
            setLoading(btn, false, 'Verify Code');
        }
    });

    // ── Password strength meter ───────────────────────────────────
    function getStrength(pwd) {
        let score = 0;
        if (pwd.length >= 8) score++;
        if (pwd.length >= 12) score++;
        if (/[A-Z]/.test(pwd)) score++;
        if (/[0-9]/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;
        return score;
    }

    $('fpNewPass').addEventListener('input', () => {
        const pwd = $('fpNewPass').value;
        const score = getStrength(pwd);
        const fill = $('strengthFill');
        const label = $('strengthLabel');
        const levels = [
            { pct: '20%', color: '#ef4444', text: 'Very weak' },
            { pct: '40%', color: '#f97316', text: 'Weak' },
            { pct: '60%', color: '#eab308', text: 'Fair' },
            { pct: '80%', color: '#22c55e', text: 'Strong' },
            { pct: '100%', color: '#38bdf8', text: 'Very strong' },
        ];
        if (!pwd) { fill.style.width = '0'; label.textContent = ''; return; }
        const lv = levels[Math.max(0, score - 1)];
        fill.style.width = lv.pct;
        fill.style.background = lv.color;
        label.textContent = lv.text;
        label.style.color = lv.color;
    });

    // ── Panel 3: Reset password ───────────────────────────────────
    $('fpResetBtn').addEventListener('click', async () => {
        const newPass = $('fpNewPass').value;
        const confirmPass = $('fpConfirmPass').value;
        $('fpNewPassError').textContent = '';
        $('fpConfirmPassError').textContent = '';
        $('fpNewPass').classList.remove('input-error');
        $('fpConfirmPass').classList.remove('input-error');

        let valid = true;
        if (!newPass || newPass.length < 8) {
            $('fpNewPass').classList.add('input-error');
            $('fpNewPassError').textContent = 'Password must be at least 8 characters.';
            valid = false;
        }
        if (!confirmPass) {
            $('fpConfirmPass').classList.add('input-error');
            $('fpConfirmPassError').textContent = 'Please confirm your password.';
            valid = false;
        } else if (newPass !== confirmPass) {
            $('fpConfirmPass').classList.add('input-error');
            $('fpConfirmPassError').textContent = 'Passwords do not match.';
            valid = false;
        }
        if (!valid) return;

        const btn = $('fpResetBtn');
        setLoading(btn, true, 'Reset Password');

        const body = new FormData();
        body.append('action', 'reset_password');
        body.append('new_password', newPass);
        body.append('confirm_password', confirmPass);

        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=student_verify_otp', { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                showPanel(3);
                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'error');
            }
        } catch {
            showToast('Network error.', 'error');
        } finally {
            setLoading(btn, false, 'Reset Password');
        }
    });

    // ── Panel 4: Close on done ────────────────────────────────────
    $('fpDoneBtn').addEventListener('click', closeModal);

    // ── Global keyboard shortcut ──────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

})();


/* ── Extracted inline scripts ── */
document.addEventListener('DOMContentLoaded', function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('logout') === 'success') {
        const alertHtml = `
            <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
              
                <span>You have been successfully logged out.</span>
            </div>
        `;
        const form = document.querySelector('form');
        if (form) {
            form.insertAdjacentHTML('beforebegin', alertHtml);
        }

        // Clean up URL without reloading
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});