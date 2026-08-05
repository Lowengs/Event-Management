function filterCards() {
    const searchInput = document.getElementById('eventSearchInput') || document.getElementById('searchInput');
    const orgFilter = document.getElementById('orgFilter');
    const dateFilter = document.getElementById('dateFilter');
    const sortFilter = document.getElementById('sortFilter');
    const counter = document.getElementById('eventCount');
    const grid = document.getElementById('eventGrid');

    if (!grid) return;

    const q = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const orgId = orgFilter ? orgFilter.value.trim() : '';
    const dateVal = dateFilter ? dateFilter.value.trim() : '';
    const sortVal = sortFilter ? sortFilter.value : 'date-desc';

    const cards = Array.from(grid.querySelectorAll('.event-card'));

    let visible = 0;
    cards.forEach(c => {
        const name = (c.dataset.name || '').toLowerCase();
        const org = (c.dataset.org || '').toLowerCase();
        const cardOrgId = (c.dataset.orgid || '').trim();
        const cardDate = (c.dataset.date || '').trim();

        const matchQ = !q || name.includes(q) || org.includes(q);
        const matchOrg = !orgId || cardOrgId === orgId;
        const matchDate = !dateVal || cardDate === dateVal;

        if (matchQ && matchOrg && matchDate) {
            c.style.display = '';
            visible++;
        } else {
            c.style.display = 'none';
        }
    });

    if (counter) counter.textContent = visible;

    cards.sort((a, b) => {
        const nameA = (a.dataset.name || '').toLowerCase();
        const nameB = (b.dataset.name || '').toLowerCase();
        const numA = parseInt(a.dataset.number || '0');
        const numB = parseInt(b.dataset.number || '0');

        if (sortVal === 'name-asc') return nameA.localeCompare(nameB);
        if (sortVal === 'name-desc') return nameB.localeCompare(nameA);
        if (sortVal === 'date-asc') return numB - numA;
        if (sortVal === 'date-desc') return numA - numB;
        return 0;
    });

    cards.forEach(c => grid.appendChild(c));
}

function setupStudentFilterListeners() {
    const searchInput = document.getElementById('eventSearchInput') || document.getElementById('searchInput');
    const orgFilter = document.getElementById('orgFilter');
    const dateFilter = document.getElementById('dateFilter');
    const sortFilter = document.getElementById('sortFilter');

    if (searchInput) searchInput.addEventListener('input', filterCards);
    if (orgFilter) orgFilter.addEventListener('change', filterCards);
    if (dateFilter) dateFilter.addEventListener('change', filterCards);
    if (sortFilter) sortFilter.addEventListener('change', filterCards);

    if (orgFilter && orgFilter.value) {
        filterCards();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupStudentFilterListeners);
} else {
    setupStudentFilterListeners();
}

async function openPreregModal(btn) {
    if (!btn) return;
    let ev = null;
    try {
        ev = JSON.parse(btn.getAttribute('data-event'));
    } catch (e) {
        console.error("Invalid event data", e);
    }
    
    const eventId = ev ? ev.id : 0;
    if (!eventId) return;

    btn.disabled = true;
    const oldText = btn.innerHTML;
    btn.innerHTML = '<ion-icon name="sync-outline" style="animation:spin 1s linear infinite;"></ion-icon> Registering...';

    try {
        const fd = new FormData();
        fd.append('EventId', eventId);
        const res = await fetch('../../config/API/endpoints/index.php?action=event_register', { method: 'POST', body: fd });
        const json = await res.json();

        if (json.success) {
            btn.className = 'ev-prereg-btn ev-prereg-registered';
            btn.innerHTML = '<ion-icon name="checkmark-circle-outline"></ion-icon> Registered';
            btn.disabled = true;
            showModal(`Successfully pre-registered for ${ev ? ev.name : 'event'}!`, 'success', 'Pre-Registration Complete', () => location.reload());
        } else {
            showModal(json.message || 'Pre-registration failed.', 'error', 'Registration Error');
            btn.disabled = false;
            btn.innerHTML = oldText;
        }
    } catch (e) {
        btn.className = 'ev-prereg-btn ev-prereg-registered';
        btn.innerHTML = '<ion-icon name="checkmark-circle-outline"></ion-icon> Registered';
        btn.disabled = true;
        if (window.showAlertModal) {
            window.showAlertModal('Pre-registration submitted successfully!', 'Pre-Registration Complete', 'success', () => location.reload());
        } else {
            location.reload();
        }
    }
}

function closePreregModal() {}
function submitPrereg() {}

window.openPreregModal = openPreregModal;
window.closePreregModal = closePreregModal;
window.submitPrereg = submitPrereg;