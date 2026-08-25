/**
 * Student Live Verification & Anti-Spoofing Tab Notifier with Minimize / Snooze
 * Allows students to minimize the challenge to access the system while keeping a floating quick-access reminder.
 */
(function () {
    let currentNotice = null;
    let originalTitle = document.title;
    let snoozeTimerInterval = null;
    const SNOOZE_DURATION_MS = 5 * 60 * 1000; // 5 minutes snooze

    // Detect base API endpoint path
    const isAppRoot = window.location.pathname.includes('/app/') && !window.location.pathname.includes('/app/student/');
    const apiEndpoint = isAppRoot 
        ? '../config/API/endpoints/index.php?action=get_verification_notice' 
        : '../../config/API/endpoints/index.php?action=get_verification_notice';
    
    const presenceCheckUrl = isAppRoot
        ? 'student/presence-check.php'
        : 'presence-check.php';

    function setTabNotification() {
        originalTitle = document.title.replace(/^\(\d+\)\s*/, '');
        document.title = `(1) ${originalTitle}`;
    }

    function clearTabNotification() {
        document.title = originalTitle.replace(/^\(\d+\)\s*/, '');
    }

    function getSnoozeKey(notice) {
        if (!notice) return '';
        return `naap_verif_snooze_${notice.EventId}_${notice.check_type}_${notice.triggered_at}`;
    }

    function isSnoozed(notice) {
        if (!notice) return false;
        const until = parseInt(sessionStorage.getItem(getSnoozeKey(notice)) || '0', 10);
        return until > Date.now();
    }

    function setSnooze(notice, durationMs = SNOOZE_DURATION_MS) {
        if (!notice) return;
        const expiry = Date.now() + durationMs;
        sessionStorage.setItem(getSnoozeKey(notice), String(expiry));
    }

    function clearSnooze(notice) {
        if (!notice) return;
        sessionStorage.removeItem(getSnoozeKey(notice));
    }

    function getRemainingSnoozeSeconds(notice) {
        if (!notice) return 0;
        const until = parseInt(sessionStorage.getItem(getSnoozeKey(notice)) || '0', 10);
        return Math.max(0, Math.ceil((until - Date.now()) / 1000));
    }

    function removeModal() {
        const modal = document.getElementById('verificationPromptModal');
        if (modal) modal.remove();
    }

    function removeFloatingPill() {
        const pill = document.getElementById('verificationFloatingPill');
        if (pill) pill.remove();
        if (snoozeTimerInterval) {
            clearInterval(snoozeTimerInterval);
            snoozeTimerInterval = null;
        }
    }

    function renderFloatingPill(notice) {
        if (document.getElementById('verificationFloatingPill')) {
            updatePillTimer(notice);
            return;
        }

        const antiSpoof = notice.check_type === 'antispoof';
        const label = antiSpoof ? 'Anti-spoofing Challenge' : 'Presence Check';

        const pill = document.createElement('div');
        pill.id = 'verificationFloatingPill';
        pill.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999999;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1.5px solid rgba(56, 189, 248, 0.6);
            border-radius: 16px;
            padding: 12px 18px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.5), 0 0 20px rgba(56, 189, 248, 0.2);
            color: #f8fafc;
            font-family: 'Inter', system-ui, sans-serif;
            display: flex;
            align-items: center;
            gap: 14px;
            max-width: 480px;
            animation: slideUpFloat 0.3s ease-out;
            cursor: default;
        `;

        pill.innerHTML = `
            <style>
                @keyframes slideUpFloat {
                    from { transform: translateY(30px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                @keyframes pulseDot {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50% { opacity: 0.4; transform: scale(0.85); }
                }
            </style>
            <div style="position:relative;width:40px;height:40px;border-radius:12px;background:rgba(56,189,248,0.15);border:1px solid rgba(56,189,248,0.3);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                ${antiSpoof ? '<ion-icon name="camera-outline"></ion-icon>' : '<ion-icon name="timer-outline"></ion-icon>'}
                <span style="position:absolute;top:-3px;right:-3px;width:10px;height:10px;border-radius:50%;background:#38bdf8;box-shadow:0 0 8px #38bdf8;animation:pulseDot 1.5s infinite;"></span>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:800;color:#f8fafc;display:flex;align-items:center;gap:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <span>${label}</span>
                    <span id="pillTimerBadge" style="font-size:11px;font-weight:700;background:rgba(234,179,8,0.2);color:#fde047;border:1px solid rgba(234,179,8,0.4);border-radius:20px;padding:2px 7px;">Snoozed</span>
                </div>
                <div style="font-size:12px;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px;">
                    ${escapeHtml(notice.EventName)} • Complete before timer ends
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <button id="pillVerifyBtn" style="border:none;border-radius:10px;padding:8px 14px;background:#2563eb;color:#fff;font-weight:700;font-size:12.5px;cursor:pointer;transition:all 0.2s;box-shadow:0 2px 8px rgba(37,99,235,0.4);">
                    Verify Now
                </button>
                <button id="pillExpandBtn" title="Expand Details" style="border:1px solid #475569;border-radius:10px;background:rgba(30,41,59,0.8);color:#cbd5e1;padding:8px 10px;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;">
                    ⤢
                </button>
            </div>
        `;

        document.body.appendChild(pill);

        pill.querySelector('#pillVerifyBtn').addEventListener('click', () => {
            clearTabNotification();
            clearSnooze(notice);
            location.href = `${presenceCheckUrl}?eventId=${encodeURIComponent(notice.EventId)}&type=${encodeURIComponent(notice.check_type)}`;
        });

        pill.querySelector('#pillExpandBtn').addEventListener('click', () => {
            clearSnooze(notice);
            removeFloatingPill();
            showVerificationModal(notice);
        });

        updatePillTimer(notice);
        if (!snoozeTimerInterval) {
            snoozeTimerInterval = setInterval(() => updatePillTimer(notice), 1000);
        }
    }

    function updatePillTimer(notice) {
        const badge = document.getElementById('pillTimerBadge');
        if (!badge) return;
        const remSec = getRemainingSnoozeSeconds(notice);
        if (remSec <= 0) {
            badge.textContent = 'Action Required';
            badge.style.background = 'rgba(239,68,68,0.2)';
            badge.style.color = '#fca5a5';
            badge.style.borderColor = 'rgba(239,68,68,0.4)';
            // When snooze runs out, pop up full modal again
            if (!document.getElementById('verificationPromptModal')) {
                removeFloatingPill();
                showVerificationModal(notice);
            }
        } else {
            const mins = Math.floor(remSec / 60);
            const secs = remSec % 60;
            badge.textContent = `Snoozed ${mins}:${secs < 10 ? '0' : ''}${secs}`;
            badge.style.background = 'rgba(234,179,8,0.2)';
            badge.style.color = '#fde047';
            badge.style.borderColor = 'rgba(234,179,8,0.4)';
        }
    }

    function showVerificationModal(notice) {
        if (document.getElementById('verificationPromptModal')) return;

        const antiSpoof = notice.check_type === 'antispoof';
        const label = antiSpoof ? 'Face verification required' : 'Presence check required';

        // Set static (1) title prefix
        setTabNotification();

        // Browser desktop notification if supported
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                new Notification(label, { 
                    body: `${notice.EventName} has requested a live verification. Click to complete it.`
                });
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission();
            }
        }

        const modal = document.createElement('div');
        modal.id = 'verificationPromptModal';
        modal.setAttribute('role', 'dialog'); 
        modal.setAttribute('aria-modal', 'true');
        modal.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(15,23,42,.85);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;padding:20px;animation:fadeInModal 0.25s ease;';
        
        modal.innerHTML = `
            <style>
                @keyframes fadeInModal {
                    from { opacity: 0; transform: scale(0.96); }
                    to { opacity: 1; transform: scale(1); }
                }
            </style>
            <div style="position:relative;max-width:460px;width:100%;box-sizing:border-box;background:#1e293b;border:1px solid #334155;border-radius:24px;padding:34px 30px;text-align:center;box-shadow:0 25px 70px rgba(0,0,0,.6);color:#f8fafc;font-family:'Inter',system-ui,sans-serif;">
                
                <!-- Minimize Button in top right corner -->
                <button id="topMinimizeBtn" title="Minimize to floating reminder" style="position:absolute;top:18px;right:18px;width:34px;height:34px;border-radius:50%;background:rgba(51,65,85,0.7);border:1px solid #475569;color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;line-height:1;transition:all 0.2s;">
                    —
                </button>

                <div style="width:68px;height:68px;border-radius:50%;background:rgba(56,189,248,0.15);border:2px solid rgba(56,189,248,0.3);margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:32px;">
                    ${antiSpoof ? '<ion-icon name="camera-outline"></ion-icon>' : '<ion-icon name="timer-outline"></ion-icon>'}
                </div>
                <span style="font-size:0.75rem;font-weight:800;color:#38bdf8;text-transform:uppercase;letter-spacing:0.08em;display:inline-block;margin-bottom:6px;">
                    Action Required
                </span>
                <h2 style="margin:0 0 12px;color:#f8fafc;font-size:22px;font-weight:800;line-height:1.3;">
                    ${label}
                </h2>
                <p style="margin:0 0 24px;color:#94a3b8;line-height:1.6;font-size:14.5px;">
                    <strong style="color:#e2e8f0;">${escapeHtml(notice.EventName)}</strong> has requested a live verification. Complete it now to remain marked as present.
                </p>
                
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <button id="startVerificationBtn" style="width:100%;border:0;border-radius:12px;padding:14px;background:#2563eb;color:#fff;font-weight:800;font-size:15px;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 14px rgba(37,99,235,0.4);">
                        Start Verification
                    </button>
                    <button id="snoozeMinimizeBtn" style="width:100%;border:1px solid #475569;border-radius:12px;padding:12px;background:rgba(30,41,59,0.7);color:#94a3b8;font-weight:700;font-size:13.5px;cursor:pointer;transition:all 0.2s;display:inline-flex;align-items:center;justify-content:center;gap:6px;">
                        <ion-icon name="timer-outline"></ion-icon> Minimize & Remind Me in 5 Mins
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        modal.querySelector('#startVerificationBtn').addEventListener('click', () => {
            clearTabNotification();
            clearSnooze(notice);
            location.href = `${presenceCheckUrl}?eventId=${encodeURIComponent(notice.EventId)}&type=${encodeURIComponent(notice.check_type)}`;
        });

        const doMinimize = () => {
            setSnooze(notice, SNOOZE_DURATION_MS);
            removeModal();
            renderFloatingPill(notice);
        };

        modal.querySelector('#topMinimizeBtn').addEventListener('click', doMinimize);
        modal.querySelector('#snoozeMinimizeBtn').addEventListener('click', doMinimize);
    }

    function handleNotice(notice) {
        currentNotice = notice;
        if (!notice) {
            // Notice cleared / event completed / completed check
            clearTabNotification();
            removeModal();
            removeFloatingPill();
            return;
        }

        setTabNotification();

        if (isSnoozed(notice)) {
            removeModal();
            renderFloatingPill(notice);
        } else {
            removeFloatingPill();
            showVerificationModal(notice);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    async function checkVerification() {
        try { 
            const response = await fetch(apiEndpoint, { credentials: 'same-origin', cache: 'no-store' }); 
            const data = await response.json(); 
            if (data.success) {
                handleNotice(data.notice || null); 
            }
        } catch (_) {}
    }

    // Initial check and interval polling every 4 seconds
    checkVerification(); 
    setInterval(checkVerification, 4000);
})();
