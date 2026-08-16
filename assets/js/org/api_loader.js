document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Dashboard
    if(window.location.pathname.includes('dashboard_org.php')) {
        fetch('../../config/API/endpoints/index.php?action=get_org_dashboard')
            .then(r => r.json())
            .then(data => {
                if(!data.success) return;
                
                // Update Name
                const orgNameEl = document.getElementById('orgNameDisplay');
                if(orgNameEl) orgNameEl.textContent = data.org_name;

                // Update Stats
                const memStat = document.getElementById('statTotalMembers');
                const evStat = document.getElementById('statUpcomingEvents');
                if(memStat) memStat.textContent = data.stats.total_members;
                if(evStat) evStat.textContent = data.stats.upcoming_events;

                // Update Events List
                const eventsList = document.getElementById('dashboardEventsList');
                if(eventsList) {
                    eventsList.innerHTML = '';
                    if(data.events.length === 0) {
                        eventsList.innerHTML = '<li class="empty-state">No events found.</li>';
                    } else {
                        data.events.forEach(ev => {
                            eventsList.innerHTML += `
                                <li class="feed-item event-item">
                                    <div class="feed-icon"><ion-icon name="calendar-outline"></ion-icon></div>
                                    <div class="feed-content">
                                        <div class="feed-title">${ev.EventName}</div>
                                        <div class="feed-desc">${ev.EventLocation || 'TBA'}</div>
                                    </div>
                                    <div class="feed-meta">${new Date(ev.EventDateTime).toLocaleDateString()}</div>
                                </li>
                            `;
                        });
                    }
                }
            });
    }

    // 2. Events (Managed by events_org.js)
    // Handled by events_org.js to avoid double-loading & table markup conflict

    // 3. Members
    if(window.location.pathname.includes('members_org.php')) {
        let cachedMembers = [];

        function renderMembers(membersList) {
            const tbody = document.getElementById('membersTableBody');
            const manualTbody = document.getElementById('manualReviewTableBody');
            
            if(!membersList) membersList = [];
            if(tbody) tbody.innerHTML = '';
            if(manualTbody) manualTbody.innerHTML = '';

            let regularCount = 0;
            let manualCount = 0;

            if(membersList.length === 0) {
                if(tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">No members found.</td></tr>';
                if(manualTbody) manualTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No pending manual reviews.</td></tr>';
            } else {
                membersList.forEach(m => {
                    const fname = m.FirstName || m.first_name || 'Member';
                    const lname = m.LastName  || m.last_name  || '';
                    const name = (fname + ' ' + lname).trim();
                    const initials = ((fname.charAt(0) || 'M') + (lname.charAt(0) || '')).toUpperCase();
                    const sid = m.student_id || m.StudentIdNumber || 'N/A';
                    const em = m.Email || 'N/A';
                    const yr = m.YearLevel || m.year_level || 'N/A';
                    const sc = m.Section || m.section || 'N/A';
                    const status = (m.Status || m.status || 'pending').toLowerCase();
                    const vStatus = (m.VerificationStatus || m.verification_status || 'pending').toLowerCase();
                    
                    const scoreVal = (m.VerificationScore !== undefined && m.VerificationScore !== null && m.VerificationScore !== '') ? m.VerificationScore : ((m.ai_verification_score !== undefined && m.ai_verification_score !== null && m.ai_verification_score !== '') ? m.ai_verification_score : null);
                    const score = scoreVal !== null ? scoreVal : 'N/A';

                    let detailsArray = [];
                    try {
                        const rawD = m.VerificationDetails || m.ai_verification_details;
                        if (Array.isArray(rawD)) detailsArray = rawD;
                        else if (typeof rawD === 'string' && rawD.trim().startsWith('[')) detailsArray = JSON.parse(rawD);
                        else if (typeof rawD === 'string' && rawD.trim()) detailsArray = [rawD];
                    } catch(e) {}
                    let reasonStr = detailsArray.length > 0 ? detailsArray.join(', ') : 'No specific reason logged';
                    
                    let displayStatus = status.charAt(0).toUpperCase() + status.slice(1);
                    let statusClass = status === 'active' ? 'active' : 'pending';
                    
                    if (status === 'pending') {
                        if (vStatus === 'rejected') {
                            displayStatus = 'AI Flagged/Rejected';
                            statusClass = 'rejected-ai';
                        } else {
                            displayStatus = 'Pending Docs / Manual Review';
                        }
                    } else if (status === 'active' && (vStatus === 'ai_verified' || vStatus === 'approved')) {
                        displayStatus = vStatus === 'ai_verified' ? 'AI Verified (Active)' : 'Active';
                        statusClass = vStatus === 'ai_verified' ? 'pending-ai' : 'active';
                    }

                    const d = m.CreatedAt || m.created_at ? new Date(m.CreatedAt || m.created_at) : null;
                    const jd = d && !isNaN(d) ? d.toLocaleString('en-US', {month: 'short', year:'numeric'}) : 'Unknown';

                    let actions = '';
                    if(status === 'pending') {
                        actions += `
                            <button class="action-btn approve-btn" title="Approve" onclick="updateMemberStatus(${m.UserId}, 'approve', this)"><ion-icon name="checkmark-outline"></ion-icon></button>
                            <button class="action-btn decline-btn" title="Decline" onclick="updateMemberStatus(${m.UserId}, 'decline', this)"><ion-icon name="close-outline"></ion-icon></button>
                        `;
                    }
                    
                    const safeName = String(name).replace(/'/g, "\\'");
                    const safeSid = String(sid).replace(/'/g, "\\'");
                    const safeEm = String(em).replace(/'/g, "\\'");
                    const safeYr = String(yr).replace(/'/g, "\\'");
                    const safeSc = String(sc).replace(/'/g, "\\'");
                    const safeJd = String(jd).replace(/'/g, "\\'");
                    const safePhone = String(m.phone || m.Phone || '').replace(/'/g, "\\'");
                    const safeCor = String(m.CorDocumentUrl || m.cor_document || '').replace(/'/g, "\\'");
                    
                    actions += `
                        <button class="action-btn view-btn" onclick="openViewMemberModal('${safeName}', '${safeSid}', '${safeEm}', '${safeYr}', '${safeSc}', '${safeJd}', '${displayStatus}', '${initials}', '', '${safePhone}', '${safeCor}')" title="View Details">
                            <ion-icon name="eye-outline"></ion-icon>
                        </button>
                        <button class="action-btn decline-btn" onclick="deleteMember(${m.UserId}, this)" title="Delete Member">
                            <ion-icon name="trash-outline"></ion-icon>
                        </button>
                    `;

                    // Render Logic
                    // If user is pending AND verification is not ai_verified -> goes to Manual Review table
                    const isManualReview = (status === 'pending' && vStatus !== 'ai_verified');

                    if (isManualReview && manualTbody) {
                        manualCount++;
                        manualTbody.innerHTML += `
                            <tr>
                                <td class="name-cell" data-label="Name">
                                    <span class="avatar" style="background:#f43f5e;">${initials}</span>
                                    <span>${name}</span>
                                </td>
                                <td data-label="Student ID">${sid}</td>
                                <td data-label="Program">${m.course || 'N/A'}</td>
                                <td data-label="Join Date">${jd}</td>
                                <td data-label="AI Score"><strong>${score}</strong></td>
                                <td data-label="Reason">
                                    <div style="font-size:12px; color:#64748b; line-height:1.4;">${reasonStr}</div>
                                    <span class="status-badge pending" style="margin-top:4px;">${displayStatus}</span>
                                </td>
                                <td class="member-actions" data-label="Actions">${actions}</td>
                            </tr>
                        `;
                    } else if (tbody) {
                        regularCount++;
                        tbody.innerHTML += `
                            <tr>
                                <td class="name-cell" data-label="Name">
                                    <span class="avatar">${initials}</span>
                                    <span>${name}</span>
                                </td>
                                <td data-label="Student ID">${sid}</td>
                                <td data-label="Email">${em}</td>
                                <td data-label="Year Level">${yr}</td>
                                <td data-label="Section">${sc}</td>
                                <td data-label="Join Date">${jd}</td>
                                <td data-label="Status"><span class="status-badge ${statusClass}">${displayStatus}</span></td>
                                <td class="member-actions" data-label="Actions">${actions}</td>
                            </tr>
                        `;
                    }
                });
                
                if (regularCount === 0 && tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">No regular members found.</td></tr>';
                if (manualCount === 0 && manualTbody) manualTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No pending manual reviews.</td></tr>';
            }
        }

        // Automatic Filtering Logic
        function applyMemberFilters() {
            const searchVal = (document.getElementById('searchMember')?.value || '').toLowerCase();
            const filterStatus = document.getElementById('filterStatus')?.value || 'all';
            const filterYear = document.getElementById('filterYearLevel')?.value || 'all';

            const filtered = cachedMembers.filter(m => {
                const name = ((m.FirstName || m.first_name || '') + ' ' + (m.LastName || m.last_name || '')).toLowerCase();
                const status = (m.Status || m.status || 'pending').toLowerCase();
                const vStatus = (m.VerificationStatus || m.verification_status || 'pending').toLowerCase();
                const yrStr = (m.YearLevel || m.year_level || '').toLowerCase();

                // Search check
                if (searchVal && !name.includes(searchVal) && !(m.StudentIdNumber || m.student_id || '').toLowerCase().includes(searchVal)) {
                    return false;
                }
                
                // Status check
                if (filterStatus !== 'all') {
                    if (filterStatus === 'active' && status !== 'active') return false;
                    if (filterStatus === 'ai_approved' && (status !== 'active' || (vStatus !== 'ai_verified' && vStatus !== 'approved'))) return false;
                    if (filterStatus === 'manual_review' && (status !== 'pending' || vStatus === 'ai_verified')) return false;
                }
                
                // Year Level check
                if (filterYear !== 'all') {
                    if (!yrStr.includes(filterYear)) return false;
                }
                
                return true;
            });
            
            renderMembers(filtered);
        }

        window.loadOrgMembersData = function() {
            fetch('../../config/API/endpoints/index.php?action=get_org_members')
                .then(r => r.json())
                .then(data => {
                    if(!data.success) return;

                    // Stats
                    const st = data.stats || {};
                    const statIds = {
                        'statMembersTotal': st.total ?? 0,
                        'statMembersActive': st.active ?? 0,
                        'statMembersPending': st.pending ?? 0,
                        'statMembersAIApproved': st.ai_approved ?? 0,
                        'statMembersManualReview': st.manual_review ?? 0
                    };
                    for(let id in statIds) {
                        const el = document.getElementById(id);
                        if(el) el.textContent = statIds[id];
                    }

                    cachedMembers = data.members || [];
                    applyMemberFilters();
                })
                .catch(err => console.error('Error fetching members:', err));
        };

        // Initial Load
        window.loadOrgMembersData();

        ['searchMember'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', applyMemberFilters);
        });
        ['filterStatus', 'filterYearLevel'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', applyMemberFilters);
        });
    }
});

// Global function to handle member approval/decline
window.updateMemberStatus = function(userId, action, btnElement) {
    const actionLabel = action === 'approve' ? 'Approve' : 'Decline';
    showConfirmModal(
        `Are you sure you want to <strong>${actionLabel}</strong> this member?`,
        function() {
            fetch('../../config/API/endpoints/index.php?action=update_member_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, action: action })
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showModal(`Member has been ${action === 'approve' ? 'approved' : 'declined'} successfully.`, 'success', 'Member Action');
                    if (typeof window.loadOrgMembersData === 'function') {
                        window.loadOrgMembersData();
                    }
                } else {
                    showModal(res.message || 'An error occurred.', 'error', 'Error');
                }
            })
            .catch(err => {
                console.error(err);
                showModal('Server error occurred.', 'error', 'Error');
            });
        },
        `${actionLabel} Member`,
        action === 'approve' ? 'warning' : 'danger'
    );
};

// Global function to handle member deletion
window.deleteMember = function(userId, btnElement) {
    const doDelete = function() {
        fetch('../../config/API/endpoints/index.php?action=delete_org_member', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId })
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                showModal('Member deleted successfully.', 'success', 'Member Deleted');
                if (typeof window.loadOrgMembersData === 'function') {
                    window.loadOrgMembersData();
                }
            } else {
                showModal(res.message || 'An error occurred.', 'error', 'Error');
            }
        })
        .catch(err => {
            console.error(err);
            showModal('Server error occurred.', 'error', 'Error');
        });
    };

    showConfirmModal('Are you sure you want to permanently delete this member? This action cannot be undone.', doDelete, 'Delete Member', 'danger');
};

