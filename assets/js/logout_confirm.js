/**
 * Global Logout Confirmation Modal
 * Intercepts any logout links across Student, Org, OSA, and Admin portals.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[href*="logout"], button[data-action="logout"], .logout-btn, .logout-link, a.nav-dropdown-item.danger');
        if (!link) return;

        if (link.dataset.confirmed === 'true') return;

        e.preventDefault();
        const targetUrl = link.href || link.dataset.targetUrl || link.getAttribute('href');

        showLogoutConfirmModal(targetUrl);
    });
});

function showLogoutConfirmModal(targetUrl) {
    let modal = document.getElementById('globalLogoutModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'globalLogoutModal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:999999;background:rgba(15,23,42,0.75);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:20px;';
        modal.innerHTML = `
            <div style="background:#ffffff;border-radius:20px;max-width:400px;width:100%;box-shadow:0 25px 60px -12px rgba(0,0,0,0.4);overflow:hidden;text-align:center;padding:32px 24px;font-family:'Plus Jakarta Sans','Inter',-apple-system,sans-serif;animation:logoutPop 0.25s cubic-bezier(0.16,1,0.3,1);">
                <div style="width:64px;height:64px;background:#fef2f2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;color:#ef4444;font-size:28px;border:2px solid #fecaca;box-shadow:0 6px 16px rgba(239,68,68,0.15);">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </div>
                <h3 style="margin:0 0 8px;font-size:20px;font-weight:800;color:#0f172a;letter-spacing:-0.4px;">Confirm Logout</h3>
                <p style="margin:0 0 24px;font-size:14px;color:#64748b;line-height:1.5;font-weight:500;">Are you sure you want to log out of your account?</p>
                <div style="display:flex;gap:12px;justify-content:center;">
                    <button id="cancelLogoutBtn" type="button" style="flex:1;padding:12px 18px;border:1.5px solid #cbd5e1;background:#f8fafc;color:#334155;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;transition:all 0.2s;">Cancel</button>
                    <button id="confirmLogoutBtn" type="button" style="flex:1;padding:12px 18px;border:none;background:linear-gradient(135deg,#ef4444,#dc2626);color:#ffffff;border-radius:10px;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 4px 14px rgba(239,68,68,0.35);transition:all 0.2s;">Yes, Logout</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById('cancelLogoutBtn').addEventListener('click', () => {
            modal.style.display = 'none';
        });

        modal.addEventListener('click', (ev) => {
            if (ev.target === modal) modal.style.display = 'none';
        });
    }

    modal.style.display = 'flex';

    const confirmBtn = document.getElementById('confirmLogoutBtn');
    confirmBtn.onclick = () => {
        window.location.href = targetUrl;
    };
}
