console.log("INDEX JS LOADED");

function initMenu() {
  const hamburgerBtn = document.getElementById('hamburger-btn');
  const navMobile = document.querySelector('.nav-mobile');

  if (hamburgerBtn && navMobile) {
    hamburgerBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      navMobile.classList.toggle('active');
    });

    document.addEventListener('click', (e) => {
      if (!navMobile.contains(e.target) && !hamburgerBtn.contains(e.target)) {
        navMobile.classList.remove('active');
      }
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMenu);
} else {
  initMenu();
}

async function indexViewOrg(btn) {
  console.log("========== View Details Clicked ==========");

  const d = btn.dataset;
  console.log("Dataset:", d);

  const orgId = d.orgid || 0;
  console.log("Organization ID:", orgId);

  const nameEl = document.getElementById('indexOmName');
  const statusEl = document.getElementById('indexOmStatus');
  const membersEl = document.getElementById('indexOmMembers');
  const eventsEl = document.getElementById('indexOmEvents');
  const adviserEl = document.getElementById('indexOmAdviserSm');
  const descEl = document.getElementById('indexOmDesc');
  const modal = document.getElementById('indexOrgModal');

  if (nameEl) nameEl.textContent = d.name || '—';
  if (statusEl) statusEl.textContent = 'Status: ' + (d.status || 'Active');
  if (membersEl) membersEl.textContent = d.members || '0';
  if (eventsEl) eventsEl.textContent = d.events || '0';
  if (adviserEl) adviserEl.textContent = d.adviser || 'N/A';
  if (descEl) descEl.textContent = d.desc || 'No description available.';

  const img = document.getElementById('indexOmLogo');
  if (img) {
    img.src = d.logo || '../assets/img/philsca.png';
    img.onerror = () => img.src = '../assets/img/philsca.png';
  }

  const hdr = document.getElementById('indexOrgModalHdr');
  if (hdr) {
    hdr.style.backgroundImage = d.banner ? `url(${d.banner})` : '';
  }

  const veBtn = document.getElementById('indexOmViewEventsBtn');
  if (veBtn) {
    const orgParam = d.orgid || d.name || '';
    veBtn.href = 'student/events.php?org=' + encodeURIComponent(orgParam);
  }

  if (modal) modal.style.display = 'flex';

  if (orgId) {
    try {
      const url = `../config/API/endpoints/index.php?action=get_organization_detail&org_id=${encodeURIComponent(orgId)}`;

      console.log("Fetching URL:", url);

      const res = await fetch(url);

      console.log("HTTP Status:", res.status);
      console.log("Content-Type:", res.headers.get("content-type"));

      const text = await res.text();

      console.log("========== API Response ==========");
      console.log(text);
      console.log("==================================");

      const json = JSON.parse(text);

      console.log("Parsed JSON:", json);

      if (json.success && json.data) {
        const o = json.data;

        if (nameEl) nameEl.textContent = o.OrgName || d.name || '—';
        if (statusEl) statusEl.textContent = 'Status: ' + (o.Status || d.status || 'Active');
        if (membersEl) membersEl.textContent = o.member_count ?? d.members ?? '0';
        if (eventsEl) eventsEl.textContent = o.event_count ?? d.events ?? '0';
        if (adviserEl) adviserEl.textContent = o.Adviser || d.adviser || 'N/A';
        if (descEl) descEl.textContent = o.Description || d.desc || 'No description available.';

        if (img && o.OrgPicture) {
          const p = (o.OrgPicture.startsWith('http') || o.OrgPicture.startsWith('../'))
            ? o.OrgPicture
            : '../' + o.OrgPicture.replace(/^\/+/, '');

          img.src = p;
        }

        if (hdr && o.OrgBanner) {
          const b = (o.OrgBanner.startsWith('http') || o.OrgBanner.startsWith('../'))
            ? o.OrgBanner
            : '../' + o.OrgBanner.replace(/^\/+/, '');

          hdr.style.backgroundImage = `url(${b})`;
        }
      } else {
        console.warn("API returned success = false", json);
      }

    } catch (err) {
      console.error("Fetch Error:", err);
    }
  } else {
    console.warn("Organization ID is missing!");
  }
}

function closeIndexOrgModal() {
  const modal = document.getElementById('indexOrgModal');
  if (modal) modal.style.display = 'none';
}

function closeIndexEventModal() {
  const modal = document.getElementById('indexEventModal');
  if (modal) modal.style.display = 'none';
}

function closeLogoutModal() {
  const modal = document.getElementById('logoutModal');
  if (modal) modal.style.display = 'none';
}

window.addEventListener('DOMContentLoaded', () => {
  const orgModal = document.getElementById('indexOrgModal');
  if (orgModal) {
    orgModal.addEventListener('click', (e) => {
      if (e.target === orgModal) closeIndexOrgModal();
    });
  }

  const eventModal = document.getElementById('indexEventModal');
  if (eventModal) {
    eventModal.addEventListener('click', (e) => {
      if (e.target === eventModal) closeIndexEventModal();
    });
  }

  const params = new URLSearchParams(window.location.search);

  if (params.get('logout') === 'success') {
    const logoutModal = document.getElementById('logoutModal');

    if (logoutModal) {
      logoutModal.style.display = 'flex';
    }

    window.history.replaceState({}, document.title, window.location.pathname);
  }
});

window.indexViewOrg = indexViewOrg;