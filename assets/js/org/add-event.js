let currentStep = 1;
let maxReachedStep = 1;

const STEP_TITLES = {
    1: 'Basic Event Information',
    2: 'Schedule and Location',
    3: 'Participants Information',
    4: 'Event Documentation',
    5: 'Attendance Setup & Review'
};

document.addEventListener('DOMContentLoaded', () => {
    const eventDateInput = document.getElementById('eventDate');
    if (eventDateInput) {
        const today = new Date().toISOString().split('T')[0];
        eventDateInput.min = today;
    }
    handleDateChange();
    toggleVenueField();
    updateStepperUI();
});

function toggleVenueField() {
    const mode = document.querySelector('input[name="EventMode"]:checked')?.value || 'On-site';
    const venueInput = document.getElementById('venue');
    const venueLabel = document.querySelector('label[for="venue"]');
    if (!venueInput) return;

    if (mode === 'Online') {
        if (venueLabel) venueLabel.innerHTML = 'Online Platform / Meeting Link <span style="font-size:12px;font-weight:400;color:#64748b;">(Optional)</span>';
        venueInput.placeholder = 'e.g. Zoom, Google Meet, MS Teams, or meeting link';
        venueInput.required = false;
        if (!venueInput.value.trim()) {
            venueInput.value = 'Online (Zoom / MS Teams)';
        }
    } else if (mode === 'Hybrid') {
        if (venueLabel) venueLabel.innerHTML = 'Venue Location & Online Link *';
        venueInput.placeholder = 'e.g. Main Auditorium & Zoom link';
        venueInput.required = true;
        if (venueInput.value === 'Online (Zoom / MS Teams)') venueInput.value = '';
    } else {
        if (venueLabel) venueLabel.innerHTML = 'Venue / Location *';
        venueInput.placeholder = 'e.g., Main Auditorium, Room 301';
        venueInput.required = true;
        if (venueInput.value === 'Online (Zoom / MS Teams)') venueInput.value = '';
    }
}
window.toggleVenueField = toggleVenueField;

// Native Picker Trigger (Calendar and Clock buttons)
function openNativePicker(inputId) {
    const el = document.getElementById(inputId);
    if (!el) return;
    if (typeof el.showPicker === 'function') {
        try {
            el.showPicker();
        } catch (err) {
            el.focus();
            el.click();
        }
    } else {
        el.focus();
        el.click();
    }
}
window.openNativePicker = openNativePicker;

function handleDateChange() {
    const dateInput = document.getElementById('eventDate');
    const startInput = document.getElementById('startTime');
    if (!dateInput || !startInput) return;

    const selectedDate = dateInput.value;
    const today = new Date().toISOString().split('T')[0];

    if (selectedDate === today) {
        const now = new Date();
        const curH = String(now.getHours()).padStart(2, '0');
        const curM = String(now.getMinutes()).padStart(2, '0');
        const curTime = `${curH}:${curM}`;
        startInput.min = curTime;

        if (startInput.value && startInput.value < curTime) {
            startInput.value = curTime;
            handleStartTimeChange();
        }
    } else {
        startInput.removeAttribute('min');
    }
}
window.handleDateChange = handleDateChange;

function handleStartTimeChange() {
    const startInput = document.getElementById('startTime');
    const endInput = document.getElementById('endTime');
    if (!startInput || !endInput || !startInput.value) return;

    const startVal = startInput.value;
    const [startH, startM] = startVal.split(':').map(Number);

    // End Time must be strictly after Start Time on the same date.
    // Set HTML5 min on endTime to remove impossible earlier hours from picker selection
    let minEndM = startM + 15;
    let minEndH = startH;
    if (minEndM >= 60) {
        minEndH += 1;
        minEndM = minEndM % 60;
    }
    const minEndStr = minEndH < 24 ? `${String(minEndH).padStart(2, '0')}:${String(minEndM).padStart(2, '0')}` : '23:59';
    endInput.min = minEndStr;

    // Smart auto-set End Time:
    // If start time is 11:00 PM (23:00), wrapping around would give 01:00 AM (which is 22 hours earlier on the same day!).
    // For single-day events, cap late night events at 23:59 (11:59 PM).
    let autoEndH = startH + 1;
    let autoEndM = startM;

    if (autoEndH >= 24) {
        endInput.value = '23:59';
    } else {
        const autoEndStr = `${String(autoEndH).padStart(2, '0')}:${String(autoEndM).padStart(2, '0')}`;
        if (!endInput.value || endInput.value <= startVal) {
            endInput.value = autoEndStr;
        }
    }
}
window.handleStartTimeChange = handleStartTimeChange;
window.smartAutoSetEndTime = handleStartTimeChange;

function handleEndTimeChange() {
    const startInput = document.getElementById('startTime');
    const endInput = document.getElementById('endTime');
    if (!startInput || !endInput || !endInput.value || !startInput.value) return;

    const startVal = startInput.value;
    const endVal = endInput.value;

    if (endVal <= startVal) {
        showToast(`End Time cannot be earlier than or equal to Start Time (${startVal}) on the same date.`, false);
        handleStartTimeChange();
    }
}
window.handleEndTimeChange = handleEndTimeChange;

function toggleVenueField() {
    const mode = document.querySelector('input[name="EventMode"]:checked')?.value || 'On-site';
    const venueGroup = document.getElementById('venueGroup');
    const venueInput = document.getElementById('venue');
    if (!venueGroup || !venueInput) return;

    if (mode === 'Online') {
        venueGroup.style.display = 'none';
        venueInput.required = false;
        venueInput.value = 'Online (Zoom / MS Teams)';
    } else {
        venueGroup.style.display = 'block';
        venueInput.required = true;
        if (venueInput.value === 'Online (Zoom / MS Teams)') venueInput.value = '';
    }
}
window.toggleVenueField = toggleVenueField;

// ── Multi-Step Wizard Engine ─────────────────────────────────────────
function setStep(stepNum) {
    if (stepNum < 1 || stepNum > 5) return;
    
    currentStep = stepNum;
    if (currentStep > maxReachedStep) {
        maxReachedStep = currentStep;
    }

    // Toggle panels
    for (let i = 1; i <= 5; i++) {
        const panel = document.getElementById(`stepPanel${i}`);
        if (panel) {
            panel.classList.toggle('active', i === currentStep);
        }
    }

    // If entering Step 5, populate the live review card
    if (currentStep === 5) {
        updateReviewSummary();
    }

    updateStepperUI();

    // Scroll smoothly to the top of the form
    const formShell = document.querySelector('.event-form-shell');
    if (formShell) {
        formShell.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
window.setStep = setStep;

function updateStepperUI() {
    // Desktop Stepper
    const progressFill = document.getElementById('stepperProgressFill');
    if (progressFill) {
        const pct = ((currentStep - 1) / 4) * 100;
        progressFill.style.width = `${pct}%`;
    }

    for (let i = 1; i <= 5; i++) {
        const node = document.getElementById(`stepNode${i}`);
        if (!node) continue;

        node.classList.remove('active', 'completed');
        if (i === currentStep) {
            node.classList.add('active');
        } else if (i < currentStep) {
            node.classList.add('completed');
        }
    }

    // Mobile Stepper
    const mobileCount = document.getElementById('mobileStepCount');
    const mobileTitle = document.getElementById('mobileStepTitle');
    const mobileProgress = document.getElementById('mobileStepProgress');

    if (mobileCount) mobileCount.textContent = `Step ${currentStep} of 5`;
    if (mobileTitle) mobileTitle.textContent = STEP_TITLES[currentStep] || '';
    if (mobileProgress) mobileProgress.style.width = `${(currentStep / 5) * 100}%`;
}

function handleStepNodeClick(targetStep) {
    // Allow jumping to already completed steps or current step
    if (targetStep <= maxReachedStep) {
        setStep(targetStep);
    } else {
        // If jumping forward, validate current step first
        if (validateStep(currentStep)) {
            setStep(targetStep);
        }
    }
}
window.handleStepNodeClick = handleStepNodeClick;

function markFieldError(el, hasError = true) {
    if (!el) return;
    if (hasError) {
        el.classList.add('field-error');
        setTimeout(() => el.classList.remove('field-error'), 1800);
    } else {
        el.classList.remove('field-error');
    }
}

function validateStep(stepNum) {
    let isValid = true;
    let firstErrorField = null;

    if (stepNum === 1) {
        const title = document.getElementById('eventTitle');
        const type = document.getElementById('eventType');
        const desc = document.getElementById('eventDescription');

        if (!title || !title.value.trim()) {
            markFieldError(title);
            if (!firstErrorField) firstErrorField = title;
            isValid = false;
        }
        if (!type || !type.value) {
            markFieldError(type);
            if (!firstErrorField) firstErrorField = type;
            isValid = false;
        }
        if (!desc || !desc.value.trim()) {
            markFieldError(desc);
            if (!firstErrorField) firstErrorField = desc;
            isValid = false;
        }

        if (!isValid) {
            showToast('Please complete all required fields in Basic Event Information.', false);
        }
    } else if (stepNum === 2) {
        const date = document.getElementById('eventDate');
        const start = document.getElementById('startTime');
        const end = document.getElementById('endTime');
        const venue = document.getElementById('venue');
        const mode = document.querySelector('input[name="EventMode"]:checked')?.value || 'On-site';

        if (!date || !date.value) {
            markFieldError(date);
            if (!firstErrorField) firstErrorField = date;
            isValid = false;
        }
        if (!start || !start.value) {
            markFieldError(start);
            if (!firstErrorField) firstErrorField = start;
            isValid = false;
        }
        if (!end || !end.value) {
            markFieldError(end);
            if (!firstErrorField) firstErrorField = end;
            isValid = false;
        }
        if (mode !== 'Online' && (!venue || !venue.value.trim())) {
            markFieldError(venue);
            if (!firstErrorField) firstErrorField = venue;
            isValid = false;
        }

        if (isValid && date && start && end) {
            const today = new Date().toISOString().split('T')[0];
            if (date.value === today) {
                const now = new Date();
                const curH = String(now.getHours()).padStart(2, '0');
                const curM = String(now.getMinutes()).padStart(2, '0');
                const curTime = `${curH}:${curM}`;
                if (start.value < curTime) {
                    markFieldError(start);
                    showToast('Event Start Time cannot be in the past for today.', false);
                    if (!firstErrorField) firstErrorField = start;
                    isValid = false;
                }
            }
            if (end.value <= start.value) {
                markFieldError(end);
                showToast(`End Time (${end.value}) must be later than Start Time (${start.value}) on the same date.`, false);
                if (!firstErrorField) firstErrorField = end;
                isValid = false;
            }
        }

        if (!isValid && !firstErrorField) {
            showToast('Please specify a valid event date, time, and venue location.', false);
        }
    } else if (stepNum === 3) {
        const attendees = document.getElementById('expectedAttendees');
        const checkedParticipants = document.querySelectorAll('input[name="participants[]"]:checked');

        if (!attendees || !attendees.value || Number(attendees.value) < 1) {
            markFieldError(attendees);
            if (!firstErrorField) firstErrorField = attendees;
            isValid = false;
        }
        if (checkedParticipants.length === 0) {
            showToast('Please select at least one target participant group.', false);
            isValid = false;
        }

        if (!isValid && firstErrorField) {
            showToast('Please specify the expected number of attendees.', false);
        }
    } else if (stepNum === 4) {
        const oplanInput = document.getElementById('oplanFile');
        const flowInput = document.getElementById('programFlowFile');
        const oplanBox = document.getElementById('oplanUploadBox');
        const flowBox = document.getElementById('programFlowUploadBox');

        const hasOplan = oplanInput && oplanInput.files && oplanInput.files.length > 0;
        const hasFlow = flowInput && flowInput.files && flowInput.files.length > 0;

        if (!hasOplan) {
            markFieldError(oplanBox);
            isValid = false;
        }
        if (!hasFlow) {
            markFieldError(flowBox);
            isValid = false;
        }

        if (!isValid) {
            showToast('Both Event Proposal / OPLAN and Program Flow documents are required.', false);
        }
    }

    if (firstErrorField) {
        firstErrorField.focus();
    }

    return isValid;
}

function nextStep(fromStep) {
    if (validateStep(fromStep)) {
        setStep(fromStep + 1);
    }
}
window.nextStep = nextStep;

function prevStep(fromStep) {
    setStep(fromStep - 1);
}
window.prevStep = prevStep;

// ── Live Review Summary Generator ────────────────────────────────────
function updateReviewSummary() {
    const titleVal = document.getElementById('eventTitle')?.value?.trim() || 'Untitled Event';
    const typeVal = document.getElementById('eventType')?.value || 'Not specified';
    
    const dateVal = document.getElementById('eventDate')?.value;
    let formattedDate = 'TBD';
    if (dateVal) {
        try {
            const d = new Date(dateVal + 'T00:00:00');
            formattedDate = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        } catch(e) {
            formattedDate = dateVal;
        }
    }

    const startVal = document.getElementById('startTime')?.value || 'TBD';
    const endVal = document.getElementById('endTime')?.value || 'TBD';
    const modeVal = document.querySelector('input[name="EventMode"]:checked')?.value || 'On-site';
    const venueVal = document.getElementById('venue')?.value?.trim() || 'TBD';

    const capVal = document.getElementById('expectedAttendees')?.value || '0';
    const speakerVal = document.getElementById('guestSpeaker')?.value?.trim() || 'None specified';

    const checkedParticipants = Array.from(document.querySelectorAll('input[name="participants[]"]:checked')).map(cb => cb.value);
    const participantsText = checkedParticipants.length > 0 ? checkedParticipants.join(', ') : 'None selected';

    const oplanFile = document.getElementById('oplanFile')?.files?.[0];
    const flowFile = document.getElementById('programFlowFile')?.files?.[0];
    const posterFile = document.getElementById('posterFile')?.files?.[0];
    const supportingInput = document.getElementById('supportingFiles');
    const supportingFiles = supportingInput?.files ? Array.from(supportingInput.files) : [];

    // Set texts
    const revTitle = document.getElementById('revTitle');
    const revType = document.getElementById('revType');
    const revSchedule = document.getElementById('revSchedule');
    const revVenue = document.getElementById('revVenue');
    const revCapacity = document.getElementById('revCapacity');
    const revParticipants = document.getElementById('revParticipants');
    const revSpeaker = document.getElementById('revSpeaker');
    const revDocs = document.getElementById('revDocs');

    if (revTitle) revTitle.textContent = titleVal;
    if (revType) revType.textContent = typeVal;
    if (revSchedule) revSchedule.textContent = `${formattedDate} (${startVal} - ${endVal})`;
    if (revVenue) revVenue.textContent = `${modeVal} • ${venueVal}`;
    if (revCapacity) revCapacity.textContent = `${capVal} Attendees`;
    if (revParticipants) revParticipants.textContent = participantsText;
    if (revSpeaker) revSpeaker.textContent = speakerVal;

    if (revDocs) {
        let supportingPill = '';
        if (supportingFiles.length > 0) {
            const fileNames = supportingFiles.map(f => f.name).join(', ');
            supportingPill = `
                <span class="review-doc-pill uploaded" title="${fileNames}">
                    <ion-icon name="checkmark-circle"></ion-icon>
                    Supporting Document: ${supportingFiles.length === 1 ? supportingFiles[0].name : `${supportingFiles.length} files attached`}
                </span>
            `;
        } else {
            supportingPill = `
                <span class="review-doc-pill optional-empty">
                    <ion-icon name="attach-outline"></ion-icon>
                    Supporting Document: None (Optional)
                </span>
            `;
        }

        revDocs.innerHTML = `
            <span class="review-doc-pill ${oplanFile ? 'uploaded' : 'missing'}">
                <ion-icon name="${oplanFile ? 'checkmark-circle' : 'alert-circle'}"></ion-icon>
                OPLAN: ${oplanFile ? oplanFile.name : 'Missing'}
            </span>
            <span class="review-doc-pill ${flowFile ? 'uploaded' : 'missing'}">
                <ion-icon name="${flowFile ? 'checkmark-circle' : 'alert-circle'}"></ion-icon>
                Program Flow: ${flowFile ? flowFile.name : 'Missing'}
            </span>
            ${supportingPill}
            <span class="review-doc-pill ${posterFile ? 'uploaded' : 'optional-empty'}">
                <ion-icon name="${posterFile ? 'checkmark-circle' : 'image-outline'}"></ion-icon>
                Pubmat: ${posterFile ? posterFile.name : 'Not attached'}
            </span>
        `;
    }
}

function showToast(msg, ok=true) { 
    let t = document.getElementById('toast'); 
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 24px;color:#fff;border-radius:10px;font-size:14px;font-weight:600;z-index:99999;box-shadow:0 10px 25px rgba(0,0,0,0.2);display:none;font-family:Inter,sans-serif;';
        document.body.appendChild(t);
    }
    t.textContent = msg; 
    t.style.background = ok ? '#16a34a' : '#dc2626'; 
    t.style.display = 'block'; 
    setTimeout(() => { if (t) t.style.display = 'none'; }, 3500); 
}

function submitAddEvent(e) {
  e.preventDefault();
  
  // Validate all steps from 1 to 4 before final submission
  for (let s = 1; s <= 4; s++) {
      if (!validateStep(s)) {
          setStep(s);
          return;
      }
  }

  const form = document.getElementById('addEventForm');
  const btn = document.getElementById('submitBtn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<ion-icon name="sync-outline" style="animation:spin 1s linear infinite;"></ion-icon> Submitting...';
  }

  try {
    const title = document.getElementById('eventTitle')?.value?.trim();
    const type = document.getElementById('eventType')?.value;
    const desc = document.getElementById('eventDescription')?.value?.trim();
    const date = document.getElementById('eventDate')?.value;
    const time = document.getElementById('startTime')?.value;
    const endTime = document.getElementById('endTime')?.value;
    const venue = document.getElementById('venue')?.value?.trim();
    const cap = document.getElementById('expectedAttendees')?.value;

    const oplanInput = document.getElementById('oplanFile');
    const programFlowInput = document.getElementById('programFlowFile');

    const hasOplan = oplanInput && oplanInput.files && oplanInput.files.length > 0;
    const hasProgramFlow = programFlowInput && programFlowInput.files && programFlowInput.files.length > 0;

    if (!title || !type || !desc || !date || !time || !endTime || !venue || !cap) {
      showToast('All event information fields are required. Please fill out all fields before submitting.', false);
      if (btn) { btn.disabled = false; btn.innerHTML = '<ion-icon name="paper-plane-outline"></ion-icon> Submit for Approval'; }
      return;
    }

    if (endTime <= time) {
      showToast(`End Time (${endTime}) must be later than Start Time (${time}) on the same date.`, false);
      if (btn) { btn.disabled = false; btn.innerHTML = '<ion-icon name="paper-plane-outline"></ion-icon> Submit for Approval'; }
      return;
    }

    if (!hasOplan || !hasProgramFlow) {
      showToast('All required documents (Event Proposal & Program Flow) must be uploaded before submitting.', false);
      if (btn) { btn.disabled = false; btn.innerHTML = '<ion-icon name="paper-plane-outline"></ion-icon> Submit for Approval'; }
      return;
    }

    // Check file sizes to prevent upload size network error
    const fileInputs = form.querySelectorAll('input[type="file"]');
    const maxBytes = 10 * 1024 * 1024; // 10MB limit
    for (let inp of fileInputs) {
        if (inp.files && inp.files.length > 0) {
            for (let f of inp.files) {
                if (f.size > maxBytes) {
                    showToast(`File "${f.name}" exceeds 10MB size limit. Please upload a smaller file.`, false);
                    if (btn) { btn.disabled = false; btn.innerHTML = '<ion-icon name="paper-plane-outline"></ion-icon> Submit for Approval'; }
                    return;
                }
            }
        }
    }

    // Inject hidden date time fields safely
    const startFormatted = date + ' ' + (time.length === 5 ? time + ':00' : time);
    const endFormatted = date + ' ' + (endTime && endTime.length === 5 ? endTime + ':00' : (endTime || ''));

    const hiddenInput = document.getElementById('eventDateTimeHidden');
    if (hiddenInput) hiddenInput.value = startFormatted;
    
    const hiddenEndInput = document.getElementById('endDateTimeHidden');
    if (hiddenEndInput) hiddenEndInput.value = endFormatted;

    const fd = new FormData(form);
    fd.set('EventDateTime', startFormatted);
    fd.set('EndDateTime', endFormatted);
    fd.set('EventDate', date);
    fd.set('EventTimeStart', time);
    fd.set('EventTimeEnd', endTime);
    
    fetch('../../config/API/endpoints/index.php?action=create_org_event', {
      method: 'POST',
      body: fd
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Event proposal submitted successfully!');
        setTimeout(() => {
          window.location.href = 'events_org.php';
        }, 1200);
      } else {
        showToast(data.message || 'Error creating event', false);
        if (btn) { btn.disabled = false; btn.innerHTML = '<ion-icon name="paper-plane-outline"></ion-icon> Submit for Approval'; }
      }
    })
    .catch(err => {
      showToast('Network error occurred.', false);
      if (btn) { btn.disabled = false; btn.innerHTML = '<ion-icon name="paper-plane-outline"></ion-icon> Submit for Approval'; }
    });
  } catch (err) {
    showToast('An unexpected error occurred: ' + err.message, false);
    if (btn) { btn.disabled = false; btn.innerHTML = '<ion-icon name="paper-plane-outline"></ion-icon> Submit for Approval'; }
  }
}

function handleFileSelect(input, nameId, boxId, hint) {
    const box = document.getElementById(boxId);
    const lbl = document.getElementById(nameId);
    if (input.files && input.files.length > 0) {
        const raw = input.files.length > 1 ? input.files.length + ' files selected' : input.files[0].name;
        const name = raw.length > 40 ? raw.substring(0, 37) + '...' : raw;
        // Checkmark state matching design
        box.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" style="width:36px;height:36px;color:#10b981;margin-bottom:6px;display:block;margin-left:auto;margin-right:auto;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
<p style="font-size:15px;font-weight:700;color:#1e293b;margin:0 0 2px;">${name}</p>
<p style="font-size:12px;color:#64748b;font-weight:500;margin:0;">Click to change file</p>`;
        box.classList.add('has-file');
        if (lbl) {
            lbl.textContent = raw;
            lbl.classList.add('has-file');
            lbl.style.color = '#10b981';
            lbl.style.fontWeight = '600';
        }
    } else {
        box.innerHTML = `<svg class="upload-svg-icon" style="width:26px;height:26px;min-width:26px;min-height:26px;display:block;margin:0 auto 6px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 367.79h76c55 0 100-29.21 100-83.6s-53-81.47-96-83.6c-8.89-85.30-71-136.8-144-136.8-69 0-113.44 45.79-128 91.2-60 5.7-112 43.42-112 100.8 0 53.4 45 111.6 104 111.6h68"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 255.79l-64-64-64 64"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M256 448.21V207.79"/></svg>
<span class="upload-label">Click to upload or drag and drop<br />${hint}</span>`;
        box.classList.remove('has-file');
        if (lbl) {
            lbl.textContent = 'No file selected';
            lbl.classList.remove('has-file');
            lbl.style.color = '';
            lbl.style.fontWeight = '';
        }
    }
}
window.handleFileSelect = handleFileSelect;

// Drag & drop support for all upload boxes
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.upload-box').forEach(box => {
        const inputId = box.getAttribute('for');
        const input = document.getElementById(inputId);
        if (!input) return;

        ['dragenter', 'dragover'].forEach(evt => {
            box.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); box.style.borderColor = '#10b981'; box.style.background = '#ecfdf5'; });
        });

        ['dragleave', 'drop'].forEach(evt => {
            box.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); box.style.borderColor = ''; box.style.background = ''; });
        });

        box.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                input.files = dt.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });
});
