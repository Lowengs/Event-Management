/* ── Extracted from student/organization.php ── */
function viewOrg(btn) {
        const d = btn.dataset;
        document.getElementById('omName').textContent      = d.name    || '—';
        document.getElementById('omStatus').textContent    = d.status  || '—';
        document.getElementById('omStatusInner').textContent = d.status || '—';
        document.getElementById('omAdviser').textContent   = d.adviser || 'N/A';
        document.getElementById('omPresident').textContent = d.president || 'N/A';
        document.getElementById('omMembers').textContent   = d.members || '0';
        document.getElementById('omEvents').textContent    = d.events  || '0';
        document.getElementById('omDesc').textContent      = d.desc    || 'No description available.';
        const img = document.getElementById('omLogo');
        img.src = d.pic || '../../assets/img/philsca.png';
        img.onerror = () => img.src = '../../assets/img/philsca.png';
        const hdr = document.getElementById('orgModalHeader');
        hdr.style.backgroundImage = d.banner ? `url(${d.banner})` : '';
        document.getElementById('orgModal').style.display = 'flex';
    }
    function closeOrgModal() { document.getElementById('orgModal').style.display = 'none'; }

    // Search filter
    const cards = document.querySelectorAll('.org-card');
    const counter = document.getElementById('orgCount');
    document.getElementById('orgSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        let v = 0;
        cards.forEach(c => {
            const match = !q || c.dataset.name.includes(q) || c.dataset.desc.includes(q);
            c.style.display = match ? '' : 'none';
            if (match) v++;
        });
        if (counter) counter.textContent = v;
    });