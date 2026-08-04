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
                    window.location.href = data.redirect || 'dashboard.php';
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
});