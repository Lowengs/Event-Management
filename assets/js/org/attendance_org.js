/**
 * Organization Attendance Management JS
 * Modern, robust implementation for QR, Facial Recognition, Anti-Spoofing, and Manual Entry
 */

let stream = null;
let scanInterval = null;
let scanMode = '';
let faceScanTimeout = null;
let faceScanBusy = false;
let isFaceApiLoaded = false;
let isFaceScanning = false;
let faceMatcher = null;
let currentLogType = 'Log In'; // 'Log In' or 'Log Out'
let pendingAttendance = null;

// Log Type Toggle (Check-In vs Check-Out)
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

// FaceAPI Options & Canvas
let faceDetectionOptions = null;
function getFaceDetectionOptions() {
  if (!faceDetectionOptions && typeof faceapi !== 'undefined' && faceapi.TinyFaceDetectorOptions) {
    faceDetectionOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 160, scoreThreshold: 0.45 });
  }
  return faceDetectionOptions;
}
const faceDetectionCanvas = document.createElement('canvas');
const faceDetectionCtx = faceDetectionCanvas.getContext('2d', { willReadFrequently: true });

// Dedicated QR scan canvas (persistent, avoids context recreation)
const qrScanCanvas = document.createElement('canvas');
const qrScanCtx = qrScanCanvas.getContext('2d', { willReadFrequently: true });

// Camera health-check interval ID
let cameraHealthInterval = null;
let scanCycleCount = 0;

// Status Toast Helper
function showStatus(msg, ok = true) {
  const el = document.getElementById('attStatus');
  if (!el) return;
  el.textContent = msg;
  el.style.display = 'block';
  el.style.background = ok ? '#f0fdf4' : '#fef2f2';
  el.style.color = ok ? '#15803d' : '#dc2626';
  el.style.borderColor = ok ? '#bbf7d0' : '#fecaca';
  setTimeout(() => { if (el) el.style.display = 'none'; }, 4000);
}

// Helper: Get selected event ID
function getEventId() {
  const select = document.getElementById('eventSelect');
  if (!select || !select.value) {
    showStatus('Please select an event first.', false);
    return null;
  }
  return select.value;
}

function isSelectedEventOngoing() {
  return true;
}

function enforceEventStatusGate() {
  // no-op gate
}

// Parse Student QR Payload
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
  } catch (e) {}

  const legacy = trimmed.replace(/^ID:\s*/i, '').trim();
  return legacy || null;
}

// Load FaceAPI Models
async function loadFaceAPI() {
    if (isFaceApiLoaded) return true;
    showStatus('Loading AI Face recognition models…', true);
    try {
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('../../assets/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('../../assets/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('../../assets/models')
        ]);
        await initFaceMatcher();
        isFaceApiLoaded = true;
        showStatus('AI Models loaded successfully!', true);
        return true;
    } catch (e) {
        console.warn('Face API load warning:', e.message);
        return false;
    }
}

// Initialize Face Matcher from DB
// FIX: threshold tightened to 0.45 (was 0.55) to reduce false-positive matches
async function initFaceMatcher() {
    try {
        const res = await fetch('../../config/API/endpoints/index.php?action=get_face_descriptors');
        const data = await res.json();
        if (data.success && data.faces && data.faces.length > 0) {
            const labeledDescriptors = data.faces.map(f => {
                const descFloat = new Float32Array(f.descriptor);
                return new faceapi.LabeledFaceDescriptors(f.student_id, [descFloat]);
            });
            faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.45);
        }
    } catch(e) {
        console.warn("Face descriptors load warning:", e);
    }
}

// Camera Control Functions
async function startCamera(mode) {
  const ev = getEventId();
  if (!ev) return;
  
  scanMode = mode || 'unified';

  if (stream) {
    try { stream.getTracks().forEach(t => t.stop()); } catch(e){}
    stream = null;
  }

  const cameraBox = document.getElementById('cameraBox');
  const video = document.getElementById('cameraFeed');
  const btnStop = document.getElementById('btnStop');

  if (cameraBox) cameraBox.style.display = 'block';
  if (btnStop) btnStop.style.display = 'inline-flex';

  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
    });
    
    if (video) {
      video.srcObject = stream;
      video.setAttribute('playsinline', true);
      video.play().catch(e => console.warn('Video play error:', e));
    }
    
    showStatus('Camera Feed Active! Position QR code or Face in camera frame.', true);
    isFaceScanning = true;
    faceScanBusy = false;
    scanCycleCount = 0;
    scheduleUnifiedScan(ev, 0);

    // FIX: health check interval reduced from 8s to 5s for faster recovery
    if (cameraHealthInterval) clearInterval(cameraHealthInterval);
    cameraHealthInterval = setInterval(() => checkCameraHealth(ev), 3000);

    // Asynchronously attempt face API model load in background
    loadFaceAPI().then(loaded => {
      if (loaded && !faceMatcher) initFaceMatcher();
    }).catch(err => console.warn('Face API background load warning:', err));

  } catch(e) { 
    showStatus('Camera access error: ' + e.message + '. Please check browser camera permissions.', false); 
  }
}

// Camera health check: detects stalled/ended tracks and auto-restarts
function checkCameraHealth(eventId) {
  if (!stream) return;
  const tracks = stream.getVideoTracks();
  if (!tracks.length || tracks[0].readyState === 'ended' || tracks[0].muted) {
    console.warn('[HealthCheck] Camera track ended/muted, restarting…');
    stopCamera();
    setTimeout(() => startCamera(scanMode || 'unified'), 500);
    return;
  }
  // If faceScanBusy has been stuck for too long, force-reset it
  if (faceScanBusy) {
    console.warn('[HealthCheck] faceScanBusy stuck, resetting…');
    faceScanBusy = false;
  }
  // If scanning has stopped but camera is still running and no modal is open,
  // restart the scan loop automatically
  const modalVisible = document.getElementById('attModal')?.style.display === 'flex';
  if (!isFaceScanning && !modalVisible) {
    console.warn('[HealthCheck] isFaceScanning was false with no modal open, restarting scan…');
    isFaceScanning = true;
    faceScanBusy = false;
    scheduleUnifiedScan(eventId, 200);
  } else if (isFaceScanning && !faceScanTimeout) {
    // Scan loop died without being rescheduled
    console.warn('[HealthCheck] scan loop stalled, rescheduling…');
    faceScanBusy = false;
    scheduleUnifiedScan(eventId, 200);
  }
}

function stopCamera() {
  if (stream) stream.getTracks().forEach(t => t.stop());
  if (scanInterval) clearInterval(scanInterval);
  if (faceScanTimeout) clearTimeout(faceScanTimeout);
  if (cameraHealthInterval) { clearInterval(cameraHealthInterval); cameraHealthInterval = null; }
  stream = null; scanInterval = null; scanMode = ''; isFaceScanning = false;
  faceScanBusy = false;
  scanCycleCount = 0;
  
  const cameraBox = document.getElementById('cameraBox');
  const btnStop = document.getElementById('btnStop');
  if (cameraBox) cameraBox.style.display = 'none';
  if (btnStop) btnStop.style.display = 'none';
}

// FIX: resumeFaceScan now always clears faceScanBusy first so scanUnified is never blocked
function resumeFaceScan(eventId, delay = 0) {
    if (!stream) return;
    // Clear busy flag unconditionally — the previous scan cycle has ended by this point
    faceScanBusy = false;
    isFaceScanning = true;
    scheduleUnifiedScan(eventId, delay);
}

function scheduleUnifiedScan(eventId, delay = 200) {
    if (!isFaceScanning) return;
    if (faceScanTimeout) clearTimeout(faceScanTimeout);
    faceScanTimeout = setTimeout(() => scanUnified(eventId), Math.max(delay, 80));
}

// Unified Scanner Execution
async function scanUnified(eventId) {
    if (!isFaceScanning || faceScanBusy) return;

    const video = document.getElementById('cameraFeed');
    if (!video || video.readyState !== video.HAVE_ENOUGH_DATA) {
        if (isFaceScanning) scheduleUnifiedScan(eventId, 300);
        return;
    }

    faceScanBusy = true;
    scanCycleCount++;

    try {
        const vw = video.videoWidth || 640;
        const vh = video.videoHeight || 480;

        // 1. Attempt QR Scan (every frame)
        if (typeof jsQR !== 'undefined') {
            // Reuse persistent canvas, only resize if dimensions changed
            if (qrScanCanvas.width !== vw || qrScanCanvas.height !== vh) {
                qrScanCanvas.width = vw;
                qrScanCanvas.height = vh;
            }
            qrScanCtx.drawImage(video, 0, 0, vw, vh);
            const imgData = qrScanCtx.getImageData(0, 0, vw, vh);
            const code = jsQR(imgData.data, imgData.width, imgData.height, { inversionAttempts: 'attemptBoth' });

            if (code && code.data) {
                const studentId = parseStudentQrPayload(code.data);
                if (studentId) {
                    isFaceScanning = false;
                    if (faceScanTimeout) clearTimeout(faceScanTimeout);
                    // FIX: reset busy flag before async call so resume works
                    faceScanBusy = false;
                    showStatus('QR Code detected!', true);
                    promptAttendance(eventId, studentId, 'qr');
                    return;
                }
            }
        }

        // 2. Attempt Face Recognition every 3rd cycle (reduces CPU load)
        const doFaceScan = isFaceApiLoaded && typeof faceapi !== 'undefined' && (scanCycleCount % 3 === 0);
        if (doFaceScan) {
            const sourceWidth = vw;
            const sourceHeight = vh;
            const targetWidth = Math.min(sourceWidth, 320);
            const targetHeight = Math.max(1, Math.round(sourceHeight * (targetWidth / sourceWidth)));
            if (faceDetectionCanvas.width !== targetWidth || faceDetectionCanvas.height !== targetHeight) {
                faceDetectionCanvas.width = targetWidth;
                faceDetectionCanvas.height = targetHeight;
            }
            faceDetectionCtx.drawImage(video, 0, 0, targetWidth, targetHeight);

            const opts = getFaceDetectionOptions();
            if (opts) {
                const detections = await faceapi.detectAllFaces(faceDetectionCanvas, opts)
                                               .withFaceLandmarks()
                                               .withFaceDescriptors();

                if (detections && detections.length > 1) {
                    // Multiple faces detected — reject
                    faceScanBusy = false;
                    showStatus('Multiple faces detected! Only one person at a time. Please retry.', false);
                    if (isFaceScanning && stream) {
                        scheduleUnifiedScan(eventId, 1000);
                    }
                    return;
                }

                const detection = detections && detections.length === 1 ? detections[0] : null;

                if (detection) {
                    if (!faceMatcher) await initFaceMatcher();

                    if (faceMatcher) {
                        const match = faceMatcher.findBestMatch(detection.descriptor);
                        // FIX: added strict distance guard (< 0.45) to prevent false-positive matches.
                        // Only a named match AND a close enough descriptor distance is accepted.
                        const MATCH_DISTANCE_THRESHOLD = 0.45;
                        if (match && match._label !== 'unknown' && match.distance < MATCH_DISTANCE_THRESHOLD) {
                            isFaceScanning = false;
                            if (faceScanTimeout) clearTimeout(faceScanTimeout);
                            // FIX: reset busy flag BEFORE the async promptAttendance call
                            faceScanBusy = false;
                            showStatus('Face Verified: ' + match._label + ' ✓ (distance: ' + match.distance.toFixed(3) + ')', true);
                            promptAttendance(eventId, match._label, 'face');
                            return;
                        }
                    }
                }
            }
        }
    } catch(e) {
        console.warn('[scanUnified] Error caught, recovering:', e.message || e);
    }

    faceScanBusy = false;
    if (isFaceScanning && stream) {
        // Keep QR decoding responsive while the camera remains open.
        const nextDelay = (scanCycleCount % 3 === 2) ? 250 : 80;
        scheduleUnifiedScan(eventId, nextDelay);
    }
}

// Prompt Attendance Confirmation Modal
async function promptAttendance(eventId, studentId, method) {
    showStatus('Looking up student details…', true);
    let studentName = '';
    let profilePhoto = '';
    let details = '';

    try {
        const res = await fetch(`../../config/API/endpoints/index.php?action=get_student_info&StudentId=${encodeURIComponent(studentId)}&EventId=${encodeURIComponent(eventId)}`);
        const data = await res.json();
        if (data.success && data.student) {
            studentName = data.student.name;
            studentId = data.student.student_id;
            profilePhoto = data.student.profile_photo;
            details = [data.student.course, data.student.year_level, data.student.section].filter(Boolean).join(' - ');

            if (data.student.already_completed) {
                showStatus(`${studentName} has already checked in and checked out for this event.`, false);
                setTimeout(() => { if(stream) resumeFaceScan(eventId, 0); }, 2500);
                return;
            }

            const targetLogType = data.student.auto_log_type || (data.student.has_logged_in ? 'Log Out' : 'Log In');
            setLogType(targetLogType);
        } else {
            // Direct record fallback if student info endpoint fails
            studentName = `Student #${studentId}`;
        }
    } catch (e) {
        studentName = `Student #${studentId}`;
    }

    pendingAttendance = { eventId, studentId, studentName, method, logType: currentLogType };
    
    const attModal = document.getElementById('attModal');
    if (attModal) {
        const modalTitle = attModal.querySelector('h3');
        if (modalTitle) modalTitle.textContent = `Confirm ${currentLogType}`;
        
        const recordBtn = document.getElementById('mdlBtnRecord');
        if (recordBtn) {
            recordBtn.textContent = `Record ${currentLogType}`;
            recordBtn.style.background = currentLogType === 'Log Out' ? '#dc2626' : '#10b981';
        }

        const photoEl = document.getElementById('mdlStudentPhoto');
        if (photoEl) photoEl.src = profilePhoto || '../../assets/img/philsca.png';
        const nameEl = document.getElementById('mdlStudentName');
        if (nameEl) nameEl.textContent = studentName;
        const idEl = document.getElementById('mdlStudentId');
        if (idEl) idEl.textContent = 'ID: ' + studentId;
        const detEl = document.getElementById('mdlStudentDetails');
        if (detEl) detEl.textContent = details || 'NAAP Student';

        attModal.style.display = 'flex';
    } else {
        // Direct record if modal is not present
        recordAttendance(eventId, studentId, studentName, method, currentLogType);
    }
}

// Global Confirm & Close for Attendance Modal
function confirmAttendanceModal(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const recordBtn = document.getElementById('mdlBtnRecord');
    if (!pendingAttendance) {
        closeAttendanceModal();
        return;
    }
    
    if (recordBtn) {
        recordBtn.disabled = true;
        recordBtn.innerHTML = '⏳ Recording…';
        recordBtn.style.opacity = '0.7';
    }

    const { eventId, studentId, studentName, method, logType } = pendingAttendance;
    const lType = logType || currentLogType;

    const fd = new FormData();
    fd.append('EventId', eventId);
    fd.append('StudentId', studentId);
    fd.append('StudentName', studentName || '');
    fd.append('Method', method || 'qr');
    fd.append('LogType', lType);

    showStatus(`Saving ${lType} record for ${studentName}…`, true);

    fetch('../../config/API/endpoints/index.php?action=record_attendance', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            closeAttendanceModal();
            showStatus(d.message || `${lType} recorded!`, d.success);
            if (typeof showModal === 'function') {
                showModal(d.message || `${lType} successfully recorded!`, d.success ? 'success' : 'error', d.success ? 'Attendance Recorded' : 'Attendance Notice');
            }
            if (d.success) {
                loadLog(eventId);
            }
            setTimeout(() => { if (stream) resumeFaceScan(eventId, 1000); }, 1500);
        })
        .catch(err => {
            closeAttendanceModal();
            showStatus('Error communicating with attendance server.', false);
            if (typeof showModal === 'function') {
                showModal('Error communicating with attendance server: ' + (err.message || err), 'error', 'Error');
            }
            setTimeout(() => { if (stream) resumeFaceScan(eventId, 1000); }, 1500);
        })
        .finally(() => {
            if (recordBtn) {
                recordBtn.disabled = false;
                recordBtn.style.opacity = '1';
                recordBtn.textContent = `Record ${currentLogType}`;
            }
            pendingAttendance = null;
        });
}
window.confirmAttendanceModal = confirmAttendanceModal;

function closeAttendanceModal(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    const attModal = document.getElementById('attModal');
    if (attModal) attModal.style.display = 'none';
    const evId = pendingAttendance ? pendingAttendance.eventId : getEventId();
    pendingAttendance = null;
    const recordBtn = document.getElementById('mdlBtnRecord');
    if (recordBtn) {
        recordBtn.disabled = false;
        recordBtn.style.opacity = '1';
        recordBtn.textContent = `Record ${currentLogType}`;
    }
    if (stream && evId) {
        resumeFaceScan(evId, 300);
    }
}
window.closeAttendanceModal = closeAttendanceModal;

// Record Attendance API Call
function recordAttendance(eventId, studentId, studentName, method, logType) {
  if (!eventId || !studentId) {
    showStatus('Event ID and Student ID are required.', false);
    return;
  }
  const lType = logType || currentLogType;
  const fd = new FormData();
  fd.append('EventId', eventId);
  fd.append('StudentId', studentId);
  fd.append('StudentName', studentName || '');
  fd.append('Method', method || 'manual');
  fd.append('LogType', lType);

  showStatus(`Saving ${lType} record…`, true);

  fetch('../../config/API/endpoints/index.php?action=record_attendance', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      showStatus(d.message || `${lType} recorded!`, d.success);
      if (typeof showModal === 'function') {
          showModal(d.message || `${lType} recorded!`, d.success ? 'success' : 'error', d.success ? 'Attendance Recorded' : 'Attendance Notice');
      }
      if (d.success) {
        loadLog(eventId);
      }
      setTimeout(() => { if (stream) resumeFaceScan(eventId, 1000); }, 1500);
    })
    .catch(err => {
      showStatus('Error communicating with attendance server.', false);
      if (typeof showModal === 'function') {
          showModal('Error communicating with attendance server: ' + (err.message || err), 'error', 'Error');
      }
      setTimeout(() => { if (stream) resumeFaceScan(eventId, 1000); }, 1500);
    });
}

// Manual Attendance Entry Trigger
function recordManual() {
  const evId = getEventId();
  if (!evId) return;
  const inp = document.getElementById('manualId');
  if (!inp) return;
  const sid = inp.value.trim();
  if (!sid) {
    showStatus('Please enter a Student ID or Student No.', false);
    return;
  }
  promptAttendance(evId, sid, 'manual');
  inp.value = '';
}

// Load Attendance Log Table
function loadLog(eventId) {
    if (!eventId) return;
    const tbody = document.getElementById('attLog');
    const attCount = document.getElementById('attCount');
    if (!tbody) return;

    fetch(`../../config/API/endpoints/index.php?action=get_attendance_log&EventId=${encodeURIComponent(eventId)}`)
      .then(r => r.json())
      .then(data => {
        if (!data.success || !data.attendance || data.attendance.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">No attendance records found for this event.</td></tr>';
          if (attCount) attCount.textContent = '0 recorded';
          return;
        }

        if (attCount) attCount.textContent = `${data.attendance.length} recorded`;

        tbody.innerHTML = data.attendance.map((a, i) => {
          const dt = a.ScannedAt ? new Date(a.ScannedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';
          const lType = a.LogType || 'Log In';
          const typeBadge = lType === 'Log Out'
              ? `<span class="mode-badge" style="background:#fee2e2;color:#b91c1c;">Log Out</span>`
              : `<span class="mode-badge" style="background:#dbeafe;color:#1d4ed8;">Log In</span>`;
          const methodStr = htmlspecialchars(a.Method || 'manual');
          
          return `<tr>
            <td style="padding:12px 16px;">${i + 1}</td>
            <td style="padding:12px 16px;font-weight:700;color:#0f172a;">${htmlspecialchars(a.StudentName || '—')}</td>
            <td style="padding:12px 16px;font-weight:600;color:#2563eb;">${htmlspecialchars(a.StudentId || '—')}</td>
            <td style="padding:12px 16px;">${typeBadge}</td>
            <td style="padding:12px 16px;"><span class="mode-badge mode-${methodStr}">${methodStr}</span></td>
            <td style="padding:12px 16px;color:#64748b;">${dt}</td>
            <td style="padding:12px 16px;text-align:right;">
              <button type="button" onclick="deleteAttendanceRow(${a.AttendanceId}, ${eventId})" style="color:#ef4444;border:none;background:none;cursor:pointer;font-size:18px;" title="Delete Record">
                <ion-icon name="trash-outline"></ion-icon>
              </button>
            </td>
          </tr>`;
        }).join('');
      })
      .catch(err => {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">Select an event to view attendance.</td></tr>';
      });
}

// Delete Attendance Record
function deleteAttendanceRow(attendanceId, eventId) {
  showConfirmModal('Are you sure you want to delete this attendance record?', function() {
    const fd = new FormData();
    fd.append('AttendanceId', attendanceId);
    
    fetch('../../config/API/endpoints/index.php?action=delete_attendance', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        showStatus(d.message || 'Record deleted', d.success);
        if (d.success) loadLog(eventId);
      })
      .catch(() => showStatus('Failed to delete attendance record.', false));
  }, 'Delete Attendance Record', 'danger');
}

function htmlspecialchars(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Upload QR Image Handling
function handleQrFileUpload(e) {
  const file = e.target ? e.target.files[0] : null;
  if (!file) return;
  const evId = getEventId();
  if (!evId) return;

  showStatus('Decoding uploaded QR image…', true);

  const reader = new FileReader();
  reader.onload = function(evt) {
    const img = new Image();
    img.onload = function() {
      const tempCanvas = document.createElement('canvas');
      const tempCtx = tempCanvas.getContext('2d');
      tempCanvas.width = img.width;
      tempCanvas.height = img.height;
      tempCtx.drawImage(img, 0, 0);

      try {
        const imgData = tempCtx.getImageData(0, 0, tempCanvas.width, tempCanvas.height);
        if (typeof jsQR !== 'undefined') {
          const decoded = jsQR(imgData.data, imgData.width, imgData.height);
          if (decoded && decoded.data) {
            const studentId = parseStudentQrPayload(decoded.data);
            if (studentId) {
              showStatus('QR code decoded successfully!', true);
              promptAttendance(evId, studentId, 'qr_upload');
            } else {
              showStatus('Invalid QR format in uploaded image.', false);
            }
          } else {
            showStatus('No QR code detected in the image. Please upload a clear QR photo.', false);
          }
        } else {
          showStatus('QR library loading, please try again in a moment.', false);
        }
      } catch (err) {
        showStatus('Error reading image: ' + err.message, false);
      }
    };
    img.src = evt.target.result;
  };
  reader.readAsDataURL(file);
  if (e.target) e.target.value = '';
}

// Anti-Spoofing Challenge Logic
const CHALLENGES = [
  { id: 'LEFT',  icon: 'arrow-back-circle-outline',    text: 'Look LEFT',  sub: 'Turn your head slowly to the left' },
  { id: 'RIGHT', icon: 'arrow-forward-circle-outline', text: 'Look RIGHT', sub: 'Turn your head slowly to the right' },
  { id: 'UP',    icon: 'arrow-up-circle-outline',      text: 'Look UP',    sub: 'Tilt your head gently upward' },
  { id: 'DOWN',  icon: 'arrow-down-circle-outline',    text: 'Look DOWN',  sub: 'Tilt your head gently downward' },
  { id: 'BLINK', icon: 'eye-outline',                  text: 'BLINK Eyes', sub: 'Blink your eyes firmly' },
];
let asStream = null, asChallenge = null, asRunning = false;
let asPollTimer = null, asTimeoutTimer = null, asHoldTimer = null, asCountdownInterval = null;
let asCanvas = document.createElement('canvas');
let asCtx = asCanvas.getContext('2d', { willReadFrequently: true });
let asApiLoaded = false;

// Silent periodic refresh for active event log (if event selected)
let autoRefreshTimer = null;

function startAutoRefreshTimer() {
  clearInterval(autoRefreshTimer);
  autoRefreshTimer = setInterval(() => {
    const sel = document.getElementById('eventSelect');
    if (!sel || !sel.selectedOptions || !sel.selectedOptions[0]) return;
    const status = (sel.selectedOptions[0].dataset.status || '').toLowerCase();
    if (status === 'completed' || status === 'cancelled' || status === 'archived') {
      clearInterval(autoRefreshTimer);
      return;
    }
    const evId = getEventId();
    if (evId) loadLog(evId);
  }, 10000);
}

// Tab notification state
let asOriginalTitle = document.title;

function startAsTitleFlash() {
  asOriginalTitle = document.title.replace(/^\(\d+\)\s*/, '');
  document.title = `(1) ${asOriginalTitle}`;
}

function stopAsTitleFlash() {
  document.title = asOriginalTitle.replace(/^\(\d+\)\s*/, '');
}

async function openAntiSpoofModal(eventId) {
  const overlay = document.getElementById('antiSpoofOverlay');
  if (!overlay) return;
  overlay.style.display = 'flex';
  setAsChallengeUI('help-circle-outline', 'Starting camera…', 'Please allow camera access');
  const fill = document.getElementById('asTimerFill');
  if (fill) { fill.style.transition = 'none'; fill.style.width = '100%'; }
  const statusEl = document.getElementById('asStatusText');
  if (statusEl) statusEl.textContent = '';
  const countdownText = document.getElementById('asCountdownText');
  if (countdownText) countdownText.textContent = '5.0s';

  startAsTitleFlash();

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
      stopAsTitleFlash();
      return;
    }
  }

  try {
    asStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 640, height: 480 } });
    const vid = document.getElementById('asVideo');
    if (vid) {
      vid.srcObject = asStream;
      await new Promise(r => { vid.onloadedmetadata = r; });
      vid.play();
    }
  } catch(e) {
    setAsChallengeUI('ban-outline', 'Camera denied', 'Please allow camera access in your browser.');
    stopAsTitleFlash();
    return;
  }

  // Setup face tracker canvas overlay
  setupFaceTrackerCanvas();

  asChallenge = CHALLENGES[Math.floor(Math.random() * CHALLENGES.length)];
  setAsChallengeUI(asChallenge.icon || 'help-circle-outline', asChallenge.text, asChallenge.sub);
  if (statusEl) statusEl.textContent = 'Face detection active...';

  if (fill) {
    fill.style.transition = `width 5000ms linear`;
    fill.style.width = '0%';
  }

  const startTime = Date.now();
  const duration = 5000;
  clearInterval(asCountdownInterval);
  asCountdownInterval = setInterval(() => {
    const elapsed = Date.now() - startTime;
    const remaining = Math.max(0, (duration - elapsed) / 1000);
    if (countdownText) countdownText.textContent = remaining.toFixed(1) + 's';
    if (remaining <= 0) clearInterval(asCountdownInterval);
  }, 100);

  asRunning = true;
  asPollTimer = setInterval(() => pollLiveness(eventId), 100);
  asTimeoutTimer = setTimeout(() => {
    if (asRunning) failAntiSpoof('Time ran out! Please try again.');
  }, 5000);
}

function setupFaceTrackerCanvas() {
  const vid = document.getElementById('asVideo');
  if (!vid) return;
  let trackerCanvas = document.getElementById('asFaceTracker');
  if (!trackerCanvas) {
    trackerCanvas = document.createElement('canvas');
    trackerCanvas.id = 'asFaceTracker';
    trackerCanvas.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;border-radius:12px;';
    vid.parentElement.style.position = 'relative';
    vid.insertAdjacentElement('afterend', trackerCanvas);
  }
}

function openAntiSpoofCheckModal(eventId) {
  openAntiSpoofModal(eventId);
}

function setAsChallengeUI(iconName, text, sub) {
  const txtEl = document.getElementById('asChallengeText');
  const subEl = document.getElementById('asChallengeSubText');
  if (txtEl) txtEl.textContent = text;
  if (subEl) subEl.textContent = sub;
}

async function pollLiveness(eventId) {
  if (!asRunning) return;
  const vid = document.getElementById('asVideo');
  if (!vid || vid.readyState < 2) return;

  asCanvas.width  = vid.videoWidth  || 320;
  asCanvas.height = vid.videoHeight || 240;
  asCtx.drawImage(vid, 0, 0, asCanvas.width, asCanvas.height);

  try {
    const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.45 });
    const allDets = await faceapi.detectAllFaces(asCanvas, opts).withFaceLandmarks();
    const statusEl = document.getElementById('asStatusText');

    // Multiple faces — reject
    if (allDets && allDets.length > 1) {
      drawFaceTracker(null, vid);
      if (statusEl) statusEl.textContent = 'Multiple faces detected! Only one person allowed. Please retry.';
      clearTimeout(asHoldTimer); asHoldTimer = null;
      return;
    }

    const det = allDets && allDets.length === 1 ? allDets[0] : null;

    // Draw face tracker overlay
    drawFaceTracker(det, vid);

    if (!det) {
      if (statusEl) statusEl.textContent = 'No face detected – position yourself in camera frame';
      clearTimeout(asHoldTimer); asHoldTimer = null;
      return;
    }

    const passed = checkPose(det.landmarks, asChallenge.id);
    if (passed) {
      if (!asHoldTimer) {
        if (statusEl) statusEl.textContent = 'Hold position…';
        asHoldTimer = setTimeout(() => passAntiSpoof(eventId), 300);
      }
    } else {
      if (statusEl) statusEl.textContent = `Face detected – perform challenge`;
      clearTimeout(asHoldTimer); asHoldTimer = null;
    }
  } catch(e) {}
}

function drawFaceTracker(det, vid) {
  const trackerCanvas = document.getElementById('asFaceTracker');
  if (!trackerCanvas) return;
  const ctx = trackerCanvas.getContext('2d');

  // Match canvas resolution to video display size
  const rect = vid.getBoundingClientRect();
  trackerCanvas.width = rect.width;
  trackerCanvas.height = rect.height;
  ctx.clearRect(0, 0, trackerCanvas.width, trackerCanvas.height);

  if (!det) return;

  const box = det.detection.box;
  const vw = vid.videoWidth || 1;
  const vh = vid.videoHeight || 1;
  const sx = rect.width / vw;
  const sy = rect.height / vh;

  // Mirror X because video is scaleX(-1)
  const bx = rect.width - (box.x + box.width) * sx;
  const by = box.y * sy;
  const bw = box.width * sx;
  const bh = box.height * sy;

  // Expand box slightly for padding
  const pad = 12;
  const fx = bx - pad;
  const fy = by - pad;
  const fw = bw + pad * 2;
  const fh = bh + pad * 2;

  // Draw rounded rectangle tracker
  const r = 14;
  ctx.strokeStyle = asHoldTimer ? '#22c55e' : '#38bdf8';
  ctx.lineWidth = 2.5;
  ctx.shadowColor = asHoldTimer ? 'rgba(34,197,94,0.5)' : 'rgba(56,189,248,0.5)';
  ctx.shadowBlur = 12;
  ctx.beginPath();
  ctx.moveTo(fx + r, fy);
  ctx.lineTo(fx + fw - r, fy);
  ctx.quadraticCurveTo(fx + fw, fy, fx + fw, fy + r);
  ctx.lineTo(fx + fw, fy + fh - r);
  ctx.quadraticCurveTo(fx + fw, fy + fh, fx + fw - r, fy + fh);
  ctx.lineTo(fx + r, fy + fh);
  ctx.quadraticCurveTo(fx, fy + fh, fx, fy + fh - r);
  ctx.lineTo(fx, fy + r);
  ctx.quadraticCurveTo(fx, fy, fx + r, fy);
  ctx.closePath();
  ctx.stroke();

  // Corner accents for futuristic look
  ctx.shadowBlur = 0;
  ctx.lineWidth = 3;
  const cl = 16;
  ctx.strokeStyle = asHoldTimer ? '#4ade80' : '#7dd3fc';
  // Top-left
  ctx.beginPath(); ctx.moveTo(fx, fy + cl); ctx.lineTo(fx, fy); ctx.lineTo(fx + cl, fy); ctx.stroke();
  // Top-right
  ctx.beginPath(); ctx.moveTo(fx + fw - cl, fy); ctx.lineTo(fx + fw, fy); ctx.lineTo(fx + fw, fy + cl); ctx.stroke();
  // Bottom-left
  ctx.beginPath(); ctx.moveTo(fx, fy + fh - cl); ctx.lineTo(fx, fy + fh); ctx.lineTo(fx + cl, fy + fh); ctx.stroke();
  // Bottom-right
  ctx.beginPath(); ctx.moveTo(fx + fw - cl, fy + fh); ctx.lineTo(fx + fw, fy + fh); ctx.lineTo(fx + fw, fy + fh - cl); ctx.stroke();

  // Label
  ctx.font = '600 11px Inter, sans-serif';
  ctx.fillStyle = asHoldTimer ? '#4ade80' : '#7dd3fc';
  ctx.fillText(asHoldTimer ? '✓ MATCHED' : '● TRACKING', fx + 4, fy - 6);
}

function checkPose(landmarks, direction) {
  const pts  = landmarks.positions;
  const nose  = pts[30];
  const lEye  = pts[36];
  const rEye  = pts[45];
  const chin  = pts[8];

  const eyeMidX   = (lEye.x + rEye.x) / 2;
  const eyeWidth  = Math.abs(rEye.x - lEye.x);
  const noseOffX  = nose.x - eyeMidX;

  if (direction === 'LEFT')  return (noseOffX / eyeWidth) < -0.22;
  if (direction === 'RIGHT') return (noseOffX / eyeWidth) > 0.22;

  const eyeMidY  = (lEye.y + rEye.y) / 2;
  const noseOffY = nose.y - eyeMidY;
  if (direction === 'UP')   return (noseOffY / eyeWidth) < 0.35;
  if (direction === 'DOWN') return (noseOffY / eyeWidth) > 0.65;

  if (direction === 'BLINK') {
    const eyeH = (pts[37].y + pts[38].y)/2 - (pts[40].y + pts[41].y)/2;
    return Math.abs(eyeH) < 4;
  }
  return false;
}

function passAntiSpoof(eventId) {
  if (!asRunning) return;
  stopAntiSpoofCamera();
  setAsChallengeUI('checkmark-circle-outline', 'Liveness Verified!', 'Challenge passed successfully');
  const statusEl = document.getElementById('asStatusText');
  if (statusEl) statusEl.textContent = 'Anti-spoofing passed! Starting face scanner…';
  setTimeout(async () => {
    closeAntiSpoofModal();
    await startCamera('unified');
  }, 1200);
}

function failAntiSpoof(reason) {
  if (!asRunning) return;
  stopAntiSpoofCamera();
  setAsChallengeUI('close-circle-outline', 'Liveness Failed', reason);
  const statusEl = document.getElementById('asStatusText');
  if (statusEl) statusEl.textContent = 'Spoofing attempt blocked or timed out.';
  setTimeout(() => closeAntiSpoofModal(), 2200);
}

function stopAntiSpoofCamera() {
  asRunning = false;
  clearInterval(asPollTimer);
  clearInterval(asCountdownInterval);
  clearTimeout(asTimeoutTimer);
  clearTimeout(asHoldTimer);
  asPollTimer = asCountdownInterval = asTimeoutTimer = asHoldTimer = null;
  if (asStream) { asStream.getTracks().forEach(t => t.stop()); asStream = null; }
  const vid = document.getElementById('asVideo');
  if (vid) vid.srcObject = null;
  // Clear face tracker
  const trackerCanvas = document.getElementById('asFaceTracker');
  if (trackerCanvas) { const ctx = trackerCanvas.getContext('2d'); ctx.clearRect(0, 0, trackerCanvas.width, trackerCanvas.height); }
  stopAsTitleFlash();
}

function closeAntiSpoofModal() {
  stopAntiSpoofCamera();
  const overlay = document.getElementById('antiSpoofOverlay');
  if (overlay) overlay.style.display = 'none';
}

// Safely attach event listeners once DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  startContinuousMonitorTimer();

  const eventSelect = document.getElementById('eventSelect');
  if (eventSelect) {
    eventSelect.addEventListener('change', () => {
      checkEventCompletedState();
      const evId = getEventId();
      if (evId) loadLog(evId);
    });
    checkEventCompletedState();
    const initialEvId = eventSelect.value;
    if (initialEvId) loadLog(initialEvId);
  }

  const btnUnified = document.getElementById('btnUnified');
  if (btnUnified) {
    btnUnified.addEventListener('click', () => startCamera('unified'));
  }

  const btnUploadQR = document.getElementById('btnUploadQR');
  const qrFileInput = document.getElementById('qrFileInput');
  if (btnUploadQR && qrFileInput) {
    btnUploadQR.addEventListener('click', () => qrFileInput.click());
    qrFileInput.addEventListener('change', handleQrFileUpload);
  }

  const btnStop = document.getElementById('btnStop');
  if (btnStop) {
    btnStop.addEventListener('click', stopCamera);
  }

  const btnManual = document.getElementById('btnManual');
  if (btnManual) {
    btnManual.addEventListener('click', recordManual);
  }

  const manualInp = document.getElementById('manualId');
  if (manualInp) {
    manualInp.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') recordManual();
    });
  }

  const btnAntiSpoof = document.getElementById('btnAntiSpoof');
  if (btnAntiSpoof) {
    btnAntiSpoof.addEventListener('click', () => {
      const ev = getEventId();
      if (ev) openAntiSpoofModal(ev);
    });
  }

  const cancelModalBtn = document.getElementById('mdlBtnCancel');
  if (cancelModalBtn) {
    cancelModalBtn.addEventListener('click', closeAttendanceModal);
  }

  const recordModalBtn = document.getElementById('mdlBtnRecord');
  if (recordModalBtn) {
    recordModalBtn.addEventListener('click', confirmAttendanceModal);
  }
});

// Export control functions globally
window.startCamera = startCamera;
window.stopCamera = stopCamera;
window.handleQrFileUpload = handleQrFileUpload;
window.recordManual = recordManual;
window.getEventId = getEventId;
window.openAntiSpoofModal = openAntiSpoofModal;
window.openAntiSpoofCheckModal = openAntiSpoofModal;
window.confirmAttendanceModal = confirmAttendanceModal;
window.closeAttendanceModal = closeAttendanceModal;
window.deleteAttendanceRow = deleteAttendanceRow;
window.deleteAttendance = deleteAttendanceRow;
window.setLogType = setLogType;
window.loadLog = loadLog;
