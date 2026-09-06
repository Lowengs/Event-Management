/**
 * OSA Portal: Audit Trail Inspector Modal Handler
 */
function showAuditDetails(data) {
    if (!data) return;
    const actor       = document.getElementById('auditModalActor');
    const userType    = document.getElementById('auditModalUserType');
    const module      = document.getElementById('auditModalModule');
    const description = document.getElementById('auditModalDescription');
    const ip          = document.getElementById('auditModalIp');
    const device      = document.getElementById('auditModalDevice');
    const browser     = document.getElementById('auditModalBrowser');
    const location    = document.getElementById('auditModalLocation');
    const action      = document.getElementById('auditModalAction');
    const status      = document.getElementById('auditModalStatus');
    const date        = document.getElementById('auditModalDate');
    const details     = document.getElementById('auditModalDetails');
    const modal       = document.getElementById('auditDetailsModal');

    if (actor)       actor.textContent = data.actor || 'Unknown';
    if (userType)    userType.textContent = (data.type || 'system').toUpperCase();
    if (module)      module.textContent = data.module || 'General';
    if (description) description.textContent = data.description || 'No detailed description available.';
    if (ip)          ip.textContent = data.ip || '127.0.0.1';
    if (device)      device.textContent = data.device || 'Windows (Desktop)';
    if (browser)     browser.textContent = data.browser || 'Browser';
    if (location)    location.textContent = data.location || 'Localhost / Campus Network';
    if (action)      action.textContent = data.action || 'Log Event';
    if (status) {
        status.textContent = (data.status || 'success').toUpperCase();
        status.style.color = (data.status || '').toLowerCase() === 'failed' ? '#ef4444' : '#16a34a';
    }
    if (date)        date.textContent = data.date || '—';
    if (details)     details.textContent = typeof data.details === 'object' ? JSON.stringify(data.details, null, 2) : data.details;
    if (modal)       modal.style.display = 'flex';
}
window.showAuditDetails = showAuditDetails;

window.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('auditDetailsModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) this.style.display = 'none';
        });
    }
});