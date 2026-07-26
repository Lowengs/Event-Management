
function filterCards() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const sortFilter = document.getElementById('sortFilter');
    const counter = document.getElementById('eventCount');
    const grid = document.getElementById('eventGrid');

    if (!grid) return;

    const q = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const st = statusFilter ? statusFilter.value.toLowerCase().trim() : '';
    const sortVal = sortFilter ? sortFilter.value : 'date-desc';

    const cards = Array.from(grid.querySelectorAll('.event-card'));

    let visible = 0;
    cards.forEach(c => {
        const name = (c.dataset.name || '').toLowerCase();
        const org = (c.dataset.org || '').toLowerCase();
        const status = (c.dataset.status || '').toLowerCase();

        const matchQ = !q || name.includes(q) || org.includes(q);
        const matchSt = !st || status === st;

        if (matchQ && matchSt) {
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
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const sortFilter = document.getElementById('sortFilter');

    if (searchInput) searchInput.addEventListener('input', filterCards);
    if (statusFilter) statusFilter.addEventListener('change', filterCards);
    if (sortFilter) sortFilter.addEventListener('change', filterCards);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupStudentFilterListeners);
} else {
    setupStudentFilterListeners();
}

let _currentEvent = null;

function openPreregModal(btn) {
    const ev = JSON.parse(btn.getAttribute('data-event'));
    _currentEvent = ev;

    document.getElementById('modalEventName').textContent = ev.name;
    document.getElementById('modalOrgName').textContent = 'by ' + ev.org;
    document.getElementById('modalMonth').textContent = ev.month;
    document.getElementById('modalDay').textContent = ev.day;
    document.getElementById('modalDate').textContent = ev.date;
    document.getElementById('modalMeta').textContent = ev.time + ' · ' + ev.place;

    clearMsg();

    if (ev.isReg) {
        showRegisteredState(ev);
    } else {
        showRegisterStep(ev);
    }

    document.getElementById('preregOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closePreregModal() {
    document.getElementById('preregOverlay').classList.remove('open');
    document.body.style.overflow = '';
}

document.getElementById('preregOverlay').addEventListener('click', function (e) {
    if (e.target === this) closePreregModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePreregModal(); });

function setStep(active) {
    [1, 2, 3].forEach(i => {
        const el = document.getElementById('step' + i + 'Indicator');
        if (el) {
            el.classList.remove('active', 'done');
            if (i < active) el.classList.add('done');
            else if (i === active) el.classList.add('active');
        }
    });
}

function clearMsg() {
    const m = document.getElementById('modalMsg');
    m.className = 'modal-msg'; m.textContent = '';
}
function showMsg(text, type) {
    const m = document.getElementById('modalMsg');
    m.textContent = text; m.className = 'modal-msg ' + type;
}

const preQsDef = [
    { q: '1. What is the primary purpose of this type of event?', opts: { a: 'Academic / Professional Development', b: 'Entertainment only', c: 'Fundraising', d: 'Sports competition' } },
    { q: '2. Which organization is hosting this event?', opts: { a: 'HOST_ORG', b: 'OSA Office', c: 'University Admin', d: 'External Group' } },
    { q: '3. What is the event mode?', opts: { a: 'EVENT_MODE', b: 'Fully online only', c: 'Correspondence only', d: 'Not yet decided' } },
    { q: '4. Who is the primary intended audience?', opts: { a: 'Faculty only', b: 'External visitors', c: 'Student organization members / general students', d: 'Alumni only' } },
    { q: '5. Where will the event be held?', opts: { a: 'EVENT_PLACE', b: 'Main Library', c: 'Online platform only', d: 'No venue confirmed' } },
];

function showPreTestStep(ev) {
    setStep(1);
    document.getElementById('step2Content').style.display = 'none';
    document.getElementById('step3Content').style.display = 'none';

    const questions = preQsDef.map(pq => {
        const opts = { ...pq.opts };
        if (opts.a === 'HOST_ORG') opts.a = ev.org;
        if (opts.a === 'EVENT_MODE') opts.a = ev.mode || 'On-site';
        if (opts.a === 'EVENT_PLACE') opts.a = ev.place || 'TBA';
        return { q: pq.q, opts };
    });

    let html = `<p style="color:#94a3b8;font-size:12px;margin:0 0 14px;">Complete this quick 5-question pre-assessment before registering.</p>
            <form id="modalPreForm">
            <input type="hidden" name="EventId" value="${ev.id}">`;
    questions.forEach((pq, qi) => {
        const qn = qi + 1;
        html += `<div class="modal-qa"><p class="q-text">${escHtml(pq.q)}</p><div class="modal-opts">`;
        Object.entries(pq.opts).forEach(([k, v]) => {
            html += `<label class="modal-opt"><input type="radio" name="q${qn}" value="${k}" required>
                    <span>${k.toUpperCase()}. ${escHtml(v)}</span></label>`;
        });
        html += `</div></div>`;
    });
    html += `<button type="submit" class="modal-action-btn primary" id="preSubmitBtn">
            <ion-icon name="document-text-outline"></ion-icon> Submit Pre-Test
        </button></form>`;

    const s1 = document.getElementById('step1Content');
    s1.innerHTML = html;
    s1.style.display = '';

    s1.querySelectorAll('.modal-opts').forEach(group => {
        group.querySelectorAll('.modal-opt').forEach(lbl => {
            lbl.addEventListener('click', () => {
                group.querySelectorAll('.modal-opt').forEach(l => l.classList.remove('sel'));
                lbl.classList.add('sel');
            });
        });
    });

    document.getElementById('modalPreForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('preSubmitBtn');
        btn.textContent = 'Submitting…'; btn.disabled = true;
        const fd = new FormData(document.getElementById('modalPreForm'));
        try {
            const r = await fetch('../../config/API/submit_pretest.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                window.location.href = `test_results.php?event_id=${ev.id}&type=pre`;
            } else {
                showMsg(d.message || 'Submission error. Try again.', 'error');
                btn.textContent = 'Submit Pre-Test'; btn.disabled = false;
            }
        } catch (err) {
            showMsg('Network error. Please try again.', 'error');
            btn.textContent = 'Submit Pre-Test'; btn.disabled = false;
        }
    });
}

function showRegisterStep(ev) {
    setStep(2);
    document.getElementById('step1Content').style.display = 'none';
    document.getElementById('step3Content').style.display = 'none';

    document.getElementById('step2Content').innerHTML = `
        <div style="text-align:center;padding:8px 0 4px;">
            <div style="font-size:44px;margin-bottom:8px;color:#10b981;"><ion-icon name="checkmark-circle-outline"></ion-icon></div>
            <p style="color:#94a3b8;font-size:13px;margin:0 0 18px;">Confirm your registration below.</p>
            <button class="modal-action-btn success" id="confirmRegBtn" style="max-width:320px;margin:0 auto;">
                <ion-icon name="person-add-outline"></ion-icon> Confirm Registration
            </button>
        </div>`;
    document.getElementById('step2Content').style.display = '';

    document.getElementById('confirmRegBtn').addEventListener('click', async () => {
        const btn = document.getElementById('confirmRegBtn');
        btn.textContent = 'Registering…'; btn.disabled = true;
        const fd = new FormData(); fd.append('EventId', ev.id);
        try {
            const r = await fetch('../../config/API/event_register.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (d.success) {
                _currentEvent.isReg = true;
                showRegisteredState(ev);
                updateCardBtn(ev.id);
            } else {
                showMsg(d.message || 'Registration failed.', 'error');
                btn.textContent = 'Confirm Registration'; btn.disabled = false;
            }
        } catch (err) {
            showMsg('Network error. Please try again.', 'error');
            btn.textContent = 'Confirm Registration'; btn.disabled = false;
        }
    });
}

function showRegisteredState(ev) {
    setStep(3);
    document.getElementById('step1Content').style.display = 'none';
    document.getElementById('step2Content').style.display = 'none';

    document.getElementById('step3Content').innerHTML = `
            <div class="registered-badge">
                <ion-icon name="checkmark-circle-outline" style="font-size:22px;"></ion-icon>
                You are registered for this event!
            </div>
            <a class="view-detail-link" href="event_detail.php?id=${ev.id}">
                View full event details & post-test →
            </a>`;
    document.getElementById('step3Content').style.display = '';
}

function updateCardBtn(eventId) {
    document.querySelectorAll('[data-event]').forEach(btn => {
        try {
            const ev = JSON.parse(btn.getAttribute('data-event'));
            if (ev.id === eventId) {
                btn.outerHTML = `<button class="ev-prereg-btn ev-prereg-registered" disabled>
                        <ion-icon name="checkmark-circle-outline"></ion-icon> Registered
                    </button>`;
            }
        } catch (e) { }
    });
}

function escHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}