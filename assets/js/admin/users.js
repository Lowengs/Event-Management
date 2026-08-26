/**
 * Admin Portal: User Management Script
 * Handles Tab Switching, View Modal (AI Score 100% / Pending), Status Toggle & Reset Password
 */

function switchTab(tab) {
    window.location.href = 'users.php?tab=' + tab;
}

function viewUserAccount(user) {
    const body = document.getElementById('viewUserBody');
    if (!body) return;

    const role = (user.role || 'User').toLowerCase();
    let detailsHtml = '';

    const st = (user.status || 'active').toLowerCase();
    const stBadgeClass = st === 'active' ? 'badge-success' : (st === 'suspended' || st === 'inactive' ? 'badge-danger' : 'badge-warning');

    if (role.includes('student')) {
        const photo = user.profile_photo ? (user.profile_photo.startsWith('http') || user.profile_photo.startsWith('../../') ? user.profile_photo : '../../' + user.profile_photo.replace(/^\/+/, '')) : '../../assets/img/philsca.png';
        const corDoc = user.cor_document ? (user.cor_document.startsWith('http') || user.cor_document.startsWith('../../') ? user.cor_document : '../../' + user.cor_document.replace(/^\/+/, '')) : null;
        const joinDate = user.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
        const officerTitle = user.Position || user.officer_role || (user.is_officer == 1 ? 'Officer' : 'Student Member');
        
        const rawVerif = (user.verification_status || 'pending').toLowerCase();
        const isVerified = (rawVerif === 'approved' || rawVerif === 'ai_verified');
        const isRejected = (rawVerif === 'rejected');
        
        const verifBadgeClass = isVerified ? 'badge-success' : (isRejected ? 'badge-danger' : 'badge-warning');
        const verifLabel = isVerified ? 'AI VERIFIED' : (isRejected ? 'REJECTED' : 'PENDING VERIFICATION');
        const scoreLabel = isVerified ? '100%' : (isRejected ? '0%' : (user.ai_verification_score !== undefined && user.ai_verification_score !== null ? user.ai_verification_score + '%' : 'Pending Review'));
        const accessNotice = isVerified 
            ? '<span style="color:#10b981;font-size:12px;font-weight:600;"><ion-icon name="checkmark-circle-outline"></ion-icon> Student has active access to student portal.</span>' 
            : '<span style="color:#f59e0b;font-size:12px;font-weight:600;"><ion-icon name="lock-closed-outline"></ion-icon> Student login is restricted until document verification is approved.</span>';

        detailsHtml = `
            <div style="display:flex;align-items:center;gap:16px;background:#f8fafc;padding:16px;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:18px;">
                <img src="${escHtml(photo)}" alt="Profile" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:3px solid #ffffff;box-shadow:0 2px 8px rgba(0,0,0,0.08);background:#fff;flex-shrink:0;">
                <div style="min-width:0;flex:1;">
                    <h4 style="font-size:1.15rem;font-weight:800;margin:0 0 2px;color:#0f172a;">${escHtml(user.name)}</h4>
                    <p style="font-size:0.85rem;color:#475569;margin:0 0 6px;font-weight:600;">@${escHtml(user.username || 'student')}</p>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <span class="badge ${stBadgeClass}">${escHtml(user.status || 'Active')}</span>
                        <span class="badge badge-purple">${escHtml(officerTitle)}</span>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:0.88rem;color:#0f172a;">
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Student ID</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.student_id || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Course / Program</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.course || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Year & Section</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.year_level || '—')} - Section ${escHtml(user.section || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Organization</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.OrgName || 'None')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;grid-column:1 / -1;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Email Address</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.Email || user.email || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Contact Number</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.phone || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Registration Date</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(joinDate)}</strong>
                </div>
                
                <!-- AI Verification Status & Score (100% for verified) -->
                <div style="background:#ffffff;padding:12px 14px;border:1px solid #e2e8f0;border-radius:10px;grid-column:1 / -1;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;margin-bottom:6px;">AI Verification Status & Evaluation Score</span>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="badge ${verifBadgeClass}">${escHtml(verifLabel)}</span>
                            <strong style="font-size:14px;color:#0f172a;">Evaluation Score: <span style="color:${isVerified ? '#10b981' : '#f59e0b'};font-weight:800;">${scoreLabel}</span></strong>
                        </div>
                    </div>
                    <div style="margin-top:6px;">
                        ${accessNotice}
                    </div>
                </div>

                <div style="background:#ffffff;padding:12px 14px;border:1px solid #e2e8f0;border-radius:10px;grid-column:1 / -1;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;margin-bottom:6px;">Certificate of Registration (COR) Document</span>
                    ${corDoc ? `
                        <div style="border:1px solid #cbd5e1;border-radius:10px;overflow:hidden;background:#f8fafc;">
                            ${corDoc.toLowerCase().endsWith('.pdf') ? `
                                <iframe src="${escHtml(corDoc)}" style="width:100%;height:350px;border:none;display:block;"></iframe>
                            ` : `
                                <img src="${escHtml(corDoc)}" alt="COR Preview" style="max-width:100%;max-height:350px;object-fit:contain;display:block;margin:0 auto;padding:8px;">
                            `}
                        </div>
                    ` : '<span style="color:#64748b;font-weight:600;font-size:13px;">No COR document uploaded</span>'}
                </div>
            </div>
        `;
    } else if (role.includes('organization')) {
        const pic = user.OrgPicture ? (user.OrgPicture.startsWith('http') || user.OrgPicture.startsWith('../../') ? user.OrgPicture : '../../' + user.OrgPicture.replace(/^\/+/, '')) : '../../assets/img/philsca.png';
        detailsHtml = `
            <div style="display:flex;align-items:center;gap:16px;background:#f8fafc;padding:16px;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:18px;">
                <img src="${escHtml(pic)}" alt="Logo" style="width:64px;height:64px;border-radius:14px;object-fit:cover;border:3px solid #ffffff;box-shadow:0 2px 8px rgba(0,0,0,0.08);background:#fff;flex-shrink:0;">
                <div style="min-width:0;flex:1;">
                    <h4 style="font-size:1.15rem;font-weight:800;margin:0 0 2px;color:#0f172a;">${escHtml(user.name || user.OrgName)}</h4>
                    <p style="font-size:0.85rem;color:#475569;margin:0 0 6px;font-weight:600;">@${escHtml(user.username || 'org')}</p>
                    <span class="badge ${stBadgeClass}">${escHtml(user.status || 'Active')}</span>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:0.88rem;color:#0f172a;">
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Organization ID</span>
                    <strong style="font-size:13.5px;color:#0f172a;">#${escHtml(user.OrgId || user.id)}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Adviser</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.Adviser || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;grid-column:1 / -1;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Login Email / Username</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.email || user.username || user.extra || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Total Events</span>
                    <strong style="font-size:16px;color:#2563eb;">${user.total_events ?? 0} Events</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Date Registered</span>
                    <strong style="font-size:13.5px;color:#0f172a;">${escHtml(user.DateRegistered || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;grid-column:1 / -1;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Description</span>
                    <p style="margin:2px 0 0;font-size:13px;color:#334155;">${escHtml(user.Description || 'No description provided.')}</p>
                </div>
            </div>
        `;
    } else {
        detailsHtml = `
            <div style="background:#f8fafc;padding:16px;border-radius:14px;border:1px solid #e2e8f0;margin-bottom:18px;">
                <h4 style="font-size:1.15rem;font-weight:800;margin:0 0 4px;color:#0f172a;">${escHtml(user.name)}</h4>
                <div style="display:flex;gap:6px;">
                    <span class="badge badge-purple">${escHtml(user.role || 'Staff')}</span>
                    <span class="badge ${stBadgeClass}">${escHtml(user.status || 'Active')}</span>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr;gap:12px;font-size:0.88rem;color:#0f172a;">
                <div style="background:#ffffff;padding:12px 16px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Account ID</span>
                    <strong style="font-size:14px;color:#0f172a;">#${escHtml(user.id || user.OsaId)}</strong>
                </div>
                <div style="background:#ffffff;padding:12px 16px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Email Address</span>
                    <strong style="font-size:14px;color:#0f172a;">${escHtml(user.email || '—')}</strong>
                </div>
                <div style="background:#ffffff;padding:12px 16px;border:1px solid #e2e8f0;border-radius:10px;">
                    <span style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;display:block;">Access Level</span>
                    <strong style="font-size:14px;color:#0f172a;">Full OSA Management Portal Access</strong>
                </div>
            </div>
        `;
    }

    body.innerHTML = detailsHtml;
    const modal = document.getElementById('viewUserModal');
    if (modal) modal.classList.add('open');
}

function closeViewUserModal() {
    const modal = document.getElementById('viewUserModal');
    if (modal) modal.classList.remove('open');
}

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
    const actionWord = isActivating ? 'activate' : 'suspend';
    const modalType = isActivating ? 'info' : 'warning';
    const modalTitle = isActivating ? 'Activate Account' : 'Suspend Account';

    const doUpdate = async function() {
        const fd = new FormData();
        fd.append('user_id', id);
        fd.append('user_tab', tab);
        fd.append('status', status);

        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=update_user_status', {
                method: 'POST',
                body: fd
            });
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
                const res = await fetch('../../config/API/endpoints/index.php?action=reset_user_password', {
                    method: 'POST',
                    body: fd
                });
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

    document.querySelectorAll('.pw-toggle-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
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