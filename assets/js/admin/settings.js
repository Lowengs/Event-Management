/**
 * settings.js — Admin Settings Handler
 */

function showToast(msg, type) {
    const c = document.getElementById('toastContainer');
    if (!c) return;
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('changePasswordForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('changePwBtn');
            const newPw = document.getElementById('newPassword').value;
            const confirmPw = document.getElementById('confirmPassword').value;

            if (newPw !== confirmPw) {
                showToast('New passwords do not match.', 'error');
                return;
            }

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Updating…';
            }

            const fd = new FormData(form);
            try {
                const res = await fetch('../../config/API/endpoints/index.php?action=change_admin_password', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    form.reset();
                }
            } catch (err) {
                showToast('Network error. Please try again.', 'error');
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<ion-icon name="key-outline"></ion-icon> Update Password';
            }
        });
    }
});
