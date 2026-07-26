/* ── Extracted from organization/qr-scanner.php ── */
/* ─── State ──────────────────────────────────────── */
let selectedEventId   = null;
let selectedEventStatus = null;
let cameraRunning     = false;
let scanCooldown      = false;
let camStream         = null;
let scanInterval      = null;
let pendingStudent    = null;
let pendingScanMethod = null;
const sessionLog      = [];

/* ─── Event select ───────────────────────────────── */
document.getElementById('eventSelect').addEventListener('change', async (e) => {
    updateSelectedEvent();
    
    const opt = e.target.options[e.target.selectedIndex];
    if (opt && opt.dataset.status === 'scheduled') {
        const fd = new FormData();
        fd.append('EventId', e.target.value);
        fd.append('EventStatus', 'Ongoing');
        try {
            const res = await fetch('../../config/API/update_org_event_status.php', { method: 'POST', body: fd });
            const d = await res.json();
            if (d.success) {
                opt.dataset.status = 'ongoing';
                updateSelectedEvent(); // refresh badges/buttons
            }
        } catch(ex){}
    }
});

function updateSelectedEvent() {
    const sel    = document.getElementById('eventSelect');
    const opt    = sel.selectedOptions[0];
    const badge  = document.getElementById('eventStatusBadge');

    selectedEventId     = sel.value ? parseInt(sel.value) : null;
    selectedEventStatus = opt?.dataset?.status || null;

    const labelMap = { ongoing:'Ongoing', upcoming:'Upcoming', ended:'Ended', completed:'Completed', scheduled:'Scheduled' };
    const classMap = { ongoing:'badge-ongoing', upcoming:'badge-upcoming', scheduled:'badge-upcoming' };

    badge.className = 'event-status-badge ' + (classMap[selectedEventStatus] || 'badge-ended');
    badge.textContent = selectedEventId
        ? (labelMap[selectedEventStatus] || selectedEventStatus || 'Unknown')
        : 'No Event';

    // Enable/disable camera button
    const canScan = selectedEventId && (selectedEventStatus === 'ongoing' || selectedEventStatus === 'scheduled');
    document.getElementById('camBtn').disabled = !canScan;
    document.getElementById('uploadScanBtn').disabled = !canScan;

    if (!canScan && cameraRunning) stopCamera();
}

/* ─── Camera scanner ─────────────────────────────── */
function toggleCamera() {
    cameraRunning ? stopCamera() : startCamera();
}

async function startCamera() {
    const video  = document.getElementById('camVideo');
    const btn    = document.getElementById('camBtn');
    const ph     = document.getElementById('camPlaceholder');
    const status = document.getElementById('camStatus');

    setStatus(status, 'info', 'Requesting camera access…');
    try {
        camStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        video.srcObject = camStream;
        await video.play();
        cameraRunning = true;
        ph.style.display = 'none';
        document.getElementById('scanLine').style.display = '';
        btn.innerHTML = '<ion-icon name="stop-circle-outline"></ion-icon> Stop Camera';
        btn.className = 'btn btn-danger';
        document.getElementById('camCard').classList.add('active');
        setStatus(status, 'info', 'Scanning for QR codes…');
        scanInterval = setInterval(scanFrame, 350);
    } catch (e) {
        setStatus(status, 'error', 'Camera access denied. Please allow camera permission.');
    }
}

function stopCamera() {
    if (camStream) { camStream.getTracks().forEach(t => t.stop()); camStream = null; }
    clearInterval(scanInterval);
    cameraRunning = false;
    const btn = document.getElementById('camBtn');
    btn.innerHTML = '<ion-icon name="camera-outline"></ion-icon> Start Camera';
    btn.className = 'btn btn-primary';
    document.getElementById('camPlaceholder').style.display = '';
    document.getElementById('scanLine').style.display = 'none';
    document.getElementById('camCard').classList.remove('active');
    setStatus(document.getElementById('camStatus'), '', '');
}

function scanFrame() {
    if (scanCooldown) return;
    const video  = document.getElementById('camVideo');
    const canvas = document.getElementById('camCanvas');
    if (video.readyState < video.HAVE_ENOUGH_DATA) return;

    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'dontInvert' });
    if (code) handleQrResult(code.data, 'camera');
}

/* ─── Upload scanner ─────────────────────────────── */
function handleUpload(e) {
    const file = e.target.files[0];
    if (!file) return;
    loadImageFile(file);
}
function handleDrop(e) {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) loadImageFile(file);
}
function loadImageFile(file) {
    const reader = new FileReader();
    reader.onload = () => {
        const img = document.getElementById('uploadImg');
        img.src = reader.result;
        document.getElementById('uploadZone').style.display = 'none';
        document.getElementById('uploadPreview').style.display = '';
        document.getElementById('uploadScanBtn').disabled = !selectedEventId || (selectedEventStatus !== 'ongoing' && selectedEventStatus !== 'scheduled');
        setStatus(document.getElementById('uploadStatus'), 'info', 'Image loaded. Click "Decode QR" to scan.');
    };
    reader.readAsDataURL(file);
}

function resetUpload() {
    document.getElementById('uploadImg').src = '';
    document.getElementById('qrFileInput').value = '';
    document.getElementById('uploadZone').style.display = '';
    document.getElementById('uploadPreview').style.display = 'none';
    document.getElementById('uploadScanBtn').disabled = true;
    setStatus(document.getElementById('uploadStatus'), '', '');
}

function scanUploadedImage() {
    const img = document.getElementById('uploadImg');
    if (!img.src) { setStatus(document.getElementById('uploadStatus'), 'error', 'No image loaded.'); return; }

    const canvas = document.createElement('canvas');
    canvas.width  = img.naturalWidth  || img.width;
    canvas.height = img.naturalHeight || img.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'attemptBoth' });

    if (code) {
        handleQrResult(code.data, 'upload');
    } else {
        setStatus(document.getElementById('uploadStatus'), 'error', 'No QR code detected. Try a clearer image.');
    }
}

/* ─── QR result handler ──────────────────────────── */
async function handleQrResult(rawData, method) {
    if (scanCooldown) return;
    scanCooldown = true;
    setTimeout(() => { scanCooldown = false; }, 2500);

    // Try to parse JSON payload from student-qr.php
    let studentId = rawData;
    try {
        const parsed = JSON.parse(rawData);
        if (parsed.type === 'student_qr') {
            studentId = JSON.stringify(parsed); // pass full payload to API
        }
    } catch(e) { /* plain text ID */ }

    const statusEl = method === 'camera'
        ? document.getElementById('camStatus')
        : document.getElementById('uploadStatus');

    setStatus(statusEl, 'info', 'Looking up student…');

    try {
        const res = await fetch(`../../config/API/get_student_info.php?StudentId=${encodeURIComponent(studentId)}&EventId=${selectedEventId || ''}`);
        const data = await res.json();

        if (!data.success) {
            setStatus(statusEl, 'error', data.message || 'Student not found.');
            return;
        }

        pendingStudent    = { ...data.student, rawQrData: rawData };
        pendingScanMethod = method;

        // Populate modal
        const s = data.student;
        const initials = s.name.split(' ').map(n => n[0] || '').join('').toUpperCase().slice(0,2);

        document.getElementById('modalName').textContent   = s.name;
        document.getElementById('modalId').textContent     = '# ' + s.student_id;
        document.getElementById('modalCourse').textContent = [s.course, s.year_level, s.section ? 'Sec ' + s.section : ''].filter(Boolean).join(' · ');
        document.getElementById('modalOrg').innerHTML    = 'Method: <ion-icon name="' + (method === 'camera' ? 'camera-outline' : 'image-outline') + '" style="vertical-align:middle;margin-right:3px;"></ion-icon> ' + (method === 'camera' ? 'Camera Scan' : 'Image Upload');

        // Photo
        const photoEl = document.getElementById('modalPhoto');
        if (s.profile_photo && s.profile_photo !== '../../assets/img/default-avatar.png') {
            photoEl.innerHTML = `<img src="${s.profile_photo}" alt="Photo" onerror="this.parentElement.innerHTML='${initials}'">`;
        } else {
            photoEl.textContent = initials;
        }

        setStatus(statusEl, 'success', '✓ QR decoded — confirm attendance in the popup.');
        openModal();

    } catch(e) {
        setStatus(statusEl, 'error', 'Network error. Please try again.');
    }
}

/* ─── Modal ──────────────────────────────────────── */
function openModal()  { document.getElementById('confirmModal').classList.add('open'); }
function closeModal() { document.getElementById('confirmModal').classList.remove('open'); }

async function confirmAttendance() {
    if (!pendingStudent || !selectedEventId) return;
    const btn = document.getElementById('modalConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Recording…';

    const fd = new FormData();
    fd.append('EventId',     selectedEventId);
    fd.append('StudentId',   pendingStudent.rawQrData);
    fd.append('StudentName', pendingStudent.name);
    fd.append('Method',      pendingScanMethod === 'camera' ? 'qr_camera' : 'qr_upload');

    try {
        const res  = await fetch('../../config/API/record_attendance.php', { method: 'POST', body: fd });
        const data = await res.json();

        closeModal();
        addToLog(pendingStudent, pendingScanMethod, data.success ? 'ok' : 'dup', data.message);

        // Reset upload state
        if (pendingScanMethod === 'upload') resetUpload();

    } catch(e) {
        addToLog(pendingStudent, pendingScanMethod, 'dup', 'Network error');
        closeModal();
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<ion-icon name="checkmark-circle-outline"></ion-icon> Record Attendance';
        pendingStudent = null;
    }
}

/* ─── Session log ────────────────────────────────── */
function addToLog(student, method, status, message) {
    const now = new Date().toLocaleTimeString();
    const row = { student, method, status, message, time: now };
    sessionLog.unshift(row);

    document.getElementById('logEmpty').style.display = 'none';
    const table = document.getElementById('logTable');
    table.style.display = '';
    const tbody = document.getElementById('logBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <div class="log-name">${escHtml(student.name)}</div>
        </td>
        <td><span class="log-id">${escHtml(student.student_id)}</span></td>
        <td><span style="font-size:12px;color:#64748b;display:inline-flex;align-items:center;gap:3px;"><ion-icon name="${method === 'camera' ? 'camera-outline' : 'image-outline'}"></ion-icon>${method === 'camera' ? 'Camera' : 'Upload'}</span></td>
        <td><span class="log-time">${now}</span></td>
        <td>
            ${status === 'ok'
                ? `<span class="log-badge-ok"><ion-icon name="checkmark-outline"></ion-icon>${escHtml(message)}</span>`
                : `<span class="log-badge-dup"><ion-icon name="warning-outline"></ion-icon>${escHtml(message)}</span>`
            }
        </td>
    `;
    tbody.prepend(tr);
}

/* ─── Helpers ────────────────────────────────────── */
function setStatus(el, type, msg) {
    if (!el) return;
    el.className = 'scan-status' + (type ? ' ' + type : '');
    el.style.display = type ? 'flex' : 'none';
    el.innerHTML = msg;
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Close modal on backdrop click
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Drag highlight
const uz = document.getElementById('uploadZone');
['dragenter','dragover'].forEach(ev => uz.addEventListener(ev, () => uz.classList.add('drag')));
['dragleave','drop'].forEach(ev => uz.addEventListener(ev, () => uz.classList.remove('drag')));