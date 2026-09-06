/**
 * NAAP System - OSA API Loader
 */
document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname;

    // ── OSA DASHBOARD ──────────────────────────────────────────────
    if (path.includes('dashboard_final.php')) {
        fetch('../../config/API/endpoints/index.php?action=get_osa_dashboard')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const st = data.stats;
                const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                set('osaTotalStudents', st.total_students?.toLocaleString() ?? '0');
                set('osaActiveOrgs', st.active_orgs?.toLocaleString() ?? '0');
                set('osaUpcomingEvents', st.upcoming_events?.toLocaleString() ?? '0');
                set('osaAvgAttendance', (st.avg_attendance ?? '0%'));
                const unread = parseInt(st.unread_count ?? 0, 10);
                const badge = document.getElementById('osaUnreadBadge');
                if (badge) {
                    if (unread > 0) {
                        badge.textContent = unread > 99 ? '99+' : unread;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(() => {});
    }

    // ── OSA STUDENTS MANAGEMENT ─────────────────────────────────────
    if (path.includes('students.php')) {
        fetch('../../config/API/endpoints/index.php?action=get_osa_students')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const st = data.stats || {};
                const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                
                // 5 Verification Monitoring Cards
                set('osaTotalStudents2', (st.total ?? 0).toLocaleString());
                set('osaPendingAi', (st.pending_ai ?? 0).toLocaleString());
                set('osaVerifiedStudents', (st.verified ?? 0).toLocaleString());
                set('osaFailedVerification', (st.failed ?? 0).toLocaleString());
                set('osaManualReview', (st.manual_review ?? 0).toLocaleString());

                set('studentsTotalBadge', (st.total ?? 0).toLocaleString());

                window.allStudentsData = data.students || [];
                renderStudents(window.allStudentsData);
                attachStudentFilters();
            })
            .catch(err => console.error('Failed to load OSA students:', err));
    }

    function renderStudents(students) {
        const tbody = document.getElementById('studentsTableBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!students.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;color:#64748b;font-weight:600;">No students found matching your criteria.</td></tr>';
            const countEl = document.getElementById('studentsCountText');
            if (countEl) countEl.textContent = '0';
            return;
        }

        const esc = str => String(str || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');

        students.forEach((s, idx) => {
            const name = [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ') || 'Student';
            
            // Verification Status & AI Evaluation Consistency
            const vsRaw = (s.verification_status || '').toLowerCase();
            const stRaw = (s.status || s.Status || 'pending').toLowerCase();
            const score = (!isNaN(parseInt(s.ai_verification_score, 10)) && s.ai_verification_score !== null) ? parseInt(s.ai_verification_score, 10) : 100;
            const detailsStr = (typeof s.ai_verification_details === 'string' ? s.ai_verification_details : JSON.stringify(s.ai_verification_details || '')).toLowerCase();

            const isExplicitApproved = (vsRaw === 'ai_verified' || vsRaw === 'approved' || vsRaw === 'verified');
            const isRejected = (vsRaw === 'rejected' || vsRaw === 'failed');
            const isNeedsReview = (vsRaw === 'needs_org_review' || vsRaw === 'manual_review' || vsRaw === 'flagged');
            const isApproved = isExplicitApproved || (stRaw === 'active' && !isRejected && !isNeedsReview);
            const isManuallyApproved = isApproved && (detailsStr.includes('manual') || detailsStr.includes('pending organization'));

            let verifLabel = 'PENDING REVIEW';
            let verifCls = 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;';
            let verifIcon = 'time-outline';

            if (isManuallyApproved) {
                verifLabel = 'MANUALLY VERIFIED';
                verifCls = 'background:#dbeafe;color:#1e40af;border:1px solid #bfdbfe;';
                verifIcon = 'checkmark-done-circle-outline';
            } else if (isApproved) {
                verifLabel = `AI VERIFIED (${score}%)`;
                verifCls = 'background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;';
                verifIcon = 'checkmark-circle-outline';
            } else if (isRejected) {
                verifLabel = 'FAILED / REJECTED';
                verifCls = 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;';
                verifIcon = 'close-circle-outline';
            } else if (isNeedsReview) {
                verifLabel = 'MANUAL REVIEW';
                verifCls = 'background:#ffedd5;color:#c2410c;border:1px solid #fed7aa;';
                verifIcon = 'alert-circle-outline';
            }

            // Cross-Portal Status Consistency:
            const isPending = !isApproved && (vsRaw === 'pending' || isNeedsReview || stRaw === 'pending');
            const displayStatus = isPending ? 'Pending' : (stRaw === 'active' ? 'Active' : (stRaw.charAt(0).toUpperCase() + stRaw.slice(1)));
            const statusClass = displayStatus === 'Active' ? 'active-badge' : 'pending-badge';

            const d = s.created_at ? new Date(s.created_at) : null;
            const joinDate = d ? d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';

            // Combined Course & Year-Section
            const courseVal = s.course || '—';
            let yrSec = '';
            if (s.year_level && s.section) {
                const secText = s.section.toString().toLowerCase().includes('section') ? s.section : `Section ${s.section}`;
                yrSec = `${s.year_level} - ${secText}`;
            } else if (s.year_level) {
                yrSec = s.year_level;
            } else if (s.section) {
                yrSec = `Section ${s.section}`;
            } else {
                yrSec = '—';
            }

            tbody.innerHTML += `
            <tr>
                <td>
                    <div class="student-name-cell">
                        <div>
                            <div class="student-name" style="font-weight:700;color:#0f172a;">${esc(name)}</div>
                            <div class="student-id" style="color:#475569;font-weight:600;">${esc(s.student_id ?? 'N/A')}</div>
                        </div>
                    </div>
                </td>
                <td style="color:#0f172a;font-weight:500;">${esc(s.Email ?? 'N/A')}</td>
                <td style="color:#0f172a;">
                    <div style="font-weight:700;color:#0f172a;font-size:13.5px;">${esc(courseVal)}</div>
                    <div style="font-size:12px;color:#475569;font-weight:600;margin-top:2px;">${esc(yrSec)}</div>
                </td>
                <td style="color:#0f172a;font-weight:600;">${esc(s.OrgName ?? 'None')}</td>
                <td style="color:#334155;">${joinDate}</td>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800;letter-spacing:0.3px;${verifCls}">
                        <ion-icon name="${verifIcon}"></ion-icon> ${verifLabel}
                    </span>
                </td>
                <td><span class="status-badge ${statusClass}">${displayStatus}</span></td>
                <td>
                    <button class="view-btn" title="View Details" style="background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:6px;transition:0.2s;" 
                        onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'"
                        onclick="openStudentModal(window.currentFilteredStudents ? window.currentFilteredStudents[${idx}] : window.allStudentsData[${idx}])">
                        <ion-icon name="eye-outline" style="font-size:16px;"></ion-icon> View
                    </button>
                </td>
            </tr>`;
        });

        const countEl = document.getElementById('studentsCountText');
        if (countEl) countEl.textContent = students.length;
    }

    function attachStudentFilters() {
        const sSearch = document.getElementById('stuSearch');
        const sCourse = document.getElementById('stuCourse');
        const sYear = document.getElementById('stuYear');
        const sStatus = document.getElementById('stuStatus');
        const sVerif = document.getElementById('stuVerif');

        function filterData() {
            if (!window.allStudentsData) return;
            const q = sSearch ? sSearch.value.toLowerCase().trim() : '';
            const c = sCourse ? sCourse.value.toLowerCase() : 'all';
            const y = sYear ? sYear.value : 'all';
            const st = sStatus ? sStatus.value.toLowerCase() : 'all';
            const vf = sVerif ? sVerif.value.toLowerCase() : 'all';

            const filtered = window.allStudentsData.filter(s => {
                const name = [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ').toLowerCase();
                const id = (s.student_id || '').toLowerCase();
                const email = (s.Email || '').toLowerCase();
                const course = (s.course || '').toLowerCase();
                const year = String(s.year_level || '').trim();
                
                const vsRaw = (s.verification_status || '').toLowerCase();
                const stRaw = (s.status || s.Status || 'pending').toLowerCase();
                const isRejected = (vsRaw === 'rejected' || vsRaw === 'failed');
                const isNeedsReview = (vsRaw === 'needs_org_review' || vsRaw === 'manual_review' || vsRaw === 'flagged');
                const isApproved = (vsRaw === 'ai_verified' || vsRaw === 'approved' || vsRaw === 'verified') || (stRaw === 'active' && !isRejected && !isNeedsReview);
                const isPending = !isApproved && !isRejected && !isNeedsReview;
                const computedStatus = isPending ? 'pending' : (stRaw === 'active' ? 'active' : 'inactive');

                const matchSearch = !q || name.includes(q) || id.includes(q) || email.includes(q);
                const matchCourse = c === 'all' || course === c;
                const matchYear = y === 'all' || year === y || year.includes(y);
                const matchStatus = st === 'all' || computedStatus === st;
                
                let matchVerif = true;
                if (vf !== 'all') {
                    if (vf === 'ai_verified') matchVerif = isApproved;
                    else if (vf === 'pending') matchVerif = isPending;
                    else if (vf === 'needs_org_review') matchVerif = isNeedsReview;
                    else if (vf === 'rejected') matchVerif = isRejected;
                }

                return matchSearch && matchCourse && matchYear && matchStatus && matchVerif;
            });

            window.currentFilteredStudents = filtered;
            renderStudents(filtered);
        }

        if (sSearch) sSearch.addEventListener('input', filterData);
        if (sCourse) sCourse.addEventListener('change', filterData);
        if (sYear) sYear.addEventListener('change', filterData);
        if (sStatus) sStatus.addEventListener('change', filterData);
        if (sVerif) sVerif.addEventListener('change', filterData);
    }

    // ── OSA EVENTS ──────────────────────────────────────────────────
    if (path.includes('app/osa/events.php')) {
        fetch('../../config/API/endpoints/index.php?action=get_osa_events')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                set('osaEventsTotal', data.stats.total ?? 0);
                set('osaEventsUpcoming', data.stats.upcoming ?? 0);
                set('osaEventsOngoing', data.stats.ongoing ?? 0);
                set('osaEventsCompleted', data.stats.completed ?? 0);
                const tbody = document.getElementById('osaEventsTableBody');
                if (tbody && data.events.length) {
                    tbody.innerHTML = '';
                    data.events.forEach(ev => {
                        const d = ev.EventDateTime ? new Date(ev.EventDateTime) : null;
                        const dtStr = d ? d.toLocaleDateString() : 'N/A';
                        const status = (ev.EventStatus || 'scheduled').toLowerCase();
                        tbody.innerHTML += `
                        <tr>
                            <td>${ev.EventName}</td>
                            <td>${ev.OrgName ?? 'N/A'}</td>
                            <td>${dtStr}</td>
                            <td>${ev.EventLocation ?? 'TBA'}</td>
                            <td><span class="status-badge ${status}-badge">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
                        </tr>`;
                    });
                }
            })
            .catch(() => {});
    }

    // ── LOGOUT CONFIRMATION ─────────────────────────────────────────
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href*="logout"]');
        if (!link || link.dataset.confirmed === 'true') return;
        e.preventDefault();
        const targetUrl = link.href;
        if (typeof showLogoutConfirmModal === 'function') {
            showLogoutConfirmModal(targetUrl);
        } else if (typeof showConfirmModal === 'function') {
            showConfirmModal('Are you sure you want to log out?', () => { window.location.href = targetUrl; }, 'Confirm Logout', 'danger');
        } else {
            window.location.href = targetUrl;
        }
    });
});