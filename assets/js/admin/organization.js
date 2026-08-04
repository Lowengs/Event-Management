/* ── Extracted from osa/organization.php ── */
// ── View toggle ──
    function setView(v) {
      document.getElementById('cardsView').style.display = v === 'cards' ? '' : 'none';
      document.getElementById('tableView').style.display = v === 'table'  ? '' : 'none';
      document.getElementById('cardsViewBtn').classList.toggle('active-view', v === 'cards');
      document.getElementById('tableViewBtn').classList.toggle('active-view', v === 'table');
    }

    // ── Client-side filter ──
    function filterOrgs() {
      const q      = document.getElementById('orgSearch').value.trim().toLowerCase();
      const status = document.getElementById('statusFilter').value.toLowerCase();

      // Filter cards
      document.querySelectorAll('#orgCardGrid .org-card').forEach(card => {
        const nameMatch   = card.dataset.name.includes(q);
        const statusMatch = status === 'all' || card.dataset.status === status;
        card.style.display = (nameMatch && statusMatch) ? '' : 'none';
      });

      // Filter table rows
      document.querySelectorAll('#orgDataTable tbody tr[data-name]').forEach(row => {
        const nameMatch   = row.dataset.name.includes(q);
        const statusMatch = status === 'all' || row.dataset.status === status;
        row.style.display = (nameMatch && statusMatch) ? '' : 'none';
      });
    }

    function viewOrgDetails(orgId) {
      const card = document.querySelector(`.org-card[data-orgid="${orgId}"]`);
      const m = document.getElementById('orgDetailModal');
      if (!card || !m) return;
      window.currentModalOrgId = orgId;
      const statusVal = (card.dataset.status || 'active').toLowerCase();
      window.currentModalOrgStatus = statusVal;

      m.querySelector('#mdOrgName').textContent        = card.dataset.name        || '—';
      m.querySelector('#mdOrgType').textContent        = card.dataset.type        || '—';
      m.querySelector('#mdOrgStatus').textContent      = card.dataset.status      || '—';
      m.querySelector('#mdOrgAdviser').textContent     = card.dataset.adviser     || '—';
      m.querySelector('#mdOrgRegistered').textContent  = card.dataset.registered  || '—';
      m.querySelector('#mdOrgPresident').textContent   = card.dataset.president   || '—';
      m.querySelector('#mdOrgVp').textContent          = card.dataset.vp          || '—';
      m.querySelector('#mdOrgMembers').textContent     = card.dataset.members     || '0';
      m.querySelector('#mdOrgOfficers').textContent    = card.dataset.officers    || '0';
      m.querySelector('#mdOrgDesc').textContent        = card.dataset.desc        || 'No description available.';
      const logoSrc   = card.dataset.logo;
      m.querySelector('#mdOrgLogo').src = logoSrc || '../../assets/img/philsca.png';
      
      const btn = m.querySelector('#modalToggleStatusBtn');
      if (btn) {
        if (statusVal === 'active') {
          btn.textContent = 'Deactivate Organization';
          btn.style.background = '#fee2e2';
          btn.style.color = '#dc2626';
        } else {
          btn.textContent = 'Activate Organization';
          btn.style.background = '#dcfce7';
          btn.style.color = '#16a34a';
        }
      }

      m.style.display = 'flex';
    }
    function closeOrgModal() {
      document.getElementById('orgDetailModal').style.display = 'none';
    }
      
      // Ensure the modal buttons work after DOM load
      document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('closeOrgModal')?.addEventListener('click', closeOrgModal);
        document.getElementById('closeOrgModalBottom')?.addEventListener('click', closeOrgModal);
        document.getElementById('orgDetailModal')?.addEventListener('click', function(e){
          if (e.target === this) this.style.display = 'none';
        });
      });