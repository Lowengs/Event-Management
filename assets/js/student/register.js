(() => {
    'use strict';

    const $ = id => document.getElementById(id);
    let toastTimer;

    function showToast(msg, type = 'info') {
        const el = $('toast');
        if (!el) return;
        el.className = `toast-${type} show`;
        const iconEl = $('toastIcon');
        if (iconEl) iconEl.textContent = '';
        const msgEl = $('toastMsg');
        if (msgEl) msgEl.textContent = msg;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 5500);
    }

    function setInputValid(inputId, errorId) {
        const inp = $(inputId);
        if (inp) {
            inp.classList.remove('input-error');
            inp.classList.add('input-success');
        }
        if (errorId) {
            const err = $(errorId);
            if (err) {
                err.textContent = '';
                err.style.display = 'none';
            }
        }
    }

    function setInputError(inputId, errorId, msg) {
        const inp = $(inputId);
        const err = errorId ? $(errorId) : null;
        if (msg) {
            if (inp) {
                inp.classList.add('input-error');
                inp.classList.remove('input-success');
            }
            if (err) {
                err.textContent = msg;
                err.style.display = 'block';
            }
        } else {
            if (inp) {
                inp.classList.remove('input-error');
                inp.classList.add('input-success');
            }
            if (err) {
                err.textContent = '';
                err.style.display = 'none';
            }
        }
    }

    function setError(id, msg) {
        const err = $(id);
        if (err) {
            err.textContent = msg;
            err.style.display = msg ? 'block' : 'none';
        }
    }

    // ── LIVE FIELD VALIDATION (GREEN WHEN FILLED / VALID) ─────────────
    function checkAllFieldStates(showErrors = false) {
        // Student ID
        const sid = $('f_student_id');
        if (sid) {
            const v = sid.value.trim();
            if (v) setInputValid('f_student_id', 'e_student_id');
            else if (showErrors) setInputError('f_student_id', 'e_student_id', 'Student ID is required.');
            else sid.classList.remove('input-error', 'input-success');
        }

        // First Name
        const fn = $('f_first_name');
        if (fn) {
            const v = fn.value.trim();
            if (v) setInputValid('f_first_name', 'e_first_name');
            else if (showErrors) setInputError('f_first_name', 'e_first_name', 'First name is required.');
            else fn.classList.remove('input-error', 'input-success');
        }

        // Middle Name (optional)
        const mn = $('f_middle_name');
        if (mn) {
            if (mn.value.trim()) {
                mn.classList.add('input-success');
                mn.classList.remove('input-error');
            } else {
                mn.classList.remove('input-success', 'input-error');
            }
        }

        // Last Name
        const ln = $('f_last_name');
        if (ln) {
            const v = ln.value.trim();
            if (v) setInputValid('f_last_name', 'e_last_name');
            else if (showErrors) setInputError('f_last_name', 'e_last_name', 'Last name is required.');
            else ln.classList.remove('input-error', 'input-success');
        }

        // Email
        const em = $('f_email');
        if (em) {
            const v = em.value.trim();
            if (v && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) setInputValid('f_email', 'e_email');
            else if (v && showErrors) setInputError('f_email', 'e_email', 'Please enter a valid email address.');
            else if (showErrors) setInputError('f_email', 'e_email', 'Email address is required.');
            else em.classList.remove('input-error', 'input-success');
        }

        // Address
        const addr = $('f_address');
        if (addr) {
            const v = addr.value.trim();
            if (v) setInputValid('f_address', 'e_address');
            else if (showErrors) setInputError('f_address', 'e_address', 'Home address is required.');
            else addr.classList.remove('input-error', 'input-success');
        }

        // Course
        const course = $('f_course');
        if (course) {
            if (course.value) setInputValid('f_course', 'e_course');
            else if (showErrors) setInputError('f_course', 'e_course', 'Please select a course.');
            else course.classList.remove('input-error', 'input-success');
        }

        // Year Level
        const yr = $('f_year_level');
        if (yr) {
            if (yr.value) setInputValid('f_year_level', 'e_year_level');
            else if (showErrors) setInputError('f_year_level', 'e_year_level', 'Please select a year level.');
            else yr.classList.remove('input-error', 'input-success');
        }

        // Section (optional)
        const sec = $('f_section');
        if (sec) {
            if (sec.value.trim()) {
                sec.classList.add('input-success');
                sec.classList.remove('input-error');
            } else {
                sec.classList.remove('input-success', 'input-error');
            }
        }

        // Username
        const uname = $('f_username');
        if (uname) {
            const v = uname.value.trim();
            if (v.length >= 4) setInputValid('f_username', 'e_username');
            else if (v && showErrors) setInputError('f_username', 'e_username', 'Username must be at least 4 characters.');
            else if (showErrors) setInputError('f_username', 'e_username', 'Username is required.');
            else uname.classList.remove('input-error', 'input-success');
        }

        // Password & Confirm Password
        const pw = $('f_password');
        const cpw = $('f_confirm_password');
        if (pw) {
            const v = pw.value;
            if (v.length >= 8) setInputValid('f_password', 'e_password');
            else if (v && showErrors) setInputError('f_password', 'e_password', 'Password must be at least 8 characters.');
            else if (showErrors) setInputError('f_password', 'e_password', 'Password is required.');
            else pw.classList.remove('input-error', 'input-success');
        }

        if (cpw && pw) {
            const v = cpw.value;
            if (v && v === pw.value && v.length >= 8) setInputValid('f_confirm_password', 'e_confirm_password');
            else if (v && v !== pw.value && showErrors) setInputError('f_confirm_password', 'e_confirm_password', 'Passwords do not match.');
            else if (showErrors) setInputError('f_confirm_password', 'e_confirm_password', 'Please confirm your password.');
            else cpw.classList.remove('input-error', 'input-success');
        }

        // Phone
        const phone = $('f_phone');
        if (phone) {
            const raw = phone.value.replace(/\D/g, '').replace(/^(?:63|0)/, '');
            if (raw.length === 10 && raw.startsWith('9')) setInputValid('f_phone', 'e_phone');
            else if (showErrors) setInputError('f_phone', 'e_phone', 'Please enter a valid 10-digit mobile number starting with 9.');
            else phone.classList.remove('input-error', 'input-success');
        }
    }

    function initLiveValidation() {
        const events = ['input', 'change', 'blur', 'keyup'];

        const bindField = (elId, errId, validator) => {
            const el = $(elId);
            if (!el) return;
            events.forEach(evt => {
                el.addEventListener(evt, () => {
                    validator(el, $(errId));
                });
            });
        };

        bindField('f_student_id', 'e_student_id', (el) => {
            if (el.value.trim()) setInputValid('f_student_id', 'e_student_id');
            else setInputError('f_student_id', 'e_student_id', 'Student ID is required.');
        });

        bindField('f_first_name', 'e_first_name', (el) => {
            if (el.value.trim()) setInputValid('f_first_name', 'e_first_name');
            else setInputError('f_first_name', 'e_first_name', 'First name is required.');
        });

        const mn = $('f_middle_name');
        if (mn) {
            events.forEach(evt => {
                mn.addEventListener(evt, () => {
                    if (mn.value.trim()) {
                        mn.classList.add('input-success');
                        mn.classList.remove('input-error');
                    } else {
                        mn.classList.remove('input-success', 'input-error');
                    }
                });
            });
        }

        bindField('f_last_name', 'e_last_name', (el) => {
            if (el.value.trim()) setInputValid('f_last_name', 'e_last_name');
            else setInputError('f_last_name', 'e_last_name', 'Last name is required.');
        });

        bindField('f_email', 'e_email', (el) => {
            const v = el.value.trim();
            if (!v) setInputError('f_email', 'e_email', 'Email address is required.');
            else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) setInputError('f_email', 'e_email', 'Please enter a valid email address.');
            else setInputValid('f_email', 'e_email');
        });

        bindField('f_address', 'e_address', (el) => {
            if (el.value.trim()) setInputValid('f_address', 'e_address');
            else setInputError('f_address', 'e_address', 'Home address is required.');
        });

        const course = $('f_course');
        if (course) {
            ['change', 'input'].forEach(evt => {
                course.addEventListener(evt, () => {
                    if (course.value) setInputValid('f_course', 'e_course');
                    else setInputError('f_course', 'e_course', 'Please select a course.');
                });
            });
        }

        const yr = $('f_year_level');
        if (yr) {
            ['change', 'input'].forEach(evt => {
                yr.addEventListener(evt, () => {
                    if (yr.value) setInputValid('f_year_level', 'e_year_level');
                    else setInputError('f_year_level', 'e_year_level', 'Please select a year level.');
                });
            });
        }

        const sec = $('f_section');
        if (sec) {
            events.forEach(evt => {
                sec.addEventListener(evt, () => {
                    if (sec.value.trim()) {
                        sec.classList.add('input-success');
                        sec.classList.remove('input-error');
                    } else {
                        sec.classList.remove('input-success', 'input-error');
                    }
                });
            });
        }

        bindField('f_username', 'e_username', (el) => {
            const v = el.value.trim();
            if (v.length >= 4) setInputValid('f_username', 'e_username');
            else if (v) setInputError('f_username', 'e_username', 'Username must be at least 4 characters.');
            else setInputError('f_username', 'e_username', 'Username is required.');
        });

        const pw = $('f_password');
        const cpw = $('f_confirm_password');
        if (pw) {
            events.forEach(evt => {
                pw.addEventListener(evt, () => {
                    if (pw.value.length >= 8) setInputValid('f_password', 'e_password');
                    else if (pw.value) setInputError('f_password', 'e_password', 'Password must be at least 8 characters.');
                    else setInputError('f_password', 'e_password', 'Password is required.');

                    if (cpw && cpw.value) {
                        if (cpw.value === pw.value && pw.value.length >= 8) setInputValid('f_confirm_password', 'e_confirm_password');
                        else setInputError('f_confirm_password', 'e_confirm_password', 'Passwords do not match.');
                    }
                });
            });
        }

        if (cpw) {
            events.forEach(evt => {
                cpw.addEventListener(evt, () => {
                    if (!cpw.value) setInputError('f_confirm_password', 'e_confirm_password', 'Please confirm your password.');
                    else if (pw && cpw.value === pw.value && pw.value.length >= 8) setInputValid('f_confirm_password', 'e_confirm_password');
                    else setInputError('f_confirm_password', 'e_confirm_password', 'Passwords do not match.');
                });
            });
        }

        const phone = $('f_phone');
        if (phone) {
            events.forEach(evt => {
                phone.addEventListener(evt, () => {
                    let digits = phone.value.replace(/\D/g, '');
                    if (digits.startsWith('63')) digits = digits.slice(2);
                    else if (digits.startsWith('0')) digits = digits.slice(1);
                    if (digits.length > 10) digits = digits.slice(0, 10);

                    let formatted = '';
                    if (digits.length > 0) formatted = digits.slice(0, 3);
                    if (digits.length > 3) formatted += ' ' + digits.slice(3, 6);
                    if (digits.length > 6) formatted += ' ' + digits.slice(6, 10);
                    phone.value = formatted;

                    if (digits.length === 10 && digits.startsWith('9')) {
                        setInputValid('f_phone', 'e_phone');
                    } else if (digits.length > 0) {
                        phone.classList.remove('input-success');
                        if (digits.length === 10 && !digits.startsWith('9')) {
                            setInputError('f_phone', 'e_phone', 'Mobile number must start with 9.');
                        } else {
                            setError('e_phone', '');
                            phone.classList.remove('input-error');
                        }
                    } else {
                        phone.classList.remove('input-success', 'input-error');
                        setError('e_phone', '');
                    }
                });
            });
        }

        // Run an immediate check on all fields so prefilled/autofilled fields turn green right away
        checkAllFieldStates(false);
        setTimeout(() => checkAllFieldStates(false), 300);
        setTimeout(() => checkAllFieldStates(false), 800);
    }
    initLiveValidation();

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
                si.classList.toggle('done', i < n);
            }
            if (i < TOTAL_STEPS) {
                const sl = $(`sline${i}`);
                if (sl) sl.classList.toggle('done', i < n);
            }
        }
        if (n === 5 && $('stepWrapper')) $('stepWrapper').style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });

        // Check field states on step change so filled fields have green borders
        checkAllFieldStates(false);

        if (n === 2) {
            evaluateRegisterPasswordStrength($('f_password') ? $('f_password').value : '');
        }

        // Preload models proactively when on Step 2 or 3
        if (n >= 2 && !modelsLoaded) {
            loadModels().catch(() => {});
        }
    }

    // ── STEP 1 NAVIGATION ──────────────────────────────────────────
    $('next1').addEventListener('click', () => {
        let ok = true;
        const sid = $('f_student_id').value.trim();
        if (!sid) {
            setInputError('f_student_id', 'e_student_id', 'Student ID is required.');
            ok = false;
        } else {
            setInputValid('f_student_id', 'e_student_id');
        }

        const fn = $('f_first_name').value.trim();
        if (!fn) {
            setInputError('f_first_name', 'e_first_name', 'First name is required.');
            ok = false;
        } else {
            setInputValid('f_first_name', 'e_first_name');
        }

        const mn = $('f_middle_name').value.trim();
        if (mn) $('f_middle_name').classList.add('input-success');

        const ln = $('f_last_name').value.trim();
        if (!ln) {
            setInputError('f_last_name', 'e_last_name', 'Last name is required.');
            ok = false;
        } else {
            setInputValid('f_last_name', 'e_last_name');
        }

        const em = $('f_email').value.trim();
        if (!em) {
            setInputError('f_email', 'e_email', 'Email address is required.');
            ok = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) {
            setInputError('f_email', 'e_email', 'Please enter a valid email address.');
            ok = false;
        } else {
            setInputValid('f_email', 'e_email');
        }

        const addr = $('f_address').value.trim();
        if (!addr) {
            setInputError('f_address', 'e_address', 'Home address is required.');
            ok = false;
        } else {
            setInputValid('f_address', 'e_address');
        }

        const course = $('f_course').value;
        if (!course) {
            setInputError('f_course', 'e_course', 'Please select a course.');
            ok = false;
        } else {
            setInputValid('f_course', 'e_course');
        }

        const yr = $('f_year_level').value;
        if (!yr) {
            setInputError('f_year_level', 'e_year_level', 'Please select a year level.');
            ok = false;
        } else {
            setInputValid('f_year_level', 'e_year_level');
        }

        const sec = $('f_section').value.trim();
        if (sec) $('f_section').classList.add('input-success');

        if (ok) {
            goToStep(2);
        } else {
            showToast('Please fill in all required fields.', 'error');
        }
    });

    // ── PASSWORD STRENGTH EVALUATION ─────────────────────────────────
    function updateRegCrit(el, isValid) {
        if (!el) return;
        const icon = el.querySelector('ion-icon');
        if (isValid) {
            el.classList.add('valid');
            el.classList.remove('invalid');
            if (icon) icon.setAttribute('name', 'checkmark-circle-outline');
        } else {
            el.classList.remove('valid');
            el.classList.add('invalid');
            if (icon) icon.setAttribute('name', 'close-circle-outline');
        }
    }

    function evaluateRegisterPasswordStrength(pwd) {
        const fill = $('regPwStrengthFill');
        const label = $('regPwStrengthLabel');
        const cLen = $('regCritLength');
        const cUpper = $('regCritUpper');
        const cLower = $('regCritLower');
        const cNum = $('regCritNumber');
        const cSpec = $('regCritSpecial');

        if (!pwd) {
            if (fill) {
                fill.style.width = '0%';
                fill.style.backgroundColor = '#ef4444';
            }
            if (label) {
                label.textContent = 'Too Short';
                label.style.background = 'rgba(239, 68, 68, 0.2)';
                label.style.color = '#fca5a5';
            }
            [cLen, cUpper, cLower, cNum, cSpec].forEach(el => updateRegCrit(el, false));
            return { score: 0, isValid: false };
        }

        const hasLen = pwd.length >= 8;
        const hasUpper = /[A-Z]/.test(pwd);
        const hasLower = /[a-z]/.test(pwd);
        const hasNum = /[0-9]/.test(pwd);
        const hasSpec = /[^A-Za-z0-9]/.test(pwd);

        updateRegCrit(cLen, hasLen);
        updateRegCrit(cUpper, hasUpper);
        updateRegCrit(cLower, hasLower);
        updateRegCrit(cNum, hasNum);
        updateRegCrit(cSpec, hasSpec);

        let score = (hasLen ? 1 : 0) + (hasUpper ? 1 : 0) + (hasLower ? 1 : 0) + (hasNum ? 1 : 0) + (hasSpec ? 1 : 0);
        if (pwd.length >= 12) score++;

        let percent = 0;
        let text = 'Weak';
        let bg = '#ef4444';
        let badgeBg = 'rgba(239, 68, 68, 0.2)';
        let badgeColor = '#fca5a5';

        if (!hasLen) {
            percent = Math.min(25, pwd.length * 3);
            text = 'Too Short';
            bg = '#ef4444';
            badgeBg = 'rgba(239, 68, 68, 0.2)';
            badgeColor = '#fca5a5';
        } else if (score <= 2) {
            percent = 25;
            text = 'Weak';
            bg = '#ef4444';
            badgeBg = 'rgba(239, 68, 68, 0.2)';
            badgeColor = '#fca5a5';
        } else if (score === 3) {
            percent = 50;
            text = 'Moderate';
            bg = '#f59e0b';
            badgeBg = 'rgba(245, 158, 11, 0.2)';
            badgeColor = '#fcd34d';
        } else if (score === 4) {
            percent = 75;
            text = 'Good';
            bg = '#0284c7';
            badgeBg = 'rgba(2, 132, 199, 0.2)';
            badgeColor = '#7dd3fc';
        } else {
            percent = 100;
            text = pwd.length >= 12 ? 'Very Strong' : 'Strong';
            bg = '#16a34a';
            badgeBg = 'rgba(22, 163, 74, 0.2)';
            badgeColor = '#86efac';
        }

        if (fill) {
            fill.style.width = percent + '%';
            fill.style.backgroundColor = bg;
        }
        if (label) {
            label.textContent = text;
            label.style.background = badgeBg;
            label.style.color = badgeColor;
        }

        return { score, isValid: (hasLen && hasUpper && hasLower && hasNum && hasSpec) };
    }

    const regPwInput = $('f_password');
    if (regPwInput) {
        regPwInput.addEventListener('input', () => {
            evaluateRegisterPasswordStrength(regPwInput.value);
        });
        evaluateRegisterPasswordStrength(regPwInput.value || '');
    }

    // Global bulletproof password visibility toggle
    window.togglePasswordVisibility = function(a, b) {
        let inputId, btn;
        if (typeof a === 'string') {
            inputId = a;
            btn = b;
        } else {
            btn = a;
            inputId = b;
        }
        const input = typeof inputId === 'string' ? document.getElementById(inputId) : (btn ? btn.parentElement.querySelector('input') : null);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        if (btn) {
            const openSvg = btn.querySelector('.eye-open');
            const closedSvg = btn.querySelector('.eye-closed');
            if (openSvg && closedSvg) {
                openSvg.style.display = isPassword ? 'none' : 'block';
                closedSvg.style.display = isPassword ? 'block' : 'none';
            }
            const icon = btn.querySelector('ion-icon');
            if (icon) {
                icon.setAttribute('name', isPassword ? 'eye-off-outline' : 'eye-outline');
            }
        }
    };

    // ── STEP 2 NAVIGATION ──────────────────────────────────────────
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
            setInputValid('f_username', 'e_username');
        }

        const pw = $('f_password').value;
        if (!pw) {
            setInputError('f_password', 'e_password', 'Password is required.');
            ok = false;
        } else if (pw.length < 8) {
            setInputError('f_password', 'e_password', 'Password must be at least 8 characters.');
            ok = false;
        } else {
            setInputValid('f_password', 'e_password');
        }

        const cpw = $('f_confirm_password').value;
        if (!cpw) {
            setInputError('f_confirm_password', 'e_confirm_password', 'Please confirm your password.');
            ok = false;
        } else if (pw !== cpw) {
            setInputError('f_confirm_password', 'e_confirm_password', 'Passwords do not match.');
            ok = false;
        } else {
            setInputValid('f_confirm_password', 'e_confirm_password');
        }

        if (ok) {
            goToStep(3);
        } else {
            showToast('Please fill in all required fields.', 'error');
        }
    });

    // ── STEP 3: FACE CAPTURE & AI MODEL LOADING ────────────────────
    let stream = null;
    let detectionLoop = null;
    let faceDescriptor = null;
    let facePhotoDataURL = null;
    let modelsLoaded = false;
    let isLoadingModels = false;

    const video = $('faceVideo');
    const canvas = $('faceCanvas');
    const ctx = canvas.getContext('2d');

    async function ensureFaceApiScript() {
        if (typeof faceapi !== 'undefined' && faceapi.nets) return true;
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.min.js';
            script.onload = () => resolve(true);
            script.onerror = () => {
                const altScript = document.createElement('script');
                altScript.src = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
                altScript.onload = () => resolve(true);
                altScript.onerror = () => resolve(false);
                document.head.appendChild(altScript);
            };
            document.head.appendChild(script);
        });
    }

    async function loadModels() {
        if (modelsLoaded) return true;
        if (isLoadingModels) {
            while (isLoadingModels) {
                await new Promise(r => setTimeout(r, 100));
            }
            return modelsLoaded;
        }

        isLoadingModels = true;
        if ($('modelLoading')) $('modelLoading').style.display = 'flex';

        await ensureFaceApiScript();

        if (typeof faceapi === 'undefined' || !faceapi.nets) {
            isLoadingModels = false;
            if ($('modelLoading')) $('modelLoading').style.display = 'none';
            console.error('FaceAPI library script is unavailable');
            showToast('Face detection engine failed to load. Please check your internet connection.', 'error');
            return false;
        }

        const candidatePaths = [
            '../../assets/models',
            '../assets/models',
            '/Project/assets/models',
            'assets/models',
            'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/',
            'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights/'
        ];

        let success = false;
        for (const p of candidatePaths) {
            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(p),
                    faceapi.nets.faceLandmark68Net.loadFromUri(p),
                    faceapi.nets.faceRecognitionNet.loadFromUri(p)
                ]);
                modelsLoaded = true;
                success = true;
                console.log('Face models successfully loaded from path:', p);
                break;
            } catch (err) {
                console.warn(`Candidate model path failed (${p}):`, err.message || err);
            }
        }

        isLoadingModels = false;
        if ($('modelLoading')) $('modelLoading').style.display = 'none';

        if (!success) {
            console.error('All model candidate paths failed.');
            showToast('Could not load face detection models. Please check your connection and retry.', 'error');
            return false;
        }
        return true;
    }

    // Proactively start pre-loading models in background on document load
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            loadModels().catch(() => {});
        }, 800);
    });

    $('openCamBtn').addEventListener('click', async () => {
        const btn = $('openCamBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="btn-spinner"></span> Starting camera…';

        const ok = await loadModels();
        if (!ok) {
            btn.disabled = false;
            btn.textContent = 'Open Camera';
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
            });
            video.srcObject = stream;
            video.style.display = 'block';
            $('camPlaceholder').style.display = 'none';
            video.addEventListener('loadedmetadata', () => {
                canvas.width = video.videoWidth;
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
                    const box = d.detection.box;
                    const score = d.detection.score;
                    const color = score > 0.55 ? '#38bdf8' : '#f97316';
                    ctx.strokeStyle = color;
                    ctx.lineWidth = 2.5;
                    ctx.beginPath();
                    if (ctx.roundRect) {
                        ctx.roundRect(box.x, box.y, box.width, box.height, 8);
                    } else {
                        ctx.rect(box.x, box.y, box.width, box.height);
                    }
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
                    statusText.className = 'status-warning';
                } else {
                    stableFrames = 0;
                    statusText.textContent = 'Position your face in the center of the frame…';
                    statusText.className = '';
                }
            } catch (_) {}
        }, 120);
    }

    function stopWebcam() {
        clearInterval(detectionLoop);
        detectionLoop = null;
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
        }
        if (video) video.srcObject = null;
    }

    async function captureFace() {
        try {
            const snap = document.createElement('canvas');
            snap.width = video.videoWidth;
            snap.height = video.videoHeight;
            snap.getContext('2d').drawImage(video, 0, 0);

            const opts = new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.4 });
            const detection = await faceapi
                .detectSingleFace(snap, opts)
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                showToast('No face detected in final frame. Retrying...', 'warning');
                return;
            }

            faceDescriptor = Array.from(detection.descriptor);
            facePhotoDataURL = snap.toDataURL('image/jpeg', 0.88);
            $('capturedImg').src = facePhotoDataURL;
            $('cameraArea').style.display = 'none';
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
        faceDescriptor = null;
        facePhotoDataURL = null;
        $('capturePreview').style.display = 'none';
        $('cameraArea').style.display = 'block';
        $('next3').disabled = true;
        $('openCamBtn').style.display = 'inline-flex';
        $('openCamBtn').disabled = false;
        $('openCamBtn').textContent = 'Open Camera';
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        video.style.display = 'none';
        canvas.style.display = 'none';
        if ($('camGuideRing')) $('camGuideRing').classList.remove('visible', 'detected');
        $('camPlaceholder').style.display = 'flex';
        $('faceStatusText').innerHTML = 'Click <strong>Open Camera</strong> to begin face registration';
        $('faceStatusText').className = '';
    }

    $('retakeBtn').addEventListener('click', retakeFace);
    $('retakeBtn2').addEventListener('click', retakeFace);
    $('back3').addEventListener('click', () => {
        stopWebcam();
        goToStep(2);
    });

    $('next3').addEventListener('click', () => {
        if (!faceDescriptor) {
            showToast('Please capture your face before continuing.', 'warning');
            return;
        }
        goToStep(4);
    });

    // ── STEP 4: FILE UPLOADS & COR VALIDATION ───────────────────────
    function setupFileZone(zoneId, inputId, innerId) {
        const zone = $(zoneId);
        const input = $(inputId);
        const inner = $(innerId);
        if (!zone || !input || !inner) return;

        zone.addEventListener('click', () => input.click());
        zone.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                input.click();
            }
        });
        zone.addEventListener('dragover', e => {
            e.preventDefault();
            zone.classList.add('drag-over');
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateZoneLabel(inner, e.dataTransfer.files[0]);
                if (inputId === 'f_cor') {
                    scanCorFile(e.dataTransfer.files[0]);
                }
            }
        });
        input.addEventListener('change', () => {
            if (input.files.length) {
                updateZoneLabel(inner, input.files[0]);
                if (inputId === 'f_cor') {
                    scanCorFile(input.files[0]);
                }
            }
        });
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
    setupFileZone('corZone', 'f_cor', 'corInner');
    $('back4').addEventListener('click', () => goToStep(3));

    // ── PRIVACY MODAL ──────────────────────────────────────────────
    const privModal = $('privModal');
    $('openPrivacyBtn').addEventListener('click', e => {
        e.preventDefault();
        privModal.classList.add('open');
    });
    $('closePrivBtn').addEventListener('click', () => privModal.classList.remove('open'));
    $('agreePrivBtn').addEventListener('click', () => {
        privModal.classList.remove('open');
        $('f_consent').checked = true;
        setError('e_consent', '');
    });
    privModal.addEventListener('click', e => {
        if (e.target === privModal) privModal.classList.remove('open');
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && privModal.classList.contains('open')) privModal.classList.remove('open');
    });

    // ── VALIDATION MODAL ───────────────────────────────────────────
    const valModalOverlay = $('valModalOverlay');
    const valModalCloseBtn = $('valModalCloseBtn');
    const valModalSubmitReviewBtn = $('valModalSubmitReviewBtn');
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

    if (valModalCloseBtn) {
        valModalCloseBtn.addEventListener('click', () => {
            hideValidationModal();
            goToStep(1);
            checkAllFieldStates(false);
        });
    }
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

    let lastCorScanResult = null;
    let isCorScanning = false;

    async function scanCorFile(file) {
        if (!file) return;
        if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
            setError('e_cor', 'Only PDF files are allowed.');
            showValidationModal('Invalid File Format', 'Please upload your Certificate of Registration (COR) in PDF format only.', 'Accepted file type: PDF (.pdf)', false);
            const corInput = $('f_cor');
            if (corInput) corInput.value = '';
            const statusEl = $('corScanStatus');
            if (statusEl) {
                statusEl.style.display = 'none';
                statusEl.innerHTML = '';
            }
            lastCorScanResult = null;
            return;
        }

        setError('e_cor', '');
        const statusEl = $('corScanStatus');
        if (statusEl) {
            statusEl.style.display = 'block';
            statusEl.innerHTML = `
                <div style="background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);color:#93c5fd;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;font-size:0.88rem;">
                    <span class="btn-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;flex-shrink:0;"></span>
                    <span>Scanning and analyzing Certificate of Registration (COR) with AI...</span>
                </div>`;
        }

        isCorScanning = true;
        const valFd = new FormData();
        valFd.append('cor', file);
        valFd.append('first_name', $('f_first_name') ? $('f_first_name').value.trim() : '');
        valFd.append('last_name', $('f_last_name') ? $('f_last_name').value.trim() : '');
        valFd.append('middle_name', $('f_middle_name') ? $('f_middle_name').value.trim() : '');
        valFd.append('student_id', $('f_student_id') ? $('f_student_id').value.trim() : '');
        valFd.append('course', $('f_course') ? $('f_course').value : '');
        valFd.append('year_level', $('f_year_level') ? $('f_year_level').value : '');
        valFd.append('section', $('f_section') ? $('f_section').value : '');

        try {
            const valRes = await fetch('../../config/API/endpoints/index.php?action=validate_cor', { method: 'POST', body: valFd });
            const valText = await valRes.text();
            let valData;
            try {
                valData = JSON.parse(valText);
            } catch (err) {
                valData = { success: false, is_valid: false, needs_review: true, score: 35, message: 'Document could not be verified automatically.' };
            }

            lastCorScanResult = valData;
            isCorScanning = false;

            if (statusEl) {
                if (valData.is_valid === true) {
                    const matchMsg = Array.isArray(valData.details) && valData.details.length 
                        ? valData.details.join(' • ') 
                        : 'Student ID and Student Name verified in document.';
                    statusEl.innerHTML = `
                        <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.35);color:#6ee7b7;border-radius:10px;padding:12px 16px;display:flex;align-items:flex-start;gap:12px;font-size:0.88rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            <div>
                                <strong style="color:#a7f3d0;display:block;margin-bottom:2px;font-size:0.92rem;">✓ COR Verified by AI (Score: ${valData.score || 100}%)</strong>
                                <span style="font-size:0.83rem;color:#cbd5e1;line-height:1.4;">${matchMsg}</span>
                            </div>
                        </div>`;
                } else {
                    statusEl.innerHTML = `
                        <div style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.35);color:#fde68a;border-radius:10px;padding:12px 16px;display:flex;align-items:flex-start;gap:12px;font-size:0.88rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <div>
                                <strong style="color:#fde047;display:block;margin-bottom:2px;font-size:0.92rem;">Organization Review Scheduled</strong>
                                <span style="font-size:0.83rem;color:#cbd5e1;line-height:1.4;">Your enrollment document will be reviewed and verified by organization officers.</span>
                            </div>
                        </div>`;
                }
            }
        } catch (e) {
            console.error('Scan error:', e);
            isCorScanning = false;
            lastCorScanResult = { is_valid: false, needs_review: true, score: 35 };
            if (statusEl) {
                statusEl.innerHTML = `
                    <div style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.35);color:#fde68a;border-radius:10px;padding:12px 16px;font-size:0.88rem;">
                        Document queued for organization manual review.
                    </div>`;
            }
        }
    }

    // ── SUBMIT REGISTRATION ─────────────────────────────────────────
    async function doSubmitRegistration(needsOrgReview = false, reviewReason = '', score = 100) {
        const btn = $('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="btn-spinner"></span> Submitting Registration…';

        const rawDigits = $('f_phone').value.replace(/\D/g, '').replace(/^(?:63|0)/, '');
        const phone = rawDigits ? ('0' + rawDigits) : ''; // Always local format: 09XXXXXXXXX
        const photo = $('f_profile_photo').files[0];
        const cor = $('f_cor').files[0];

        const fd = new FormData();
        fd.append('student_id', $('f_student_id').value.trim());
        fd.append('first_name', $('f_first_name').value.trim());
        fd.append('middle_name', $('f_middle_name').value.trim());
        fd.append('last_name', $('f_last_name').value.trim());
        fd.append('address', $('f_address').value.trim());
        fd.append('email', $('f_email').value.trim());
        fd.append('course', $('f_course').value);
        fd.append('year_level', $('f_year_level').value);
        fd.append('section', $('f_section').value || '');
        fd.append('username', $('f_username').value.trim());
        fd.append('password', $('f_password').value);
        fd.append('phone', phone);
        fd.append('profile_photo', photo);
        fd.append('cor_document', cor);
        fd.append('face_descriptor', JSON.stringify(faceDescriptor));
        fd.append('face_photo', facePhotoDataURL);
        fd.append('needs_org_review', needsOrgReview ? '1' : '0');
        fd.append('verification_status', needsOrgReview ? 'needs_org_review' : 'ai_verified');
        fd.append('ai_verification_score', score);
        if (reviewReason) {
            fd.append('ai_verification_details', reviewReason);
        }

        try {
            const res = await fetch('../../config/API/endpoints/index.php?action=student_register', { method: 'POST', body: fd });
            let data;
            const text = await res.text();
            try {
                data = JSON.parse(text);
            } catch (jsonErr) {
                console.error('Registration server response is not JSON:', text);
                const snippet = text ? text.replace(/<[^>]+>/g, ' ').trim().slice(0, 120) : '';
                showToast(snippet ? `Server: ${snippet}` : 'Server returned an unexpected response. Please check server logs.', 'error');
                btn.disabled = false;
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
                    statusTitle.textContent = 'Registration Successful!';
                    statusMessage.innerHTML = 'Your Certificate of Registration has been verified and your account is now <strong>Active</strong>.<br>A confirmation email has been sent to your registered email address. You can now log in securely.';
                    svgIcon.style.stroke = '#10b981';
                } else {
                    statusTitle.textContent = 'Account Pending Verification';
                    statusMessage.innerHTML = 'Your account is pending verification. You cannot access the system until your registration and enrollment document are verified and approved.';
                    svgIcon.style.stroke = '#f59e0b';
                }

                goToStep(5);
                showToast(data.message, 'success');
            } else {
                showToast(data.message || 'Registration failed.', 'error');
                btn.disabled = false;
                btn.textContent = 'Submit Registration';
                if (data.field === 'email' || data.field === 'student_id') {
                    goToStep(1);
                    if (data.field === 'email') setInputError('f_email', 'e_email', data.message);
                    if (data.field === 'student_id') setInputError('f_student_id', 'e_student_id', data.message);
                } else if (data.field === 'username') {
                    goToStep(2);
                    setInputError('f_username', 'e_username', data.message);
                }
            }
        } catch (err) {
            console.error('Registration submit error:', err);
            showToast('Network error. Please check your connection and try again.', 'error');
            btn.disabled = false;
            btn.textContent = 'Submit Registration';
        }
    }

    $('submitBtn').addEventListener('click', async () => {
        let ok = true;
        const photo = $('f_profile_photo').files[0];
        if (!photo) {
            setError('e_profile_photo', 'Profile photo is required.');
            ok = false;
        } else if (photo.size > 5 * 1024 * 1024) {
            setError('e_profile_photo', 'Profile photo must be smaller than 5 MB.');
            ok = false;
        } else {
            setError('e_profile_photo', '');
        }

        const cor = $('f_cor').files[0];
        if (!cor) {
            setError('e_cor', 'Certificate of Registration (COR) is required.');
            ok = false;
        } else if (cor.type !== 'application/pdf' && !cor.name.toLowerCase().endsWith('.pdf')) {
            setError('e_cor', 'Only PDF files are allowed.');
            ok = false;
        } else if (cor.size > 10 * 1024 * 1024) {
            setError('e_cor', 'COR file must be smaller than 10 MB.');
            ok = false;
        } else {
            setError('e_cor', '');
        }

        const rawPhone = $('f_phone').value.replace(/\D/g, '').replace(/^(?:63|0)/, '');
        if (!rawPhone) {
            setInputError('f_phone', 'e_phone', 'Phone number is required.');
            ok = false;
        } else if (rawPhone.length !== 10 || !rawPhone.startsWith('9')) {
            setInputError('f_phone', 'e_phone', 'Please enter a valid 10-digit mobile number starting with 9.');
            ok = false;
        } else {
            setInputValid('f_phone', 'e_phone');
        }

        if (!$('f_consent').checked) {
            setError('e_consent', 'You must agree to the Terms of Service & Privacy Policy.');
            ok = false;
        } else {
            setError('e_consent', '');
        }

        if (!ok) {
            showToast('Please fill in all required fields.', 'error');
            return;
        }

        if (!faceDescriptor || !facePhotoDataURL) {
            showToast('Face registration data is missing. Please go back to Step 3.', 'error');
            return;
        }

        const btn = $('submitBtn');
        if (isCorScanning) {
            btn.disabled = true;
            btn.innerHTML = '<span class="btn-spinner"></span> Scanning COR with AI...';
            while (isCorScanning) {
                await new Promise(r => setTimeout(r, 100));
            }
        }

        let valData = lastCorScanResult;
        if (!valData) {
            btn.disabled = true;
            btn.innerHTML = '<span class="btn-spinner"></span> Validating COR with AI...';
            await scanCorFile(cor);
            valData = lastCorScanResult;
        }

        if (!valData || valData.is_valid === false || valData.needs_review === true) {
            await doSubmitRegistration(true, 'Pending Organization Manual Review', valData ? (valData.score || 35) : 35);
            return;
        }

        await doSubmitRegistration(false, '', 100);
    });

    window.addEventListener('beforeunload', stopWebcam);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && stream) stopWebcam();
    });
})();