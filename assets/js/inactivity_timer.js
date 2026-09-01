/**
 * assets/js/inactivity_timer.js
 * NAAP System — 40-Minute Inactivity Auto-Logout with Warning & Inactivity Modal
 * 
 * - 40-minute total inactivity threshold (2400 seconds)
 * - 2-minute countdown warning modal at 38 minutes of inactivity
 * - Modal popup on logout: "You were logged out for being inactive"
 * - Cross-tab synchronization via localStorage & storage events
 * - Background keepalive session ping every 10 minutes when user is active
 */

(function () {
    'use strict';

    // ── Configuration Constants ──────────────────────────────────────
    const INACTIVITY_TIMEOUT_MS = 40 * 60 * 1000;   // 40 minutes = 2,400,000 ms
    const WARNING_DURATION_MS   = 2 * 60 * 1000;    // 2 minutes = 120,000 ms
    const WARNING_THRESHOLD_MS  = INACTIVITY_TIMEOUT_MS - WARNING_DURATION_MS; // 38 minutes = 2,280,000 ms
    const PING_INTERVAL_MS      = 10 * 60 * 1000;   // 10 minutes keep-alive heartbeat
    const THROTTLE_MS           = 3000;             // Throttle event listeners to every 3 seconds

    const STORAGE_KEY_LAST_ACTIVITY = 'naap_last_activity';
    const STORAGE_KEY_LOGOUT        = 'naap_session_expired';

    let lastActivityTime = Date.now();
    let lastPingTime = Date.now();
    let lastThrottledTime = 0;
    let warningModalElement = null;
    let countdownIntervalId = null;
    let checkIntervalId = null;
    let isWarningActive = false;

    // ── Check if Current Page is an Authenticated Portal Page ────────
    function isAuthPage() {
        const path = window.location.pathname.toLowerCase();
        
        // Exclude public authentication pages
        if (path.endsWith('/login.php') || 
            path.endsWith('/register.php') || 
            path.endsWith('/forgot_password.php') ||
            path.endsWith('/assessment_error.php')) {
            return false;
        }

        // Exclude guest landing page if user is not logged in
        if (path.endsWith('/app/index.php') || path.endsWith('/project/index.php') || path === '/' || path.endsWith('/index.php')) {
            const hasLogoutBtn = document.querySelector('a[href*="logout"], button[data-action="logout"], .logout-btn, .logout-link, .nav-dropdown-item.danger');
            return !!hasLogoutBtn;
        }

        // Inside admin, osa, organization, or student portal
        if (path.includes('/app/admin/') || 
            path.includes('/app/osa/') || 
            path.includes('/app/organization/') || 
            path.includes('/app/student/')) {
            return true;
        }

        // Check for general logout or dashboard indicators in DOM
        const hasPortalElements = document.querySelector('aside.sidebar, aside.admin-sidebar, .dashboard-layout, .content-shell, a[href*="logout"]');
        return !!hasPortalElements;
    }

    // ── Get Relative API Endpoint URL ─────────────────────────────────
    function getApiEndpointUrl(action) {
        const path = window.location.pathname.toLowerCase();
        let prefix = '../../config/API/endpoints/index.php';
        if (path.includes('/app/admin/') || path.includes('/app/osa/') || path.includes('/app/organization/') || path.includes('/app/student/')) {
            prefix = '../../config/API/endpoints/index.php';
        } else if (path.includes('/app/')) {
            prefix = '../config/API/endpoints/index.php';
        } else {
            prefix = 'config/API/endpoints/index.php';
        }
        return prefix + '?action=' + encodeURIComponent(action);
    }

    // ── Get Portal Logout & Login Redirect URLs ───────────────────────
    function getLogoutUrl() {
        const path = window.location.pathname.toLowerCase();
        if (path.includes('/app/admin/')) {
            return {
                api: getApiEndpointUrl('admin_logout'),
                redirect: 'login.php?session_expired=1'
            };
        }
        if (path.includes('/app/osa/')) {
            return {
                api: getApiEndpointUrl('osa_logout'),
                redirect: 'login.php?session_expired=1'
            };
        }
        if (path.includes('/app/organization/')) {
            return {
                api: getApiEndpointUrl('org_logout'),
                redirect: '../osa/login.php?session_expired=1'
            };
        }
        // Student or general
        return {
            api: getApiEndpointUrl('student_logout'),
            redirect: path.includes('/app/student/') ? 'login.php?session_expired=1' : 'student/login.php?session_expired=1'
        };
    }

    // ── Update Activity Timestamp ─────────────────────────────────────
    function recordUserActivity(explicit = false) {
        const now = Date.now();
        if (!explicit && (now - lastThrottledTime < THROTTLE_MS)) {
            return;
        }
        lastThrottledTime = now;
        lastActivityTime = now;
        try {
            localStorage.setItem(STORAGE_KEY_LAST_ACTIVITY, now.toString());
        } catch (e) {}

        // If the warning modal is currently showing and the user interacted, hide modal & keep alive
        if (isWarningActive) {
            hideWarningModal();
            sendKeepAlivePing();
        }

        // Check if keep-alive ping to backend is needed
        if (now - lastPingTime > PING_INTERVAL_MS) {
            sendKeepAlivePing();
        }
    }

    // ── Heartbeat Keepalive Ping ──────────────────────────────────────
    async function sendKeepAlivePing() {
        lastPingTime = Date.now();
        try {
            const url = getApiEndpointUrl('session_ping');
            const res = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                cache: 'no-cache'
            });
            if (res.ok) {
                const data = await res.json();
                if (data.session_expired || !data.session_active) {
                    performAutoLogout();
                }
            }
        } catch (e) {
            // Silently ignore network dropouts; client timer handles timeout
        }
    }

    // ── Ensure Global Styles are Injected ─────────────────────────────
    function ensureStyles() {
        if (document.getElementById('naapInactivityGlobalStyle')) return;
        const style = document.createElement('style');
        style.id = 'naapInactivityGlobalStyle';
        style.textContent = `
            @keyframes naapFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes naapScaleUp {
                from { opacity: 0; transform: scale(0.92); }
                to { opacity: 1; transform: scale(1); }
            }
            @keyframes naapPulseWarning {
                0%, 100% { transform: scale(1); box-shadow: 0 8px 20px rgba(217, 119, 6, 0.18); }
                50% { transform: scale(1.05); box-shadow: 0 10px 26px rgba(217, 119, 6, 0.35); }
            }
            #naapInactivityStayBtn:hover {
                background: linear-gradient(135deg, #1d4ed8, #1e40af) !important;
                transform: translateY(-1px);
                box-shadow: 0 6px 18px rgba(37, 99, 235, 0.45) !important;
            }
            #naapInactivityLogoutBtn:hover {
                background: #fee2e2 !important;
                color: #dc2626 !important;
                border-color: #fecaca !important;
            }
            #naapLoggedOutDismissBtn:hover {
                background: #1d4ed8 !important;
                transform: translateY(-1px);
                box-shadow: 0 6px 18px rgba(30, 64, 175, 0.4) !important;
            }
        `;
        document.head.appendChild(style);
    }

    // ── Build & Inject 38-Minute Warning Modal ─────────────────────────
    function createWarningModal() {
        if (warningModalElement) return warningModalElement;
        ensureStyles();

        const modal = document.createElement('div');
        modal.id = 'naapInactivityWarningModal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'naapInactivityTitle');
        modal.style.cssText = `
            position: fixed;
            inset: 0;
            z-index: 9999999;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            opacity: 0;
            transition: opacity 0.25s ease;
        `;

        modal.innerHTML = `
            <div style="
                background: #ffffff;
                border-radius: 20px;
                max-width: 420px;
                width: 100%;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(226, 232, 240, 0.8);
                overflow: hidden;
                text-align: center;
                padding: 32px 28px;
                transform: scale(0.95);
                transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            " id="naapInactivityCard">
                
                <div style="
                    width: 64px;
                    height: 64px;
                    background: #fef3c7;
                    border: 2px solid #fde68a;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 18px;
                    color: #d97706;
                    box-shadow: 0 8px 20px rgba(217, 119, 6, 0.18);
                    animation: naapPulseWarning 2s infinite;
                ">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>

                <h3 id="naapInactivityTitle" style="
                    margin: 0 0 8px;
                    font-size: 21px;
                    font-weight: 800;
                    color: #0f172a;
                    letter-spacing: -0.3px;
                ">Inactivity Warning</h3>

                <p style="
                    margin: 0 0 16px;
                    font-size: 14.5px;
                    color: #64748b;
                    line-height: 1.5;
                    font-weight: 450;
                ">
                    You will be logged out for being inactive in:
                </p>

                <div style="
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 18px;
                    background: #f8fafc;
                    border: 1.5px solid #e2e8f0;
                    border-radius: 12px;
                    margin-bottom: 24px;
                ">
                    <span id="naapInactivityCountdown" style="
                        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                        font-size: 22px;
                        font-weight: 800;
                        color: #dc2626;
                    ">02:00</span>
                </div>

                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button id="naapInactivityLogoutBtn" type="button" style="
                        flex: 1;
                        padding: 12px 16px;
                        border: 1.5px solid #e2e8f0;
                        background: #f8fafc;
                        color: #475569;
                        border-radius: 12px;
                        font-weight: 600;
                        font-size: 14px;
                        cursor: pointer;
                        transition: all 0.2s;
                    ">Log Out</button>

                    <button id="naapInactivityStayBtn" type="button" style="
                        flex: 1.4;
                        padding: 12px 18px;
                        border: none;
                        background: linear-gradient(135deg, #1e40af, #2563eb);
                        color: #ffffff;
                        border-radius: 12px;
                        font-weight: 700;
                        font-size: 14px;
                        cursor: pointer;
                        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
                        transition: all 0.2s;
                    ">Stay Logged In</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Bind Button Actions
        modal.querySelector('#naapInactivityStayBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            recordUserActivity(true);
        });

        modal.querySelector('#naapInactivityLogoutBtn').addEventListener('click', (e) => {
            e.stopPropagation();
            performAutoLogout();
        });

        warningModalElement = modal;
        return modal;
    }

    // ── Show Warning Modal ────────────────────────────────────────────
    function showWarningModal() {
        if (isWarningActive) return;
        isWarningActive = true;

        const modal = createWarningModal();
        modal.style.display = 'flex';
        requestAnimationFrame(() => {
            modal.style.opacity = '1';
            const card = modal.querySelector('#naapInactivityCard');
            if (card) card.style.transform = 'scale(1)';
        });

        updateCountdownDisplay();
        clearInterval(countdownIntervalId);
        countdownIntervalId = setInterval(updateCountdownDisplay, 1000);
    }

    // ── Hide Warning Modal ────────────────────────────────────────────
    function hideWarningModal() {
        if (!isWarningActive || !warningModalElement) return;
        isWarningActive = false;
        clearInterval(countdownIntervalId);

        warningModalElement.style.opacity = '0';
        const card = warningModalElement.querySelector('#naapInactivityCard');
        if (card) card.style.transform = 'scale(0.95)';

        setTimeout(() => {
            if (warningModalElement) warningModalElement.style.display = 'none';
        }, 250);
    }

    // ── Update Countdown Timer Display ────────────────────────────────
    function updateCountdownDisplay() {
        const now = Date.now();
        const storedLastActivity = parseInt(localStorage.getItem(STORAGE_KEY_LAST_ACTIVITY) || lastActivityTime, 10);
        const effectiveLastActivity = Math.max(lastActivityTime, storedLastActivity);
        const elapsed = now - effectiveLastActivity;
        const remainingMs = Math.max(0, INACTIVITY_TIMEOUT_MS - elapsed);

        const countdownEl = document.getElementById('naapInactivityCountdown');
        if (countdownEl) {
            const totalSecs = Math.ceil(remainingMs / 1000);
            const mins = Math.floor(totalSecs / 60);
            const secs = totalSecs % 60;
            countdownEl.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }

        if (remainingMs <= 0) {
            clearInterval(countdownIntervalId);
            performAutoLogout();
        }
    }

    // ── Execute Auto Logout ───────────────────────────────────────────
    function performAutoLogout() {
        clearInterval(checkIntervalId);
        clearInterval(countdownIntervalId);

        try {
            localStorage.removeItem(STORAGE_KEY_LAST_ACTIVITY);
            localStorage.setItem(STORAGE_KEY_LOGOUT, Date.now().toString());
        } catch (e) {}

        const logoutInfo = getLogoutUrl();

        // Fire and forget logout endpoint call to destroy server session
        try {
            fetch(logoutInfo.api, { method: 'POST', cache: 'no-cache' }).catch(() => {});
        } catch (e) {}

        // Immediate redirect to login page with session expired message
        window.location.href = logoutInfo.redirect;
    }

    // ── Periodic Inactivity Check Routine ─────────────────────────────
    function checkInactivity() {
        if (!isAuthPage()) return;

        const now = Date.now();
        const storedLastActivity = parseInt(localStorage.getItem(STORAGE_KEY_LAST_ACTIVITY) || lastActivityTime, 10);
        const effectiveLastActivity = Math.max(lastActivityTime, storedLastActivity);
        const elapsed = now - effectiveLastActivity;

        if (elapsed >= INACTIVITY_TIMEOUT_MS) {
            performAutoLogout();
        } else if (elapsed >= WARNING_THRESHOLD_MS) {
            showWarningModal();
        } else if (isWarningActive) {
            // User became active in another tab
            hideWarningModal();
        }
    }

    // ── Listen for Cross-Tab Storage Events ───────────────────────────
    function initStorageListener() {
        window.addEventListener('storage', (e) => {
            if (e.key === STORAGE_KEY_LAST_ACTIVITY && e.newValue) {
                const updatedTime = parseInt(e.newValue, 10);
                if (!isNaN(updatedTime)) {
                    lastActivityTime = Math.max(lastActivityTime, updatedTime);
                    if (isWarningActive) {
                        hideWarningModal();
                    }
                }
            } else if (e.key === STORAGE_KEY_LOGOUT) {
                // Another tab timed out and triggered logout
                const logoutInfo = getLogoutUrl();
                window.location.href = logoutInfo.redirect;
            }
        });
    }

    // ── Register User Interaction Events ──────────────────────────────
    function initActivityListeners() {
        const events = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'click', 'wheel'];
        events.forEach((evt) => {
            window.addEventListener(evt, () => recordUserActivity(false), { passive: true });
        });
    }

    // ── Display "Logged Out" Modal on Login Pages ─────────────────────
    function checkLoginExpiredNotice() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('session_expired') === '1' || params.get('logout') === 'timeout') {
            ensureStyles();

            // Clean up old banners if any existed
            const oldAlert = document.getElementById('naapSessionExpiredAlert');
            if (oldAlert) oldAlert.remove();

            if (document.getElementById('naapLoggedOutModal')) return;

            const modal = document.createElement('div');
            modal.id = 'naapLoggedOutModal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.style.cssText = `
                position: fixed;
                inset: 0;
                z-index: 9999999;
                background: rgba(15, 23, 42, 0.7);
                backdrop-filter: blur(8px);
                -webkit-backdrop-filter: blur(8px);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                font-family: 'Inter', system-ui, -apple-system, sans-serif;
                animation: naapFadeIn 0.2s ease;
            `;

            modal.innerHTML = `
                <div style="
                    background: #ffffff;
                    border-radius: 20px;
                    max-width: 380px;
                    width: 100%;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
                    padding: 32px 24px;
                    text-align: center;
                    animation: naapScaleUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
                ">
                    <div style="
                        width: 60px;
                        height: 60px;
                        background: #fef2f2;
                        border: 2px solid #fecaca;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto 16px;
                        color: #ef4444;
                        box-shadow: 0 6px 16px rgba(239, 68, 68, 0.15);
                    ">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>

                    <h3 style="
                        margin: 0 0 8px;
                        font-size: 19px;
                        font-weight: 800;
                        color: #0f172a;
                        letter-spacing: -0.3px;
                    ">Logged Out</h3>

                    <p style="
                        margin: 0 0 24px;
                        font-size: 14.5px;
                        color: #475569;
                        line-height: 1.5;
                        font-weight: 500;
                    ">
                        You were logged out for being inactive
                    </p>

                    <button id="naapLoggedOutDismissBtn" type="button" style="
                        width: 100%;
                        padding: 12px 18px;
                        border: none;
                        background: #1e40af;
                        color: #ffffff;
                        border-radius: 12px;
                        font-weight: 700;
                        font-size: 14.5px;
                        cursor: pointer;
                        box-shadow: 0 4px 14px rgba(30, 64, 175, 0.3);
                        transition: all 0.2s;
                    ">Okay</button>
                </div>
            `;

            document.body.appendChild(modal);

            const dismissModal = (e) => {
                if (e) {
                    if (typeof e.preventDefault === 'function') e.preventDefault();
                    if (typeof e.stopPropagation === 'function') e.stopPropagation();
                }
                modal.style.opacity = '0';
                modal.style.transition = 'opacity 0.2s ease';
                setTimeout(() => {
                    if (modal.parentNode) modal.parentNode.removeChild(modal);
                }, 200);

                // Focus first input on login form if available
                setTimeout(() => {
                    const firstInput = document.querySelector('input[type="text"], input[type="email"], select');
                    if (firstInput) firstInput.focus();
                }, 220);
            };

            const card = modal.querySelector('#naapLoggedOutCard') || modal.firstElementChild;
            if (card) {
                card.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }

            const dismissBtn = modal.querySelector('#naapLoggedOutDismissBtn');
            if (dismissBtn) {
                dismissBtn.addEventListener('click', (e) => {
                    dismissModal(e);
                });
            }

            modal.addEventListener('click', (e) => {
                dismissModal(e);
            });

            function escHandler(e) {
                if (e.key === 'Escape' || e.key === 'Enter') {
                    if (typeof e.preventDefault === 'function') e.preventDefault();
                    if (typeof e.stopPropagation === 'function') e.stopPropagation();
                    dismissModal(e);
                    document.removeEventListener('keydown', escHandler, true);
                }
            }
            document.addEventListener('keydown', escHandler, true);

            // Clean up the URL query parameter
            try {
                const newUrl = window.location.pathname + (window.location.hash || '');
                window.history.replaceState({}, document.title, newUrl);
            } catch (e) {}
        }
    }

    // ── Main Initialization ───────────────────────────────────────────
    function init() {
        checkLoginExpiredNotice();

        if (!isAuthPage()) {
            return;
        }

        // Initialize local storage timestamp if not present
        const now = Date.now();
        lastActivityTime = now;
        try {
            if (!localStorage.getItem(STORAGE_KEY_LAST_ACTIVITY)) {
                localStorage.setItem(STORAGE_KEY_LAST_ACTIVITY, now.toString());
            }
        } catch (e) {}

        initActivityListeners();
        initStorageListener();

        // Run checking routine every 2 seconds
        clearInterval(checkIntervalId);
        checkIntervalId = setInterval(checkInactivity, 2000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
