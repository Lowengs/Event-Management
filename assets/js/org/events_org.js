
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

function resetUploadBoxes() {
    const fields = [
        { inputId: 'evProposal', nameId: 'evProposalName', boxId: 'evProposalBox', hint: 'PDF, DOC, DOCX (Max 10MB)', icon: 'cloud-upload-outline' },
        { inputId: 'evProgramFlow', nameId: 'evProgramFlowName', boxId: 'evProgramFlowBox', hint: 'PDF, DOC, DOCX (Max 10MB)', icon: 'document-text-outline' },
        { inputId: 'evPicture', nameId: 'evPictureName', boxId: 'evPictureBox', hint: 'PNG, JPG, WEBP (Max 5MB)', icon: 'image-outline' },
        { inputId: 'evOther', nameId: 'evOtherName', boxId: 'evOtherBox', hint: 'Any file format (Max 10MB)', icon: 'folder-open-outline' },
        { inputId: 'evFinReport', nameId: 'evFinReportName', boxId: 'evFinReportBox', hint: 'PDF, DOC, XLSX (Max 10MB)', icon: 'cash-outline' }
    ];

    fields.forEach(f => {
        const inp = document.getElementById(f.inputId);
        const box = document.getElementById(f.boxId);
        const lbl = document.getElementById(f.nameId);
        if (inp) inp.value = '';
        if (box) {
            box.classList.remove('has-file');
            box.innerHTML = `<ion-icon name="${f.icon}" class="upload-svg-icon" style="font-size:22px;"></ion-icon>
<span class="upload-label">Click to upload file<br /><span style="font-size:11px;color:#94a3b8;">${f.hint}</span></span>`;
        }
        if (lbl) {
            lbl.textContent = 'No file selected';
            lbl.classList.remove('has-file');
        }
    });
}

function openAddEvent() {
    document.getElementById('eventFormTitle').textContent = 'Create New Event';
    document.getElementById('eventForm').reset();
    resetUploadBoxes();
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
    resetUploadBoxes();
    document.getElementById('evFormEventId').value = ev.EventId || '';
    document.getElementById('evName').value = ev.EventName || '';
    document.getElementById('evDesc').value = ev.EventDescription || ev.EventDetails || '';
    document.getElementById('evPlace').value = ev.EventPlace || ev.EventLocation || '';
    
    if (document.getElementById('evType')) document.getElementById('evType').value = ev.EventType || 'Seminar / Workshop';
    if (document.getElementById('evCapacity')) document.getElementById('evCapacity').value = ev.EventCapacity || '';
    if (document.getElementById('evSpeaker')) document.getElementById('evSpeaker').value = ev.EventSpeaker || '';

    const evDateEl = document.getElementById('evDate');
    if (evDateEl) evDateEl.removeAttribute('min');

    if (ev.EventDateTime) {
        const parts = ev.EventDateTime.split(' ');
        if (evDateEl) evDateEl.value = parts[0] || '';
        if (parts[1] && document.getElementById('evTimeStart')) {
            document.getElementById('evTimeStart').value = parts[1].substring(0,5);
        }
    }

    if (ev.EndDateTime && ev.EndDateTime !== '0000-00-00 00:00:00' && ev.EndDateTime !== '') {
        const parts = ev.EndDateTime.split(' ');
        if (parts[1] && document.getElementById('evTimeEnd')) {
            document.getElementById('evTimeEnd').value = parts[1].substring(0,5);
        }
    } else {
        if (document.getElementById('evTimeEnd')) document.getElementById('evTimeEnd').value = '';
    }
    
function handleModeChange(mode) {
    const placeEl = document.getElementById('evPlace');
    if (!placeEl) return;
    if (mode === 'Online') {
        if (!placeEl.value || placeEl.value.trim() === '' || placeEl.value === 'On-site' || !placeEl.value.toLowerCase().includes('online')) {
            placeEl.value = 'Online';
        }
        placeEl.placeholder = 'Online (Zoom / MS Teams / GMeet)';
    } else if (mode === 'On-site') {
        if (placeEl.value === 'Online' || placeEl.value === 'Online (Zoom / MS Teams)') {
            placeEl.value = '';
        }
        placeEl.placeholder = 'e.g. Main Auditorium / Room 302';
    } else if (mode === 'Hybrid') {
        if (!placeEl.value || placeEl.value.trim() === '') {
            placeEl.value = 'Hybrid (Campus & Online)';
        }
        placeEl.placeholder = 'e.g. Auditorium & Zoom';
    }
}

    if (document.getElementById('evMode')) {
        document.getElementById('evMode').value = ev.EventMode || 'On-site';
        handleModeChange(ev.EventMode || 'On-site');
    }
    if (document.getElementById('attEnabled')) document.getElementById('attEnabled').checked = (ev.AttendanceEnabled == 1 || ev.AttendanceEnabled === '1');
    if (document.getElementById('attMethod')) document.getElementById('attMethod').value = ev.AttendanceMethod || 'Face & QR';

    // Poster / Pubmat preview
    const posterPrev = document.getElementById('evPosterPreview');
    if (posterPrev) {
        if (ev.EventPicture && ev.EventPicture.trim() !== '') {
            posterPrev.src = ev.EventPicture.startsWith('http') || ev.EventPicture.startsWith('/') ? ev.EventPicture : '../../' + ev.EventPicture;
            posterPrev.style.display = 'block';
        } else {
            posterPrev.style.display = 'none';
            posterPrev.src = '';
        }
    }

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
    document.getElementById('viewEvVenue').textContent = ev.EventPlace || ev.EventLocation || '—';
    document.getElementById('viewEvSpeaker').textContent = ev.EventSpeaker || '—';
    document.getElementById('viewEvCapacity').textContent = ev.EventCapacity || '—';
    const preRegEl = document.getElementById('viewEvPreReg');
    if (preRegEl) preRegEl.textContent = ev.pre_registered_count || 0;

    // Status badge
    const statusBadge = document.getElementById('viewEvStatusBadge');
    if (statusBadge) statusBadge.innerHTML = `<ion-icon name="ellipse" style="font-size:8px;"></ion-icon> ${ev.EventStatus || 'Scheduled'}`;

    // Mode badge
    const modeBadge = document.getElementById('viewEvModeBadge');
    if (modeBadge) modeBadge.textContent = ev.EventMode || '—';

    // End time
    const endTimeEl = document.getElementById('viewEvEndTime');
    if (endTimeEl) {
        if (ev.EndDateTime && ev.EndDateTime !== '0000-00-00 00:00:00' && ev.EndDateTime !== '') {
            const endParts = ev.EndDateTime.split(' ');
            if (endParts[1]) {
                const t = endParts[1].split(':');
                let h = parseInt(t[0]); const m = t[1];
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                endTimeEl.textContent = `${h.toString().padStart(2,'0')}:${m} ${ampm}`;
            } else endTimeEl.textContent = '—';
        } else endTimeEl.textContent = '—';
    }

    
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

    const url = mode === 'edit' ? '../../config/API/endpoints/index.php?action=update_org_event' : '../../config/API/endpoints/index.php?action=create_org_event';

    fetch(url, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success);
        if (d.success) { closeM('eventFormModal'); loadEvents(); }
    })
    .catch(e => showToast("Error saving event", false));
});

function loadEvents() {
    fetch('../../config/API/endpoints/index.php?action=get_org_events')
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            allEvents = data.events || [];
            const s = data.stats || {
                total: allEvents.length,
                upcoming: allEvents.filter(e => !['completed','ongoing','cancelled'].includes((e.EventStatus||'').toLowerCase())).length,
                ongoing: allEvents.filter(e => (e.EventStatus||'').toLowerCase() === 'ongoing').length,
                completed: allEvents.filter(e => (e.EventStatus||'').toLowerCase() === 'completed').length
            };
            if (document.getElementById('statEventsTotal'))     document.getElementById('statEventsTotal').textContent     = s.total     ?? 0;
            if (document.getElementById('statEventsUpcoming'))  document.getElementById('statEventsUpcoming').textContent  = s.upcoming  ?? 0;
            if (document.getElementById('statEventsOngoing'))   document.getElementById('statEventsOngoing').textContent   = s.ongoing   ?? 0;
            if (document.getElementById('statEventsCompleted')) document.getElementById('statEventsCompleted').textContent = s.completed ?? 0;
            
            applyFilters();
        }
    })
    .catch(() => {});
}

function applyFilters() {
    const q = (document.getElementById('evSearch')?.value || '').toLowerCase().trim();
    const st = (document.getElementById('statusFilter')?.value || '').toLowerCase().trim();
    const sort = document.getElementById('sortFilter')?.value || 'date-desc';

    let filtered = allEvents.filter(e => {
        const nameMatch = !q || (e.EventName || '').toLowerCase().includes(q) || (e.EventLocation || '').toLowerCase().includes(q);
        const statusMatch = !st || (e.EventStatus || '').toLowerCase() === st;
        return nameMatch && statusMatch;
    });

    if (sort === 'date-asc') {
        filtered.sort((a, b) => new Date(a.EventDateTime || 0) - new Date(b.EventDateTime || 0));
    } else if (sort === 'name-asc') {
        filtered.sort((a, b) => (a.EventName || '').localeCompare(b.EventName || ''));
    } else if (sort === 'name-desc') {
        filtered.sort((a, b) => (b.EventName || '').localeCompare(a.EventName || ''));
    } else {
        filtered.sort((a, b) => new Date(b.EventDateTime || 0) - new Date(a.EventDateTime || 0));
    }

    renderEvents(filtered);
}

function renderEvents(evs) {
    const tbody = document.getElementById('eventsTableBody');
    if (!evs || evs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:40px;color:#94a3b8;">No events found...</td></tr>';
        return;
    }
    
    // Sort completed events needing post-activity & financial report to the very top
    evs.sort((a, b) => {
        const order = { 'Completed': 1, 'Ongoing': 2, 'Scheduled': 3, 'Delayed': 4, 'Cancelled': 5 };
        const weightA = order[a.EventStatus] || 3;
        const weightB = order[b.EventStatus] || 3;
        if (weightA !== weightB) return weightA - weightB;
        return new Date(b.EventDateTime || 0) - new Date(a.EventDateTime || 0);
    });

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
        const savedMode = String(ev.EventMode || '').trim();
        const isOnline = ['online', 'hybrid'].includes(savedMode.toLowerCase()) || /zoom|teams|online/i.test(String(ev.EventPlace || ev.EventLocation || ''));
        const displayMode = isOnline && savedMode.toLowerCase() === 'on-site' ? 'Online' : (savedMode || 'On-site');
        const noFinancialReport = parseInt(ev.no_financial_report || ev.NoFinancialReport || 0) > 0;
        const reportsUploaded = parseInt(ev.post_report_uploaded || 0) > 0 && (parseInt(ev.financial_report_uploaded || 0) > 0 || noFinancialReport);
        const showOrangeAlert = isCompleted && !reportsUploaded;
        const hasAssessment = parseInt(ev.has_pretest || 0) > 0 && parseInt(ev.has_posttest || 0) > 0;
        const hasAnyAssessment = hasAssessment || parseInt(ev.has_assessment || 0) > 0 || parseInt(ev.has_pretest || 0) > 0 || parseInt(ev.has_posttest || 0) > 0;
        const showBlueAlert = !isCompleted && !hasAnyAssessment;

        return `
        <tr>
            <td class="event-name-cell" data-label="">
                <div class="event-title-cell" style="${showOrangeAlert ? 'color: #f97316; font-weight: 700;' : (showBlueAlert ? 'color: #3b82f6; font-weight: 700;' : '')}">
                    ${ev.EventName}
                    ${showOrangeAlert ? '<ion-icon name="alert-circle-outline" style="color: #f97316; font-size: 1.15rem; vertical-align: middle; margin-left: 4px;" title="Post-activity report pending"></ion-icon>' : ''}
                    ${showBlueAlert ? '<ion-icon name="alert-circle-outline" style="color: #3b82f6; font-size: 1.15rem; vertical-align: middle; margin-left: 4px;" title="Pre-Test / Post-Test not created yet"></ion-icon>' : ''}
                    <span class="event-subtitle">${displayMode}</span>
                </div>
            </td>
            <td data-label="Date &amp; Time">
                <div class="with-icon"><ion-icon name="calendar-outline"></ion-icon> ${evDate}</div>
                <div class="with-icon" style="font-size:0.85rem;color:#64748b;margin-top:2px;"><ion-icon name="time-outline"></ion-icon> ${evTime}</div>
            </td>
            <td data-label="Location" style="max-width: 140px; min-width: 120px;">
                <div class="with-icon" style="white-space: normal; word-break: break-word; line-height: 1.35; align-items: flex-start;">
                    <ion-icon name="location-outline" style="margin-top: 2px; flex-shrink: 0;"></ion-icon>
                    <span>${ev.EventPlace || '—'}</span>
                </div>
            </td>
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
                    ${(!isCompleted && ev.EventStatus !== 'Ongoing') ? `
                    <button class="action-icon-btn edit-btn" onclick='openEditEvent(${JSON.stringify(ev).replace(/'/g, "&#39;")})' title="Edit Event" style="background:#fdf4ff;border:1.5px solid #d8b4fe;color:#7c3aed;">
                        <ion-icon name="create-outline"></ion-icon>
                    </button>` : ''}
                    ${!isCompleted && !hasAnyAssessment ? `
                    <button class="action-icon-btn create-assessment-btn" onclick="window.location.href='assesment.php?event_id=${ev.EventId}'" title="${(ev.has_assessment || ev.has_pretest || ev.has_posttest) ? 'Manage Pre-Test & Post-Test' : 'Create Pre-Test / Post-Test'}" style="background:#eef2ff;border:1.5px solid #818cf8;color:#4f46e5;">
                        <ion-icon name="${(ev.has_assessment || ev.has_pretest || ev.has_posttest) ? 'journal-outline' : 'add-circle-outline'}"></ion-icon>
                    </button>` : ''}
                    ${isCompleted ? `
                    <button class="action-icon-btn upload-report-btn" onclick='openUploadPostReportModal(${ev.EventId}, ${JSON.stringify(ev.EventName).replace(/'/g, "&#39;")}, ${noFinancialReport ? 'true' : 'false'})' title="Upload Post-Activity / Financial Report" style="background:#fff7ed;border:1.5px solid #fdba74;color:#ea580c;">
                        <ion-icon name="document-text-outline"></ion-icon>
                    </button>
                    ${isOnline ? `<button class="action-icon-btn" onclick="setNoFinancialReport(${ev.EventId}, ${noFinancialReport ? 0 : 1})" title="${noFinancialReport ? 'Require a financial report' : 'Mark as no financial involvement'}" style="background:${noFinancialReport ? '#ecfdf5' : '#f8fafc'};border:1.5px solid ${noFinancialReport ? '#86efac' : '#cbd5e1'};color:${noFinancialReport ? '#15803d' : '#475569'};">
                        <ion-icon name="${noFinancialReport ? 'checkmark-circle-outline' : 'cash-outline'}"></ion-icon>
                    </button>` : ''}` : ''}
                    ${isInterrupted ? `
                    <button class="action-icon-btn reschedule-btn" onclick='openReschedule(${JSON.stringify(ev).replace(/'/g, "&#39;")})' title="Reschedule Event">
                        <ion-icon name="calendar-number-outline"></ion-icon>
                    </button>` : ''}
                    ${(isOnline && !isCompleted && ev.EventStatus === 'Ongoing') ? `
                    <button class="action-icon-btn" onclick='openAntiSpoofing(${ev.EventId}, ${JSON.stringify(ev.EventName).replace(/'/g, "&#39;")})' title="Activate Anti-Spoofing" style="background:#f0fdf4;border:1.5px solid #4ade80;color:#16a34a;">
                        <ion-icon name="shield-checkmark-outline"></ion-icon>
                    </button>
                    <button class="action-icon-btn" onclick='openPresenceCheckModal(${ev.EventId}, ${JSON.stringify(ev.EventName).replace(/'/g, "&#39;")})' title="Trigger Periodic Presence Check" style="background:#eff6ff;border:1.5px solid #60a5fa;color:#2563eb;">
                        <ion-icon name="time-outline"></ion-icon>
                    </button>` : ''}
                    <button class="action-icon-btn delete-btn" onclick='deleteEvent(${ev.EventId})' title="Delete Event">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </div>
            </td>
        </tr>
    `}).join('');
}

function openUploadPostReportModal(eventId, eventName, noFinancialReport = false) {
    document.getElementById('reportEventId').value = eventId;
    document.getElementById('reportEventNameDisplay').value = eventName;
    document.getElementById('uploadReportModal').dataset.noFinancialReport = noFinancialReport ? '1' : '0';
    selectReportUploadType('PostActivityReport', eventName, noFinancialReport);
    openM('uploadReportModal');
}

function selectReportUploadType(type, eventName = '', noFinancialReport = false) {
    const isFinancial = type === 'FinancialReport';
    const typeInput = document.getElementById('reportDocType');
    const titleInput = document.getElementById('reportTitle');
    const fileLabel = document.getElementById('reportFileLabel');
    const eventNameInput = document.getElementById('reportEventNameDisplay');
    const name = eventName || (eventNameInput ? eventNameInput.value : '');
    const modal = document.getElementById('uploadReportModal');
    const noFinance = noFinancialReport || (modal && modal.dataset.noFinancialReport === '1');

    if (isFinancial && noFinance) {
        showModal('This event is marked as having no financial involvement. Change that setting first to upload a financial report.', 'warning', 'No Financial Report Required');
        return;
    }
    if (typeInput) typeInput.value = type;
    if (titleInput) titleInput.value = (isFinancial ? 'Financial Report - ' : 'Post-Activity Report - ') + name;
    if (fileLabel) fileLabel.textContent = isFinancial ? 'Financial Report File' : 'Post-Activity Report File';

    document.querySelectorAll('.report-type-toggle').forEach(btn => {
        const active = btn.dataset.reportType === type;
        btn.classList.toggle('active', active);
        btn.style.borderColor = active ? '#ea580c' : '#cbd5e1';
        btn.style.background = active ? '#fff7ed' : '#fff';
        btn.style.color = active ? '#c2410c' : '#475569';
    });
}

function submitPostActivityReport(e) {
    e.preventDefault();
    const btn = document.getElementById('uploadReportSubmitBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Uploading...'; }

    const fd = new FormData(document.getElementById('uploadReportForm'));
    fetch('../../config/API/endpoints/index.php?action=upload_org_event_reports', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
    const ev = allEvents.find(e => e.EventId == eventId);
    if (ev) ev.EventStatus = newStatus;
    
    applyFilters();
    renderTestStatusList();

    const fd = new FormData();
    fd.append('EventId', eventId);
    fd.append('EventStatus', newStatus);
    
    fetch('../../config/API/endpoints/index.php?action=update_org_event_status', { method: 'POST', body: fd })
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
    
    fetch('../../config/API/endpoints/index.php?action=delete_org_event', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        showToast(d.message, d.success);
        if (d.success) loadEvents();
    })
    .catch(e => showToast("Error deleting event", false));
}

function handleFileSelect(input, nameId, boxId, hint) {
    const box = document.getElementById(boxId);
    const lbl = document.getElementById(nameId);
    if (input.files && input.files.length > 0) {
        const raw = input.files.length > 1 ? input.files.length + ' files selected' : input.files[0].name;
        const name = raw.length > 35 ? raw.substring(0, 32) + '...' : raw;
        if (box) {
            box.innerHTML = `<ion-icon name="checkmark-circle-outline" style="font-size:26px;color:#10b981;margin-bottom:4px;"></ion-icon>
<p style="font-size:12px;font-weight:700;color:#1e293b;margin:0 0 2px;">${name}</p>
<p style="font-size:11px;color:#64748b;font-weight:500;margin:0;">Click to change file</p>`;
            box.classList.add('has-file');
        }
        if (lbl) {
            lbl.textContent = raw;
            lbl.classList.add('has-file');
        }
    } else {
        if (box) {
            box.innerHTML = `<ion-icon name="cloud-upload-outline" class="upload-svg-icon" style="font-size:22px;"></ion-icon>
<span class="upload-label">Click to upload file<br /><span style="font-size:11px;color:#94a3b8;">${hint}</span></span>`;
            box.classList.remove('has-file');
        }
        if (lbl) {
            lbl.textContent = 'No file selected';
            lbl.classList.remove('has-file');
        }
    }
}

function previewPoster(input) {
    const p = document.getElementById('evPosterPreview');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const ext = file.name.split('.').pop().toLowerCase();
        const validExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!validExts.includes(ext) || (file.type && !file.type.startsWith('image/'))) {
            showToast('Pubmat must be an image file (PNG, JPG, WEBP, GIF)', false);
            input.value = '';
            handleFileSelect(input, 'evPictureName', 'evPictureBox', 'PNG, JPG, WEBP (Max 5MB)');
            if (p) {
                p.style.display = 'none';
                p.src = '';
            }
            return;
        }
        handleFileSelect(input, 'evPictureName', 'evPictureBox', 'PNG, JPG, WEBP (Max 5MB)');
        if (p) {
            p.src = URL.createObjectURL(file);
            p.style.display = 'block';
        }
    } else {
        handleFileSelect(input, 'evPictureName', 'evPictureBox', 'PNG, JPG, WEBP (Max 5MB)');
        if (p) {
            p.style.display = 'none';
            p.src = '';
        }
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
        const status = (ev.EventStatus || 'Scheduled').toLowerCase().trim();
        
        const matchesSearch = !searchStr || name.includes(searchStr) || loc.includes(searchStr);
        let matchesStatus = !statusStr || status === statusStr;
        if (statusStr === 'scheduled' && (status === 'upcoming' || status === 'scheduled')) matchesStatus = true;
        if (statusStr === 'upcoming' && (status === 'scheduled' || status === 'upcoming')) matchesStatus = true;
        
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

        const res1  = await fetch('../../config/API/endpoints/index.php?action=update_org_event', { method: 'POST', body: fd });
        const data1 = await res1.json();

        if (!data1.success) {
            showToast(data1.message || 'Failed to save changes', false);
            return;
        }

        const fd2 = new FormData();
        fd2.append('EventId',     id);
        fd2.append('EventStatus', 'Scheduled');
        await fetch('../../config/API/endpoints/index.php?action=update_org_event_status', { method: 'POST', body: fd2 });

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

async function updateEventStatus(eventId, newStatus) {
    try {
        const fd = new FormData();
        fd.append('EventId', eventId);
        fd.append('EventStatus', newStatus);
        
        const res = await fetch('../../config/API/endpoints/index.php?action=update_org_event_status', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            showToast(data.message || `Status updated to ${newStatus}`, true);
            loadEvents();
            setTimeout(renderTestStatusList, 300);
        } else {
            showToast(data.message || 'Error updating status', false);
        }
    } catch(e) {
        showToast('Network error updating event status', false);
    }
}
window.updateEventStatus = updateEventStatus;


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
                <p style="margin:0;">Anti-spoofing activates immediately for the selected event.</p>
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
        const r = await fetch('../../config/API/endpoints/index.php?action=trigger_antispoofing', { method: 'POST', body: fd });
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
                <p style="margin:0;">The presence-check notification activates immediately for testing.</p>
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
    const dur = 0;
    if (btn) { btn.disabled = true; btn.textContent = 'Pinging...'; }
    try {
        const fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('duration_sec', dur);
        fd.append('action', 'trigger');
        const r = await fetch('../../config/API/endpoints/index.php?action=trigger_presence_check', { method: 'POST', body: fd });
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

document.addEventListener('DOMContentLoaded', () => {
    loadEvents();
    document.getElementById('evSearch')?.addEventListener('input', applyFilters);
    document.getElementById('statusFilter')?.addEventListener('change', applyFilters);
    document.getElementById('sortFilter')?.addEventListener('change', applyFilters);
});
if (document.readyState === 'interactive' || document.readyState === 'complete') {
    loadEvents();
}

function setNoFinancialReport(eventId, noFinancialReport) {
    const message = noFinancialReport
        ? 'Mark this online event as having no financial involvement? OSA will be informed.'
        : 'Require a financial report for this event again?';
    if (!confirm(message)) return;
    const fd = new FormData();
    fd.append('EventId', eventId);
    fd.append('NoFinancialReport', noFinancialReport);
    fetch('../../config/API/endpoints/index.php?action=set_org_event_no_finance', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.success) loadEvents(); else showModal(data.message || 'Unable to update financial report requirement', 'error', 'Error'); })
        .catch(() => showModal('Network error while updating the financial report requirement', 'error', 'Network Error'));
}
