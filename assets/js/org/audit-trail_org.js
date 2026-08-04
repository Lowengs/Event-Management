/* ── Extracted from organization/audit-trail_org.php ── */
/* ── Audit Trail Pagination (25/page) ───────────────────────── */
const AUDIT_PAGE_SIZE = 25;
let auditCurrentPage  = 1;
let auditFilteredRows = [];

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

function getAllRows() {
    return Array.from(document.querySelectorAll('#auditLogBody tr')).filter(r => r.children.length > 2);
}

function applyAuditSearch() {
    const q   = (document.getElementById('auditSearch') || {}).value || '';
    const cat = (document.getElementById('auditCategoryFilter') || {}).value || '';
    const query = q.toLowerCase().trim();
    const category = cat.toLowerCase();
    auditFilteredRows = getAllRows().filter(row => {
        const text = row.textContent.toLowerCase();
        return (!query || text.includes(query)) && (!category || text.includes(category));
    });
    auditCurrentPage = 1;
    renderAuditPage();
}

function renderAuditPage() {
    const total     = auditFilteredRows.length;
    const totalPages = Math.max(1, Math.ceil(total / AUDIT_PAGE_SIZE));
    const start     = (auditCurrentPage - 1) * AUDIT_PAGE_SIZE;
    const end       = Math.min(start + AUDIT_PAGE_SIZE, total);

    // Hide all rows
    getAllRows().forEach(r => r.style.display = 'none');
    // Show filtered+paged rows
    auditFilteredRows.slice(start, end).forEach(r => r.style.display = '');

    // Update info
    const info = document.getElementById('auditPageInfo');
    if (info) {
        if (total === 0) {
            info.innerHTML = 'No entries found';
        } else {
            info.innerHTML = `Showing <strong>${start + 1}–${end}</strong> of <strong>${total}</strong> entries`;
        }
    }

    // Build page controls
    const ctrl = document.getElementById('auditPageControls');
    if (!ctrl) return;
    ctrl.innerHTML = '';

    const makeBtn = (label, page, isActive, isDisabled) => {
        const btn = document.createElement('button');
        btn.className = 'page-btn' + (isActive ? ' active' : '');
        btn.disabled = isDisabled;
        btn.textContent = label;
        btn.addEventListener('click', () => {
            auditCurrentPage = page;
            renderAuditPage();
        });
        return btn;
    };

    ctrl.appendChild(makeBtn('‹ Prev', auditCurrentPage - 1, false, auditCurrentPage === 1));

    // Show max 5 page buttons around current
    const pageWindow = 2;
    for (let p = Math.max(1, auditCurrentPage - pageWindow); p <= Math.min(totalPages, auditCurrentPage + pageWindow); p++) {
        ctrl.appendChild(makeBtn(p, p, p === auditCurrentPage, false));
    }

    ctrl.appendChild(makeBtn('Next ›', auditCurrentPage + 1, false, auditCurrentPage >= totalPages));
}

// Init on load
window.addEventListener('DOMContentLoaded', () => {
    const searchEl = document.getElementById('auditSearch');
    if (searchEl) searchEl.addEventListener('input', applyAuditSearch);

    const catEl = document.getElementById('auditCategoryFilter');
    if (catEl) catEl.addEventListener('change', applyAuditSearch);

    auditFilteredRows = getAllRows();
    renderAuditPage();
});