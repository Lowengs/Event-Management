/**
 * login.js — OSA & Organization Portal Login & Password Reset Handler
 */

function showToast(msg, isError = false) {
    const toast = document.getElementById('adminToast');
    const toastMsg = document.getElementById('adminToastMsg');
    const toastIcon = document.getElementById('adminToastIcon');

    if (toast && toastMsg) {
        toastMsg.textContent = msg;
        if (toastIcon) {
            toastIcon.textContent = isError ? '❌ ' : '✅ ';
        }
        toast.style.background = isError ? '#ef4444' : '#10b981';
        toast.style.color = '#ffffff';
        toast.style.display = 'flex';
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
            toast.style.display = 'none';
        }, 4000);
    } else {
        if (window.showAlertModal) {
            window.showAlertModal(msg, isError ? 'Error' : 'Success', isError ? 'error' : 'success');
        } else {
            alert(msg);
        }
    }
}

function openForgotModal() {
    const modal = document.getElementById('forgotModal');
    const step1 = document.getElementById('forgotStep1');
    const step2 = document.getElementById('forgotStep2');
    const msg = document.getElementById('forgotMsg');

    if (msg) msg.textContent = '';
    if (step1) step1.style.display = 'block';
    if (step2) step2.style.display = 'none';
    if (modal) modal.style.display = 'flex';
}

function closeForgotModal() {
    const modal = document.getElementById('forgotModal');
    if (modal) modal.style.display = 'none';
}

window.addEventListener('DOMContentLoaded', () => {
    // ── Tab Switching ──────────────────────────────────────────────────
    const btnOSA   = document.getElementById('btnOSA');
    const btnORG   = document.getElementById('btnORG');
    const titleOsa = document.getElementById('titleOsa');
    const titleOrg = document.getElementById('titleOrg');
    const formOSA  = document.getElementById('formOSA');
    const formORG  = document.getElementById('formORG');

    if (btnOSA && btnORG) {
        btnOSA.addEventListener('click', (e) => {
            e.preventDefault();
            btnOSA.classList.add('active');
            btnORG.classList.remove('active');

            if (titleOsa) titleOsa.classList.add('active');
            if (titleOrg) titleOrg.classList.remove('active');

            if (formOSA) formOSA.classList.add('active');
            if (formORG) formORG.classList.remove('active');
        });

        btnORG.addEventListener('click', (e) => {
            e.preventDefault();
            btnORG.classList.add('active');
            btnOSA.classList.remove('active');

            if (titleOrg) titleOrg.classList.add('active');
            if (titleOsa) titleOsa.classList.remove('active');

            if (formORG) formORG.classList.add('active');
            if (formOSA) formOSA.classList.remove('active');
        });
    }

    // ── Forgot Password Modal Triggers ─────────────────────────────────
    const osaForgotLink = document.getElementById('osaForgotLink');
    if (osaForgotLink) {
        osaForgotLink.addEventListener('click', (e) => {
            e.preventDefault();
            openForgotModal();
        });
    }

    const modal = document.getElementById('forgotModal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeForgotModal();
        });
    }

    // ── Forgot Password Step 1: Send Reset Code ─────────────────────────
    const forgotSendBtn = document.getElementById('forgotSendBtn');
    if (forgotSendBtn) {
        forgotSendBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            const emailInput = document.getElementById('forgotEmail');
            const forgotMsg  = document.getElementById('forgotMsg');
            const email      = emailInput ? emailInput.value.trim() : '';

            if (forgotMsg) {
                forgotMsg.textContent = '';
                forgotMsg.style.color = '#f87171';
            }

            if (!email) {
                if (forgotMsg) forgotMsg.textContent = 'Please enter your OSA email address.';
                return;
            }

            forgotSendBtn.disabled = true;
            forgotSendBtn.textContent = 'Sending…';

            try {
                const fd = new FormData();
                fd.append('action', 'send_code');
                fd.append('email', email);

                const res = await fetch('../../config/API/endpoints/index.php?action=osa_forgot_password', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    if (forgotMsg) {
                        forgotMsg.style.color = '#4ade80';
                        forgotMsg.textContent = data.message || 'Verification code sent!';
                    }
                    setTimeout(() => {
                        const step1 = document.getElementById('forgotStep1');
                        const step2 = document.getElementById('forgotStep2');
                        if (step1) step1.style.display = 'none';
                        if (step2) step2.style.display = 'block';
                    }, 1200);
                } else {
                    if (forgotMsg) forgotMsg.textContent = data.message || 'Failed to send reset code.';
                }
            } catch (err) {
                if (forgotMsg) forgotMsg.textContent = 'Network error. Please try again.';
            } finally {
                forgotSendBtn.disabled = false;
                forgotSendBtn.textContent = 'Send Reset Code';
            }
        });
    }

    // ── Forgot Password Step 2: Reset Password ──────────────────────────
    const resetSaveBtn = document.getElementById('resetSaveBtn');
    if (resetSaveBtn) {
        resetSaveBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            const email = (document.getElementById('forgotEmail') || {}).value || '';
            const pin   = (document.getElementById('resetPin') || {}).value || '';
            const newP  = (document.getElementById('resetNewPass') || {}).value || '';
            const confP = (document.getElementById('resetConfPass') || {}).value || '';
            const resetMsg = document.getElementById('resetMsg');

            if (resetMsg) {
                resetMsg.textContent = '';
                resetMsg.style.color = '#f87171';
            }

            if (!pin || !newP || !confP) {
                if (resetMsg) resetMsg.textContent = 'All fields are required.';
                return;
            }

            if (newP !== confP) {
                if (resetMsg) resetMsg.textContent = 'Passwords do not match.';
                return;
            }

            resetSaveBtn.disabled = true;
            resetSaveBtn.textContent = 'Resetting…';

            try {
                const fd = new FormData();
                fd.append('action', 'reset_password');
                fd.append('email', email.trim());
                fd.append('pin', pin.trim());
                fd.append('new_password', newP);

                const res = await fetch('../../config/API/endpoints/index.php?action=osa_forgot_password', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    if (resetMsg) {
                        resetMsg.style.color = '#4ade80';
                        resetMsg.textContent = data.message || 'Password reset successfully!';
                    }
                    showToast('Password reset successful. Please sign in.', false);
                    setTimeout(() => {
                        closeForgotModal();
                    }, 1500);
                } else {
                    if (resetMsg) resetMsg.textContent = data.message || 'Failed to reset password.';
                }
            } catch (err) {
                if (resetMsg) resetMsg.textContent = 'Network error. Please try again.';
            } finally {
                resetSaveBtn.disabled = false;
                resetSaveBtn.textContent = 'Reset Password';
            }
        });
    }

    // ── Form OSA Submit Handler ─────────────────────────────────────────
    if (formOSA) {
        formOSA.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email    = (document.getElementById('osaEmail') || {}).value || '';
            const pass     = (document.getElementById('osaPassword') || {}).value || '';
            const errEmail = document.getElementById('osaEmailErr');
            const errPass  = document.getElementById('osaPassErr');
            const btn      = document.getElementById('osaSbmBtn');

            if (errEmail) errEmail.textContent = '';
            if (errPass)  errPass.textContent = '';

            let valid = true;
            if (!email.trim()) {
                if (errEmail) errEmail.textContent = 'Email is required.';
                valid = false;
            }
            if (!pass) {
                if (errPass) errPass.textContent = 'Password is required.';
                valid = false;
            }
            if (!valid) return;

            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Signing in…';
            }

            try {
                const fd = new FormData();
                fd.append('email', email.trim());
                fd.append('password', pass);

                const res = await fetch('../../config/API/endpoints/index.php?action=osa_login', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Login successful! Redirecting…', false);
                    window.location.href = data.redirect || 'dashboard_final.php';
                } else {
                    showToast(data.message || 'Invalid credentials', true);
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Sign In';
                    }
                }
            } catch (err) {
                showToast('Network error. Please try again.', true);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Sign In';
                }
            }
        });
    }

    // ── Form ORG Submit Handler ─────────────────────────────────────────
    if (formORG) {
        formORG.addEventListener('submit', async (e) => {
            e.preventDefault();
            const orgId    = (document.getElementById('orgSelect') || {}).value || '';
            const username = (document.getElementById('orgUsername') || {}).value || '';
            const pass     = (document.getElementById('orgPassword') || {}).value || '';
            const errOrg   = document.getElementById('orgSelectErr');
            const errUser  = document.getElementById('orgUserErr');
            const errPass  = document.getElementById('orgPassErr');
            const btn      = document.getElementById('orgSbmBtn');

            if (errOrg)  errOrg.textContent = '';
            if (errUser) errUser.textContent = '';
            if (errPass) errPass.textContent = '';

            let valid = true;
            if (!orgId) {
                if (errOrg) errOrg.textContent = 'Please select an organization.';
                valid = false;
            }
            if (!username.trim()) {
                if (errUser) errUser.textContent = 'Username is required.';
                valid = false;
            }
            if (!pass) {
                if (errPass) errPass.textContent = 'Password is required.';
                valid = false;
            }
            if (!valid) return;

            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Signing in…';
            }

            try {
                const fd = new FormData();
                fd.append('org_id', orgId);
                fd.append('username', username.trim());
                fd.append('password', pass);

                const res = await fetch('../../config/API/endpoints/index.php?action=org_login', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    showToast('Login successful! Redirecting…', false);
                    window.location.href = data.redirect || '../organization/dashboard_org.php';
                } else {
                    showToast(data.message || 'Invalid organization credentials', true);
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Sign In';
                    }
                }
            } catch (err) {
                showToast('Network error. Please try again.', true);
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Sign In';
                }
            }
        });
    }
});
