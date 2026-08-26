
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
            { pct: '100%', color: '#38bdf8', text: 'Very strong' },
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
            if (!inp) return;
            const isHidden = inp.type === 'password';
            inp.type = isHidden ? 'text' : 'password';
            const icon = btn.querySelector('ion-icon');
            if (icon) {
                icon.setAttribute('name', isHidden ? 'eye-off-outline' : 'eye-outline');
            } else {
                btn.textContent = isHidden ? '🙈' : '👁';
            }
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
                startDetection();
            }, { once: true });

            btn.style.display = 'none';
            $('faceStatusText').innerHTML = 'Position your face in the center of the frame…';

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
        const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 });
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
                    const color = score > 0.55 ? '#38bdf8' : '#f97316';
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

                if (detections.length === 1 && detections[0].detection.score > 0.55) {
                    stableFrames++;
                    if (stableFrames >= 2) {
                        isCapturing = true;
                        statusText.innerHTML = 'Processing face...';
                        statusText.className = 'status-success';
                        captureFace();
                    } else {
                        statusText.innerHTML = `Face detected! Hold still... (${stableFrames}/2)`;
                        statusText.className = 'status-success';
                    }
                } else if (detections.length > 1) {
                    stableFrames = 0;
                    statusText.textContent = 'Multiple faces detected. Please be alone in frame.';
                    statusText.className   = 'status-warning';
                } else {
                    stableFrames = 0;
                    statusText.textContent = 'Position your face in the center of the frame…';
                    statusText.className   = '';
                }
            } catch (_) {  }
        }, 120);
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

            const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 });
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
    const valModalOverlay        = $('valModalOverlay');
    const valModalCloseBtn       = $('valModalCloseBtn');
    const valModalSubmitReviewBtn= $('valModalSubmitReviewBtn');
    let onOrgReviewSubmitHandler = null;

    function showValidationModal(title, message, hint, allowOrgReview = false, onReviewCallback = null) {
        if (!valModalOverlay) return;
        if (title) $('valModalTitle').textContent = title;
        if (message) $('valModalMsg').textContent = message;
        if (hint !== undefined && $('valModalHint')) {
            $('valModalHint').textContent = hint;
            $('valModalHint').style.display = hint ? 'block' : 'none';
        }
        if (valModalSubmitReviewBtn) {
            valModalSubmitReviewBtn.style.display = allowOrgReview ? 'block' : 'none';
            onOrgReviewSubmitHandler = onReviewCallback;
        }
        valModalOverlay.classList.add('active');
    }

    function hideValidationModal() {
        if (valModalOverlay) valModalOverlay.classList.remove('active');
        onOrgReviewSubmitHandler = null;
    }

    if (valModalSubmitReviewBtn) {
        valModalSubmitReviewBtn.addEventListener('click', () => {
            const cb = onOrgReviewSubmitHandler;
            hideValidationModal();
            if (typeof cb === 'function') cb();
        });
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
                    showValidationModal('Invalid File Format', 'Please upload your Certificate of Registration (COR) in PDF format only.', 'Accepted file type: PDF (.pdf)', false);
                    corInput.value = '';
                    return;
                } else {
                    setError('e_cor', '');
                }
            }
        });
    }

    async function doSubmitRegistration(needsOrgReview = false, reviewReason = '', score = 100) {
        const btn = $('submitBtn');
        btn.disabled  = true;
        btn.innerHTML = '<span class="btn-spinner"></span> Submitting Registration…';

        const phone = iti ? iti.getNumber() : $('f_phone').value.trim();
        const photo = $('f_profile_photo').files[0];
        const cor   = $('f_cor').files[0];

        const fd = new FormData();
        fd.append('student_id',             $('f_student_id').value.trim());
        fd.append('first_name',             $('f_first_name').value.trim());
        fd.append('middle_name',            $('f_middle_name').value.trim());
        fd.append('last_name',              $('f_last_name').value.trim());
        fd.append('address',                $('f_address').value.trim());
        fd.append('email',                  $('f_email').value.trim());
        fd.append('course',                 $('f_course').value);
        fd.append('year_level',             $('f_year_level').value);
        fd.append('section',                $('f_section').value || '');
        fd.append('username',               $('f_username').value.trim());
        fd.append('password',               $('f_password').value);
        fd.append('phone',                  phone);
        fd.append('profile_photo',          photo);
        fd.append('cor_document',           cor);
        fd.append('face_descriptor',        JSON.stringify(faceDescriptor));
        fd.append('face_photo',             facePhotoDataURL);
        fd.append('needs_org_review',       needsOrgReview ? '1' : '0');
        fd.append('verification_status',    needsOrgReview ? 'needs_org_review' : 'ai_verified');
        fd.append('ai_verification_score',  score);
        if (reviewReason) {
            fd.append('ai_verification_details', reviewReason);
        }

        try {
            const res  = await fetch('../../config/API/endpoints/index.php?action=student_register', { method: 'POST', body: fd });
            let data;
            const text = await res.text();
            try {
                data = JSON.parse(text);
            } catch (jsonErr) {
                console.error('Registration server response is not JSON:', text);
                showToast('Server returned an unexpected response. Please check server logs.', 'error');
                btn.disabled    = false;
                btn.textContent = 'Submit Registration';
                return;
            }

            if (data.success) {
                stopWebcam();
                
                const panel5 = document.getElementById('panel5');
                const svgIcon = panel5.querySelector('svg');
                const statusTitle = panel5.querySelector('h2');
                const statusMessage = panel5.querySelector('p');
                
                if (data.status === 'active' || data.verification_status === 'ai_verified') {
                    statusTitle.textContent = "Registration Successful!";
                    statusMessage.innerHTML = "Your account has been verified and is now <strong>Active</strong>.<br>You can now log in securely.";
                    svgIcon.style.stroke = "#10b981"; // success green
                } else {
                    statusTitle.textContent = "Registration Submitted for Review!";
                    statusMessage.innerHTML = "Your registration has been submitted and is currently <strong>Pending Review</strong> by your Student Organization officers.<br>You will be notified once verified.";
                    svgIcon.style.stroke = "#f59e0b"; // warning amber
                }
                
                goToStep(5);
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Registration failed.', 'error');
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
            console.error('Registration submit error:', err);
            showToast('Network error. Please check your connection and try again.', 'error');
            btn.disabled    = false;
            btn.textContent = 'Submit Registration';
        }
    }

    $('submitBtn').addEventListener('click', async () => {
        let ok = true;

        const photo = $('f_profile_photo').files[0];
        if (!photo) {
            setError('e_profile_photo', 'Profile photo is required.'); ok = false;
        } else if (photo.size > 5 * 1024 * 1024) {
            setError('e_profile_photo', 'Profile photo must be smaller than 5 MB.'); ok = false;
        } else { setError('e_profile_photo', ''); }

        const cor = $('f_cor').files[0];
        if (!cor) {
            setError('e_cor', 'Certificate of Registration (COR) is required.'); ok = false;
        } else if (cor.type !== 'application/pdf' && !cor.name.toLowerCase().endsWith('.pdf')) {
            setError('e_cor', 'Only PDF files are allowed.'); ok = false;
        } else if (cor.size > 10 * 1024 * 1024) {
            setError('e_cor', 'COR file must be smaller than 10 MB.'); ok = false;
        } else { setError('e_cor', ''); }

        const phone = iti ? iti.getNumber() : $('f_phone').value.trim();
        if (!phone) {
            setError('e_phone', 'Phone number is required.'); ok = false;
        } else if (iti && !iti.isValidNumber()) {
            setError('e_phone', 'Please enter a valid phone number.'); ok = false;
        } else { setError('e_phone', ''); }

        if (!$('f_consent').checked) {
            setError('e_consent', 'You must agree to the Terms of Service & Privacy Policy.'); ok = false;
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
            let valData;
            const valText = await valRes.text();
            try {
                valData = JSON.parse(valText);
            } catch (jsonErr) {
                console.warn('COR validation returned non-JSON:', valText);
                valData = { success: true, is_valid: true, needs_review: false };
            }

            if (valData.is_valid === false || valData.needs_review === true) {
                const errorMsg = valData.message || valData.error || 'The details detected on your COR document do not fully match your inputted registration information.';
                btn.disabled = false;
                btn.innerHTML = 'Submit Registration';
                showValidationModal(
                    'Document Mismatch Detected',
                    errorMsg,
                    'You can review and fix your details, or proceed to submit your registration for manual verification by your Student Organization officers.',
                    true,
                    () => {
                        doSubmitRegistration(true, errorMsg, valData.score || 35);
                    }
                );
                return;
            }
        } catch (e) {
            console.error('COR validation error:', e);
        }

        // All checks passed or AI validated successfully
        await doSubmitRegistration(false, '', 100);
    });

    window.addEventListener('beforeunload', stopWebcam);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && stream) stopWebcam();
    });

})();