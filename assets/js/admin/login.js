/**
 * login.js — Admin Login JavaScript Handler
 */

window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('adminLoginForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn   = document.getElementById('loginBtn');
            const errEl = document.getElementById('loginError');
            const email = (document.getElementById('adminEmail') || {}).value || '';
            const pass  = (document.getElementById('adminPassword') || {}).value || '';

            if (errEl) {
                errEl.classList.remove('visible');
                errEl.textContent = '';
            }

            if (!email.trim() || !pass) {
                if (errEl) {
                    errEl.textContent = 'Please enter both email and password.';
                    errEl.classList.add('visible');
                }
                return;
            }

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Signing in…';
            }

            try {
                const fd = new FormData();
                fd.append('email', email.trim());
                fd.append('password', pass);

                const res = await fetch('../../config/API/endpoints/index.php?action=admin_login', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();

                if (data.success) {
                    const toast = document.getElementById('adminToast');
                    if (toast) {
                        toast.innerHTML = '<ion-icon name="checkmark-circle-outline" style="font-size:22px;margin-right:8px;vertical-align:middle;"></ion-icon> Login successful! Redirecting…';
                        toast.style.background = 'linear-gradient(135deg, #059669, #10b981)';
                        toast.style.color = '#ffffff';
                        toast.style.display = 'flex';
                        toast.style.alignItems = 'center';
                        toast.style.borderRadius = '14px';
                        toast.style.padding = '14px 22px';
                        toast.style.fontWeight = '600';
                        toast.style.boxShadow = '0 16px 40px rgba(16, 185, 129, 0.4)';
                    }
                    setTimeout(() => {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 800);
                } else {
                    if (errEl) {
                        errEl.textContent = data.message || 'Login failed.';
                        errEl.classList.add('visible');
                    }
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<ion-icon name="log-in-outline"></ion-icon> Sign In';
                    }
                }
            } catch (err) {
                if (errEl) {
                    errEl.textContent = 'Network error. Please try again.';
                    errEl.classList.add('visible');
                }
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<ion-icon name="log-in-outline"></ion-icon> Sign In';
                }
            }
        });
    }

    // Password visibility toggle
    document.querySelectorAll('.pw-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const input = targetId ? document.getElementById(targetId) : btn.parentElement.querySelector('input');
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = btn.querySelector('ion-icon');
            if (icon) {
                icon.setAttribute('name', isPassword ? 'eye-off-outline' : 'eye-outline');
            }
        });
    });
});