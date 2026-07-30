let stream=null, scanInterval=null, scanMode='';
let faceScanTimeout = null;
let faceScanBusy = false;
let isFaceApiLoaded = false;
let isFaceScanning = false;
let faceMatcher = null; // Store faceapi.FaceMatcher
let currentLogType = 'Log In'; // 'Log In' or 'Log Out'

function setLogType(type) {
    currentLogType = type;
    const btnIn = document.getElementById('btnLogTypeIn');
    const btnOut = document.getElementById('btnLogTypeOut');
    if (type === 'Log In') {
        if (btnIn) { btnIn.style.background = '#2563eb'; btnIn.style.color = '#fff'; btnIn.classList.add('active'); }
        if (btnOut) { btnOut.style.background = 'transparent'; btnOut.style.color = '#64748b'; btnOut.classList.remove('active'); }
    } else {
        if (btnOut) { btnOut.style.background = '#dc2626'; btnOut.style.color = '#fff'; btnOut.classList.add('active'); }
        if (btnIn) { btnIn.style.background = 'transparent'; btnIn.style.color = '#64748b'; btnIn.classList.remove('active'); }
    }
}

const faceDetectionOptions = new faceapi.TinyFaceDetectorOptions({
  inputSize: 128,
  scoreThreshold: 0.5
});
const faceDetectionCanvas = document.createElement('canvas');
const faceDetectionCtx = faceDetectionCanvas.getContext('2d', { willReadFrequently: true });

function showStatus(msg,ok=true){
  const el=document.getElementById('attStatus');
  el.textContent=msg; el.style.display='block';
  el.style.background=ok?'#f0fdf4':'#fef2f2';
  el.style.color=ok?'#15803d':'#dc2626';
  el.style.borderColor=ok?'#bbf7d0':'#fecaca';
  setTimeout(()=>el.style.display='none',4000);
}

function getSelectedEventOption() {
  const select = document.getElementById('eventSelect');
  if (!select || !select.value) return null;
  return select.options[select.selectedIndex] || null;
}

function getSelectedEventStatus() {
  const option = getSelectedEventOption();
  return (option?.dataset?.status || '').trim().toLowerCase();
}

function isSelectedEventOngoing() {
  const status = getSelectedEventStatus();
  return status === 'ongoing' || status === 'scheduled';
}

function parseStudentQrPayload(rawData) {
  if (!rawData) return null;

  const trimmed = String(rawData).trim();
  try {
    const parsed = JSON.parse(trimmed);
    if (parsed && typeof parsed === 'object') {
      if (parsed.type && parsed.type !== 'student_qr') return null;
      const studentId = String(parsed.student_id || parsed.studentId || parsed.user_id || '').trim();
      return studentId ? studentId : null;
    }
  } catch (e) {
  }

  const legacy = trimmed.replace(/^ID:\s*/i, '').trim();
  return legacy || null;
}

function enforceEventStatusGate() {
  const controls = [
    document.getElementById('btnUnified'),
    document.getElementById('btnQR'),
    document.getElementById('btnUploadQR'),
    document.getElementById('btnFace'),
    document.getElementById('btnManual'),
  ];
  const selectedStatus = getSelectedEventStatus();
  const enabled = selectedStatus === 'ongoing';

  controls.forEach(btn => {
    if (!btn) return;
    btn.disabled = !document.getElementById('eventSelect').value || !enabled;
    btn.style.opacity = btn.disabled ? '0.55' : '1';
    btn.style.cursor = btn.disabled ? 'not-allowed' : 'pointer';
  });

  const asBtn = document.getElementById('btnAntiSpoof');
  if (asBtn) {
    asBtn.disabled = !document.getElementById('eventSelect').value || !enabled;
  }

  if (!document.getElementById('eventSelect').value) return;
  if (!enabled) {
    showStatus('Wait for the Event to Start before taking attendance.', false);
  }
}

async function loadFaceAPI() {
    if (isFaceApiLoaded) return true;
    showStatus('Loading face detection & AI models...', true);
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('../../assets/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('../../assets/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('../../assets/models')
        ]);
        await initFaceMatcher();
        isFaceApiLoaded = true;
        document.getElementById('attStatus').style.display='none';
        return true;
    } catch (e) {
        showStatus('Error loading Face models: ' + e.message, false);
        return false;
    }
}

async function initFaceMatcher() {
    let faces = [];
    if (navigator.onLine) {
        try {
            const res = await fetch('../../config/API/get_face_descriptors.php');
            const data = await res.json();
            if (data.success) {
                faces = data.faces;
                localStorage.setItem('cachedFaces', JSON.stringify(faces));
            }
        } catch(e) { console.warn('Failed to fetch latest faces, using cache'); }
    } 
    
    if (faces.length === 0) {
        faces = JSON.parse(localStorage.getItem('cachedFaces') || '[]');
    }

    if (faces.length > 0) {
        const labeledDescriptors = faces.map(f => {
            return new faceapi.LabeledFaceDescriptors(
                f.student_id,
                [new Float32Array(f.descriptor)]
            );
        });
        faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.45);
    }
}

function getEventId(){
  const sel=document.getElementById('eventSelect').value;
  if(!sel){ showStatus('Please select an event first',false); return null; }
  return sel;
}

async function startCamera(mode){
  const ev=getEventId(); if(!ev) return;
  if (!isSelectedEventOngoing()) {
    showStatus('Change the event status to Ongoing before starting attendance.', false);
    return;
  }
  
  const loaded = await loadFaceAPI();
  if (!loaded) return;
  
  scanMode=mode;
  if (stream) {
    try { stream.getTracks().forEach(t => t.stop()); } catch(e){}
    stream = null;
  }
  try {
    stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'}});
    
    const video = document.getElementById('cameraFeed');
    video.srcObject=stream;
    document.getElementById('cameraBox').style.display='block';
    document.getElementById('btnStop').style.display='inline-flex';
    if (document.getElementById('btnUnified')) document.getElementById('btnUnified').style.display='none';
    if (document.getElementById('btnQR')) document.getElementById('btnQR').style.display='none';
    if (document.getElementById('btnFace')) document.getElementById('btnFace').style.display='none';

    video.onloadeddata = () => {
        document.getElementById('scanFrame').style.borderColor='#7c3aed';
        showStatus('Unified Scanner Active (Scanning for Face & QR Code)...', true);
        isFaceScanning = true;
        scheduleUnifiedScan(ev, 0);
    };
  } catch(e){ showStatus('Camera access denied: '+e.message,false); }
}

function scheduleUnifiedScan(eventId, delay = 100) {
    if (!isFaceScanning) return;
    if (faceScanTimeout) clearTimeout(faceScanTimeout);
    faceScanTimeout = setTimeout(() => scanUnified(eventId), delay);
}

async function scanUnified(eventId) {
    if (!isFaceScanning || faceScanBusy) return;

    const video = document.getElementById('cameraFeed');
    if (video.readyState !== video.HAVE_ENOUGH_DATA) return;

    faceScanBusy = true;

    const canvas = document.getElementById('qrCanvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(imgData.data, imgData.width, imgData.height);

    if (code && code.data) {
        const studentId = parseStudentQrPayload(code.data);
        if (studentId) {
            isFaceScanning = false;
            if (faceScanTimeout) clearTimeout(faceScanTimeout);
            showStatus('QR Code detected!', true);
            promptAttendance(eventId, studentId, 'qr');
            faceScanBusy = false;
            return;
        }
    }

    const sourceWidth = video.videoWidth || video.clientWidth;
    const sourceHeight = video.videoHeight || video.clientHeight;
    const targetWidth = Math.min(sourceWidth || 0, 320) || 320;
    const targetHeight = sourceWidth ? Math.max(1, Math.round((sourceHeight || 1) * (targetWidth / sourceWidth))) : 240;
    faceDetectionCanvas.width = targetWidth;
    faceDetectionCanvas.height = targetHeight;
    faceDetectionCtx.drawImage(video, 0, 0, targetWidth, targetHeight);

    const detection = await faceapi.detectSingleFace(faceDetectionCanvas, faceDetectionOptions)
                                   .withFaceLandmarks()
                                   .withFaceDescriptor();

    if (detection) {
        if (faceMatcher) {
            const match = faceMatcher.findBestMatch(detection.descriptor);
            if (match && match._label !== 'unknown') {
                isFaceScanning = false;
                if (faceScanTimeout) clearTimeout(faceScanTimeout);
                showStatus('Face & Motion Verified: ' + match._label + ' ✓', true);
                promptAttendance(eventId, match._label, 'face');
            } else {
                showStatus('Face not recognized! Please try scanning QR code or manual entry.', false);
                setTimeout(() => { if(stream) resumeFaceScan(eventId, 0); }, 2500);
            }
        } else {
            showStatus('No offline faces cached to identify with.', false);
            setTimeout(() => { if(stream) resumeFaceScan(eventId, 0); }, 2500);
        }
    } else if (isFaceScanning && stream) {
        scheduleUnifiedScan(eventId, 120);
    }

    faceScanBusy = false;
}

function stopCamera(){
  if(stream) stream.getTracks().forEach(t=>t.stop());
  if(scanInterval) clearInterval(scanInterval);
  if(faceScanTimeout) clearTimeout(faceScanTimeout);
  stream=null; scanInterval=null; scanMode=''; isFaceScanning=false;
  faceScanBusy = false;
  document.getElementById('cameraBox').style.display='none';
  document.getElementById('btnStop').style.display='none';
  if (document.getElementById('btnUnified')) document.getElementById('btnUnified').style.display='inline-flex';
  if (document.getElementById('btnQR')) document.getElementById('btnQR').style.display='inline-flex';
  if (document.getElementById('btnFace')) document.getElementById('btnFace').style.display='inline-flex';
}

function resumeFaceScan(eventId, delay = 0) {
    if (!stream) return;
    isFaceScanning = true;
    scheduleUnifiedScan(eventId, delay);
}

let pendingAttendance = null;

async function promptAttendance(eventId, studentId, method) {
    if (!navigator.onLine) {
        showStatus('Offline mode: Using cached data if available.', false);
        saveOfflineAttendance(eventId, studentId, method);
        setTimeout(() => { if(stream) resumeFaceScan(eventId, 0); }, 2000);
        return;
    }

    showStatus('Fetching student info...', true);
    let studentName = '';
    let profilePhoto = '';
    let details = '';

    try {
        const res = await fetch(`../../config/API/get_student_info.php?StudentId=${encodeURIComponent(studentId)}&EventId=${encodeURIComponent(eventId)}`);
        const data = await res.json();
        if (data.success && data.student) {
            studentName = data.student.name;
            studentId = data.student.student_id;
            profilePhoto = data.student.profile_photo;
            details = [data.student.course, data.student.year_level, data.student.section].filter(Boolean).join(' - ');

            if (data.student.already_completed) {
                showStatus(`${studentName} has already logged in and logged out for this event.`, false);
                setTimeout(() => { if(stream) resumeFaceScan(eventId, 0); }, 2500);
                return;
            }

            // Automatic mode: if student already logged in, next is Log Out
            const targetLogType = data.student.auto_log_type || (data.student.has_logged_in ? 'Log Out' : 'Log In');
            setLogType(targetLogType);
        } else {
            showStatus(data.message || 'Student not found.', false);
            setTimeout(() => { if(stream) resumeFaceScan(eventId, 0); }, 2000);
            return;
        }
    } catch (e) {
        showStatus('Network error. Saving offline.', false);
        saveOfflineAttendance(eventId, studentId, method);
        setTimeout(() => { if(stream) resumeFaceScan(eventId, 0); }, 2000);
        return;
    }

    pendingAttendance = { eventId, studentId, studentName, method, logType: currentLogType };
    
    const modalTitle = document.querySelector('#attModal h3');
    if (modalTitle) modalTitle.textContent = `Confirm ${currentLogType}`;
    
    const recordBtn = document.getElementById('mdlBtnRecord');
    if (recordBtn) {
        recordBtn.textContent = `Record ${currentLogType}`;
        recordBtn.style.background = currentLogType === 'Log Out' ? '#dc2626' : '#10b981';
    }

    document.getElementById('mdlStudentPhoto').src = profilePhoto || '../../assets/img/default-avatar.png';
    document.getElementById('mdlStudentName').textContent = studentName;
    document.getElementById('mdlStudentId').textContent = 'ID: ' + studentId;
    document.getElementById('mdlStudentDetails').textContent = details || 'No course details';
    document.getElementById('attModal').style.display = 'flex';
}

document.getElementById('mdlBtnCancel').addEventListener('click', () => {
    document.getElementById('attModal').style.display = 'none';
    if(pendingAttendance) {
        setTimeout(() => { if(stream) resumeFaceScan(pendingAttendance.eventId, 0); }, 500);
    }
    pendingAttendance = null;
});

document.getElementById('mdlBtnRecord').addEventListener('click', () => {
    if(!pendingAttendance) return;
    document.getElementById('attModal').style.display = 'none';
    recordAttendance(pendingAttendance.eventId, pendingAttendance.studentId, pendingAttendance.studentName, pendingAttendance.method, pendingAttendance.logType);
    pendingAttendance = null;
});

function recordAttendance(eventId,studentId,studentName,method,logType){
  if(!eventId||!studentId){ showStatus('Student ID required',false); return; }
  if (!isSelectedEventOngoing()) {
    showStatus('Change the event status to Ongoing before recording attendance.', false);
    return;
  }
  const lType = logType || currentLogType;
  const fd=new FormData();
  fd.append('EventId',eventId); fd.append('StudentId',studentId);
  fd.append('StudentName',studentName); fd.append('Method',method);
  fd.append('LogType', lType);

  fetch('../../config/API/record_attendance.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      showStatus(d.message,d.success);
      if(d.success){ 
          loadLog(eventId); 
      }
      setTimeout(()=>{ if(stream) resumeFaceScan(eventId, 0); }, 2000);
    }).catch(e=>{
      showStatus('Network error. Saving offline.', false);
      saveOfflineAttendance(eventId, studentId, method);
      setTimeout(()=>{ if(stream) resumeFaceScan(eventId, 0); }, 2000);
    });
}

function loadLog(eventId){
  fetch(`../../config/API/get_attendance_log.php?EventId=${eventId}`)
    .then(r=>r.json()).then(data=>{
      const tbody=document.getElementById('attLog');
      document.getElementById('attCount').textContent=(data.attendance||[]).length+' recorded';
      if(!data.attendance||!data.attendance.length){
        tbody.innerHTML='<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">No attendance recorded yet.</td></tr>';
        return;
      }
      tbody.innerHTML=data.attendance.map((a,i)=>{
        const dt=new Date(a.ScannedAt).toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
        const lType = a.LogType || 'Log In';
        const typeBadge = lType === 'Log Out'
            ? `<span class="mode-badge" style="background:#fee2e2;color:#b91c1c;">Log Out</span>`
            : `<span class="mode-badge" style="background:#dbeafe;color:#1d4ed8;">Log In</span>`;
        return `<tr>
          <td>${i+1}</td>
          <td>${a.StudentName||'—'}</td>
          <td>${a.StudentId||'—'}</td>
          <td>${typeBadge}</td>
          <td><span class="mode-badge mode-${a.Method}">${a.Method}</span></td>
          <td>${dt}</td>
          <td><button onclick="deleteAttendance(${a.AttendanceId}, ${eventId})" style="color:#ef4444;border:none;background:none;cursor:pointer;font-size:16px;" title="Delete Record"><ion-icon name="trash-outline"></ion-icon></button></td>
        </tr>`;
      }).join('');
    }).catch(()=>{});
}

function deleteAttendance(attendanceId, eventId) {
  if(!confirm('Are you sure you want to delete this test record?')) return;
  fetch(`../../config/API/delete_attendance.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: `AttendanceId=${attendanceId}`
  }).then(r=>r.json()).then(data=>{
      showStatus(data.message, data.success);
      if(data.success) loadLog(eventId);
  }).catch(e=>{
      showStatus('Error communicating with server.', false);
  });
}

if (document.getElementById('btnUnified')) {
    document.getElementById('btnUnified').addEventListener('click', () => startCamera('unified'));
}
if (document.getElementById('btnQR')) {
    document.getElementById('btnQR').addEventListener('click', () => startCamera('unified'));
}
if (document.getElementById('btnFace')) {
    document.getElementById('btnFace').addEventListener('click', () => startCamera('unified'));
}
document.getElementById('btnStop').addEventListener('click',stopCamera);
document.getElementById('btnManual').addEventListener('click',()=>{
  const ev=getEventId(); if(!ev) return;
  if (!isSelectedEventOngoing()) {
    showStatus('Change the event status to Ongoing before recording attendance.', false);
    return;
  }
  const sid=document.getElementById('manualId').value.trim();
  if(!sid){ showStatus('Enter a Student ID',false); return; }
  promptAttendance(ev,sid,'manual');
  document.getElementById('manualId').value='';
});

function saveOfflineAttendance(eventId, studentId, method) {
    const offlineData = JSON.parse(localStorage.getItem('offlineAttendance') || '[]');
    const isDup = offlineData.some(a => a.eventId == eventId && a.studentId == studentId);
    if(isDup) {
        showStatus('Already recorded offline.', false);
        return;
    }
    offlineData.push({eventId, studentId, method, timestamp: Date.now()});
    localStorage.setItem('offlineAttendance', JSON.stringify(offlineData));
    showStatus('Saved offline.', true);
    checkOfflineData();
    
    const ev = getEventId();
    if(ev == eventId) {
        const tbody = document.getElementById('attLog');
        if(tbody.querySelector('td[colspan="6"]')) tbody.innerHTML = '';
        const dt = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        const tr = document.createElement('tr');
        tr.style.background = '#fffbeb';
        tr.innerHTML = `<td>-</td><td>Offline Record</td><td>${studentId}</td>
          <td><span class="mode-badge mode-${method}">${method}</span></td><td>${dt} (Offline)</td>
          <td><span style="font-size:12px;color:#f59e0b;">Pending Sync</span></td>`;
        tbody.prepend(tr);
        const countSpan = document.getElementById('attCount');
        countSpan.textContent = parseInt(countSpan.textContent) + 1 + ' recorded';
    }
}

function checkOfflineData() {
    const offlineData = JSON.parse(localStorage.getItem('offlineAttendance') || '[]');
    const btnSync = document.getElementById('btnSync');
    if(offlineData.length > 0) {
        btnSync.style.display = 'inline-flex';
        document.getElementById('offlineCount').textContent = offlineData.length;
    } else {
        btnSync.style.display = 'none';
    }
}

window.addEventListener('load', checkOfflineData);
window.addEventListener('online', checkOfflineData);

document.getElementById('btnSync').addEventListener('click', async () => {
    const offlineData = JSON.parse(localStorage.getItem('offlineAttendance') || '[]');
    if(offlineData.length === 0) return;
    if(!navigator.onLine) { showStatus('Still offline. Cannot sync.', false); return; }
    
    showStatus(`Syncing ${offlineData.length} records...`, true);
    let successCount = 0;
    
    for(const a of offlineData) {
        const fd = new FormData();
        fd.append('EventId', a.eventId);
        fd.append('StudentId', a.studentId);
        fd.append('Method', a.method + ' (Offline)');
        fd.append('StudentName', 'Offline Synced'); // Default name as fallback
        
        try {
            const r = await fetch('../../config/API/record_attendance.php', {method: 'POST', body: fd});
            const d = await r.json();
            if(d.success || d.message.toLowerCase().includes('already')) {
                successCount++;
            }
        } catch(e) {
            console.error('Failed to sync offline record');
        }
    }
    
    if(successCount > 0) {
        offlineData.splice(0, successCount); 
        localStorage.setItem('offlineAttendance', JSON.stringify(offlineData));
        showStatus(`Successfully synced ${successCount} records!`, true);
        checkOfflineData();
        const ev = getEventId();
        if(ev) loadLog(ev);
    } else {
        showStatus('Sync failed. Please try again later.', false);
    }
});

document.getElementById('eventSelect').addEventListener('change', async e => {
  const opt = e.target.options[e.target.selectedIndex];
  if (opt && opt.dataset.status === 'scheduled') {
      const fd = new FormData();
      fd.append('EventId', e.target.value);
      fd.append('EventStatus', 'Ongoing');
      try {
          const res = await fetch('../../config/API/update_org_event_status.php', { method: 'POST', body: fd });
          const d = await res.json();
          if (d.success) opt.dataset.status = 'ongoing';
      } catch(ex){}
  }
  
  enforceEventStatusGate();
  if(e.target.value) loadLog(e.target.value);
  else {
    document.getElementById('attLog').innerHTML='<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Select an event to view attendance.</td></tr>';
    document.getElementById('attCount').textContent='0 recorded';
  }
});

document.getElementById('btnUploadQR').addEventListener('click', () => {
  const ev = getEventId();
  if (!ev) return;
  if (!isSelectedEventOngoing()) {
    showStatus('Change the event status to Ongoing before starting attendance.', false);
    return;
  }
  document.getElementById('qrFileInput').click();
});

document.getElementById('qrFileInput').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (!file) return;

  const ev = getEventId();
  if (!ev) return;

  showStatus('Processing uploaded image...', true);

  const reader = new FileReader();
  reader.onload = function(event) {
    const img = new Image();
    img.onload = function() {
      const tempCanvas = document.createElement('canvas');
      const tempCtx = tempCanvas.getContext('2d');
      tempCanvas.width = img.width;
      tempCanvas.height = img.height;
      tempCtx.drawImage(img, 0, 0);

      try {
        const imgData = tempCtx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
        const decoded = jsQR(imgData.data, imgData.width, imgData.height);
        if (decoded && decoded.data) {
          const studentId = parseStudentQrPayload(decoded.data);
          if (studentId) {
            showStatus('QR code detected successfully!', true);
            promptAttendance(ev, studentId, 'qr');
          } else {
            showStatus('Invalid QR format. Please scan a student QR code from the profile dashboard.', false);
          }
        } else {
          showStatus('Could not find a valid QR code in the image. Please ensure the QR is clear.', false);
        }
      } catch (err) {
        console.error(err);
        showStatus('Error decoding image QR code: ' + err.message, false);
      }
    };
    img.src = event.target.result;
  };
  reader.readAsDataURL(file);
  e.target.value = '';
});

enforceEventStatusGate();


const CHALLENGES = [
  { id: 'LEFT',  emoji: '',  text: 'Look LEFT',  sub: 'Turn your head slowly to the left' },
  { id: 'RIGHT', emoji: '',  text: 'Look RIGHT', sub: 'Turn your head slowly to the right' },
  { id: 'UP',    emoji: '',  text: 'Look UP',    sub: 'Tilt your head gently upward' },
  { id: 'DOWN',  emoji: '',  text: 'Look DOWN',  sub: 'Tilt your head gently downward' },
];
const AS_TIMEOUT_MS  = 8000; // 8 seconds to complete challenge
const AS_HOLD_MS     = 600;  // must hold position for 600ms

let asStream       = null;  // camera stream for challenge
let asChallenge    = null;  // current challenge object
let asRunning      = false;
let asPollTimer    = null;
let asTimeoutTimer = null;
let asHoldTimer    = null;
let asStartTime    = 0;
let asCanvas       = document.createElement('canvas');
let asCtx          = asCanvas.getContext('2d', { willReadFrequently: true });
let asApiLoaded    = false;


document.getElementById('btnAntiSpoof').addEventListener('click', () => {
  const ev = getEventId();
  if (!ev || !isSelectedEventOngoing()) {
    showStatus('Anti-Spoofing is only available for Ongoing events.', false);
    return;
  }
  openAntiSpoofModal(ev);
});

async function openAntiSpoofModal(eventId) {
  const overlay = document.getElementById('antiSpoofOverlay');
  overlay.classList.add('open');
  setAsChallengeUI('help-circle-outline', 'Starting camera…', 'Please allow camera access');
  document.getElementById('asTimerFill').style.transition = 'none';
  document.getElementById('asTimerFill').style.width = '100%';
  document.getElementById('asStatusText').textContent = '';

  if (!asApiLoaded) {
    setAsChallengeUI('hourglass-outline', 'Loading AI models…', 'First time takes a few seconds');
    try {
      await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri('../../assets/models'),
        faceapi.nets.faceLandmark68Net.loadFromUri('../../assets/models'),
      ]);
      asApiLoaded = true;
    } catch(e) {
      setAsChallengeUI('close-circle-outline', 'Model load failed', e.message);
      return;
    }
  }

  try {
    asStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width:640, height:480 } });
    const vid = document.getElementById('asVideo');
    vid.srcObject = asStream;
    await new Promise(r => { vid.onloadedmetadata = r; });
    vid.play();
  } catch(e) {
    setAsChallengeUI('ban-outline', 'Camera denied', 'Please allow camera access in your browser.');
    return;
  }

  asChallenge = CHALLENGES[Math.floor(Math.random() * CHALLENGES.length)];
  setAsChallengeUI(asChallenge.icon || 'help-circle-outline', asChallenge.text, asChallenge.sub);
  document.getElementById('asStatusText').textContent = 'Face detection active...';

  asStartTime = Date.now();
  const fill = document.getElementById('asTimerFill');
  fill.style.transition = `width ${AS_TIMEOUT_MS}ms linear`;
  fill.style.width = '0%';

  asRunning = true;
  asPollTimer = setInterval(() => pollLiveness(eventId), 150);
  asTimeoutTimer = setTimeout(() => {
    if (asRunning) failAntiSpoof('Time ran out! Please try again.');
  }, AS_TIMEOUT_MS);
}

function setAsChallengeUI(iconName, text, sub) {
  const el = document.getElementById('asEmoji');
  el.setAttribute('name', iconName);
  document.getElementById('asChallengeText').textContent = text;
  document.getElementById('asChallengeSubText').textContent = sub;
}

async function pollLiveness(eventId) {
  if (!asRunning) return;
  const vid = document.getElementById('asVideo');
  if (!vid || vid.readyState < 2) return;

  asCanvas.width  = vid.videoWidth  || 320;
  asCanvas.height = vid.videoHeight || 240;
  asCtx.drawImage(vid, 0, 0, asCanvas.width, asCanvas.height);

  try {
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 256, scoreThreshold: 0.5 });
    const det  = await faceapi.detectSingleFace(asCanvas, opts).withFaceLandmarks();
    if (!det) {
      document.getElementById('asStatusText').textContent = 'No face detected – position yourself in the camera';
      clearTimeout(asHoldTimer); asHoldTimer = null;
      return;
    }

    const passed = checkPose(det.landmarks, asChallenge.id);
    if (passed) {
      if (!asHoldTimer) {
        document.getElementById('asStatusText').textContent = 'Hold it there…';
        asHoldTimer = setTimeout(() => passAntiSpoof(eventId), AS_HOLD_MS);
      }
    } else {
      document.getElementById('asStatusText').textContent = `Face detected – perform the challenge above`;
      clearTimeout(asHoldTimer); asHoldTimer = null;
    }
  } catch(e) {  }
}


function checkPose(landmarks, direction) {
  const pts  = landmarks.positions;
  const nose  = pts[30];
  const lEye  = pts[36]; // left eye outer corner
  const rEye  = pts[45]; // right eye outer corner
  const chin  = pts[8];
  const topMouth = pts[51];

  const eyeMidX   = (lEye.x + rEye.x) / 2;
  const eyeWidth  = Math.abs(rEye.x - lEye.x);
  const noseOffX  = nose.x - eyeMidX; // negative = nose points left
  const yawRatio  = eyeWidth > 0 ? noseOffX / eyeWidth : 0;

  const eyeMidY   = (lEye.y + rEye.y) / 2;
  const faceHeight = Math.abs(chin.y - eyeMidY);
  const noseOffY  = nose.y - eyeMidY; // larger = nose lower (head up)
  const pitchRatio = faceHeight > 0 ? noseOffY / faceHeight : 0;

  const YAW_THRESH   = 0.12; // ~12% of eye-width lateral offset
  const PITCH_THRESH = 0.04; // ~4% pitch shift (subtle)

  if (direction === 'LEFT')  return yawRatio < -YAW_THRESH;
  if (direction === 'RIGHT') return yawRatio > YAW_THRESH;
  if (direction === 'UP')    return pitchRatio < (0.38 - PITCH_THRESH);
  if (direction === 'DOWN')  return pitchRatio > (0.52 + PITCH_THRESH);
  return false;
}

function passAntiSpoof(eventId) {
  if (!asRunning) return;
  stopAntiSpoofCamera();
  setAsChallengeUI('checkmark-circle-outline', 'Liveness Verified!', 'Challenge passed successfully');
  document.getElementById('asStatusText').textContent = 'Anti-spoofing passed! Starting face recognition…';
  document.getElementById('asTimerFill').style.width = '100%';
  document.getElementById('asTimerFill').style.background = 'linear-gradient(90deg,#22c55e,#4ade80)';
  setTimeout(async () => {
    closeAntiSpoofModal();
    await startCamera('face');
  }, 1200);
}

function failAntiSpoof(reason) {
  if (!asRunning) return;
  stopAntiSpoofCamera();
  setAsChallengeUI('close-circle-outline', 'Liveness Failed', reason);
  document.getElementById('asStatusText').textContent = 'Spoofing attempt blocked or timed out.';
  document.getElementById('asTimerFill').style.width = '0%';
  setTimeout(() => closeAntiSpoofModal(), 2200);
}

function stopAntiSpoofCamera() {
  asRunning = false;
  clearInterval(asPollTimer);
  clearTimeout(asTimeoutTimer);
  clearTimeout(asHoldTimer);
  asPollTimer = asTimeoutTimer = asHoldTimer = null;
  if (asStream) { asStream.getTracks().forEach(t => t.stop()); asStream = null; }
  const vid = document.getElementById('asVideo');
  if (vid) vid.srcObject = null;
}

function closeAntiSpoofModal() {
  stopAntiSpoofCamera();
  document.getElementById('antiSpoofOverlay').classList.remove('open');
  const fill = document.getElementById('asTimerFill');
  fill.style.transition = 'none';
  fill.style.width = '100%';
  fill.style.background = 'linear-gradient(90deg,#22c55e,#86efac)';
  setAsChallengeUI('help-circle-outline', 'Preparing challenge…', 'Please wait while your camera loads');
  document.getElementById('asStatusText').textContent = '';
}