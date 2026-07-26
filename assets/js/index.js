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


/* Extracted from index.php */
function indexViewOrg(btn) {
      const d = btn.dataset;
      document.getElementById('indexOmName').textContent    = d.name    || '—';
      document.getElementById('indexOmStatus').textContent  = 'Status: ' + (d.status  || 'Active');
      document.getElementById('indexOmMembers').textContent = d.members || '0';
      document.getElementById('indexOmEvents').textContent  = d.events  || '0';
      document.getElementById('indexOmAdviserSm').textContent = d.adviser || 'N/A';
      document.getElementById('indexOmDesc').textContent    = d.desc    || 'No description available.';
      const img = document.getElementById('indexOmLogo');
      img.src = d.logo || '../assets/img/philsca.png';
      img.onerror = () => img.src = '../assets/img/philsca.png';
      const hdr = document.getElementById('indexOrgModalHdr');
      hdr.style.backgroundImage = d.banner ? `url(${d.banner})` : '';
      document.getElementById('indexOrgModal').style.display = 'flex';
    }