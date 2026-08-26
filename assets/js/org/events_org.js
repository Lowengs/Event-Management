
let allEvents = [];

function openM(id) { 
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('active');
    const content = el.querySelector('.modal-content');
    if (content) content.scrollTop = 0;
    const body = el.querySelector('.modal-body');
    if (body) body.scrollTop = 0;
}
function closeM(id) { 
    const el = document.getElementById(id);
    if (el) el.classList.remove('active'); 
}

function showToast(msg, ok = true) {
    const t = document.getElementById('toast');
    if (t) {
        t.textContent = msg;
        t.style.background = ok ? '#16a34a' : '#dc2626';
        t.style.display = 'block';
        setTimeout(() => { if (t) t.style.display = 'none'; }, 3500);
    } else if (typeof showModal === 'function') {
        showModal(msg, ok ? 'success' : 'error');
    }
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

    if (document.getElementById('evPosterPreview')) document.getElementById('evPosterPreview').style.display = 'none';
    if (document.getElementById('evAudience')) document.getElementById('evAudience').value = 'members';
    document.getElementById('saveEventBtn').dataset.mode = 'create';
    openM('eventFormModal');
}

function handleModeChange(mode) {
    const placeEl = document.getElementById('evPlace');
    if (!placeEl) return;
    if (mode === 'Online') {
        if (!placeEl.value || placeEl.value.trim() === '' || placeEl.value === 'On-site' || !placeEl.value.toLowerCase().includes('online')) {
            placeEl.value = 'Online (Zoom / MS Teams)';
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
window.handleModeChange = handleModeChange;

function openEditEvent(evInput) {
    let ev = evInput;
    if (typeof evInput === 'number' || typeof evInput === 'string') {
        ev = (typeof allEvents !== 'undefined' && Array.isArray(allEvents)) ? allEvents.find(e => String(e.EventId) === String(evInput)) : null;
    }
    if (!ev) return;

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
            document.getElementById('evTimeStart').value = parts[1].substring(0, 5);
        }
    }

    if (ev.EndDateTime && ev.EndDateTime !== '0000-00-00 00:00:00' && ev.EndDateTime !== '') {
        const parts = ev.EndDateTime.split(' ');
        if (parts[1] && document.getElementById('evTimeEnd')) {
            document.getElementById('evTimeEnd').value = parts[1].substring(0, 5);
        }
    } else {
        if (document.getElementById('evTimeEnd')) document.getElementById('evTimeEnd').value = '';
    }

    if (document.getElementById('evMode')) {
        document.getElementById('evMode').value = ev.EventMode || 'On-site';
        handleModeChange(ev.EventMode || 'On-site');
    }
    if (document.getElementById('evAudience')) {
        document.getElementById('evAudience').value = (ev.Audience || 'all').toLowerCase() === 'members' ? 'members' : 'all';
    }
    if (document.getElementById('attEnabled')) document.getElementById('attEnabled').checked = (ev.AttendanceEnabled == 1 || ev.AttendanceEnabled === '1');
    if (document.getElementById('attMethod')) document.getElementById('attMethod').value = ev.AttendanceMethod || 'Face & QR';

    // Poster / Pubmat preview
    const posterPrev = document.getElementById('evPosterPreview');
    const picBox = document.getElementById('evPictureBox');
    const picLbl = document.getElementById('evPictureName');
    if (ev.EventPicture && ev.EventPicture.trim() !== '') {
        const picSrc = ev.EventPicture.startsWith('http') || ev.EventPicture.startsWith('/') ? ev.EventPicture : '../../' + ev.EventPicture;
        const picName = ev.EventPicture.split('/').pop();
        if (posterPrev) {
            posterPrev.src = picSrc;
            posterPrev.style.display = 'block';
        }
        if (picBox) {
            picBox.classList.add('has-file');
            picBox.innerHTML = `<ion-icon name="image-outline" class="upload-svg-icon" style="font-size:26px;color:#10b981;margin-bottom:4px;"></ion-icon>
<p style="font-size:12px;font-weight:700;color:#1e293b;margin:0 0 2px;">${picName}</p>
<p style="font-size:11px;color:#16a34a;font-weight:600;margin:0;">[✓ Pubmat Attached] • Click to replace</p>`;
        }
        if (picLbl) {
            picLbl.textContent = 'Current pubmat: ' + picName;
            picLbl.classList.add('has-file');
        }
    } else {
        if (posterPrev) {
            posterPrev.style.display = 'none';
            posterPrev.src = '';
        }
    }

    // Show existing attached documents in the upload dropboxes
    const docFields = [
        { doc: ev.EventProposalDoc || ev.EventProposal, boxId: 'evProposalBox', nameId: 'evProposalName', defaultHint: 'PDF, DOC, DOCX (Max 10MB)', icon: 'cloud-upload-outline' },
        { doc: ev.EventProgramFlowDoc || ev.EventProgramFlow, boxId: 'evProgramFlowBox', nameId: 'evProgramFlowName', defaultHint: 'PDF, DOC, DOCX (Max 10MB)', icon: 'document-text-outline' },
        { doc: ev.EventOtherDoc || ev.EventOther, boxId: 'evOtherBox', nameId: 'evOtherName', defaultHint: 'Any file format (Max 10MB)', icon: 'folder-open-outline' },
        { doc: ev.FinancialReportDoc || ev.EventFinancialReport, boxId: 'evFinReportBox', nameId: 'evFinReportName', defaultHint: 'PDF, DOC, XLSX (Max 10MB)', icon: 'cash-outline' }
    ];

    docFields.forEach(f => {
        const box = document.getElementById(f.boxId);
        const lbl = document.getElementById(f.nameId);
        if (f.doc) {
            let docName = typeof f.doc === 'object' ? (f.doc.Title || f.doc.FilePath.split('/').pop()) : f.doc.split('/').pop();
            if (box) {
                box.classList.add('has-file');
                box.innerHTML = `<ion-icon name="document-text-outline" class="upload-svg-icon" style="font-size:26px;color:#10b981;margin-bottom:4px;"></ion-icon>
<p style="font-size:12px;font-weight:700;color:#1e293b;margin:0 0 2px;">${docName}</p>
<p style="font-size:11px;color:#16a34a;font-weight:600;margin:0;">[✓ Attached File] • Click to replace</p>`;
            }
            if (lbl) {
                lbl.textContent = 'Current file: ' + docName;
                lbl.classList.add('has-file');
            }
        }
    });

    const saveBtn = document.getElementById('saveEventBtn');
    if (saveBtn) {
        saveBtn.dataset.mode = 'edit';
        saveBtn.innerHTML = '<ion-icon name="save-outline"></ion-icon> Update Event';
    }
    openM('eventFormModal');
}

function openViewEvent(evInput) {
    let ev = evInput;
    if (typeof evInput === 'number' || typeof evInput === 'string') {
        ev = (typeof allEvents !== 'undefined' && Array.isArray(allEvents)) ? allEvents.find(e => String(e.EventId) === String(evInput)) : null;
    }
    if (!ev) return;

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
        const end = new Date(ev.EndDateTime.replace(' ', 'T'));
        const diffMs = end - start;
        if (diffMs > 0) {
            const totalMins = Math.round(diffMs / 60000);
            const hrs = Math.floor(totalMins / 60);
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

    // Audience badge in details modal
    const audienceBadge = document.getElementById('viewEvAudienceBadge');
    if (audienceBadge) {
        const isMembers = (ev.Audience || 'all').toLowerCase() === 'members';
        if (isMembers) {
            audienceBadge.innerHTML = `<ion-icon name="lock-closed-outline"></ion-icon> Members Only`;
            audienceBadge.style.background = 'rgba(255, 255, 255, 0.25)';
        } else {
            audienceBadge.innerHTML = `<ion-icon name="globe-outline"></ion-icon> All Students`;
            audienceBadge.style.background = 'rgba(255, 255, 255, 0.15)';
        }
    }

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
                endTimeEl.textContent = `${h.toString().padStart(2, '0')}:${m} ${ampm}`;
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
        posterImg.onerror = function () { posterContainer.style.display = 'none'; };
    } else {
        posterContainer.style.display = 'none';
        posterImg.src = '';
    }

    openM('eventViewModal');
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
        const bothAssessmentsCreated = parseInt(ev.has_pretest || 0) > 0 && parseInt(ev.has_posttest || 0) > 0;
        const hasAnyAssessment = bothAssessmentsCreated || parseInt(ev.has_assessment || 0) > 0 || parseInt(ev.has_pretest || 0) > 0 || parseInt(ev.has_posttest || 0) > 0;
        const showBlueAlert = !isCompleted && !bothAssessmentsCreated;

        return `
        <tr>
            <td class="event-name-cell" data-label="">
                <div class="event-title-cell" style="${showOrangeAlert ? 'color: #f97316; font-weight: 700;' : (showBlueAlert ? 'color: #3b82f6; font-weight: 700;' : '')}">
                    ${ev.EventName}
                    <span class="type-pill ${displayMode.toLowerCase()}">${displayMode}</span>
                </div>
            </td>
            <td data-label="Date &amp; Time">
                <div class="with-icon"><ion-icon name="calendar-outline"></ion-icon> ${evDate}</div>
                <div class="with-icon" style="font-size:0.85rem;color:#64748b;margin-top:2px;"><ion-icon name="time-outline"></ion-icon> ${evTime}</div>
            </td>
            <td data-label="Location" style="max-width: 140px; min-width: 120px;">
                <div class="with-icon" style="white-space: normal; word-break: break-word; line-height: 1.35; align-items: flex-start;">
                    <ion-icon name="location-outline" style="margin-top: 2px; flex-shrink: 0;"></ion-icon>
                    <span>${ev.EventPlace || ev.EventLocation || (displayMode === 'Online' ? 'Online (Zoom / MS Teams)' : '—')}</span>
                </div>
            </td>
            <td data-label="Status">
                <div class="status-action-wrap">
                    <span class="status-badge ${(ev.EventStatus || 'scheduled').toLowerCase()}">
                        <ion-icon name="${isCompleted ? 'checkmark-circle-outline' : ev.EventStatus === 'Ongoing' ? 'time-outline' : ev.EventStatus === 'Cancelled' ? 'close-outline' : ev.EventStatus === 'Delayed' ? 'hourglass-outline' : 'calendar-outline'}"></ion-icon>
                        ${ev.EventStatus || 'Scheduled'}
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
                    <button class="action-icon-btn view-btn" onclick="openViewEvent(${ev.EventId})" title="View Details">
                        <ion-icon name="eye-outline"></ion-icon>
                    </button>
                    ${(!isCompleted && ev.EventStatus !== 'Ongoing') ? `
                    <button class="action-icon-btn edit-btn" onclick="openEditEvent(${ev.EventId})" title="Edit Event" style="background:#fdf4ff;border:1.5px solid #d8b4fe;color:#7c3aed;">
                        <ion-icon name="create-outline"></ion-icon>
                    </button>` : ''}
                    ${!isCompleted && !bothAssessmentsCreated ? `
                    <button class="action-icon-btn create-assessment-btn" onclick="window.location.href='assesment.php?event_id=${ev.EventId}'" title="${(parseInt(ev.has_pretest || 0) > 0 || parseInt(ev.has_posttest || 0) > 0) ? 'Manage Pre-Test & Post-Test' : 'Create Pre-Test / Post-Test'}" style="background:#eef2ff;border:1.5px solid #818cf8;color:#4f46e5;">
                        <ion-icon name="${(parseInt(ev.has_pretest || 0) > 0 || parseInt(ev.has_posttest || 0) > 0) ? 'journal-outline' : 'add-circle-outline'}"></ion-icon>
                    </button>` : ''}
                    ${isCompleted ? `
                    <button class="action-icon-btn upload-report-btn" onclick="openUploadPostReportModal(${ev.EventId})" title="Upload Post-Activity / Financial Report" style="background:#fff7ed;border:1.5px solid #fdba74;color:#ea580c;">
                        <ion-icon name="document-text-outline"></ion-icon>
                    </button>
                    ${!noFinancialReport ? `<button class="action-icon-btn mark-no-finance-btn" onclick="setNoFinancialReport(${ev.EventId}, 1)" title="Mark as No Financial Involvement" style="background:#ecfdf5;border:1.5px solid #86efac;color:#15803d;">
                        <ion-icon name="cash-outline"></ion-icon>
                    </button>` : ''}` : ''}
                    ${isInterrupted ? `
                    <button class="action-icon-btn reschedule-btn" onclick="openReschedule(${ev.EventId})" title="Reschedule Event">
                        <ion-icon name="calendar-number-outline"></ion-icon>
                    </button>` : ''}
                    ${(isOnline && !isCompleted && ev.EventStatus === 'Ongoing') ? `
                    <button class="action-icon-btn" onclick="openLiveMonitoringModal(${ev.EventId})" title="Live Verification & Anti-Spoofing Monitoring" style="background:#f0fdf4;border:1.5px solid #4ade80;color:#16a34a;">
                        <ion-icon name="shield-checkmark-outline"></ion-icon>
                    </button>` : ''}
                    <button class="action-icon-btn delete-btn" onclick="deleteEvent(${ev.EventId})" title="Delete Event">
                        <ion-icon name="trash-outline"></ion-icon>
                    </button>
                </div>
            </td>
        </tr>
    `}).join('');
}

function openUploadPostReportModal(eventIdInput, eventNameInput = '', noFinancialReport = false) {
    const ev = (typeof allEvents !== 'undefined' && Array.isArray(allEvents)) ? allEvents.find(e => String(e.EventId) === String(eventIdInput)) : null;
    const eventId = eventIdInput;
    const eventName = eventNameInput || (ev ? ev.EventName : '');
    
    const idEl = document.getElementById('reportEventId');
    if (idEl) idEl.value = eventId;
    const nameEl = document.getElementById('reportEventNameDisplay');
    if (nameEl) nameEl.value = eventName;
    
    const isNoFinance = (noFinancialReport == 1 || noFinancialReport === true || (ev && (ev.NoFinancialReport == 1 || ev.NoFinancialReport === '1' || ev.no_financial_report == 1)));

    const modal = document.getElementById('uploadReportModal');
    if (modal) modal.dataset.noFinancialReport = isNoFinance ? '1' : '0';

    // Hide or show the Financial Report toggle button based on noFinance status
    const finBtn = document.querySelector('.report-type-toggle[data-report-type="FinancialReport"]');
    if (finBtn) {
        finBtn.style.display = isNoFinance ? 'none' : 'block';
    }

    selectReportUploadType('PostActivityReport', eventName);
    openM('uploadReportModal');
}

function selectReportUploadType(type, eventName = '') {
    const isFinancial = type === 'FinancialReport';
    const typeInput = document.getElementById('reportDocType');
    const titleInput = document.getElementById('reportTitle');
    const fileLabel = document.getElementById('reportFileLabel');
    const eventNameInput = document.getElementById('reportEventNameDisplay');
    const eventIdInput = document.getElementById('reportEventId');
    const name = eventName || (eventNameInput ? eventNameInput.value : '');
    const eventId = eventIdInput ? eventIdInput.value : '';
    const modal = document.getElementById('uploadReportModal');
    const isNoFinance = modal && modal.dataset.noFinancialReport === '1';

    if (isFinancial && isNoFinance) {
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

    // Check if report has already been uploaded for this event
    const noticeBox = document.getElementById('alreadyUploadedNotice');
    const noticeTitle = document.getElementById('alreadyUploadedNoticeTitle');
    const noticeDesc = document.getElementById('alreadyUploadedNoticeDesc');

    if (noticeBox && eventId && typeof allEvents !== 'undefined' && Array.isArray(allEvents)) {
        const ev = allEvents.find(e => String(e.EventId) === String(eventId));
        if (ev) {
            const isUploaded = isFinancial ? (ev.financial_report_uploaded == 1 || ev.financial_report_uploaded === '1') : (ev.post_report_uploaded == 1 || ev.post_report_uploaded === '1');
            const docTitle = isFinancial ? (ev.financial_report_title || '') : (ev.post_report_title || '');

            if (isUploaded) {
                noticeBox.style.display = 'flex';
                if (noticeTitle) noticeTitle.textContent = (isFinancial ? 'Financial Report' : 'Post-Activity Report') + ' Already Uploaded';
                if (noticeDesc) {
                    noticeDesc.textContent = 'You have already submitted a report' + (docTitle ? ' ("' + docTitle + '")' : '') + ' for this event. Submitting a new file below will update your report if you misclicked the wrong file.';
                }
            } else {
                noticeBox.style.display = 'none';
            }
        } else {
            noticeBox.style.display = 'none';
        }
    }
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
                showToast(data.message || 'Report uploaded successfully', true);
                closeM('uploadReportModal');
                loadEvents();
            } else {
                showModal(data.message || 'Failed to upload report', 'error', 'Upload Failed');
            }
        })
        .catch(err => {
            showModal('Network error occurred while uploading report', 'error', 'Network Error');
        })
        .finally(() => {
            if (btn) { btn.disabled = false; btn.textContent = 'Submit Documentation'; }
        });
}

function handleOverrideChange(eventId, newStatus, selectEl) {
    if (!newStatus) return;
    if (selectEl) selectEl.value = '';

    if (newStatus === 'Reschedule') {
        const ev = allEvents.find(e => e.EventId == eventId);
        if (ev) openReschedule(ev);
        return;
    }

    if (newStatus === 'Cancelled') {
        showConfirmModal(
            'Are you sure you want to mark this event as <strong>Cancelled</strong>? Attendees will see this event as cancelled and live verification will be closed.',
            function() {
                updateEventStatus(eventId, 'Cancelled');
            },
            'Cancel Event',
            'danger'
        );
        return;
    }

    if (newStatus === 'Delayed') {
        showConfirmModal(
            'Are you sure you want to mark this event as <strong>Delayed</strong>?',
            function() {
                updateEventStatus(eventId, 'Delayed');
            },
            'Delay Event',
            'warning'
        );
        return;
    }

    updateEventStatus(eventId, newStatus);
}

function updateEventStatus(eventId, newStatus) {
    const ev = allEvents.find(e => e.EventId == eventId);
    if (ev) ev.EventStatus = newStatus;

    applyFilters();
    if (typeof renderTestStatusList === 'function') renderTestStatusList();

    const fd = new FormData();
    fd.append('EventId', eventId);
    fd.append('EventStatus', newStatus);

    fetch('../../config/API/endpoints/index.php?action=update_org_event_status', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                showModal(d.message || `Event status updated to ${newStatus}`, 'success', 'Status Updated');
                loadEvents();
            } else {
                showModal(d.message || 'Failed to update event status', 'error', 'Update Failed');
                loadEvents();
            }
        })
        .catch(e => {
            showModal('Error updating status: ' + (e.message || e), 'error', 'Error');
            loadEvents();
        });
}

function deleteEvent(eventId) {
    showConfirmModal(
        'Are you sure you want to delete this event? This action will permanently delete the event along with all attendance records, registrations, assessments, and reports.',
        function() {
            const fd = new FormData();
            fd.append('EventId', eventId);

            fetch('../../config/API/endpoints/index.php?action=delete_org_event', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showModal(d.message || 'Event deleted successfully.', 'success', 'Event Deleted');
                        loadEvents();
                    } else {
                        showModal(d.message || 'Failed to delete event.', 'error', 'Delete Failed');
                    }
                })
                .catch(e => {
                    showModal('Error deleting event: ' + (e.message || e), 'error', 'Error');
                });
        },
        'Delete Event',
        'danger'
    );
}

window.handleOverrideChange = handleOverrideChange;
window.updateEventStatus = updateEventStatus;
window.deleteEvent = deleteEvent;

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

function validateReportPdfSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'pdf' && file.type !== 'application/pdf') {
            showToast('Event reports must be in PDF format (.pdf) only', false);
            input.value = '';
            handleFileSelect(input, 'reportDocFileName', 'reportDocFileBox', 'PDF only (Max 25MB)');
            return;
        }
    }
    handleFileSelect(input, 'reportDocFileName', 'reportDocFileBox', 'PDF only (Max 25MB)');
}
window.validateReportPdfSelect = validateReportPdfSelect;

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
    const sortEl = document.getElementById('sortFilter');

    const searchStr = searchEl ? searchEl.value.toLowerCase().trim() : '';
    const statusStr = statusEl ? statusEl.value.toLowerCase().trim() : '';
    const sortVal = sortEl ? sortEl.value : 'date-desc';

    let filtered = allEvents.filter(ev => {
        const name = (ev.EventName || '').toLowerCase();
        const loc = (ev.EventPlace || ev.EventLocation || '').toLowerCase();
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

let _isEventsLoading = false;
let _eventPollInterval = null;

function loadEvents(isManual = false) {
    if (_isEventsLoading) return;
    _isEventsLoading = true;

    const syncIcon = document.getElementById('syncIcon');
    if (syncIcon && isManual) {
        syncIcon.style.transition = 'transform 0.6s ease';
        syncIcon.style.transform = 'rotate(360deg)';
        setTimeout(() => {
            if (syncIcon) {
                syncIcon.style.transition = 'none';
                syncIcon.style.transform = 'none';
            }
        }, 600);
    }

    fetch('../../config/API/endpoints/index.php?action=get_org_events')
        .then(r => r.json())
        .then(data => {
            _isEventsLoading = false;
            if (data.success) {
                allEvents = data.events || data.data || [];
                // Update stat cards
                if (data.stats) {
                    const el = id => document.getElementById(id);
                    if (el('statEventsTotal'))     el('statEventsTotal').textContent     = data.stats.total     ?? allEvents.length;
                    if (el('statEventsUpcoming'))   el('statEventsUpcoming').textContent   = data.stats.upcoming  ?? 0;
                    if (el('statEventsOngoing'))    el('statEventsOngoing').textContent    = data.stats.ongoing   ?? 0;
                    if (el('statEventsCompleted'))  el('statEventsCompleted').textContent  = data.stats.completed ?? 0;
                }
                applyFilters();
            } else {
                console.warn('get_org_events API error:', data.message);
                if (typeof initialEventsData !== 'undefined' && Array.isArray(initialEventsData) && initialEventsData.length > 0) {
                    allEvents = initialEventsData;
                    applyFilters();
                }
            }
        })
        .catch(err => {
            _isEventsLoading = false;
            console.error('loadEvents fetch error:', err);
            if (typeof initialEventsData !== 'undefined' && Array.isArray(initialEventsData) && initialEventsData.length > 0) {
                allEvents = initialEventsData;
                applyFilters();
                let total = allEvents.length, upcoming = 0, ongoing = 0, completed = 0;
                allEvents.forEach(ev => {
                    const s = (ev.EventStatus || 'Scheduled').toLowerCase();
                    if (s === 'completed') completed++;
                    else if (s === 'ongoing') ongoing++;
                    else upcoming++;
                });
                const el = id => document.getElementById(id);
                if (el('statEventsTotal'))     el('statEventsTotal').textContent     = total;
                if (el('statEventsUpcoming'))   el('statEventsUpcoming').textContent   = upcoming;
                if (el('statEventsOngoing'))    el('statEventsOngoing').textContent    = ongoing;
                if (el('statEventsCompleted'))  el('statEventsCompleted').textContent  = completed;
            }
        });
}

function startAutoSync() {
    if (_eventPollInterval) clearInterval(_eventPollInterval);
    // Poll every 10 seconds for real-time live event status updates without reloading
    _eventPollInterval = setInterval(() => {
        const activeModal = document.querySelector('.modal-wrapper.active, .modal.active, .custom-modal-overlay');
        const searchInput = document.getElementById('evSearch');
        if (activeModal || (searchInput && document.activeElement === searchInput)) {
            return; // Don't interrupt modal interactions or active search typing
        }
        loadEvents(false);
    }, 10000);
}

// Auto-sync immediately whenever the tab or window regains focus
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        loadEvents(false);
    }
});
window.addEventListener('focus', () => {
    loadEvents(false);
});

loadEvents();
startAutoSync();

let _rsCurrentEvent = null;

function openReschedule(evInput) {
    let ev = evInput;
    if (typeof evInput === 'number' || typeof evInput === 'string') {
        ev = (typeof allEvents !== 'undefined' && Array.isArray(allEvents)) ? allEvents.find(e => String(e.EventId) === String(evInput)) : null;
    }
    if (!ev) return;
    _rsCurrentEvent = ev;
    document.getElementById('rsEventId').value = ev.EventId;
    document.getElementById('rsEventName').textContent = ev.EventName;
    const statusEl = document.getElementById('rsStatus');
    statusEl.textContent = ev.EventStatus;
    statusEl.className = 'rs-status-badge ' + (ev.EventStatus || '').toLowerCase();

    const today = new Date().toISOString().split('T')[0];
    const rsDateInput = document.getElementById('rsDate');
    if (rsDateInput) rsDateInput.min = today;

    const dt = ev.EventDateTime ? ev.EventDateTime.split(' ') : ['', ''];
    document.getElementById('rsDate').value = dt[0] || '';
    document.getElementById('rsTime').value = dt[1] ? dt[1].substring(0, 5) : '';

    const endDt = ev.EndDateTime ? ev.EndDateTime.split(' ') : ['', ''];
    document.getElementById('rsTimeEnd').value = endDt[1] ? endDt[1].substring(0, 5) : '';

    document.getElementById('rsPlace').value = ev.EventPlace || ev.EventLocation || '';
    document.getElementById('rescheduleModal').classList.add('open');
}

function closeReschedule() {
    document.getElementById('rescheduleModal').classList.remove('open');
}

async function saveReschedule() {
    const id = document.getElementById('rsEventId').value;
    const date = document.getElementById('rsDate').value;
    const time = document.getElementById('rsTime').value;
    const timeEnd = document.getElementById('rsTimeEnd').value;
    const place = document.getElementById('rsPlace').value.trim();
    if (!date || !time || !place) { showToast('Please fill in Date, Start Time, and Venue', false); return; }

    const current = _rsCurrentEvent || {};
    const btn = document.getElementById('rsSaveBtn');
    btn.disabled = true; btn.textContent = 'Saving…';

    try {
        const startDt = date + ' ' + time + ':00';
        const endDt = timeEnd ? (date + ' ' + timeEnd + ':00') : '';

        const fd = new FormData();
        fd.append('EventId', id);
        fd.append('EventDateTime', startDt);
        if (endDt) fd.append('EndDateTime', endDt);
        fd.append('EventPlace', place);
        fd.append('EventLocation', place);
        fd.append('EventName', current.EventName || '');
        fd.append('EventDescription', current.EventDescription || '');
        fd.append('EventSpeaker', current.EventSpeaker || '');
        fd.append('EventCapacity', current.EventCapacity || 0);
        fd.append('EventType', current.EventType || '');
        fd.append('EventMode', current.EventMode || '');
        fd.append('AttendanceMethod', current.AttendanceMethod || '');
        fd.append('EventStatus', 'Scheduled');

        const res1 = await fetch('../../config/API/endpoints/index.php?action=update_org_event', { method: 'POST', body: fd });
        const data1 = await res1.json();

        if (!data1.success) {
            showToast(data1.message || 'Failed to save changes', false);
            return;
        }

        const fd2 = new FormData();
        fd2.append('EventId', id);
        fd2.append('EventStatus', 'Scheduled');
        await fetch('../../config/API/endpoints/index.php?action=update_org_event_status', { method: 'POST', body: fd2 });

        showToast('Event rescheduled & set to Scheduled ✓', true);
        closeReschedule();
        loadEvents();

    } catch (e) {
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
        'Ongoing': { bg: '#d97706', color: '#ffffff', border: '#b45309' },
        'Delayed': { bg: '#ea580c', color: '#ffffff', border: '#c2410c' },
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

let activeMonitoringTimers = {};

function openLiveMonitoringModal(eventIdInput, eventNameInput = '') {
    const ev = (typeof allEvents !== 'undefined' && Array.isArray(allEvents)) ? allEvents.find(e => String(e.EventId) === String(eventIdInput)) : null;
    const eventId = eventIdInput;
    const eventName = eventNameInput || (ev ? ev.EventName : 'Event #' + eventId);

    const existing = document.getElementById('liveMonitoringModal');
    if (existing) existing.remove();

    const isTimerRunning = !!activeMonitoringTimers[eventId];

    const modal = document.createElement('div');
    modal.id = 'liveMonitoringModal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,0.65);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px;';
    modal.innerHTML = `
        <div style="background:#ffffff;border-radius:24px;padding:28px;max-width:540px;width:100%;box-shadow:0 25px 60px -12px rgba(0,0,0,0.35);animation:modalPop 0.2s ease;font-family:'Inter',system-ui,sans-serif;color:#1e293b;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:46px;height:46px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:14px;display:flex;align-items:center;justify-content:center;">
                        <ion-icon name="shield-checkmark-outline" style="font-size:26px;color:#16a34a;"></ion-icon>
                    </div>
                    <div>
                        <h3 style="margin:0;font-size:17px;font-weight:800;color:#0f172a;">Live Attendance &amp; Anti-Spoofing</h3>
                        <p style="margin:2px 0 0;font-size:12px;color:#64748b;">Automated Monitoring &amp; Verification Timers</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('liveMonitoringModal').remove()" style="background:none;border:none;color:#94a3b8;font-size:24px;cursor:pointer;line-height:1;">
                    <ion-icon name="close-outline"></ion-icon>
                </button>
            </div>

            <div style="background:#f8fafc;border-radius:12px;padding:12px 16px;margin-bottom:16px;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <p style="margin:0 0 2px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;">Target Event</p>
                    <p style="margin:0;font-size:14.5px;font-weight:700;color:#0f172a;">${eventName}</p>
                </div>
                <span style="font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;background:${isTimerRunning ? 'rgba(16,185,129,0.15)' : 'rgba(148,163,184,0.15)'};color:${isTimerRunning ? '#15803d' : '#64748b'};border:1px solid ${isTimerRunning ? '#86efac' : '#cbd5e1'};">
                    ${isTimerRunning ? '● Auto-Monitoring Active' : '○ Standby'}
                </span>
            </div>

            <!-- Auto-Timer Scheduler Config Card -->
            <div style="background:#eff6ff;border:1.5px solid #bfdbfe;border-radius:16px;padding:16px 18px;margin-bottom:16px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <ion-icon name="timer-outline" style="font-size:18px;color:#1e3a8a;"></ion-icon>
                        <strong style="font-size:13.5px;color:#1e3a8a;">Automated Timed Monitoring Schedule</strong>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;font-size:12.5px;">
                    <div style="background:#fff;border:1px solid #dbeafe;border-radius:10px;padding:10px;">
                        <strong style="color:#1d4ed8;display:block;margin-bottom:3px;">Anti-Spoofing</strong>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                            <select id="antiSpoofIntervalSelect" style="width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:4px 6px;font-size:12px;font-weight:600;color:#0f172a;background:#fff;">
                                <option value="30" selected>Every 30 Mins (Standard)</option>
                                <option value="15">Every 15 Mins</option>
                                <option value="45">Every 45 Mins</option>
                                <option value="60">Every 60 Mins</option>
                            </select>
                        </div>
                    </div>
                    <div style="background:#fff;border:1px solid #dbeafe;border-radius:10px;padding:10px;">
                        <strong style="color:#1d4ed8;display:block;margin-bottom:3px;">Continuous Monitoring</strong>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:2px;">
                            <select id="presenceIntervalSelect" style="width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:4px 6px;font-size:12px;font-weight:600;color:#0f172a;background:#fff;">
                                <option value="5" selected>Every 5 Mins (Standard)</option>
                                <option value="10">Every 10 Mins</option>
                                <option value="15">Every 15 Mins</option>
                            </select>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="toggleAutomatedMonitoring(${eventId}, '${eventName.replace(/'/g, "\\'")}')" id="autoMonitorToggleBtn" style="width:100%;border:none;border-radius:10px;padding:10px;background:${isTimerRunning ? '#ef4444' : '#2563eb'};color:#fff;font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                    <ion-icon name="${isTimerRunning ? 'stop-circle-outline' : 'play-circle-outline'}" style="font-size:17px;"></ion-icon>
                    ${isTimerRunning ? 'Stop Automated Interval Monitoring' : 'Start Automated Timer Monitoring'}
                </button>
            </div>

            <!-- Instant Manual Triggers -->
            <p style="font-size:12.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin:0 0 10px;">
                Instant Manual Triggers
            </p>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px;">
                <button type="button" id="triggerAntiSpoofBtn" onclick="activateAntiSpoofing(${eventId})" style="text-align:left;display:flex;align-items:center;gap:12px;padding:12px 14px;border:1.5px solid #86efac;border-radius:12px;background:#f0fdf4;cursor:pointer;transition:all 0.2s;">
                    <div style="width:34px;height:34px;border-radius:8px;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                        <ion-icon name="camera-outline"></ion-icon>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13.5px;font-weight:700;color:#166534;">Trigger Anti-Spoofing Challenge Now</div>
                        <div style="font-size:11.5px;color:#15803d;">Facial camera recognition challenge for all online attendees</div>
                    </div>
                </button>

                <button type="button" id="triggerPresenceBtn" onclick="triggerPresenceCheck(${eventId})" style="text-align:left;display:flex;align-items:center;gap:12px;padding:12px 14px;border:1.5px solid #bfdbfe;border-radius:12px;background:#eff6ff;cursor:pointer;transition:all 0.2s;">
                    <div style="width:34px;height:34px;border-radius:8px;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                        <ion-icon name="timer-outline"></ion-icon>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13.5px;font-weight:700;color:#1e40af;">Trigger Presence Check Ping Now</div>
                        <div style="font-size:11.5px;color:#2563eb;">Immediate 1-tap participation confirmation prompt</div>
                    </div>
                </button>
            </div>

            <div style="display:flex;justify-content:flex-end;">
                <button type="button" onclick="document.getElementById('liveMonitoringModal').remove()" style="padding:9px 20px;border:1.5px solid #cbd5e1;border-radius:10px;background:#f8fafc;font-weight:600;font-size:13px;cursor:pointer;color:#475569;">
                    Close
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
}

function toggleAutomatedMonitoring(eventId, eventName) {
    if (activeMonitoringTimers[eventId]) {
        // Stop timer
        clearInterval(activeMonitoringTimers[eventId].antiSpoofInterval);
        clearInterval(activeMonitoringTimers[eventId].presenceInterval);
        delete activeMonitoringTimers[eventId];
        showToast('Automated monitoring stopped for ' + eventName, true);
        openLiveMonitoringModal(eventId, eventName);
    } else {
        // Start timer
        const antiSpoofSelect = document.getElementById('antiSpoofIntervalSelect');
        const antiSpoofMins = antiSpoofSelect ? parseInt(antiSpoofSelect.value, 10) : 30;

        const presenceSelect = document.getElementById('presenceIntervalSelect');
        const presenceMins = presenceSelect ? parseInt(presenceSelect.value, 10) : 5;

        // Anti-Spoofing: Configurable interval (starts after selected minutes)
        const antiSpoofTimer = setInterval(() => {
            activateAntiSpoofing(eventId, true);
        }, antiSpoofMins * 60 * 1000);

        // Continuous Presence Check: Configurable interval
        const presenceTimer = setInterval(() => {
            triggerPresenceCheck(eventId, true);
        }, presenceMins * 60 * 1000);

        activeMonitoringTimers[eventId] = {
            antiSpoofInterval: antiSpoofTimer,
            presenceInterval: presenceTimer,
            antiSpoofMins: antiSpoofMins,
            presenceMins: presenceMins
        };

        showModal(`Automated monitoring started!<br><br>• <strong>Anti-Spoofing Challenge:</strong> Every ${antiSpoofMins} Minutes<br>• <strong>Continuous Monitoring:</strong> Every ${presenceMins} Minutes`, 'success', 'Monitoring Started');
        openLiveMonitoringModal(eventId, eventName);
    }
}

async function activateAntiSpoofing(eventId, isAutomated = false) {
    const btn = document.getElementById('triggerAntiSpoofBtn');
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.innerHTML = `
            <div style="width:34px;height:34px;border-radius:8px;background:#dcfce7;color:#15803d;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                <span class="btn-spinner" style="display:inline-block;width:16px;height:16px;border:2px solid rgba(22,101,52,0.3);border-top-color:#166534;border-radius:50%;animation:spin 0.6s linear infinite;"></span>
            </div>
            <div style="flex:1;">
                <div style="font-size:13.5px;font-weight:700;color:#166534;">Activating Anti-Spoofing Challenge...</div>
                <div style="font-size:11.5px;color:#15803d;">Updating event database state & broadcasting...</div>
            </div>
        `;
    }
    try {
        const fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('grace_minutes', 15);
        const r = await fetch('../../config/API/endpoints/index.php?action=trigger_antispoofing', { method: 'POST', body: fd });
        const data = await r.json();
        
        if (data.success) {
            if (btn) {
                btn.style.background = '#dcfce7';
                btn.style.borderColor = '#22c55e';
                btn.innerHTML = `
                    <div style="width:34px;height:34px;border-radius:8px;background:#16a34a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                        <ion-icon name="checkmark-circle"></ion-icon>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13.5px;font-weight:800;color:#15803d;">Anti-Spoofing Active &amp; Synced ✓</div>
                        <div style="font-size:11.5px;color:#166534;">Online attendees prompted to verify</div>
                    </div>
                `;
            }
            showToast('Anti-spoofing challenge activated in database ✓', true);
            setTimeout(() => { if (!isAutomated) document.getElementById('liveMonitoringModal')?.remove(); }, 1200);
        } else {
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
            showModal(data.message || 'Failed to activate anti-spoofing', 'error', 'Error');
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        showModal('Network error activating anti-spoofing', 'error', 'Error');
    }
}

async function triggerPresenceCheck(eventId, isAutomated = false) {
    const btn = document.getElementById('triggerPresenceBtn');
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.innerHTML = `
            <div style="width:34px;height:34px;border-radius:8px;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                <span class="btn-spinner" style="display:inline-block;width:16px;height:16px;border:2px solid rgba(29,78,216,0.3);border-top-color:#1d4ed8;border-radius:50%;animation:spin 0.6s linear infinite;"></span>
            </div>
            <div style="flex:1;">
                <div style="font-size:13.5px;font-weight:700;color:#1e40af;">Sending Presence Check Ping...</div>
                <div style="font-size:11.5px;color:#2563eb;">Updating continuous monitoring state in DB...</div>
            </div>
        `;
    }
    try {
        const fd = new FormData();
        fd.append('event_id', eventId);
        fd.append('duration_sec', 0);
        fd.append('action', 'trigger');
        const r = await fetch('../../config/API/endpoints/index.php?action=trigger_presence_check', { method: 'POST', body: fd });
        const data = await r.json();
        
        if (data.success) {
            if (btn) {
                btn.style.background = '#eff6ff';
                btn.style.borderColor = '#3b82f6';
                btn.innerHTML = `
                    <div style="width:34px;height:34px;border-radius:8px;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                        <ion-icon name="checkmark-circle"></ion-icon>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:13.5px;font-weight:800;color:#1d4ed8;">Presence Check Ping Sent ✓</div>
                        <div style="font-size:11.5px;color:#1e40af;">Online attendees notified in real-time</div>
                    </div>
                `;
            }
            showToast('Presence check ping recorded in database ✓', true);
            setTimeout(() => { if (!isAutomated) document.getElementById('liveMonitoringModal')?.remove(); }, 1200);
        } else {
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
            showModal(data.message || 'Failed to trigger presence check', 'error', 'Error');
        }
    } catch (e) {
        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
        showModal('Network error triggering presence check', 'error', 'Error');
    }
}

window.openLiveMonitoringModal = openLiveMonitoringModal;
window.toggleAutomatedMonitoring = toggleAutomatedMonitoring;
window.activateAntiSpoofing = activateAntiSpoofing;
window.triggerPresenceCheck = triggerPresenceCheck;

async function submitEventForm(e) {
    if (e) e.preventDefault();
    const form = document.getElementById('eventForm');
    if (!form) return;

    const btn = document.getElementById('saveEventBtn');
    const eventIdInput = document.getElementById('evFormEventId');
    const mode = (btn && btn.dataset.mode) ? btn.dataset.mode : (eventIdInput && eventIdInput.value ? 'edit' : 'create');

    const name = document.getElementById('evName')?.value?.trim();
    const date = document.getElementById('evDate')?.value;
    const timeStart = document.getElementById('evTimeStart')?.value;
    const venue = document.getElementById('evPlace')?.value?.trim();
    const desc = document.getElementById('evDesc')?.value?.trim();
    const timeEnd = document.getElementById('evTimeEnd')?.value;

    if (!name || !date || !timeStart || !venue || !desc) {
        showToast('Please fill in all required fields marked with *', false);
        return;
    }

    if (timeEnd && timeEnd <= timeStart) {
        showToast(`End Time (${timeEnd}) must be later than Start Time (${timeStart}) on the same date.`, false);
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.textContent = mode === 'edit' ? 'Updating Event...' : 'Creating Event...';
    }

    try {
        const fd = new FormData(form);
        const timeEnd = document.getElementById('evTimeEnd')?.value;
        if (date && timeStart) {
            fd.set('EventDateTime', date + ' ' + (timeStart.length === 5 ? timeStart + ':00' : timeStart));
        }
        if (date && timeEnd) {
            fd.set('EndDateTime', date + ' ' + (timeEnd.length === 5 ? timeEnd + ':00' : timeEnd));
        }
        const action = mode === 'edit' ? 'update_org_event' : 'create_org_event';

        const res = await fetch(`../../config/API/endpoints/index.php?action=${action}`, {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message || (mode === 'edit' ? 'Event updated successfully!' : 'Event and documents created successfully!'), true);
            closeM('eventFormModal');
            resetUploadBoxes();
            form.reset();
            loadEvents();
        } else {
            showToast(data.message || 'Failed to submit event', false);
        }
    } catch (err) {
        showToast('Network error while saving event', false);
        console.error('Submit event error:', err);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<ion-icon name="save-outline"></ion-icon> ${mode === 'edit' ? 'Update Event' : 'Submit'}`;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadEvents();
    document.getElementById('evSearch')?.addEventListener('input', applyFilters);
    document.getElementById('statusFilter')?.addEventListener('change', applyFilters);
    document.getElementById('sortFilter')?.addEventListener('change', applyFilters);

    const eventForm = document.getElementById('eventForm');
    if (eventForm) {
        eventForm.addEventListener('submit', submitEventForm);
    }
    const saveBtn = document.getElementById('saveEventBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', (e) => {
            e.preventDefault();
            submitEventForm(e);
        });
    }
});
if (document.readyState === 'interactive' || document.readyState === 'complete') {
    loadEvents();
}

function setNoFinancialReport(eventId, noFinancialReport) {
    const message = noFinancialReport
        ? 'Mark this event as having no financial involvement? OSA will be informed.'
        : 'Require a financial report for this event again?';
    showConfirmModal(message, function() {
        const fd = new FormData();
        fd.append('EventId', eventId);
        fd.append('NoFinancialReport', noFinancialReport);
        fetch('../../config/API/endpoints/index.php?action=set_org_event_no_finance', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => { 
                if (data.success) {
                    showToast(data.message || 'Marked as no financial involvement', true);
                    loadEvents(); 
                } else {
                    showModal(data.message || 'Unable to update financial report requirement', 'error', 'Error'); 
                }
            })
            .catch(() => showModal('Network error while updating the financial report requirement', 'error', 'Network Error'));
    }, noFinancialReport ? 'Mark No Financial Involvement' : 'Require Financial Report', 'warning');
}

function openTestModal() {
    renderTestStatusList();
    openM('testStatusModal');
}

function renderTestStatusList() {
    const list = document.getElementById('testStatusList');
    if (!list) return;
    if (!allEvents || allEvents.length === 0) {
        list.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;color:#94a3b8;">No events found</td></tr>';
        return;
    }
    list.innerHTML = allEvents.map(ev => {
        const s = ev.EventStatus || 'Scheduled';
        return `
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="padding:10px 14px;font-weight:600;color:#0f172a;">${ev.EventName}</td>
                <td style="padding:10px 14px;">
                    <span class="status-badge ${(s).toLowerCase()}">${s}</span>
                </td>
                <td style="padding:10px 14px;text-align:right;">
                    <select onchange="updateEventStatus(${ev.EventId}, this.value)" style="border:1px solid #cbd5e1;border-radius:8px;padding:6px 10px;font-size:0.85rem;background:#fff;outline:none;cursor:pointer;">
                        <option value="" disabled selected>Change Status...</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                        <option value="Delayed">Delayed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </td>
            </tr>
        `;
    }).join('');
}

// Global window assignments to ensure HTML onclick handlers always work reliably
window.openM = openM;
window.closeM = closeM;
window.openAddEvent = openAddEvent;
window.openEditEvent = openEditEvent;
window.openViewEvent = openViewEvent;
window.openUploadPostReportModal = openUploadPostReportModal;
window.selectReportUploadType = selectReportUploadType;
window.submitPostActivityReport = submitPostActivityReport;
window.openReschedule = openReschedule;
window.closeReschedule = closeReschedule;
window.saveReschedule = saveReschedule;
window.openTestModal = openTestModal;
window.renderTestStatusList = renderTestStatusList;
window.setNoFinancialReport = setNoFinancialReport;
window.handleFileSelect = handleFileSelect;
window.previewPoster = previewPoster;
window.submitEventForm = submitEventForm;
window.deleteEvent = deleteEvent;
window.updateEventStatus = updateEventStatus;
window.handleOverrideChange = handleOverrideChange;

