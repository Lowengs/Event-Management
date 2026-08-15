
(() => {
    'use strict';

    const $ = id => document.getElementById(id);
    let toastTimer;

    function showToast(msg, type = 'info') {
        const icons = { success: '', error: '', info: '', warning: '' };
        const el = $('toast');
        el.className = `toast-${type} show`;
        $('toastIcon').textContent = '';
        $('toastMsg').textContent  = msg;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 5500);
    }

    function setInputError(inputId, errorId, msg) {
        const inp = $(inputId);
        if (inp) inp.classList.toggle('input-error', !!msg);
        const err = $(errorId);
        if (err) { err.textContent = msg; err.style.display = msg ? 'block' : 'none'; }
    }

    function setError(id, msg) {
        const err = $(id);
        if (err) { err.textContent = msg; err.style.display = msg ? 'block' : 'none'; }
    }

    const TOTAL_STEPS = 4;

    function goToStep(n) {
        for (let i = 1; i <= 5; i++) {
            const p = $(`panel${i}`);
            if (p) p.classList.toggle('active', i === n);
        }
        for (let i = 1; i <= TOTAL_STEPS; i++) {
            const si = $(`sitem${i}`);
            if (si) {
                si.classList.toggle('active', i === n);
                si.classList.toggle('done',   i < n);
            }
            if (i < TOTAL_STEPS) {
                const sl = $(`sline${i}`);
                if (sl) sl.classList.toggle('done', i < n);
            }
        }
        if (n === 5) $('stepWrapper').style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    $('next1').addEventListener('click', () => {
        let ok = true;

        const sid = $('f_student_id').value.trim();
        setInputError('f_student_id', 'e_student_id', sid ? '' : 'Student ID is required.');
        if (!sid) ok = false;

        const fn = $('f_first_name').value.trim();
        setInputError('f_first_name', 'e_first_name', fn ? '' : 'First name is required.');
        if (!fn) ok = false;

        const ln = $('f_last_name').value.trim();
        setInputError('f_last_name', 'e_last_name', ln ? '' : 'Last name is required.');
        if (!ln) ok = false;

        const em = $('f_email').value.trim();
        if (!em) {
            setInputError('f_email', 'e_email', 'Email address is required.');
            ok = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
            setInputError('f_email', 'e_email', 'Please enter a valid email address.');
            ok = false;
        } else {
            setInputError('f_email', 'e_email', '');
        }

        const addr = $('f_address').value.trim();
        setInputError('f_address', 'e_address', addr ? '' : 'Home address is required.');
        if (!addr) ok = false;

        const course = $('f_course').value;
        setError('e_course', course ? '' : 'Please select a course.');
        if (!course) ok = false;

        const yr = $('f_year_level').value;
        setError('e_year_level', yr ? '' : 'Please select a year level.');
        if (!yr) ok = false;

        if (ok) goToStep(2);
    });

    $('f_password').addEventListener('input', () => {
        const pwd = $('f_password').value;
        const levels = [
            { pct: '20%',  color: '#ef4444', text: 'Very weak'   },
            { pct: '40%',  color: '#f97316', text: 'Weak'        },
            { pct: '60%',  color: '#eab308', text: 'Fair'        },
            { pct: '80%',  color: '#22c55e', text: 'Strong'      },
            { pct: '100%', color: '#4fd1c5', text: 'Very strong' },
        ];
        let score = 0;
        if (pwd.length >= 8)           score++;
        if (pwd.length >= 12)          score++;
        if (/[A-Z]/.test(pwd))         score++;
        if (/[0-9]/.test(pwd))         score++;
        if (/[^A-Za-z0-9]/.test(pwd))  score++;

        const fill  = $('strengthFill');
        const label = $('strengthLabel');
        if (!pwd) { fill.style.width = '0'; label.textContent = ''; return; }
        const lv = levels[Math.max(0, score - 1)];
        fill.style.width      = lv.pct;
        fill.style.background = lv.color;
        label.textContent     = lv.text;
        label.style.color     = lv.color;
    });

    document.querySelectorAll('.pw-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const inp = $(btn.dataset.target);
            const isHidden = inp.type === 'password';
            inp.type = isHidden ? 'text' : 'password';
            btn.textContent = isHidden ? '🙈' : '👁';
        });
    });

    $('back2').addEventListener('click', () => goToStep(1));

    $('next2').addEventListener('click', () => {
        let ok = true;

        const uname = $('f_username').value.trim();
        if (!uname) {
            setInputError('f_username', 'e_username', 'Username is required.');
            ok = false;
        } else if (uname.length < 4) {
            setInputError('f_username', 'e_username', 'Username must be at least 4 characters.');
            ok = false;
        } else {
            setInputError('f_username', 'e_username', '');
        }

        const pw = $('f_password').value;
        if (!pw) {
            setInputError('f_password', 'e_password', 'Password is required.');
            ok = false;
        } else if (pw.length < 8) {
            setInputError('f_password', 'e_password', 'Password must be at least 8 characters.');
            ok = false;
        } else {
            setInputError('f_password', 'e_password', '');
        }

        const cpw = $('f_confirm_password').value;
        if (!cpw) {
            setInputError('f_confirm_password', 'e_confirm_password', 'Please confirm your password.');
            ok = false;
        } else if (pw !== cpw) {
            setInputError('f_confirm_password', 'e_confirm_password', 'Passwords do not match.');
            ok = false;
        } else {
            setInputError('f_confirm_password', 'e_confirm_password', '');
        }

        if (ok) goToStep(3);
    });

    let stream           = null;
    let detectionLoop    = null;
    let faceDescriptor   = null;    // 128-float array
    let facePhotoDataURL = null;    // base64 JPEG
    let modelsLoaded     = false;

    const video  = $('faceVideo');
    const canvas = $('faceCanvas');
    const ctx    = canvas.getContext('2d');

    async function loadModels() {
        if (modelsLoaded) return true;
        $('modelLoading').style.display = 'flex';
        try {
            const MODEL_URL = '../../assets/models';
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            ]);
            modelsLoaded = true;
            return true;
        } catch (err) {
            console.error('Model load failed:', err);
            showToast('Could not load face detection models. Check your internet connection.', 'error');
            return false;
        } finally {
            $('modelLoading').style.display = 'none';
        }
    }

    $('openCamBtn').addEventListener('click', async () => {
        const btn = $('openCamBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="btn-spinner"></span> Starting…';

        const ok = await loadModels();
        if (!ok) { btn.disabled = false; btn.textContent = 'Open Camera'; return; }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
            });
            video.srcObject = stream;
            video.style.display = 'block';
            $('camPlaceholder').style.display = 'none';

            video.addEventListener('loadedmetadata', () => {
                canvas.width  = video.videoWidth;
                canvas.height = video.videoHeight;
                canvas.style.display = 'block';
                $('camGuideRing').classList.add('visible');
                startDetection();
            }, { once: true });

            btn.style.display = 'none';
            $('faceStatusText').innerHTML = 'Position your face within the oval guide…';

        } catch (err) {
            btn.disabled = false;
            btn.textContent = 'Open Camera';
            if (err.name === 'NotAllowedError') {
                showToast('Camera access was denied. Please allow camera access in your browser settings.', 'error');
            } else {
                showToast('Could not access camera: ' + err.message, 'error');
            }
        }
    });

        function startDetection() {
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
        let stableFrames = 0;
        let isCapturing = false;

        detectionLoop = setInterval(async () => {
            if (!video || video.readyState < 2 || isCapturing) return;
            try {
                const detections = await faceapi
                    .detectAllFaces(video, opts)
                    .withFaceLandmarks()
                    .withFaceDescriptors();

                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const resized = faceapi.resizeResults(detections, { width: video.videoWidth, height: video.videoHeight });

                resized.forEach(d => {
                    const box   = d.detection.box;
                    const score = d.detection.score;
                    const color = score > 0.60 ? '#4fd1c5' : '#f97316';
                    ctx.strokeStyle = color;
                    ctx.lineWidth   = 2.5;
                    ctx.beginPath();
                    ctx.roundRect(box.x, box.y, box.width, box.height, 8);
                    ctx.stroke();
                    ctx.fillStyle = color;
                    ctx.font = 'bold 13px Poppins, sans-serif';
                    ctx.fillText(`${(score * 100).toFixed(0)}%`, box.x + 4, box.y - 8);
                });

                const statusText = $('faceStatusText');
                const guideRing  = $('camGuideRing');

                if (detections.length === 1 && detections[0].detection.score > 0.60) {
                    stableFrames++;
                    if (stableFrames >= 3) {
                        isCapturing = true;
                        guideRing.classList.add('detected');
                        statusText.innerHTML = 'Processing face...';
                        statusText.className = 'status-success';
                        captureFace(); // Auto-capture!
                    } else {
                        statusText.innerHTML = `Face detected! Hold still... (${stableFrames}/3)`;
                        statusText.className = 'status-success';
                        guideRing.classList.add('detected');
                    }
                } else if (detections.length > 1) {
                    stableFrames = 0;
                    statusText.textContent = 'Multiple faces detected. Please be alone in frame.';
                    statusText.className   = 'status-warning';
                    guideRing.classList.remove('detected');
                } else {
                    stableFrames = 0;
                    statusText.textContent = 'Position your face within the oval guide…';
                    statusText.className   = '';
                    guideRing.classList.remove('detected');
                }
            } catch (_) {  }
        }, 200);
    }

    function stopWebcam() {
        clearInterval(detectionLoop);
        detectionLoop = null;
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        video.srcObject = null;
    }

    async function captureFace() {
        try {
            const snap = document.createElement('canvas');
            snap.width  = video.videoWidth;
            snap.height = video.videoHeight;
            snap.getContext('2d').drawImage(video, 0, 0);

            const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
            const detection = await faceapi
                .detectSingleFace(snap, opts)
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                showToast('No face detected in final frame. Retrying...', 'warning');
                return; // The interval will just resume and try again because we don't clear it yet
            }

            faceDescriptor   = Array.from(detection.descriptor);
            facePhotoDataURL = snap.toDataURL('image/jpeg', 0.88);

            $('capturedImg').src = facePhotoDataURL;
            $('cameraArea').style.display     = 'none';
            $('capturePreview').style.display = 'block';

            stopWebcam();
            $('next3').disabled = false;
            showToast('Face captured successfully!', 'success');

        } catch (err) {
            console.error('Capture error:', err);
            showToast('Failed to process face. Please try again.', 'error');
            stopWebcam();
            retakeFace();
        }
    }

    function retakeFace() {
        faceDescriptor = null; facePhotoDataURL = null;
        $('capturePreview').style.display = 'none';
        $('cameraArea').style.display     = 'block';
        $('next3').disabled               = true;

        $('openCamBtn').style.display = 'inline-flex';
        $('openCamBtn').disabled      = false;
        $('openCamBtn').textContent   = 'Open Camera';

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        video.style.display   = 'none';
        canvas.style.display  = 'none';
        $('camGuideRing').classList.remove('visible', 'detected');
        $('camPlaceholder').style.display = 'flex';
        $('faceStatusText').innerHTML = 'Click <strong>Open Camera</strong> to begin face registration';
        $('faceStatusText').className = '';
    }

    $('retakeBtn').addEventListener('click', retakeFace);
    $('retakeBtn2').addEventListener('click', retakeFace);

    $('back3').addEventListener('click', () => { stopWebcam(); goToStep(2); });
    $('next3').addEventListener('click', () => {
        if (!faceDescriptor) { showToast('Please capture your face before continuing.', 'warning'); return; }
        goToStep(4);
    });

    function setupFileZone(zoneId, inputId, innerId) {
        const zone  = $(zoneId);
        const input = $(inputId);
        const inner = $(innerId);

        zone.addEventListener('click',   () => input.click());
        zone.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault(); zone.classList.remove('drag-over');
            if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; updateZoneLabel(inner, e.dataTransfer.files[0]); }
        });
        input.addEventListener('change', () => { if (input.files.length) updateZoneLabel(inner, input.files[0]); });
    }

    function updateZoneLabel(inner, file) {
        inner.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24"
                 fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span style="color:#86efac;font-weight:500;">${file.name}</span>
            <span class="file-hint">${(file.size / 1024 / 1024).toFixed(2)} MB</span>`;
    }

    setupFileZone('photoZone', 'f_profile_photo', 'photoInner');
    setupFileZone('corZone',   'f_cor',            'corInner');

    $('back4').addEventListener('click', () => goToStep(3));

    const privModal = $('privModal');
    $('openPrivacyBtn').addEventListener('click', e => { e.preventDefault(); privModal.classList.add('open'); });
    $('closePrivBtn').addEventListener('click',   () => privModal.classList.remove('open'));
    $('agreePrivBtn').addEventListener('click',   () => {
        privModal.classList.remove('open');
        $('f_consent').checked = true;
        setError('e_consent', '');
    });
    privModal.addEventListener('click', e => { if (e.target === privModal) privModal.classList.remove('open'); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && privModal.classList.contains('open')) privModal.classList.remove('open');
    });

    // Validation / Error Modal helpers
    const valModalOverlay  = $('valModalOverlay');
    const valModalCloseBtn = $('valModalCloseBtn');

    function showValidationModal(title, message, hint) {
        if (!valModalOverlay) return;
        if (title) $('valModalTitle').textContent = title;
        if (message) $('valModalMsg').textContent = message;
        if (hint !== undefined && $('valModalHint')) {
            $('valModalHint').textContent = hint;
            $('valModalHint').style.display = hint ? 'block' : 'none';
        }
        valModalOverlay.classList.add('active');
    }

    function hideValidationModal() {
        if (valModalOverlay) valModalOverlay.classList.remove('active');
    }

    if (valModalCloseBtn) valModalCloseBtn.addEventListener('click', hideValidationModal);
    if (valModalOverlay) {
        valModalOverlay.addEventListener('click', e => {
            if (e.target === valModalOverlay) hideValidationModal();
        });
    }
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && valModalOverlay && valModalOverlay.classList.contains('active')) {
            hideValidationModal();
        }
    });

    const corInput = $('f_cor');
    if (corInput) {
        corInput.addEventListener('change', () => {
            if (corInput.files.length) {
                const f = corInput.files[0];
                if (f.type !== 'application/pdf' && !f.name.toLowerCase().endsWith('.pdf')) {
                    setError('e_cor', 'Only PDF files are allowed.');
                    showValidationModal('Invalid File Format', 'Please upload your Certificate of Registration (COR) in PDF format only.', 'Accepted file type: PDF (.pdf)');
                    corInput.value = '';
                    return;
                } else {
                    setError('e_cor', '');
                }
            }
        });
    }

    $('submitBtn').addEventListener('click', async () => {
        let ok = true;

        const photo = $('f_profile_photo').files[0];
        if (!photo) {
            setError('e_profile_photo', 'Profile photo is required.'); ok = false;
        } else if (photo.size > 5 * 1024 * 1024) {
            setError('e_profile_photo', 'Profile photo must be smaller than 5 MB.'); ok = false;
        } else { setError('e_profile_photo', ''); }

        const phone = $('f_phone').value.trim();
        setInputError('f_phone', 'e_phone', phone ? '' : 'Phone number is required.');
        if (!phone) ok = false;

        const cor = $('f_cor').files[0];
        if (!cor) {
            setError('e_cor', 'COR document is required.'); ok = false;
        } else if (cor.type !== 'application/pdf' && !cor.name.toLowerCase().endsWith('.pdf')) {
            setError('e_cor', 'Only PDF format is accepted for COR upload.');
            showValidationModal('Invalid File Format', 'Please upload your Certificate of Registration (COR) in PDF format only.', 'Accepted file type: PDF (.pdf)');
            ok = false;
        } else if (cor.size > 10 * 1024 * 1024) {
            setError('e_cor', 'COR must be smaller than 10 MB.'); ok = false;
        } else { setError('e_cor', ''); }

        if (!$('f_consent').checked) {
            setError('e_consent', 'You must agree to the Data Privacy Agreement to proceed.'); ok = false;
        } else { setError('e_consent', ''); }

        if (!ok) return;

        if (!faceDescriptor || !facePhotoDataURL) {
            showToast('Face registration data is missing. Please go back to Step 3.', 'error'); return;
        }

        const btn = $('submitBtn');
        btn.disabled  = true;
        btn.innerHTML = '<span class="btn-spinner"></span> Validating COR with AI...';

        const valFd = new FormData();
        valFd.append('cor', cor);
        valFd.append('first_name', $('f_first_name').value.trim());
        valFd.append('last_name', $('f_last_name').value.trim());
        valFd.append('middle_name', $('f_middle_name').value.trim());
        valFd.append('student_id', $('f_student_id').value.trim());
        valFd.append('course', $('f_course').value);
        valFd.append('year_level', $('f_year_level').value);
        valFd.append('section', $('f_section').value || '');

        try {
            const valRes = await fetch('../../config/API/endpoints/index.php?action=validate_cor', { method: 'POST', body: valFd });
            const valData = await valRes.json();
            if (!valData.success) {
                const errorMsg = valData.message || valData.error || valData.details || valData.reason || 'The details in your COR do not match your inputted registration information.';
                showToast('COR Validation Failed', 'error');
                btn.disabled = false;
                btn.innerHTML = 'Submit Registration';
                showValidationModal('COR Validation Mismatch', errorMsg, 'Please ensure your inputted details exactly match your uploaded document.');
                return; // Stop submission
            }
        } catch (e) {
            console.error(e);
            showToast('Error validating COR. Please try again.', 'error');
            btn.disabled = false;
            btn.innerHTML = 'Submit Registration';
            return;
        }
        
        btn.innerHTML = '<span class="btn-spinner"></span> Submitting…';

        const fd = new FormData();
        fd.append('student_id',      $('f_student_id').value.trim());
        fd.append('first_name',      $('f_first_name').value.trim());
        fd.append('middle_name',     $('f_middle_name').value.trim());
        fd.append('last_name',       $('f_last_name').value.trim());
        fd.append('address',         $('f_address').value.trim());
        fd.append('email',           $('f_email').value.trim());
        fd.append('course',          $('f_course').value);
        fd.append('year_level',      $('f_year_level').value);
        fd.append('section',         $('f_section').value || '');
        fd.append('username',        $('f_username').value.trim());
        fd.append('password',        $('f_password').value);
        fd.append('phone',           phone);
        fd.append('profile_photo',   photo);
        fd.append('cor_document',    cor);
        fd.append('face_descriptor', JSON.stringify(faceDescriptor));
        fd.append('face_photo',      facePhotoDataURL);

        try {
            const res  = await fetch('../../config/API/endpoints/index.php?action=student_register', { method: 'POST', body: fd });
            const data = await res.json();

            if (data.success) {
                stopWebcam();
                
                const panel5 = document.getElementById('panel5');
                const svgIcon = panel5.querySelector('svg');
                const statusTitle = panel5.querySelector('h2');
                const statusMessage = panel5.querySelector('p');
                
                if (data.status === 'active') {
                    statusTitle.textContent = "Registration Successful!";
                    statusMessage.innerHTML = "Your account has been verified and is now <strong>Active</strong>.<br>You can now log in securely.";
                    svgIcon.style.stroke = "#10b981"; // success green
                } else {
                    statusTitle.textContent = "Registration Submitted!";
                    statusMessage.innerHTML = "Your account is pending review.<br>We'll notify you via email once approved.";
                    svgIcon.style.stroke = "#f59e0b"; // warning amber
                }
                
                goToStep(5);
                showToast(data.message, 'success');
            } else {
                showToast(data.message, 'error');
                btn.disabled    = false;
                btn.textContent = 'Submit Registration';

                if (data.field === 'email' || data.field === 'student_id') {
                    goToStep(1);
                    if (data.field === 'email')      setInputError('f_email',      'e_email',      data.message);
                    if (data.field === 'student_id') setInputError('f_student_id', 'e_student_id', data.message);
                } else if (data.field === 'username') {
                    goToStep(2);
                    setInputError('f_username', 'e_username', data.message);
                }
            }
        } catch (err) {
            showToast('Network error. Please check your connection and try again.', 'error');
            btn.disabled    = false;
            btn.textContent = 'Submit Registration';
        }
    });

    window.addEventListener('beforeunload', stopWebcam);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && stream) stopWebcam();
    });

})();



(function() {
        let _corExtracted = null;

        function showCorToast(msg, success) {
            const t = document.getElementById('toast');
            if (t) {
                const icon = document.getElementById('toastIcon');
                const msgEl = document.getElementById('toastMsg');
                if (icon)  icon.textContent = success ? '\u2713' : '\u2717';
                if (msgEl) msgEl.textContent = msg;
                t.className = 'toast ' + (success ? 'toast-success' : 'toast-error');
                t.style.opacity = '1';
                setTimeout(() => { t.style.opacity = '0'; }, 3500);
            }
        }

        const corFileInput = document.getElementById('f_cor');
        if (corFileInput) {
            corFileInput.addEventListener('change', function () {
                const panel = document.getElementById('corAiPanel');
                if (this.files && this.files[0] && panel) {
                    panel.style.display = 'block';
                    document.getElementById('corAiResult').style.display = 'none';
                    document.getElementById('corAiError').style.display = 'none';
                    _corExtracted = null;
                }
            });
        }

        const scanBtn = document.getElementById('corAiScanBtn');
        if (scanBtn) {
            scanBtn.addEventListener('click', async function () {
                const fileInput = document.getElementById('f_cor');
                if (!fileInput || !fileInput.files[0]) {
                    showCorToast('Please upload your COR first.', false); return;
                }

                const spinner = document.getElementById('corAiSpinner');
                scanBtn.disabled = true;
                scanBtn.style.opacity = '0.7';
                scanBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation:corSpin .8s linear infinite"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Analyzing...';
                if (spinner) spinner.style.display = 'block';
                document.getElementById('corAiResult').style.display = 'none';
                document.getElementById('corAiError').style.display = 'none';

                const fd = new FormData();
                fd.append('cor', fileInput.files[0]);

                try {
                    const r = await fetch('../../config/API/endpoints/index.php?action=ai_analyze_cor', { method: 'POST', body: fd });
                    const d = await r.json();

                    if (d.success && d.data) {
                        _corExtracted = d.data;

                        const fields = [
                            ['Student ID',  d.data.StudentId   || '—'],
                            ['First Name',  d.data.FirstName   || '—'],
                            ['Middle Name', d.data.MiddleName  || '(none)'],
                            ['Last Name',   d.data.LastName    || '—'],
                            ['Course',      d.data.Course      || '—'],
                            ['Year Level',  d.data.YearLevel   || '—'],
                            ['Section',     d.data.Section     || '—'],
                            ['School Year', d.data.SchoolYear  || '—'],
                        ];
                        const tbody = document.getElementById('corAiTable');
                        tbody.innerHTML = fields.map(([label, val]) =>
                            `<tr><td style="padding:4px 0;color:#94a3b8;width:40%;">${label}</td><td style="padding:4px 0;font-weight:600;color:#e2e8f0;">${val}</td></tr>`
                        ).join('');

                        const conf = d.data.Confidence || 'medium';
                        const confColors = {high: '#4ade80', medium: '#fbbf24', low: '#f87171'};
                        document.getElementById('corAiConfidence').innerHTML =
                            `AI Confidence: <span style="color:${confColors[conf]||'#94a3b8'};font-weight:700;">${conf.charAt(0).toUpperCase()+conf.slice(1)}</span>`;

                        document.getElementById('corAiResult').style.display = 'block';
                        showCorToast('COR scanned! Review the data then click Apply.', true);
                    } else {
                        document.getElementById('corAiError').textContent = d.message || 'Could not extract data from COR.';
                        document.getElementById('corAiError').style.display = 'block';
                    }
                } catch (err) {
                    document.getElementById('corAiError').textContent = 'Network error. Please try again.';
                    document.getElementById('corAiError').style.display = 'block';
                } finally {
                    scanBtn.disabled = false;
                    scanBtn.style.opacity = '1';
                    scanBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Analyze COR with AI';
                    if (spinner) spinner.style.display = 'none';
                }
            });
        }

        const applyBtn = document.getElementById('corAiApplyBtn');
        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                if (!_corExtracted) return;
                const d = _corExtracted;

                function setField(id, val) {
                    const el = document.getElementById(id);
                    if (el && val) el.value = val;
                }
                function setSelect(id, val) {
                    const el = document.getElementById(id);
                    if (!el || !val) return;
                    for (let i = 0; i < el.options.length; i++) {
                        if (el.options[i].value === val || el.options[i].text.toLowerCase().includes(val.toLowerCase())) {
                            el.selectedIndex = i; break;
                        }
                    }
                }

                setField('f_student_id', d.StudentId);
                setField('f_first_name',  d.FirstName);
                setField('f_middle_name', d.MiddleName);
                setField('f_last_name',   d.LastName);
                setSelect('f_course',     d.Course);
                setSelect('f_year_level', d.YearLevel);
                if (d.Section) setSelect('f_section', d.Section);

                document.getElementById('corAiPanel').style.borderColor = '#4ade80';
                document.getElementById('corAiPanel').style.background  = 'linear-gradient(135deg,#052e16,#14532d)';
                showCorToast('Registration form auto-filled! Please verify the details in Step 1.', true);

                const s1 = document.getElementById('sitem1');
                if (s1) { s1.style.boxShadow = '0 0 0 4px #4ade8040'; setTimeout(() => s1.style.boxShadow = '', 2000); }
            });
        }
    })();