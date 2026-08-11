document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('.nav-item');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            const targetId = item.getAttribute('data-target');
            if (targetId) {
                e.preventDefault();
                switchTab(targetId);
            }
        });
    });

    document.querySelectorAll('.mobile-dash-nav').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const targetId = btn.getAttribute('data-target');
            if (targetId) {
                e.preventDefault();
                switchTab(targetId);
                const navMob = document.querySelector('.nav-mobile');
                if (navMob) navMob.classList.remove('active');
            }
        });
    });
});

function switchTab(targetId) {
    if (!targetId) return;
    // Update active state on nav links
    document.querySelectorAll('.nav-item, .mobile-dash-nav').forEach(nav => {
        if (nav.getAttribute('data-target') === targetId) {
            nav.classList.add('active');
        } else {
            nav.classList.remove('active');
        }
    });

    // Update active state on content sections
    document.querySelectorAll('.content-section').forEach(section => {
        if (section.id === targetId) {
            section.classList.add('active');
        } else {
            section.classList.remove('active');
        }
    });

    if (targetId === 'certificates-content' && typeof loadCerts === 'function') {
        loadCerts();
    }
    if (targetId === 'registrations-content' && typeof loadRegistrations === 'function') loadRegistrations(1);
}

let registrationPage = 1;
function escRegistration(value) { const d=document.createElement('div'); d.textContent=value||''; return d.innerHTML; }
async function loadRegistrations(page = 1) {
    const list = document.getElementById('registrationList');
    if (!list) return;
    registrationPage = page;
    list.innerHTML = '<p style="color:#94a3b8;padding:18px 0;">Loading registrations…</p>';
    const search = document.getElementById('registrationSearch')?.value || '';
    const status = document.getElementById('registrationStatusFilter')?.value || '';
    const url = `../../config/API/endpoints/index.php?action=get_student_registrations&page=${page}&per_page=3&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`;
    try {
        const res = await fetch(url); const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Unable to load registrations');
        if (!data.registrations.length) list.innerHTML = '<p style="color:#94a3b8;padding:18px 0;">No registrations found.</p>';
        else list.innerHTML = data.registrations.map(r => {
            const stLower = String(r.EventStatus||'scheduled').toLowerCase();
            const isOngoing = (stLower === 'ongoing');
            const badgeStyle = isOngoing 
                ? 'background:#10b981;color:#ffffff;border:1px solid #059669;box-shadow:0 0 10px rgba(16,185,129,0.4);font-weight:800;padding:4px 12px;border-radius:20px;font-size:0.75rem;' 
                : 'padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:700;';
            return `<div class="registration-card"><div class="reg-header"><div><h3 class="event-name">${escRegistration(r.EventName)}</h3><p class="event-org">${escRegistration(r.OrgName || 'NAAP')}</p><div class="event-date"><i class='bx bx-calendar'></i> ${escRegistration(r.EventDateTime || 'TBA')} ${r.EventLocation ? ` · <i class='bx bx-map'></i> ${escRegistration(r.EventLocation)}` : ''}</div></div><span class="status-badge ${stLower}" style="${badgeStyle}">${escRegistration(r.EventStatus || 'Scheduled')}</span></div></div>`;
        }).join('');
        if (data.registrations.length) {
            list.querySelectorAll('.registration-card').forEach((card, index) => {
                const r = data.registrations[index];
                const btnStyle = 'display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:8px;font-size:.82rem;font-weight:700;text-decoration:none;border:none;';
                const muted = (icon, label) => `<button disabled style="${btnStyle}background:rgba(255,255,255,0.05);color:#64748b;border:1px solid rgba(255,255,255,0.08);cursor:not-allowed;"><i class='bx ${icon}'></i> ${label}</button>`;
                const warning = (icon, label) => `<span style="${btnStyle}background:#fef3c7;color:#b45309;border:1px solid #fde68a;"><i class='bx ${icon}'></i> ${label}</span>`;
                const danger = (icon, label) => `<span style="${btnStyle}background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;"><i class='bx ${icon}'></i> ${label}</span>`;

                // Pre-Test Condition: Needs attendance check-in
                let pre = '';
                if (Number(r.pre_taken)) {
                    pre = `<span style="${btnStyle}background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(52,211,153,0.3);"><i class='bx bx-check-circle'></i> Pre-Test Taken</span>`;
                } else if (!Number(r.has_checkin)) {
                    pre = warning('bx-time-five', 'Attendance required first');
                } else if (!Number(r.pre_created)) {
                    pre = muted('bx-file-blank', 'Pre-Test not created');
                } else {
                    pre = `<a href="pre-test.php?event_id=${r.EventId}&type=pretest" style="${btnStyle}background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;box-shadow:0 4px 12px rgba(37,99,235,0.3);"><i class='bx bx-file'></i> Take Pre-Test</a>`;
                }

                // Post-Test Condition: Needs attendance check-in AND Pre-Test taken
                let post = '';
                if (Number(r.post_taken)) {
                    post = `<span style="${btnStyle}background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(52,211,153,0.3);"><i class='bx bx-check-circle'></i> Post-Test Taken</span> <a href="test_results.php?event_id=${r.EventId}&type=post" style="${btnStyle}background:linear-gradient(135deg,#0ea5e9,#4fd1c5);color:#fff;box-shadow:0 4px 12px rgba(14,165,233,0.3);"><i class='bx bx-brain'></i> AI Insight</a>`;
                } else if (!Number(r.has_checkin)) {
                    post = warning('bx-lock-alt', 'Attendance & Pre-Test required');
                } else if (!Number(r.pre_taken)) {
                    post = danger('bx-lock-alt', 'Pre-Test required first');
                } else if (!Number(r.post_created)) {
                    post = muted('bx-file-blank', 'Post-Test not created');
                } else {
                    post = `<a href="pre-test.php?event_id=${r.EventId}&type=posttest" style="${btnStyle}background:linear-gradient(135deg,#0284c7,#0d9488);color:#fff;box-shadow:0 4px 12px rgba(13,148,136,0.3);"><i class='bx bx-check-square'></i> Take Post-Test</a>`;
                }

                card.insertAdjacentHTML('beforeend', `<div class="reg-actions" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">${pre}${post}</div>`);
            });
        }
        const pager = document.getElementById('registrationPagination');
        if (pager) {
            pager.innerHTML = `
                <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-top:20px;padding:12px 18px;background:rgba(15,23,42,0.6);border:1px solid rgba(255,255,255,0.08);border-radius:14px;backdrop-filter:blur(8px);">
                    <button type="button" ${data.page <= 1 ? 'disabled' : ''} onclick="loadRegistrations(${data.page - 1})" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:${data.page <= 1 ? 'rgba(255,255,255,0.04)' : 'linear-gradient(135deg, #1e293b, #334155)'};color:${data.page <= 1 ? '#475569' : '#38bdf8'};border:1px solid ${data.page <= 1 ? 'rgba(255,255,255,0.05)' : 'rgba(56,189,248,0.3)'};border-radius:10px;font-weight:700;font-size:0.85rem;cursor:${data.page <= 1 ? 'not-allowed' : 'pointer'};transition:all 0.2s;">
                        <i class='bx bx-chevron-left' style="font-size:1.1rem;"></i> Previous
                    </button>
                    <span style="color:#e2e8f0;font-size:0.85rem;font-weight:700;padding:6px 14px;background:rgba(56,189,248,0.12);border:1px solid rgba(56,189,248,0.25);border-radius:20px;">
                        Page <strong style="color:#38bdf8;">${data.page}</strong> of <strong>${data.pages}</strong>
                    </span>
                    <button type="button" ${data.page >= data.pages ? 'disabled' : ''} onclick="loadRegistrations(${data.page + 1})" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:${data.page >= data.pages ? 'rgba(255,255,255,0.04)' : 'linear-gradient(135deg, #1e293b, #334155)'};color:${data.page >= data.pages ? '#475569' : '#38bdf8'};border:1px solid ${data.page >= data.pages ? 'rgba(255,255,255,0.05)' : 'rgba(56,189,248,0.3)'};border-radius:10px;font-weight:700;font-size:0.85rem;cursor:${data.page >= data.pages ? 'not-allowed' : 'pointer'};transition:all 0.2s;">
                        Next <i class='bx bx-chevron-right' style="font-size:1.1rem;"></i>
                    </button>
                </div>
            `;
        }
        const summary = document.getElementById('registrationSummary');
        if (summary) summary.innerHTML = `Showing <strong>${data.registrations.length}</strong> of <strong>${data.total}</strong> registrations | Page <strong>${data.page}</strong> of <strong>${data.pages}</strong>`;
    } catch (e) { list.innerHTML = `<p style="color:#fca5a5;padding:18px 0;">${escRegistration(e.message)}</p>`; const summary=document.getElementById('registrationSummary'); if(summary) summary.textContent='Unable to load registrations.'; }
}
window.loadRegistrations = loadRegistrations;
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('registrationSearchBtn')?.addEventListener('click', () => loadRegistrations(1));
    document.getElementById('registrationSearch')?.addEventListener('input', () => loadRegistrations(1));
    document.getElementById('registrationStatusFilter')?.addEventListener('change', () => loadRegistrations(1));
    if (document.getElementById('registrationList')) loadRegistrations(1);
});

let eventDetailsBodyOverflow = '';

        function openEventDetailsModal(data) {
            document.getElementById('detailsModalTitle').textContent = data.title || 'Event';
            document.getElementById('detailsModalOrg').textContent = data.org || 'NAAP';
            document.getElementById('detailsModalStatus').textContent = data.status || 'Upcoming';
            document.getElementById('detailsModalDate').textContent = data.date || 'TBA';
            document.getElementById('detailsModalTime').textContent = data.time || 'TBA';
            document.getElementById('detailsModalLocation').textContent = data.location || 'TBA';
            document.getElementById('detailsModalDescription').textContent = data.description || 'No event description available.';

            const modal = document.getElementById('eventDetailsModal');
            eventDetailsBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeEventDetailsModal() {
            const modal = document.getElementById('eventDetailsModal');
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = eventDetailsBodyOverflow;
        }

        document.getElementById('eventDetailsModal')?.addEventListener('click', function (e) {
            if (e.target === this) closeEventDetailsModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.getElementById('eventDetailsModal')?.classList.contains('show')) {
                closeEventDetailsModal();
            }
        });
