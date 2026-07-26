
let allEvents = [];

function openM(id) { document.getElementById(id).classList.add('active'); }
function closeM(id) { document.getElementById(id).classList.remove('active'); }

function showToast(msg, ok=true) { 
    const t = document.getElementById('toast'); 
    t.textContent = msg; 
    t.style.background = ok ? '#16a34a' : '#dc2626'; 
    t.style.display = 'block'; 
    setTimeout(() => t.style.display = 'none', 3500); 
}

function openAddEvent() {
    document.getElementById('eventFormTitle').textContent = 'Create New Event';
    document.getElementById('eventForm').reset();
    document.getElementById('evFormEventId').value = '';
    
    const today = new Date().toISOString().split('T')[0];
    if (document.getElementById('evDate')) document.getElementById('evDate').min = today;

    document.getElementById('evPosterPreview').style.display = 'none';
    document.getElementById('saveEventBtn').dataset.mode = 'create';
    openM('eventFormModal');
}

function openEditEvent(ev) {
    document.getElementById('eventFormTitle').textContent = 'Edit Event';
    document.getElementById('eventForm').reset();
    document.getElementById('evFormEventId').value = ev.EventId || '';
    document.getElementById('evName').value = ev.EventName || '';
    document.getElementById('evDesc').value = ev.EventDescription || '';
    document.getElementById('evPlace').value = ev.EventPlace || ev.EventLocation || '';
    
    if (document.getElementById('evType')) document.getElementById('evType').value = ev.EventType || 'Seminar / Workshop';
    if (document.getElementById('evCapacity')) document.getElementById('evCapacity').value = ev.EventCapacity || '';
    if (document.getElementById('evSpeaker')) document.getElementById('evSpeaker').value = ev.EventSpeaker || '';

    if (ev.EventDateTime) {
        const [d, t] = ev.EventDateTime.split(' ');
        document.getElementById('evDate').value = d;
        if (t) document.getElementById('evTimeStart').value = t.substring(0,5);
    }

    if (ev.EndDateTime) {
        const [_, endT] = ev.EndDateTime.split(' ');
        if (endT && document.getElementById('evTimeEnd')) document.getElementById('evTimeEnd').value = endT.substring(0,5);
    }
    
    document.getElementById('evMode').value = ev.EventMode || 'On-site';
    document.getElementById('attEnabled').checked = ev.AttendanceEnabled == 1;
    if (document.getElementById('attMethod')) document.getElementById('attMethod').value = ev.AttendanceMethod || 'QR Code';

    document.getElementById('evPosterPreview').style.display = 'none';
    document.getElementById('saveEventBtn').dataset.mode = 'edit';
    openM('eventFormModal');
}

function openViewEvent(ev) {
    document.getElementById('viewEvTitle').textContent = ev.EventName || '—';
    document.getElementById('viewEvDesc').textContent = ev.EventDescription || '—';
    
    let evDate = '—';
    let evTime = '—';
    if (ev.EventDateTime) {
        const parts = ev.EventDateTime.split(' ');
        evDate = parts[0] || '—';
        if (parts[1]) {
            const timeParts = parts[1].split(':');
            if (timeParts.length >= 2) {
                let h = parseInt(timeParts[0]);
                const m = timeParts[1];
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                evTime = `${h.toString().padStart(2, '0')}:${m} ${ampm}`;
            }
        }
    }
    
    let durationText = '—';
    if (ev.EventDateTime && ev.EndDateTime && ev.EndDateTime !== '0000-00-00 00:00:00' && ev.EndDateTime !== '') {
        const start = new Date(ev.EventDateTime.replace(' ', 'T'));
        const end   = new Date(ev.EndDateTime.replace(' ', 'T'));
        const diffMs = end - start;
        if (diffMs > 0) {
            const totalMins = Math.round(diffMs / 60000);
            const hrs  = Math.floor(totalMins / 60);
            const mins = totalMins % 60;
            durationText = hrs > 0 ? `${hrs}h ${mins > 0 ? mins + 'm' : ''}`.trim() : `${mins}m`;
        }
    }
    const durEl = document.getElementById('viewEvDuration');
    if (durEl) durEl.textContent = durationText;

    document.getElementById('viewEvDate').textContent = evDate;
    document.getElementById('viewEvTime').textContent = evTime;
    document.getElementById('viewEvStatus').textContent = ev.EventStatus || 'Scheduled';
    document.getElementById('viewEvVenue').textContent = ev.EventPlace || ev.EventLocation || '—';
    document.getElementById('viewEvMode').textContent = ev.EventMode || '—';
    document.getElementById('viewEvSpeaker').textContent = ev.EventSpeaker || '—';
    document.getElementById('viewEvCapacity').textContent = ev.EventCapacity || '—';
    
    const posterContainer = document.getElementById('viewEvPosterContainer');
    const posterImg = document.getElementById('viewEvPoster');
    if (ev.EventPicture) {
        let picPath = ev.EventPicture;
        if (picPath.startsWith('assets/')) {
            picPath = '../../' + picPath;
        } else if (!picPath.startsWith('../../') && !picPath.startsWith('http')) {
            picPath = '../../assets/uploads/events/' + picPath;
        }
        posterContainer.style.display = 'block';
        posterImg.src = picPath;
        posterImg.onerror = function() { posterContainer.style.display = 'none'; };
    } else {
        posterContainer.style.display = 'none';
        posterImg.src = '';
    }
    
    openM('eventViewModal');
}

document.getElementById('saveEventBtn').addEventListener('click', function() {
    const mode = this.dataset.mode;
    const form = document.getElementById('eventForm');
    
    if (!document.getElementById('evName').value || !document.getElementById('evDate').value) {
        showToast("Please fill in required fields", false);
        return;
    }

    const fd = new FormData(form);
    
    const date = document.getElementById('evDate').value;
    const time = document.getElementById('evTimeStart').value;
    const timeEnd = document.getElementById('evTimeEnd') ? document.getElementById('evTimeEnd').value : '';
    fd.set('EventDateTime', `${date} ${time}:00`);
    if (timeEnd) {
        fd.set('EndDateTime', `${date} ${timeEnd}:00`);
    }

    const url = mode === 'edit' ? '../../config/API/update_org_event.php' : '../../config/API/create_org_event.php';

    fetch(url, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success);
        if (d.success) { closeM('eventFormModal'); loadEvents(); }
    })
    .catch(e => showToast("Error saving event", false));
});

function loadEvents() {
    fetch('../../config/API/get_org_events.php')
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            allEvents = data.events || [];
            applyFilters();
            const s = data.stats;
            document.getElementById('statEventsTotal').textContent    = s.total    ?? 0;
            document.getElementById('statEventsUpcoming').textContent  = s.upcoming  ?? 0;
            document.getElementById('statEventsOngoing').textContent   = s.ongoing   ?? 0;
            document.getElementById('statEventsCompleted').textContent = s.completed ?? 0;
        }
    })
    .catch(() => {});
}

function renderEvents(evs) {
    const tbody = document.getElementById('eventsTableBody');
    if (!evs || evs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:40px;color:#94a3b8;">No events found...</td></tr>';
        return;
    }
    
    tbody.innerHTML = evs.map(ev => {
        const evDate = ev.EventDateTime ? ev.EventDateTime.split(' ')[0] : '—';
        let evTime = ev.EventDateTime ? ev.EventDateTime.split(' ')[1] : '—';
        
        if (evTime !== '—') {
            const timeParts = evTime.split(':');
            if (timeParts.length >= 2) {
                let h = parseInt(timeParts[0]);
                const m = timeParts[1];
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                evTime = `${h.toString().padStart(2, '0')}:${m} ${ampm}`;
            }
        }

        const isCompleted = ev.EventStatus === 'Completed';
        const isInterrupted = ['Cancelled', 'Delayed'].includes(ev.EventStatus);
        const isOnline = ['Online', 'Hybrid'].includes(ev.EventMode || '');

        return `
        <tr>
            <td class="event-name-cell" data-label="">
                <div class="event-title-cell">
                    ${ev.EventName}
                    ${ev.EventMode ? `<span class="event-subtitle">${ev.EventMode}</span>` : ''}
                </div>
            </td>
            <td data-label="Date"><div class="with-icon"><ion-icon name="calendar-outline"></ion-icon> ${evDate}</div></td>
            <td data-label="Time"><div class="with-icon" style="line-height:1.2;"><ion-icon name="time-outline"></ion-icon> ${evTime}</div></td>
            <td data-label="Location"><div class="with-icon"><ion-icon name="location-outline"></ion-icon> ${ev.EventPlace || '—'}</div></td>
            <td data-label="Capacity">${ev.EventCapacity || '—'}</td>
            <td data-label="Pre-Reg"><div class="with-icon" style="color: #0ea5e9; font-weight: 600;"><ion-icon name="person-add-outline"></ion-icon> ${ev.pre_registered_count || 0}</div></td>
            <td data-label="Status">
                <div class="status-action-wrap">
                    <span class="status-badge ${(ev.EventStatus||'scheduled').toLowerCase()}">
                        <ion-icon name="${isCompleted?'checkmark-circle-outline':ev.EventStatus==='Ongoing'?'time-outline':ev.EventStatus==='Cancelled'?'close-outline':ev.EventStatus==='Delayed'?'hourglass-outline':'calendar-outline'}"></ion-icon>
                        ${ev.EventStatus||'Scheduled'}
                    </span>
                    ${isCompleted ? '' : `
                    <select class="org-status-select" onchange="handleOverrideChange(${ev.EventId}, this.value, this)" title="Override status">
                        <option value="" disabled selected>Override</option>
                        ${isInterrupted ? `
                            <option value="Reschedule">Reschedule</option>
                        ` : `
                            <option value="Cancelled">Cancelled</option>
                            <option value="Delayed">Delayed</option>
                        `}
                    </select>
                    `}
                </div>
            </td>
            <td class="action-cell" data-label="">
                <div class="actions-cell">
                    <button class="action-icon-btn view-btn" onclick='openViewEvent(${JSON.stringify(ev).replace(/'/g, "&#39;")})' title="View Details">
                        <ion-icon name="eye-outline"></ion-icon>
                    </button>
                    <button class="action-icon-btn edit-btn" onclick='openEditEvent(${JSON.stringify(ev).replace(/'/g, "&#39;")})' title="Edit Event">
                        <ion-icon name="document-text-outline"></ion-icon>
                    </button>
                    ${isInterrupted ? `
                    <button class="action-icon-btn reschedule-btn" onclick='openReschedule(${JSON.stringify(ev).replace(/'/g, "&#39;")})' title="Reschedule Event">
                        <ion-icon name="calendar-number-outline"></ion-icon>
                    </button>` : ''}
                    ${isOnline ? `
                    <button class="action-icon-btn" onclick='openAntiSpoofing(${ev.EventId}, ${JSON.stringify(ev.EventName).replace(/'/g, "&#39;")})' title="Activate Anti-Spoofing" style="background:#f0fdf4;border:1.5px solid #4ade80;color:#16a34a;">
                        <ion-icon name="shield-checkmark-outline"></ion-icon>
                    </button>
                    <button class="action-icon-btn" onclick='openPresenceCheckModal(${ev.EventId}, ${JSON.stringify(ev.EventName).replace(/'/g, "&#39;")})' title="Trigger Random Presence Check" style="background:#eff6ff;border:1.5px solid #60a5fa;color:#2563eb;">
                        <ion-icon name="radio-outline"></ion-icon>
                    </button>` : ''}
                    <button class="action-icon-btn delete-btn" onclick='deleteEvent(${ev.EventId})' title="Delete Event">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </div>
            </td>
        </tr>
    `}).join('');
}

function handleOverrideChange(eventId, selectedVal, selectEl) {
    if (!selectedVal) return;
    const ev = allEvents.find(e => e.EventId == eventId);
    if (selectedVal === 'Reschedule') {
        if (ev) openReschedule(ev);
    } else {
        updateEventStatus(eventId, selectedVal);
    }
    if (selectEl) selectEl.selectedIndex = 0;
}

function updateEventStatus(eventId, newStatus) {
    if (!newStatus) return;
    
    const ev = allEvents.find(e => e.EventId == eventId);
    if (ev) ev.EventStatus = newStatus;
    
    applyFilters();
    renderTestStatusList();

    const fd = new FormData();
    fd.append('EventId', eventId);
    fd.append('EventStatus', newStatus);
    
    fetch('../../config/API/update_org_event_status.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success);
        if (d.success) loadEvents();
    })
    .catch(e => showToast("Error updating status", false));
}

function deleteEvent(eventId) {
    if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) return;
    
    const fd = new FormData();
    fd.append('EventId', eventId);
    
    fetch('../../config/API/delete_org_event.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success);
        if (d.success) loadEvents();
    })
    .catch(e => showToast("Error deleting event", false));
}

function previewPoster(input) {
    const p = document.getElementById('evPosterPreview');
    if (input.files && input.files[0]) {
        p.src = URL.createObjectURL(input.files[0]);
        p.style.display = 'block';
    }
}

function applyFilters() {
    const searchEl = document.getElementById('evSearch');
    const statusEl = document.getElementById('statusFilter');
    const sortEl   = document.getElementById('sortFilter');

    const searchStr = searchEl ? searchEl.value.toLowerCase().trim() : '';
    const statusStr = statusEl ? statusEl.value.toLowerCase().trim() : '';
    const sortVal   = sortEl   ? sortEl.value : 'date-desc';

    let filtered = allEvents.filter(ev => {
        const name = (ev.EventName || '').toLowerCase();
        const loc  = (ev.EventPlace || ev.EventLocation || '').toLowerCase();
        const status = (ev.EventStatus || 'Scheduled').toLowerCase();
        
        const matchesSearch = !searchStr || name.includes(searchStr) || loc.includes(searchStr);
        const matchesStatus = !statusStr || status === statusStr;
        
        return matchesSearch && matchesStatus;
    });

    filtered.sort((a, b) => {
        if (sortVal === 'date-asc') {
            return new Date(a.EventDateTime || 0) - new Date(b.EventDateTime || 0);
        } else if (sortVal === 'date-desc') {
            return new Date(b.EventDateTime || 0) - new Date(a.EventDateTime || 0);
        } else if (sortVal === 'name-asc') {
            return (a.EventName || '').localeCompare(b.EventName || '');
        } else if (sortVal === 'name-desc') {
            return (b.EventName || '').localeCompare(a.EventName || '');
        }
        return 0;
    });

    renderEvents(filtered);
}

function setupFilterListeners() {
    const evSearch = document.getElementById('evSearch');
    const statusFilter = document.getElementById('statusFilter');
    const sortFilter = document.getElementById('sortFilter');

    if (evSearch) evSearch.addEventListener('input', applyFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFilters);
    if (sortFilter) sortFilter.addEventListener('change', applyFilters);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupFilterListeners);
} else {
    setupFilterListeners();
}

loadEvents();


let _rsCurrentEvent = null;

function openReschedule(ev) {
    _rsCurrentEvent = ev;
    document.getElementById('rsEventId').value   = ev.EventId;
    document.getElementById('rsEventName').textContent = ev.EventName;
    const statusEl = document.getElementById('rsStatus');
    statusEl.textContent = ev.EventStatus;
    statusEl.className = 'rs-status-badge ' + (ev.EventStatus||'').toLowerCase();
    
    const today = new Date().toISOString().split('T')[0];
    const rsDateInput = document.getElementById('rsDate');
    if (rsDateInput) rsDateInput.min = today;
    
    const dt = ev.EventDateTime ? ev.EventDateTime.split(' ') : ['',''];
    document.getElementById('rsDate').value  = dt[0] || '';
    document.getElementById('rsTime').value  = dt[1] ? dt[1].substring(0,5) : '';

    const endDt = ev.EndDateTime ? ev.EndDateTime.split(' ') : ['',''];
    document.getElementById('rsTimeEnd').value = endDt[1] ? endDt[1].substring(0,5) : '';

    document.getElementById('rsPlace').value = ev.EventPlace || ev.EventLocation || '';
    document.getElementById('rescheduleModal').classList.add('open');
}

function closeReschedule() {
    document.getElementById('rescheduleModal').classList.remove('open');
}

async function saveReschedule() {
    const id      = document.getElementById('rsEventId').value;
    const date    = document.getElementById('rsDate').value;
    const time    = document.getElementById('rsTime').value;
    const timeEnd = document.getElementById('rsTimeEnd').value;
    const place   = document.getElementById('rsPlace').value.trim();
    if (!date || !time || !place) { showToast('Please fill in Date, Start Time, and Venue', false); return; }

    const current = _rsCurrentEvent || {};
    const btn = document.getElementById('rsSaveBtn');
    btn.disabled = true; btn.textContent = 'Saving…';

    try {
        const startDt = date + ' ' + time + ':00';
        const endDt   = timeEnd ? (date + ' ' + timeEnd + ':00') : '';

        const fd = new FormData();
        fd.append('EventId',          id);
        fd.append('EventDateTime',    startDt);
        if (endDt) fd.append('EndDateTime', endDt);
        fd.append('EventPlace',       place);
        fd.append('EventLocation',    place);
        fd.append('EventName',        current.EventName        || '');
        fd.append('EventDescription', current.EventDescription || '');
        fd.append('EventSpeaker',     current.EventSpeaker     || '');
        fd.append('EventCapacity',    current.EventCapacity    || 0);
        fd.append('EventType',        current.EventType        || '');
        fd.append('EventMode',        current.EventMode        || '');
        fd.append('AttendanceMethod', current.AttendanceMethod || '');
        fd.append('EventStatus',      'Scheduled');

        const res1  = await fetch('../../config/API/update_org_event.php', { method: 'POST', body: fd });
        const data1 = await res1.json();

        if (!data1.success) {
            showToast(data1.message || 'Failed to save changes', false);
            return;
        }

        const fd2 = new FormData();
        fd2.append('EventId',     id);
        fd2.append('EventStatus', 'Scheduled');
        await fetch('../../config/API/update_org_event_status.php', { method: 'POST', body: fd2 });

        showToast('Event rescheduled & set to Scheduled ✓', true);
        closeReschedule();
        loadEvents();

    } catch(e) {
        showToast('Network error — please try again', false);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save & Set to Scheduled';
    }
}


function openTestModal() {
    renderTestStatusList();
    openM('testStatusModal');
}

function renderTestStatusList() {
    const tbody = document.getElementById('testStatusList');
    if (!tbody) return;
    if (!allEvents || allEvents.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;color:#94a3b8;">No events available</td></tr>';
        return;
    }

    const statuses = ['Scheduled', 'Ongoing', 'Delayed', 'Cancelled', 'Completed'];
    const activeStyles = {
        'Scheduled': { bg: '#2563eb', color: '#ffffff', border: '#1d4ed8' },
        'Ongoing':   { bg: '#d97706', color: '#ffffff', border: '#b45309' },
        'Delayed':   { bg: '#ea580c', color: '#ffffff', border: '#c2410c' },
        'Cancelled': { bg: '#dc2626', color: '#ffffff', border: '#b91c1c' },
        'Completed': { bg: '#16a34a', color: '#ffffff', border: '#15803d' }
    };

    tbody.innerHTML = allEvents.map(ev => {
        const curStatus = ev.EventStatus || 'Scheduled';
        const btns = statuses.map(s => {
            const isCur = curStatus.toLowerCase() === s.toLowerCase();
            const style = isCur ? activeStyles[s] : { bg: '#f1f5f9', color: '#475569', border: '#cbd5e1' };
            const label = isCur ? `✓ ${s}` : s;
            return `<button type="button" onclick="updateEventStatus(${ev.EventId}, '${s}')" 
                style="padding:5px 12px;font-size:0.78rem;font-weight:700;border:1.5px solid ${style.border};border-radius:6px;background:${style.bg};color:${style.color};cursor:pointer;margin:2px;box-shadow:${isCur ? '0 2px 5px rgba(0,0,0,0.15)' : 'none'};transition:all 0.15s;">
                ${label}
            </button>`;
        }).join('');

        return `<tr style="border-bottom:1px solid #f1f5f9;">
            <td style="padding:12px 14px;font-weight:600;color:#0f172a;">${ev.EventName}</td>
            <td style="padding:12px 14px;"><span class="status-badge ${curStatus.toLowerCase()}">${curStatus}</span></td>
            <td style="padding:12px 14px;text-align:right;">${btns}</td>
        </tr>`;
    }).join('');
}


function openAntiSpoofing(eventId, eventName) {
    const existing = document.getElementById('antiSpoofingModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'antiSpoofingModal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
        <div style="background:#fff;border-radius:18px;padding:32px;max-width:420px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);animation:modalPop 0.2s ease;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="width:44px;height:44px;background:#f0fdf4;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <ion-icon name="shield-checkmark-outline" style="font-size:24px;color:#16a34a;"></ion-icon>
                </div>
                <div>
                    <h3 style="margin:0;font-size:16px;color:#0f172a;">Activate Anti-Spoofing</h3>
                    <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Online attendance verification</p>
                </div>
            </div>
            <div style="background:#f8fafc;border-radius:10px;padding:14px;margin-bottom:20px;border:1px solid #e2e8f0;">
                <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;">Event</p>
                <p style="margin:0;font-size:15px;font-weight:700;color:#0f172a;">${eventName}</p>
            </div>
            <div style="font-size:13px;color:#475569;line-height:1.6;margin-bottom:20px;">
                <p style="margin:0 0 8px;">Students will see a <strong>5-minute readiness countdown</strong>, then the attendance scan will activate.</p>
                <p style="margin:0;">Grace period: <strong>15 minutes</strong> to complete attendance.</p>
            </div>
            <div style="display:flex;gap:10px;">
                <button onclick="document.getElementById('antiSpoofingModal').remove()" style="flex:1;padding:12px;border:1.5px solid #e2e8f0;border-radius:10px;background:#f8fafc;font-weight:600;font-size:14px;cursor:pointer;color:#475569;">Cancel</button>
                <button id="antiSpoofActivateBtn" onclick="activateAntiSpoofing(${eventId})" style="flex:2;padding:12px;border:none;border-radius:10px;background:#16a34a;color:#fff;font-weight:700;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <ion-icon name="shield-checkmark-outline"></ion-icon> Activate Now
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
}

async function activateAntiSpoofing(eventId) {
    const btn = document.getElementById('antiSpoofActivateBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Activating...'; }
    try {
        const fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('grace_minutes', 15);
        const r = await fetch('../../config/API/trigger_antispoofing.php', { method: 'POST', body: fd });
        const data = await r.json();
        document.getElementById('antiSpoofingModal')?.remove();
        if (data.success) {
            showToast('Anti-spoofing activated — students are now notified', true);
        } else {
            showToast(data.message || 'Failed to activate', false);
        }
    } catch(e) {
        showToast('Network error', false);
    }
}


function openPresenceCheckModal(eventId, eventName) {
    const existing = document.getElementById('presenceCheckModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'presenceCheckModal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
        <div style="background:#fff;border-radius:18px;padding:32px;max-width:420px;width:100%;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                <div style="width:44px;height:44px;background:#eff6ff;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <ion-icon name="radio-outline" style="font-size:24px;color:#2563eb;"></ion-icon>
                </div>
                <div>
                    <h3 style="margin:0;font-size:16px;color:#0f172a;">Periodic Presence Check</h3>
                    <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Random attendance ping</p>
                </div>
            </div>
            <div style="background:#f8fafc;border-radius:10px;padding:14px;margin-bottom:20px;border:1px solid #e2e8f0;">
                <p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;">Event</p>
                <p style="margin:0;font-size:15px;font-weight:700;color:#0f172a;">${eventName}</p>
            </div>
            <div style="font-size:13px;color:#475569;line-height:1.6;margin-bottom:20px;">
                <p style="margin:0 0 8px;">Pings all online attending students to confirm they are still present.</p>
                <p style="margin:0 0 12px;">Students receive a <strong>sound alert & 90-second countdown</strong> to click "I'm Still Here" or do a quick 3s face scan.</p>
                <label style="display:block;font-size:12px;font-weight:600;color:#334155;margin-bottom:6px;">Countdown Duration:</label>
                <select id="presenceDurationSelect" style="width:100%;padding:10px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
                    <option value="60">60 Seconds (1 Minute)</option>
                    <option value="90" selected>90 Seconds (1.5 Minutes)</option>
                    <option value="120">120 Seconds (2 Minutes)</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;">
                <button onclick="document.getElementById('presenceCheckModal').remove()" style="flex:1;padding:12px;border:1.5px solid #e2e8f0;border-radius:10px;background:#f8fafc;font-weight:600;font-size:14px;cursor:pointer;color:#475569;">Cancel</button>
                <button id="presenceTriggerBtn" onclick="triggerPresenceCheck(${eventId})" style="flex:2;padding:12px;border:none;border-radius:10px;background:#2563eb;color:#fff;font-weight:700;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <ion-icon name="radio-outline"></ion-icon> Ping Presence Now
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
}

async function triggerPresenceCheck(eventId) {
    const btn = document.getElementById('presenceTriggerBtn');
    const dur = document.getElementById('presenceDurationSelect')?.value || 90;
    if (btn) { btn.disabled = true; btn.textContent = 'Pinging...'; }
    try {
        const fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('duration_sec', dur);
        fd.append('action', 'trigger');
        const r = await fetch('../../config/API/trigger_presence_check.php', { method: 'POST', body: fd });
        const data = await r.json();
        document.getElementById('presenceCheckModal')?.remove();
        if (data.success) {
            showToast('Presence check ping sent to online students ✓', true);
        } else {
            showToast(data.message || 'Failed to trigger presence check', false);
        }
    } catch(e) {
        showToast('Network error', false);
    }
}