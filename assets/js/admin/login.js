
(() => {
    'use strict';

    const $ = id => document.getElementById(id);
    let toastTimer;

    function showToast(msg, type = 'info') {
        const icons = { success: '', error: '', info: '' };
        const el  = $('adminToast');
        el.className = `${type} show`;
        $('adminToastIcon').textContent = icons[type] ?? 'ℹ️';
        $('adminToastMsg').textContent  = msg;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.remove('show'), 4500);
    }

    function fieldErr(inputId, errId, msg) {
        const inp = $(inputId);
        const err = $(errId);
        if (!inp || !err) return;
        inp.classList.toggle('input-err', !!msg);
        err.textContent = msg;
        err.classList.toggle('show', !!msg);
    }

    function clearErr(inputId, errId) { fieldErr(inputId, errId, ''); }

    function setLoading(btn, loading, label = 'Sign In') {
        btn.disabled  = loading;
        btn.innerHTML = loading ? '<span class="btn-spinner"></span> Please wait…' : label;
    }

    const btnOSA   = $('btnOSA');
    const btnORG   = $('btnORG');
    const formOSA  = $('formOSA');
    const formORG  = $('formORG');
    const titleOsa = $('titleOsa');
    const titleOrg = $('titleOrg');

    function switchTab(tab) {
        const isOsa = tab === 'osa';
        btnOSA.classList.toggle('active', isOsa);
        btnORG.classList.toggle('active', !isOsa);
        formOSA.classList.toggle('active', isOsa);
        formORG.classList.toggle('active', !isOsa);
        titleOsa.classList.toggle('active', isOsa);
        titleOrg.classList.toggle('active', !isOsa);
    }

    btnOSA.addEventListener('click', () => switchTab('osa'));
    btnORG.addEventListener('click', () => switchTab('org'));

    formOSA.addEventListener('submit', async e => {
        e.preventDefault();

        const emailEl = $('osaEmail');
        const passEl  = $('osaPassword');
        let valid = true;

        if (!emailEl.value.trim()) {
            fieldErr('osaEmail', 'osaEmailErr', 'Email is required.');
            valid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
            fieldErr('osaEmail', 'osaEmailErr', 'Enter a valid email address.');
            valid = false;
        } else {
            clearErr('osaEmail', 'osaEmailErr');
        }

        if (!passEl.value) {
            fieldErr('osaPassword', 'osaPassErr', 'Password is required.');
            valid = false;
        } else {
            clearErr('osaPassword', 'osaPassErr');
        }

        if (!valid) return;

        const btn = $('osaSbmBtn');
        setLoading(btn, true);

        const body = new FormData();
        body.append('email',    emailEl.value.trim());
        body.append('password', passEl.value);
        body.append('remember', $('osaRemember').checked ? '1' : '0');

        try {
            const res  = await fetch('../../config/API/osa_login.php', { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => { window.location.href = data.redirect; }, 1200);
            } else if (data.locked) {
                setLoading(btn, false);
                btn.disabled = true;
                let secs = data.remaining || 300;
                const tick = () => {
                    const m = Math.floor(secs / 60), s = secs % 60;
                    btn.innerHTML = `🔒 Locked — ${m}:${String(s).padStart(2,'0')}`;
                    if (secs-- <= 0) { clearInterval(t); btn.disabled = false; btn.innerHTML = 'Sign In'; }
                };
                tick(); const t = setInterval(tick, 1000);
                fieldErr('osaPassword', 'osaPassErr', data.message);
                showToast(data.message, 'error');
            } else {
                showToast(data.message, 'error');
                setLoading(btn, false);
                const msg = data.attempts_left !== undefined
                    ? `Incorrect password. ${data.attempts_left} attempt(s) left before lockout.`
                    : data.message;
                if (data.message.toLowerCase().includes('email') || data.message.toLowerCase().includes('account')) {
                    fieldErr('osaEmail', 'osaEmailErr', data.message);
                } else {
                    fieldErr('osaPassword', 'osaPassErr', msg);
                }
            }
        } catch {
            showToast('Network error. Please check your connection.', 'error');
            setLoading(btn, false);
        }
    });

    $('osaEmail').addEventListener('input',    () => clearErr('osaEmail',    'osaEmailErr'));
    $('osaPassword').addEventListener('input', () => clearErr('osaPassword', 'osaPassErr'));

    formORG.addEventListener('submit', async e => {
        e.preventDefault();

        const orgEl  = $('orgSelect');
        const userEl = $('orgUsername');
        const passEl = $('orgPassword');
        let valid = true;

        if (!orgEl.value) {
            fieldErr('orgSelect', 'orgSelectErr', 'Please select your organization.');
            valid = false;
        } else {
            clearErr('orgSelect', 'orgSelectErr');
        }

        if (!userEl.value.trim()) {
            fieldErr('orgUsername', 'orgUserErr', 'Username is required.');
            valid = false;
        } else {
            clearErr('orgUsername', 'orgUserErr');
        }

        if (!passEl.value) {
            fieldErr('orgPassword', 'orgPassErr', 'Password is required.');
            valid = false;
        } else {
            clearErr('orgPassword', 'orgPassErr');
        }

        if (!valid) return;

        const btn = $('orgSbmBtn');
        setLoading(btn, true);

        const body = new FormData();
        body.append('org_id',   orgEl.value);
        body.append('username', userEl.value.trim());
        body.append('password', passEl.value);
        body.append('remember', $('orgRemember').checked ? '1' : '0');

        try {
            const res  = await fetch('../../config/API/org_login.php', { method: 'POST', body });
            const data = await res.json();

            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => { window.location.href = data.redirect; }, 1200);
            } else if (data.locked) {
                setLoading(btn, false);
                btn.disabled = true;
                let secs = data.remaining || 300;
                const tick = () => {
                    const m = Math.floor(secs / 60), s = secs % 60;
                    btn.innerHTML = `🔒 Locked — ${m}:${String(s).padStart(2,'0')}`;
                    if (secs-- <= 0) { clearInterval(t); btn.disabled = false; btn.innerHTML = 'Sign In'; }
                };
                tick(); const t = setInterval(tick, 1000);
                fieldErr('orgPassword', 'orgPassErr', data.message);
                showToast(data.message, 'error');
            } else {
                showToast(data.message, 'error');
                setLoading(btn, false);
                const msg = data.attempts_left !== undefined
                    ? `Incorrect password. ${data.attempts_left} attempt(s) left before lockout.`
                    : data.message;
                fieldErr('orgPassword', 'orgPassErr', msg);
            }
        } catch {
            showToast('Network error. Please check your connection.', 'error');
            setLoading(btn, false);
        }
    });

    $('orgSelect').addEventListener('change',  () => clearErr('orgSelect',   'orgSelectErr'));
    $('orgUsername').addEventListener('input', () => clearErr('orgUsername', 'orgUserErr'));
    $('orgPassword').addEventListener('input', () => clearErr('orgPassword', 'orgPassErr'));

})();



document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('logout') === 'success') {
        const alertHtml = `
            <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid #22c55e; color: #166534; padding: 12px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
                <ion-icon name="checkmark-circle" style="font-size: 1.2rem; color: #22c55e;"></ion-icon>
                <span>You have been successfully logged out.</span>
            </div>
        `;
        
        const form = document.querySelector('form');
        if (form) {
            form.insertAdjacentHTML('beforebegin', alertHtml);
        }
        
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

document.getElementById('osaForgotLink')?.addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('forgotStep1').style.display = 'block';
    document.getElementById('forgotStep2').style.display = 'none';
    document.getElementById('forgotMsg').textContent = '';
    document.getElementById('forgotModal').style.display = 'flex';
});

document.getElementById('forgotSendBtn')?.addEventListener('click', function() {
    const email = document.getElementById('forgotEmail').value.trim();
    const msgEl = document.getElementById('forgotMsg');
    if (!email) { msgEl.innerHTML = '<span style="color:#f87171">Please enter your email.</span>'; return; }

    this.textContent = 'Sending...';
    this.disabled = true;
    const btn = this;

    const fd = new FormData();
    fd.append('email', email);
    fetch('../../config/API/osa_forgot_password.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.textContent = 'Send Reset Code';
            btn.disabled = false;
            if (data.success) {
                let hint = data.dev_pin ? '<br><strong style="color:#4ade80">Dev PIN: ' + data.dev_pin + '</strong>' : '';
                msgEl.innerHTML = '<span style="color:#4ade80">' + data.message + '</span>' + hint;
                setTimeout(() => {
                    document.getElementById('forgotStep1').style.display = 'none';
                    document.getElementById('forgotStep2').style.display = 'block';
                }, 1800);
            } else {
                msgEl.innerHTML = '<span style="color:#f87171">' + data.message + '</span>';
            }
        });
});

document.getElementById('resetSaveBtn')?.addEventListener('click', function() {
    const pin  = document.getElementById('resetPin').value.trim();
    const np   = document.getElementById('resetNewPass').value;
    const cp   = document.getElementById('resetConfPass').value;
    const msgEl= document.getElementById('resetMsg');

    if (!pin || !np || !cp) { msgEl.innerHTML = '<span style="color:#f87171">All fields are required.</span>'; return; }
    if (np !== cp)          { msgEl.innerHTML = '<span style="color:#f87171">Passwords do not match.</span>'; return; }
    if (np.length < 8)      { msgEl.innerHTML = '<span style="color:#f87171">Password must be at least 8 characters.</span>'; return; }

    this.textContent = 'Saving...';
    this.disabled = true;
    const btn = this;

    const fd = new FormData();
    fd.append('pin', pin);
    fd.append('new_password', np);
    fd.append('confirm_password', cp);
    fetch('../../config/API/osa_reset_password.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.textContent = 'Reset Password';
            btn.disabled = false;
            if (data.success) {
                msgEl.innerHTML = '<span style="color:#4ade80">' + data.message + '</span>';
                setTimeout(() => { document.getElementById('forgotModal').style.display = 'none'; }, 2000);
            } else {
                msgEl.innerHTML = '<span style="color:#f87171">' + data.message + '</span>';
            }
        });
});