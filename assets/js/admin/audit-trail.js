/**
 * Admin Portal: System Audit Trail Inspector
 */

function showDetails(btn) {
    const actor = btn.getAttribute('data-actor') || '—';
    const actorType = (btn.getAttribute('data-actortype') || 'system').toUpperCase();
    const action = btn.getAttribute('data-action') || '—';
    const status = (btn.getAttribute('data-status') || 'success').toUpperCase();
    const date = btn.getAttribute('data-date') || '—';
    const ip = btn.getAttribute('data-ip') || '127.0.0.1';
    const device = btn.getAttribute('data-device') || 'Desktop';
    const browser = btn.getAttribute('data-browser') || 'Browser';
    const location = btn.getAttribute('data-location') || 'Local Network';
    const rawDetails = btn.getAttribute('data-details') || '';

    let formattedJson = '';
    let parsedObj = null;
    if (rawDetails) {
        try {
            parsedObj = JSON.parse(rawDetails);
            formattedJson = JSON.stringify(parsedObj, null, 2);
        } catch (e) {
            formattedJson = rawDetails;
        }
    }

    const isSuccess = status.toLowerCase() === 'success';
    const statusBg = isSuccess ? '#dcfce7' : '#fee2e2';
    const statusColor = isSuccess ? '#166534' : '#991b1b';

    const html = `
        <div style="display:flex;flex-direction:column;gap:16px;font-family:'Inter',sans-serif;">
            <!-- Summary Grid -->
            <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:12px;background:#f8fafc;padding:16px;border-radius:14px;border:1px solid #e2e8f0;">
                <div>
                    <span style="font-size:0.72rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Action</span>
                    <div style="font-size:0.95rem;font-weight:800;color:#0f172a;margin-top:2px;">${action}</div>
                </div>
                <div>
                    <span style="font-size:0.72rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Status</span>
                    <div style="margin-top:2px;">
                        <span style="background:${statusBg};color:${statusColor};padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:800;display:inline-block;">${status}</span>
                    </div>
                </div>
                <div>
                    <span style="font-size:0.72rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Actor / User</span>
                    <div style="font-size:0.9rem;font-weight:700;color:#2563eb;margin-top:2px;">
                        ${actor} <span style="font-size:0.72rem;color:#64748b;font-weight:600;">(${actorType})</span>
                    </div>
                </div>
                <div>
                    <span style="font-size:0.72rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">Timestamp</span>
                    <div style="font-size:0.85rem;color:#334155;margin-top:2px;font-weight:600;">${date}</div>
                </div>
            </div>

            <!-- Network, Device & Location Info -->
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:14px;padding:16px;">
                <div style="font-size:0.75rem;font-weight:800;color:#0284c7;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                    <ion-icon name="globe-outline"></ion-icon> Network, Device & Location Details
                </div>
                <div style="display:grid;grid-template-columns:repeat(2, 1fr);gap:10px;font-size:0.84rem;">
                    <div>
                        <span style="color:#64748b;font-size:0.74rem;display:block;">IP Address</span>
                        <strong style="color:#0f172a;font-family:monospace;">${ip}</strong>
                    </div>
                    <div>
                        <span style="color:#64748b;font-size:0.74rem;display:block;">Location</span>
                        <strong style="color:#0f172a;">${location}</strong>
                    </div>
                    <div>
                        <span style="color:#64748b;font-size:0.74rem;display:block;">Device / OS</span>
                        <strong style="color:#2563eb;">${device}</strong>
                    </div>
                    <div>
                        <span style="color:#64748b;font-size:0.74rem;display:block;">Browser</span>
                        <strong style="color:#0f172a;">${browser}</strong>
                    </div>
                </div>
            </div>

            ${formattedJson ? `
            <div>
                <span style="font-size:0.74rem;color:#64748b;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;display:block;margin-bottom:6px;">Additional Metadata Payload</span>
                <pre style="background:#0f172a;color:#38bdf8;padding:14px;border-radius:12px;font-size:0.78rem;font-family:monospace;white-space:pre-wrap;word-break:break-all;max-height:200px;overflow-y:auto;margin:0;border:1px solid #1e293b;">${htmlspecialchars(formattedJson)}</pre>
            </div>
            ` : ''}
        </div>
    `;

    const body = document.getElementById('detailsContent');
    const modal = document.getElementById('detailsModal');
    if (body) body.innerHTML = html;
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('open');
    }
}
window.showDetails = showDetails;

window.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('detailsModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
                this.classList.remove('open');
            }
        });
    }
});

function htmlspecialchars(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}