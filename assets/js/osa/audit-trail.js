/**
 * audit-trail.js — OSA Audit Trail Details Viewer
 */

function showAuditDetails(data) {
    if (!data) return;
    const actor = document.getElementById('auditModalActor');
    const ip = document.getElementById('auditModalIp');
    const action = document.getElementById('auditModalAction');
    const status = document.getElementById('auditModalStatus');
    const date = document.getElementById('auditModalDate');
    const details = document.getElementById('auditModalDetails');
    const modal = document.getElementById('auditDetailsModal');

    if (actor) actor.textContent = data.actor || 'Unknown';
    if (ip) ip.textContent = data.ip || '127.0.0.1';
    if (action) action.textContent = data.action || 'Log Event';
    if (status) status.textContent = (data.status || 'success').toUpperCase();
    if (date) date.textContent = data.date || '—';
    if (details) details.textContent = typeof data.details === 'object' ? JSON.stringify(data.details, null, 2) : data.details;
    if (modal) modal.style.display = 'flex';
}
