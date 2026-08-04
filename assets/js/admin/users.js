/**
 * users.js — Admin User Management JavaScript Handler
 */

function switchTab(tab) {
    window.location.href = 'users.php?tab=' + tab;
}

// ── View User Details Modal ──────────────────────────────────────────
function viewUserAccount(user) {
    const body = document.getElementById('viewUserBody');
    if (!body) return;
    let extraHtml = '';
    if (user.student_id) extraHtml += `<p><strong>Student ID:</strong> ${user.student_id}</p>`;
    if (user.course) extraHtml += `<p><strong>Course:</strong> ${user.course}</p>`;
    if (user.year_level) extraHtml += `<p><strong>Year Level:</strong> ${user.year_level}</p>`;
    if (user.section) extraHtml += `<p><strong>Section:</strong> ${user.section}</p>`;

    body.innerHTML = `
        <div style="background:#f8fafc;padding:16px;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:16px;">
            <h4 style="font-size:1.1rem;margin-bottom:8px;color:#0f172a;">${escHtml(user.name)}</h4>
            <span class="badge badge-purple">${escHtml(user.role)}</span>
            <span class="badge ${user.status === 'active' ? 'badge-success' : 'badge-danger'}">${escHtml(user.status)}</span>
        </div>
        <div style="font-size:0.9rem;color:#334155;">
            <p><strong>Account ID:</strong> #${user.id}</p>
            <p><strong>Email / Username:</strong> ${escHtml(user.email || user.extra || '—')}</p>
            ${extraHtml}
        </div>
    `;
    const modal = document.getElementById('viewUserModal');
    if (modal) modal.classList.add('open');
}

function closeViewUserModal() {
    const modal = document.getElementById('viewUserModal');
    if (modal) modal.classList.remove('open');
}

// ── Reset Password Modal ─────────────────────────────────────────────
function openResetPasswordModal(user) {
    const idEl = document.getElementById('resetUserId');
    const heading = document.getElementById('resetUserHeading');
    const form = document.getElementById('resetPasswordForm');
    const modal = document.getElementById('resetPasswordModal');

    if (idEl) idEl.value = user.id;
    if (heading) heading.innerHTML = `Resetting password for <strong>${escHtml(user.name)}</strong> (${escHtml(user.email || user.role)})`;
    if (form) form.reset();
    if (modal) modal.classList.add('open');
}

function closeResetPasswordModal() {
    const modal = document.getElementById('resetPasswordModal');
    if (modal) modal.classList.remove('open');
}

// ── Suspend Status Update ─────────────────────────────────────────────
async function updateUserStatus(id, tab, status) {
    if (!confirm('Are you sure you want to SUSPEND this user account?')) return;
    const fd = new FormData();
    fd.append('user_id', id);
    fd.append('user_tab', tab);
    fd.append('status', status);
    try {
        const res = await fetch('../../config/API/endpoints/index.php?action=update_user_status', { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 800);
    } catch (err) {
        showToast('Network error.', 'error');
    }
}

async function deleteUserAccount(id, role) {
    if (!confirm('Delete this account permanently? This cannot be undone.')) return;
    try {
        const res = await fetch('../../config/API/endpoints/index.php?action=delete_user', {
            method: 'POST',
            body: new URLSearchParams({ user_id: id, role: role })
        });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 600);
    } catch (err) {
        showToast('Network error.', 'error');
    }
}

function showToast(msg, type) {
    const c = document.getElementById('toastContainer');
    if (!c) return;
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

function escHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

window.addEventListener('DOMContentLoaded', () => {
    const viewModal = document.getElementById('viewUserModal');
    if (viewModal) {
        viewModal.addEventListener('click', function(e) {
            if (e.target === this) closeViewUserModal();
        });
    }

    const resetModal = document.getElementById('resetPasswordModal');
    if (resetModal) {
        resetModal.addEventListener('click', function(e) {
            if (e.target === this) closeResetPasswordModal();
        });
    }

    const resetForm = document.getElementById('resetPasswordForm');
    if (resetForm) {
        resetForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('confirmResetBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Resetting…';
            }

            const fd = new FormData(resetForm);
            try {
                const res = await fetch('../../config/API/endpoints/index.php?action=reset_user_password', { method: 'POST', body: fd });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    closeResetPasswordModal();
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
