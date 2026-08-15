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

    const tabEl = document.getElementById('resetUserTab');

    if (form) form.reset();
    if (idEl) idEl.value = user.id;
    if (tabEl) {
        const currentTab = new URLSearchParams(window.location.search).get('tab') || 'students';
        tabEl.value = currentTab;
    }
    if (heading) heading.innerHTML = `Resetting password for <strong>${escHtml(user.name)}</strong> (${escHtml(user.email || user.role)})`;
    if (modal) modal.classList.add('open');
}

function closeResetPasswordModal() {
    const modal = document.getElementById('resetPasswordModal');
    if (modal) modal.classList.remove('open');
}

async function updateUserStatus(id, tab, status) {
    const isActivating = (status === 'active');
    const actionWord   = isActivating ? 'activate' : 'suspend';
    const modalType    = isActivating ? 'info' : 'warning';
    const modalTitle   = isActivating ? 'Activate Account' : 'Suspend Account';

    const doUpdate = async function() {
        const fd = new FormData();
        fd.append('user_id', id);
        fd.append('user_tab', tab);
        fd.append('status', status);
        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=update_user_status', { method: 'POST', body: fd });
            const data = await res.json();
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) setTimeout(() => location.reload(), 600);
        } catch (err) {
            showToast('Network error.', 'error');
        }
    };

    showConfirmModal(`Are you sure you want to <strong>${actionWord.toUpperCase()}</strong> this account?`, doUpdate, modalTitle, modalType);
}

async function deleteUserAccount(id, role) {
    const doDelete = async function() {
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
    };

    let msg = 'Are you sure you want to delete this account permanently? This action cannot be undone.';
    let title = 'Delete Account';

    if (role === 'organization') {
        title = 'Delete Organization & All Linked Data';
        msg = `<strong>Warning: Permanent Organization Deletion</strong><br><br>Deleting this organization will permanently remove all associated data connected to it across the system, including:<br>• <strong>Events & Attendance Records</strong><br>• <strong>Certificates & Templates</strong><br>• <strong>Uploaded Documents & Reports</strong><br>• <strong>Assessments (Pre/Post Tests)</strong><br>• <strong>Organization Members & Student Associations</strong><br>• <strong>Announcements & Messages</strong><br><br>Are you sure you want to permanently delete this organization?`;
    }

    showConfirmModal(msg, doDelete, title, 'danger');
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
