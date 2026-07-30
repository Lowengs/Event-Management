document.addEventListener('DOMContentLoaded', () => {
    const eventDateInput = document.getElementById('eventDate');
    if (eventDateInput) {
        const today = new Date().toISOString().split('T')[0];
        eventDateInput.min = today;
    }
    toggleVenueField();
});

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

function showToast(msg, ok=true) { 
    let t = document.getElementById('toast'); 
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;padding:12px 24px;color:#fff;border-radius:8px;font-size:14px;font-weight:600;z-index:9999;box-shadow:0 10px 25px rgba(0,0,0,0.2);display:none;';
        document.body.appendChild(t);
    }
    t.textContent = msg; 
    t.style.background = ok ? '#16a34a' : '#dc2626'; 
    t.style.display = 'block'; 
    setTimeout(() => { if (t) t.style.display = 'none'; }, 3500); 
}

function submitAddEvent(e) {
  e.preventDefault();
  const form = document.getElementById('addEventForm');
  const btn = document.getElementById('submitBtn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Submitting...';
  }

  try {
    // Manual check
    const title = document.getElementById('eventTitle')?.value?.trim();
    const type = document.getElementById('eventType')?.value;
    const date = document.getElementById('eventDate')?.value;
    const time = document.getElementById('startTime')?.value;

    if (!title || !type || !date || !time) {
      showToast('Please fill out all required fields (Title, Type, Date, Start Time).', false);
      if (btn) { btn.disabled = false; btn.textContent = 'Submit Event'; }
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
                    if (btn) { btn.disabled = false; btn.textContent = 'Submit Event'; }
                    return;
                }
            }
        }
    }

    // Inject hidden date time field safely
    const hiddenInput = document.getElementById('eventDateTimeHidden');
    if (hiddenInput) {
      hiddenInput.value = date + ' ' + time + ':00';
    }

    const fd = new FormData(form);
    
    fetch('../../config/API/create_org_event.php', {
      method: 'POST',
      body: fd
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Event created successfully!');
        setTimeout(() => {
          window.location.href = 'events_org.php';
        }, 1000);
      } else {
        showToast(data.message || 'Error creating event', false);
        if (btn) { btn.disabled = false; btn.textContent = 'Submit Event'; }
      }
    })
    .catch(err => {
      showToast('Network error occurred.', false);
      if (btn) { btn.disabled = false; btn.textContent = 'Submit Event'; }
    });
  } catch (err) {
    alert('An unexpected error occurred: ' + err.message);
    if (btn) { btn.disabled = false; btn.textContent = 'Submit Event'; }
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
