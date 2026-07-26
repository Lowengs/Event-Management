document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Dashboard
    if(window.location.pathname.includes('dashboard_org.php')) {
        fetch('../../config/API/get_org_dashboard.php')
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

    // 2. Events
    if(window.location.pathname.includes('events_org.php')) {
        fetch('../../config/API/get_org_events.php')
            .then(r => r.json())
            .then(data => {
                if(!data.success) return;

                // Stats
                const st = data.stats;
                const statIds = {
                    'statEventsTotal': st.total,
                    'statEventsUpcoming': st.upcoming,
                    'statEventsOngoing': st.ongoing,
                    'statEventsCompleted': st.completed
                };
                for(let id in statIds) {
                    const el = document.getElementById(id);
                    if(el) el.textContent = statIds[id];
                }

                // Table
                const tbody = document.getElementById('eventsTableBody');
                if(tbody) {
                    tbody.innerHTML = '';
                    if(data.events.length === 0) {
                        tbody.innerHTML = '<div class="table-row" style="padding: 20px; text-align: center; justify-content: center; width: 100%;">No events found.</div>';
                    } else {
                        data.events.forEach(ev => {
                            const d = ev.EventDateTime ? new Date(ev.EventDateTime) : null;
                            const dtStr = d ? d.toISOString().split('T')[0] : 'N/A';
                            const tmStr = d ? d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 'N/A';
                            const status = (ev.EventStatus || 'scheduled').toLowerCase();
                            
                            tbody.innerHTML += `
                                <div class="table-row">
                                    <div class="table-col event-title">
                                        <strong>${ev.EventName}</strong>
                                        <span>${ev.EventType || 'Event'}</span>
                                    </div>
                                    <div class="table-col organization">
                                        <strong>${ev.OrgName || 'Unknown'}</strong>
                                    </div>
                                    <div class="table-col date"><ion-icon name="calendar-outline"></ion-icon> ${dtStr}</div>
                                    <div class="table-col time"><ion-icon name="time-outline"></ion-icon> ${tmStr}</div>
                                    <div class="table-col location"><ion-icon name="location-outline"></ion-icon> ${ev.EventLocation || 'TBA'}</div>
                                    <div class="table-col status"><span class="status-pill ${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span></div>
                                    <div class="table-col actions">
                                        <button class="action-circle view" aria-label="View event" onclick="openModal('viewEventModal')">
                                            <ion-icon name="eye-outline"></ion-icon>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    }
                }
            });
    }

    // 3. Members
    if(window.location.pathname.includes('members_org.php')) {
        fetch('../../config/API/get_org_members.php')
            .then(r => r.json())
            .then(data => {
                if(!data.success) return;

                // Stats
                const st = data.stats;
                const statIds = {
                    'statMembersTotal': st.total,
                    'statMembersActive': st.active,
                    'statMembersPending': st.pending,
                    'statMembersAIApproved': st.ai_approved,
                    'statMembersManualReview': st.manual_review
                };
                for(let id in statIds) {
                    const el = document.getElementById(id);
                    if(el) el.textContent = statIds[id];
                }

                // Table Rendering Function
                const tbody = document.getElementById('membersTableBody');
                const manualTbody = document.getElementById('manualReviewTableBody');
                
                function renderMembers(membersList) {
                    if(tbody) tbody.innerHTML = '';
                    if(manualTbody) manualTbody.innerHTML = '';

                    let regularCount = 0;
                    let manualCount = 0;

                    if(membersList.length === 0) {
                        if(tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 20px;">No members found.</td></tr>';
                        if(manualTbody) manualTbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">No pending manual reviews.</td></tr>';
                    } else {
                        membersList.forEach(m => {
                            const name = (m.FirstName + ' ' + m.LastName).trim();
                            const initials = (m.FirstName.charAt(0) + m.LastName.charAt(0)).toUpperCase();
                            const sid = m.StudentIdNumber || 'N/A';
                            const em = m.Email || 'N/A';
                            const yr = m.YearLevel || 'N/A';
                            const sc = m.Section || 'N/A';
                            const status = (m.Status || 'pending').toLowerCase();
                            const vStatus = (m.VerificationStatus || 'pending').toLowerCase();
                            
                            const score = m.VerificationScore !== null ? m.VerificationScore : 'N/A';
                            let detailsArray = [];
                            try {
                                if (Array.isArray(m.VerificationDetails)) detailsArray = m.VerificationDetails;
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
                            } else if (status === 'active' && vStatus === 'ai_verified') {
                                displayStatus = 'AI Verified (Active)';
                                statusClass = 'pending-ai'; // Just keeping the purple styling if desired, but user is active
                            }

                            const d = m.CreatedAt ? new Date(m.CreatedAt) : null;
                            const jd = d ? d.toLocaleString('en-US', {month: 'short', year:'numeric'}) : 'Unknown';

                            let actions = '';
                            if(status === 'pending') {
                                actions += `
                                    <button class="action-btn approve-btn" title="Approve" onclick="updateMemberStatus(${m.UserId}, 'approve', this)"><ion-icon name="checkmark-outline"></ion-icon></button>
                                    <button class="action-btn decline-btn" title="Decline" onclick="updateMemberStatus(${m.UserId}, 'decline', this)"><ion-icon name="close-outline"></ion-icon></button>
                                `;
                            }
                            
                            const safeName = name.replace(/'/g, "\\'");
                            const safeSid = sid.replace(/'/g, "\\'");
                            const safeEm = em.replace(/'/g, "\\'");
                            const safeYr = yr.replace(/'/g, "\\'");
                            const safeSc = sc.replace(/'/g, "\\'");
                            const safeJd = jd.replace(/'/g, "\\'");
                            const safePhone = (m.phone || m.Phone || '').replace(/'/g, "\\'");
                            const safeCor = (m.CorDocumentUrl || '').replace(/'/g, "\\'");
                            
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
                
                // Initial render
                renderMembers(data.members);
                
                // Filtering Logic
                const filterBtn = document.getElementById('filterBtn');
                if (filterBtn) {
                    filterBtn.addEventListener('click', () => {
                        const searchVal = (document.getElementById('searchMember')?.value || '').toLowerCase();
                        const filterStatus = document.getElementById('filterStatus')?.value || 'all';
                        const filterYear = document.getElementById('filterYearLevel')?.value || 'all';

                        const filtered = data.members.filter(m => {
                            const name = (m.FirstName + ' ' + m.LastName).toLowerCase();
                            const status = (m.Status || 'pending').toLowerCase();
                            const vStatus = (m.VerificationStatus || 'pending').toLowerCase();
                            const yrStr = (m.YearLevel || '').toLowerCase();

                            // Search check
                            if (searchVal && !name.includes(searchVal) && !(m.StudentIdNumber||'').toLowerCase().includes(searchVal)) {
                                return false;
                            }
                            
                            // Status check
                            if (filterStatus !== 'all') {
                                if (filterStatus === 'active' && status !== 'active') return false;
                                if (filterStatus === 'ai_approved' && (status !== 'active' || vStatus !== 'ai_verified')) return false;
                                if (filterStatus === 'manual_review' && (status !== 'pending' || vStatus === 'ai_verified')) return false;
                            }
                            
                            // Year Level check
                            if (filterYear !== 'all') {
                                if (!yrStr.includes(filterYear)) return false;
                            }
                            
                            return true;
                        });
                        
                        renderMembers(filtered);
                    });
                }
            });
    }
});

// Global function to handle member approval/decline
window.updateMemberStatus = function(userId, action, btnElement) {
    if (!confirm(`Are you sure you want to ${action} this member?`)) return;

    fetch('../../config/API/update_member_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, action: action })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            alert(`Member has been ${action}d successfully.`);
            const cell = btnElement.closest('.member-actions');
            if (cell) {
                // Remove the approve and decline buttons
                const approveBtn = cell.querySelector('.approve-btn');
                const declineBtn = cell.querySelector('.decline-btn');
                if (approveBtn) approveBtn.remove();
                if (declineBtn) declineBtn.remove();
                
                // Also update the status badge in the row
                const row = cell.closest('tr');
                if (row) {
                    const statusCell = row.querySelector('[data-label="Status"], [data-label="Reason"]');
                    if (statusCell) {
                        const badge = statusCell.querySelector('.status-badge');
                        if (badge) {
                            if (action === 'approve') {
                                badge.textContent = 'Active';
                                badge.className = 'status-badge active';
                            } else {
                                badge.textContent = 'Rejected';
                                badge.className = 'status-badge rejected';
                            }
                        }
                    }
                }
            }
        } else {
            alert(res.message || 'An error occurred.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server error occurred.');
    });
};

// Global function to handle member deletion
window.deleteMember = function(userId, btnElement) {
    if (!confirm('Are you sure you want to permanently delete this member? This action cannot be undone.')) return;

    fetch('../../config/API/delete_org_member.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            alert('Member deleted successfully.');
            // Remove the row from the table
            const row = btnElement.closest('tr');
            if (row) {
                row.remove();
            }
        } else {
            alert(res.message || 'An error occurred.');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Server error occurred.');
    });
};

