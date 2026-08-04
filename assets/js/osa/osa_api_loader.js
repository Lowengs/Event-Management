document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname;

    // ── OSA Dashboard ─────────────────────────────────────────────────
    if (path.includes('dashboard_final.php')) {
        fetch('../../config/API/endpoints/index.php?action=get_osa_dashboard')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const st = data.stats;
                const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                set('osaTotalStudents',  st.total_students?.toLocaleString() ?? '0');
                set('osaActiveOrgs',     st.active_orgs?.toLocaleString() ?? '0');
                set('osaUpcomingEvents', st.upcoming_events?.toLocaleString() ?? '0');
                set('osaAvgAttendance',  (st.avg_attendance ?? '0%'));
            });
    }

    // ── OSA Students ──────────────────────────────────────────────────
    if (path.includes('students.php')) {
        fetch('../../config/API/endpoints/index.php?action=get_osa_students')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const st = data.stats;
                const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                set('osaTotalStudents2',   st.total?.toLocaleString() ?? '0');
                set('osaIlas',             st.ilas ?? '0');
                set('osaIcs',              st.ics ?? '0');
                set('osaInet',             st.inet ?? '0');
                set('studentsTotalBadge',  st.total?.toLocaleString() ?? '0');

                // Save full data for filtering
                window.allStudentsData = data.students || [];
                renderStudents(window.allStudentsData);
                attachStudentFilters();
            });
    }

    function renderStudents(students) {
        const tbody = document.getElementById('studentsTableBody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        if (!students.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;">No students found matching your criteria.</td></tr>';
            const countEl = document.getElementById('studentsCountText');
            if (countEl) countEl.textContent = '0';
            return;
        }

        students.forEach(s => {
            const name = [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ');
            const initials = ((s.first_name?.[0] ?? '') + (s.last_name?.[0] ?? '')).toUpperCase();
            const status = (s.status || s.Status || 'pending').toLowerCase();
            const statusClass = status === 'active' ? 'active-badge' : 'pending-badge';
            const d = s.created_at ? new Date(s.created_at) : null;
            const joinDate = d ? d.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}) : 'N/A';

            // Escape for modal params
            const esc = (str) => String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');

            tbody.innerHTML += `
            <tr>
                <td>
                    <div class="student-name-cell">
                        <div>
                            <div class="student-name">${esc(name)}</div>
                            <div class="student-id">${esc(s.student_id ?? 'N/A')}</div>
                        </div>
                    </div>
                </td>
                <td>${esc(s.Email ?? 'N/A')}</td>
                <td>${esc(s.course ?? 'N/A')}</td>
                <td>${esc(s.year_level ?? 'N/A')}-${esc(s.section ?? 'N/A')}</td>
                <td>${esc(s.OrgName ?? 'None')}</td>
                <td>${joinDate}</td>
                <td><span class="status-badge ${statusClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></td>
                <td>
                    <button class="view-btn" title="View Details" style="background:#e8f4ff; color:#0071e3; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; transition:0.2s;" 
                        onmouseover="this.style.background='#d0e8ff'" onmouseout="this.style.background='#e8f4ff'"
                        onclick="openStudentModal('${esc(s.student_id ?? '')}','${esc(name)}','${esc(s.course ?? '')}','${esc(s.year_level ?? '')}','${esc(s.section ?? '')}','${esc(s.Email ?? '')}','${esc(s.phone ?? '')}','${esc(s.OrgName ?? '')}','${status}','${esc(s.profile_photo ?? '')}')">
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

        function filterData() {
            if (!window.allStudentsData) return;
            
            const q = sSearch ? sSearch.value.toLowerCase().trim() : '';
            const c = sCourse ? sCourse.value.toLowerCase() : 'all';
            const y = sYear ? sYear.value : 'all';
            const st = sStatus ? sStatus.value.toLowerCase() : 'all';

            const filtered = window.allStudentsData.filter(s => {
                const name = [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ').toLowerCase();
                const id = (s.student_id || '').toLowerCase();
                const course = (s.course || '').toLowerCase();
                const year = String(s.year_level || '').trim();
                const status = (s.status || s.Status || 'pending').toLowerCase();

                const matchSearch = !q || name.includes(q) || id.includes(q);
                const matchCourse = c === 'all' || course === c;
                const matchYear = y === 'all' || year === y || year.includes(y);
                const matchStatus = st === 'all' || status === st;

                return matchSearch && matchCourse && matchYear && matchStatus;
            });

            renderStudents(filtered);
        }

        if(sSearch) sSearch.addEventListener('input', filterData);
        if(sCourse) sCourse.addEventListener('change', filterData);
        if(sYear) sYear.addEventListener('change', filterData);
        if(sStatus) sStatus.addEventListener('change', filterData);
    }

    // ── OSA Events ────────────────────────────────────────────────────
    if (path.includes('app/osa/events.php')) {
        fetch('../../config/API/endpoints/index.php?action=get_osa_events')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
                set('osaEventsTotal',     data.stats.total ?? 0);
                set('osaEventsUpcoming',  data.stats.upcoming ?? 0);
                set('osaEventsOngoing',   data.stats.ongoing ?? 0);
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
            });
    }

    // ── Student Public Events page ────────────────────────────────────
    if (path.includes('student/events.php')) {
        // Events on this page are already server-rendered via PHP loop
        // but we expose the API for future use
    }

    // ── Global Logout Interceptor ────────────────────────────────────
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href*="logout"]');
        if (!link || link.dataset.confirmed === 'true') return;
        e.preventDefault();
        const targetUrl = link.href;
        if (typeof showLogoutConfirmModal === 'function') {
            showLogoutConfirmModal(targetUrl);
        } else {
            if (confirm("Are you sure you want to log out?")) {
                window.location.href = targetUrl;
            }
        }
    });
});
