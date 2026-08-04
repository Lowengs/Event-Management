/* ── Extracted & API-connected for student/organization.php ── */
async function viewOrg(btn) {
    const d = btn.dataset;
    const orgId = d.orgid || 0;

    // Pop initial values from dataset
    document.getElementById('omName').textContent        = d.name    || '—';
    document.getElementById('omStatus').textContent      = d.status  || '—';
    document.getElementById('omStatusInner').textContent = d.status || '—';
    document.getElementById('omAdviser').textContent     = d.adviser || 'N/A';
    document.getElementById('omPresident').textContent   = d.president || 'N/A';
    document.getElementById('omMembers').textContent     = d.members || '0';
    document.getElementById('omEvents').textContent      = d.events  || '0';
    document.getElementById('omDesc').textContent        = d.desc    || 'No description available.';

    const img = document.getElementById('omLogo');
    if (img) {
        img.src = d.pic || '../../assets/img/philsca.png';
        img.onerror = () => img.src = '../../assets/img/philsca.png';
    }
    const hdr = document.getElementById('orgModalHeader');
    if (hdr) {
        hdr.style.backgroundImage = d.banner ? `url(${d.banner})` : '';
    }

    const veLink = document.getElementById('omViewEventsLink');
    if (veLink) {
        const orgParam = d.orgid || d.name || '';
        veLink.href = 'events.php?org=' + encodeURIComponent(orgParam);
    }

    document.getElementById('orgModal').style.display = 'flex';

    // Fetch live details via API Endpoint (Stored Procedure / Database)
    if (orgId) {
        try {
            const res = await fetch(`../../config/API/endpoints/index.php?action=get_organization_detail&org_id=${encodeURIComponent(orgId)}`);
            const json = await res.json();
            if (json.success && json.data) {
                const o = json.data;
                document.getElementById('omName').textContent        = o.OrgName || d.name || '—';
                document.getElementById('omStatus').textContent      = o.Status  || d.status || 'Active';
                document.getElementById('omStatusInner').textContent = o.Status  || d.status || 'Active';
                document.getElementById('omAdviser').textContent     = o.Adviser || d.adviser || 'N/A';
                document.getElementById('omPresident').textContent   = o.president_name || o.President || d.president || 'N/A';
                document.getElementById('omMembers').textContent     = o.member_count ?? d.members ?? '0';
                document.getElementById('omEvents').textContent      = o.event_count  ?? d.events  ?? '0';
                document.getElementById('omDesc').textContent        = o.Description || d.desc || 'No description available.';
                if (img && o.OrgPicture) {
                    const p = (o.OrgPicture.startsWith('http') || o.OrgPicture.startsWith('../../')) ? o.OrgPicture : '../../' + o.OrgPicture.replace(/^\/+/, '');
                    img.src = p;
                }
                if (hdr && o.OrgBanner) {
                    const b = (o.OrgBanner.startsWith('http') || o.OrgBanner.startsWith('../../')) ? o.OrgBanner : '../../' + o.OrgBanner.replace(/^\/+/, '');
                    hdr.style.backgroundImage = `url(${b})`;
                }
            }
        } catch (err) {
            // dataset fallback used above
        }
    }
}

function closeOrgModal() {
    const modal = document.getElementById('orgModal');
    if (modal) modal.style.display = 'none';
}

// Search filter
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.org-card');
    const counter = document.getElementById('orgCount');
    const searchInput = document.getElementById('orgSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            let v = 0;
            cards.forEach(c => {
                const match = !q || (c.dataset.name || '').includes(q) || (c.dataset.desc || '').includes(q);
                c.style.display = match ? '' : 'none';
                if (match) v++;
            });
            if (counter) counter.textContent = v;
        });
    }
});