/**
 * audit-trail.js — Admin Audit Trail Modal Viewer
 */

function showDetails(btn) {
    const actor   = btn.getAttribute('data-actor')   || '—';
    const action  = btn.getAttribute('data-action')  || '—';
    const status  = btn.getAttribute('data-status')  || 'success';
    const date    = btn.getAttribute('data-date')    || '—';
    const ip      = btn.getAttribute('data-ip')      || '127.0.0.1';
    const raw     = btn.getAttribute('data-details') || '';

    let formattedDetails = raw;
    try {
        const obj = JSON.parse(raw);
        formattedDetails = JSON.stringify(obj, null, 2);
    } catch (e) {}

    const text = `User / Actor: ${actor}
IP Address  : ${ip}
Action      : ${action}
Status      : ${status.toUpperCase()}
Timestamp   : ${date}

--- Metadata & Details ---
${formattedDetails}`;

    const content = document.getElementById('detailsContent');
    const modal = document.getElementById('detailsModal');
    if (content) content.textContent = text;
    if (modal) modal.classList.add('open');
}

window.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('detailsModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) this.classList.remove('open');
        });
    }
});
