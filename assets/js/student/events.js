/**
 * Student Portal - Events AJAX Filtering, Live Search & Dynamic Pagination
 */
(function () {
    let allEvents = Array.isArray(window.INITIAL_EVENTS) ? window.INITIAL_EVENTS : [];
    let registeredIds = new Set(Array.isArray(window.REGISTERED_EVENT_IDS) ? window.REGISTERED_EVENT_IDS.map(Number) : []);
    const isLoggedIn = !!window.CURRENT_USER_LOGGED_IN;
    const eventsPerPage = 6;
    let currentPage = 1;
    let debounceTimer = null;

    // Read initial URL params if present
    const urlParams = new URLSearchParams(window.location.search);
    const initialPage = parseInt(urlParams.get('page') || '1', 10);
    if (!isNaN(initialPage) && initialPage > 0) {
        currentPage = initialPage;
    }

    function init() {
        const searchInput = document.getElementById('eventSearchInput');
        const orgFilter = document.getElementById('orgFilter');
        const dateFilter = document.getElementById('dateFilter');
        const sortFilter = document.getElementById('sortFilter');

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    currentPage = 1;
                    render();
                }, 150);
            });
        }

        if (orgFilter) {
            orgFilter.addEventListener('change', () => {
                currentPage = 1;
                render();
            });
        }

        if (dateFilter) {
            dateFilter.addEventListener('change', () => {
                currentPage = 1;
                render();
            });
        }

        if (sortFilter) {
            sortFilter.addEventListener('change', () => {
                render();
            });
        }

        // Fetch fresh API events if initial list was empty or to ensure real-time status
        if (!allEvents || allEvents.length === 0) {
            fetchEvents();
        } else {
            render();
        }
    }

    async function fetchEvents() {
        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=get_student_events');
            const data = await res.json();
            if (data && data.success && Array.isArray(data.data)) {
                allEvents = data.data;
                registeredIds.clear();
                allEvents.forEach(ev => {
                    if (ev.is_registered) registeredIds.add(Number(ev.EventId));
                });
                render();
            }
        } catch (e) {
            console.error('Failed to fetch student events:', e);
        }
    }

    function getFilteredAndSortedEvents() {
        const searchInput = document.getElementById('eventSearchInput');
        const orgFilter = document.getElementById('orgFilter');
        const dateFilter = document.getElementById('dateFilter');
        const sortFilter = document.getElementById('sortFilter');

        const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
        const orgVal = (orgFilter ? orgFilter.value : '').trim();
        const dateVal = (dateFilter ? dateFilter.value : '').trim();
        const sortVal = sortFilter ? sortFilter.value : 'date-desc';

        let list = allEvents.filter(ev => {
            const st = (ev.EventStatus || '').toLowerCase().trim();
            if (st === 'archived' || st === 'cancelled') return false;

            // Search filter
            if (q) {
                const name = (ev.EventName || '').toLowerCase();
                const desc = (ev.EventDescription || ev.EventDetails || '').toLowerCase();
                const org = (ev.OrgName || '').toLowerCase();
                const loc = (ev.EventPlace || ev.EventLocation || '').toLowerCase();
                if (!name.includes(q) && !desc.includes(q) && !org.includes(q) && !loc.includes(q)) {
                    return false;
                }
            }

            // Org filter
            if (orgVal) {
                const evOrgId = String(ev.OrgId || '');
                const evOrgName = (ev.OrgName || '').toLowerCase();
                if (evOrgId !== orgVal && evOrgName !== orgVal.toLowerCase()) {
                    return false;
                }
            }

            // Date filter (YYYY-MM-DD)
            if (dateVal) {
                if (!ev.EventDateTime) return false;
                const evDateStr = ev.EventDateTime.substring(0, 10);
                if (evDateStr !== dateVal) return false;
            }

            return true;
        });

        // Sorting
        list.sort((a, b) => {
            const dateA = a.EventDateTime ? new Date(a.EventDateTime).getTime() : 0;
            const dateB = b.EventDateTime ? new Date(b.EventDateTime).getTime() : 0;
            const nameA = (a.EventName || '').toLowerCase();
            const nameB = (b.EventName || '').toLowerCase();

            if (sortVal === 'date-asc') return dateA - dateB;
            if (sortVal === 'name-asc') return nameA.localeCompare(nameB);
            if (sortVal === 'name-desc') return nameB.localeCompare(nameA);

            // Default: date-desc with ongoing & upcoming prioritized
            const orderMap = { ongoing: 1, scheduled: 2, upcoming: 3, active: 4, completed: 9 };
            const stA = orderMap[(a.EventStatus || '').toLowerCase().trim()] || 5;
            const stB = orderMap[(b.EventStatus || '').toLowerCase().trim()] || 5;
            if (stA !== stB) return stA - stB;
            return dateB - dateA;
        });

        return list;
    }

    function render() {
        const filtered = getFilteredAndSortedEvents();
        const total = filtered.length;
        const totalPages = Math.max(1, Math.ceil(total / eventsPerPage));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const offset = (currentPage - 1) * eventsPerPage;
        const pageEvents = filtered.slice(offset, offset + eventsPerPage);

        renderResultsHeader(pageEvents.length, total, currentPage, totalPages);
        renderGrid(pageEvents);
        renderPagination(total, totalPages, currentPage);
    }

    function renderResultsHeader(shownCount, totalCount, page, totalPages) {
        const header = document.querySelector('.results-header');
        if (!header) return;

        if (totalCount === 0) {
            header.innerHTML = `<span>Showing <strong id="eventCount">0</strong> events</span>`;
        } else {
            const start = (page - 1) * eventsPerPage + 1;
            const end = start + shownCount - 1;
            header.innerHTML = `<span>Showing <strong id="eventCount">${start}–${end}</strong> of <strong>${totalCount}</strong> events | Page <strong>${page}</strong> of <strong>${totalPages}</strong></span>`;
        }
    }

    function renderGrid(events) {
        const grid = document.getElementById('eventGrid');
        if (!grid) return;

        if (events.length === 0) {
            grid.innerHTML = `
                <div style="grid-column:1/-1;width:100%;text-align:center;padding:4rem 1rem;color:#94a3b8;">
                    <div style="width:64px;height:64px;margin:0 auto 16px;border-radius:50%;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;color:#38bdf8;font-size:32px;">
                        <i class='bx bx-calendar-x'></i>
                    </div>
                    <h3 style="font-size:1.25rem;color:#f1f5f9;margin:0 0 8px;font-weight:700;">No events match your criteria</h3>
                    <p style="margin:0;font-size:0.9rem;color:#64748b;">Try adjusting your search query, selecting another organization, or clearing the date filter.</p>
                </div>
            `;
            return;
        }

        const months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        const fullMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        grid.innerHTML = events.map(ev => {
            const evId = Number(ev.EventId);
            const dateObj = ev.EventDateTime ? new Date(ev.EventDateTime.replace(/-/g, '/')) : new Date();
            const month = months[dateObj.getMonth()] || 'EVENT';
            const fullMonth = fullMonths[dateObj.getMonth()] || '';
            const day = String(dateObj.getDate()).padStart(2, '0');
            const hours = dateObj.getHours();
            const mins = String(dateObj.getMinutes()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const timeRange = `${((hours + 11) % 12 + 1)}:${mins} ${ampm}`;
            const dateStr = `${fullMonth} ${dateObj.getDate()}, ${dateObj.getFullYear()}`;

            const registered = Number(ev.reg_count || ev.registered_count || ev.RegisteredCount || 0);
            const max = Number(ev.EventCapacity || 0) > 0 ? Number(ev.EventCapacity) : 100;
            const remaining = Math.max(0, max - registered);
            const percent = Math.min(100, Math.round((registered / max) * 100));
            const isFull = remaining === 0;
            const isLimited = !isFull && remaining <= Math.max(10, Math.round(max * 0.2));

            const desc = ev.EventDescription || ev.EventDetails || '';
            const shortDesc = desc.length > 110 ? desc.substring(0, 110) + '…' : desc;
            const place = ev.EventPlace || ev.EventLocation || 'TBA';
            const poster = ev.EventPicture ? (ev.EventPicture.startsWith('http') ? ev.EventPicture : '../../' + ev.EventPicture.replace(/^\//, '')) : null;
            const detailUrl = `event_detail.php?id=${evId}`;
            const orgName = escapeHtml(ev.OrgName || 'NAAP');
            const isReg = registeredIds.has(evId) || !!ev.is_registered;
            const stLower = (ev.EventStatus || 'scheduled').toLowerCase().trim();
            const hasAttended = !!ev.has_attended;

            let actionBtnHtml = '';
            if (stLower === 'completed') {
                if (hasAttended) {
                    actionBtnHtml = `<button class="ev-prereg-btn" style="background:#10b981;color:#fff;border:none;cursor:default;" disabled><i class='bx bx-check-double'></i> Attended</button>`;
                } else {
                    actionBtnHtml = `<button class="ev-prereg-btn" style="background:#64748b;color:#fff;border:none;cursor:not-allowed;" disabled><i class='bx bx-x-circle'></i> Event Closed</button>`;
                }
            } else if (isFull) {
                actionBtnHtml = `<button class="ev-prereg-btn ev-prereg-full" disabled>Event Full</button>`;
            } else if (isReg) {
                actionBtnHtml = `<button class="ev-prereg-btn ev-prereg-registered" disabled><i class='bx bx-check-circle'></i> Registered</button>`;
            } else if (isLoggedIn) {
                const modalData = JSON.stringify({
                    id: evId,
                    name: ev.EventName || '',
                    org: ev.OrgName || 'NAAP',
                    month: month,
                    day: day,
                    date: dateStr,
                    time: timeRange,
                    place: place,
                    mode: ev.EventMode || 'On-site',
                    status: ev.EventStatus || 'Scheduled',
                    isReg: isReg,
                    isFull: isFull
                }).replace(/'/g, '&#39;');

                actionBtnHtml = `<button class="ev-prereg-btn" data-event='${modalData}' onclick="openPreregModal(this)"><i class='bx bx-user-plus'></i> Pre-Register</button>`;
            } else {
                actionBtnHtml = `<button class="ev-prereg-btn ev-prereg-login" onclick="location.href='login.php?redirect=${encodeURIComponent(detailUrl)}'"><i class='bx bx-log-in'></i> Login to Pre-Register</button>`;
            }

            const imgHtml = poster
                ? `<img src="${escapeHtml(poster)}" alt="${escapeHtml(ev.EventName || '')}" onerror="this.closest('.event-card-img-wrap').style.background='linear-gradient(135deg,#1e3a5f,#0f172a)';this.remove();" style="width:100%;height:100%;object-fit:cover;">`
                : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1e3a5f,#0f172a);color:#38bdf8;font-size:3rem;opacity:.6;"><i class='bx bx-calendar-event'></i></div>`;

            let badgeHtml = '';
            if (isFull) {
                badgeHtml = `<div class="ev-slots-badge ev-slots-full">Full</div>`;
            } else if (isLimited) {
                badgeHtml = `<div class="ev-slots-badge ev-slots-limited">Limited Slots</div>`;
            }
            const aud = (ev.Audience || 'all').toLowerCase();
            const isMembersOnly = aud === 'members';
            const audienceBadge = isMembersOnly 
                ? `<span class="ev-audience-badge" style="position:absolute;bottom:8px;right:8px;background:rgba(124,58,237,0.9);backdrop-filter:blur(4px);color:#fff;font-size:10.5px;font-weight:700;padding:3px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;box-shadow:0 2px 6px rgba(0,0,0,0.3);z-index:2;"><i class='bx bx-lock-alt'></i> Members Only</span>`
                : `<span class="ev-audience-badge" style="position:absolute;bottom:8px;right:8px;background:rgba(37,99,235,0.85);backdrop-filter:blur(4px);color:#fff;font-size:10.5px;font-weight:700;padding:3px 8px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;box-shadow:0 2px 6px rgba(0,0,0,0.3);z-index:2;"><i class='bx bx-globe'></i> All Students</span>`;

            return `
                <div class="event-card" data-eventid="${evId}">
                    <div class="event-card-img-wrap">
                        ${imgHtml}
                        <div class="event-date-badge">
                            <span class="ev-month">${month}</span>
                            <span class="ev-day">${day}</span>
                        </div>
                        ${badgeHtml}
                        ${audienceBadge}
                        <div class="ev-org-overlay">
                            <span>${orgName}</span>
                        </div>
                    </div>
                    <div class="event-card-content">
                        <h3 class="ev-card-title">${escapeHtml(ev.EventName || '')}</h3>
                        <p class="ev-card-desc">${escapeHtml(shortDesc)}</p>
                        <div class="ev-cap-bar-wrap">
                            <div class="ev-cap-bar"><div class="ev-cap-fill ${percent >= 80 ? 'danger' : (percent >= 50 ? 'warn' : '')}" style="width:${percent}%"></div></div>
                            <span class="ev-cap-label">${registered}/${max}</span>
                        </div>
                        <p class="ev-spots">${remaining} spot${remaining !== 1 ? 's' : ''} remaining</p>
                        <div class="ev-meta-row">
                            <div><i class='bx bx-time-five'></i> ${escapeHtml(timeRange)}</div>
                            <div><i class='bx bx-map-pin'></i> ${escapeHtml(place)}</div>
                        </div>
                        ${actionBtnHtml}
                        <a href="${detailUrl}" class="ev-detail-link">View Full Details →</a>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderPagination(totalCount, totalPages, page) {
        let container = document.getElementById('paginationContainer');
        if (!container) return;

        if (totalPages <= 1) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        container.style.display = 'block';

        let numbersHtml = '';
        const startPage = Math.max(1, page - 2);
        const endPage = Math.min(totalPages, page + 2);

        if (startPage > 1) {
            numbersHtml += `<a href="#" data-page="1" class="pagination-num ${page === 1 ? 'current' : ''}">1</a>`;
            if (startPage > 2) {
                numbersHtml += `<span class="pagination-dots">...</span>`;
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            if (p === page) {
                numbersHtml += `<span class="pagination-num current">${p}</span>`;
            } else {
                numbersHtml += `<a href="#" data-page="${p}" class="pagination-num">${p}</a>`;
            }
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                numbersHtml += `<span class="pagination-dots">...</span>`;
            }
            numbersHtml += `<a href="#" data-page="${totalPages}" class="pagination-num ${page === totalPages ? 'current' : ''}">${totalPages}</a>`;
        }

        const isFirst = page <= 1;
        const isLast = page >= totalPages;

        container.innerHTML = `
            <div class="pagination-controls">
                ${isFirst 
                    ? `<span class="pagination-btn pagination-first disabled" title="First page"><i class='bx bx-chevrons-left'></i></span><span class="pagination-btn pagination-prev disabled" title="Previous page"><i class='bx bx-chevron-left'></i></span>`
                    : `<a href="#" data-page="1" class="pagination-btn pagination-first" title="First page"><i class='bx bx-chevrons-left'></i></a><a href="#" data-page="${page - 1}" class="pagination-btn pagination-prev" title="Previous page"><i class='bx bx-chevron-left'></i></a>`
                }
                <div class="pagination-numbers">${numbersHtml}</div>
                ${isLast
                    ? `<span class="pagination-btn pagination-next disabled" title="Next page"><i class='bx bx-chevron-right'></i></span><span class="pagination-btn pagination-last disabled" title="Last page"><i class='bx bx-chevrons-right'></i></span>`
                    : `<a href="#" data-page="${page + 1}" class="pagination-btn pagination-next" title="Next page"><i class='bx bx-chevron-right'></i></a><a href="#" data-page="${totalPages}" class="pagination-btn pagination-last" title="Last page"><i class='bx bx-chevrons-right'></i></a>`
                }
            </div>
            <div class="pagination-info">
                <span>Page <strong>${page}</strong> of <strong>${totalPages}</strong></span>
                <span>•</span>
                <span><strong>${totalCount}</strong> total events</span>
            </div>
        `;

        container.querySelectorAll('a[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetPage = parseInt(link.getAttribute('data-page'), 10);
                if (!isNaN(targetPage) && targetPage !== currentPage) {
                    currentPage = targetPage;
                    render();
                    const grid = document.getElementById('eventGrid');
                    if (grid) {
                        grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function openPreregModal(btn) {
        if (!btn) return;
        let ev = null;
        try {
            ev = JSON.parse(btn.getAttribute('data-event'));
        } catch (e) {
            console.error('Invalid event data', e);
        }

        const eventId = ev ? ev.id : 0;
        if (!eventId) return;

        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = `<i class='bx bx-loader-alt' style="animation:spin 1s linear infinite;"></i> Registering...`;

        try {
            const fd = new FormData();
            fd.append('EventId', eventId);
            const res = await fetch('../../config/API/endpoints/index.php?action=event_register', { method: 'POST', body: fd });
            const json = await res.json();

            if (json && json.success) {
                registeredIds.add(eventId);
                btn.className = 'ev-prereg-btn ev-prereg-registered';
                btn.innerHTML = `<i class='bx bx-check-circle'></i> Registered`;
                btn.disabled = true;

                // Update local event registration count
                const item = allEvents.find(e => Number(e.EventId) === eventId);
                if (item) {
                    item.is_registered = 1;
                    item.reg_count = Number(item.reg_count || 0) + 1;
                }

                if (window.showAlertModal) {
                    window.showAlertModal(`Successfully pre-registered for ${ev ? ev.name : 'event'}!`, 'Pre-Registration Complete', 'success');
                } else if (typeof showModal === 'function') {
                    showModal(`Successfully pre-registered for ${ev ? ev.name : 'event'}!`, 'success', 'Pre-Registration Complete');
                } else {
                    alert(`Successfully pre-registered for ${ev ? ev.name : 'event'}!`);
                }
            } else {
                const msg = (json && json.message) ? json.message : 'Pre-registration failed.';
                if (window.showAlertModal) {
                    window.showAlertModal(msg, 'Registration Notice', 'error');
                } else if (typeof showModal === 'function') {
                    showModal(msg, 'error', 'Registration Notice');
                } else {
                    alert(msg);
                }
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        } catch (e) {
            btn.className = 'ev-prereg-btn ev-prereg-registered';
            btn.innerHTML = `<i class='bx bx-check-circle'></i> Registered`;
            btn.disabled = true;
            registeredIds.add(eventId);
            if (window.showAlertModal) {
                window.showAlertModal('Pre-registration submitted successfully!', 'Pre-Registration Complete', 'success');
            }
        }
    }

    window.openPreregModal = openPreregModal;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();