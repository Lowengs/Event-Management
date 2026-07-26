document.addEventListener('DOMContentLoaded', function () {
  const sidebar      = document.getElementById('sidebar');
  const overlay      = document.getElementById('sidebarOverlay');
  const hamburgerBtn = document.getElementById('hamburgerBtn');

  if (sidebar && overlay && hamburgerBtn) {
    hamburgerBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.toggle('open');
      overlay.classList.toggle('show');
    });

    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth > 900) {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
      }
    });
  }
});

/* -- Sidebar logout modal -- */
(function(){
  var btn    = document.getElementById('logoutBtn');
  var modal  = document.getElementById('logoutModal');
  var cancel = document.getElementById('logoutCancelBtn');
  if (btn)    btn.addEventListener('click',    function(){ modal.style.display='flex'; });
  if (cancel) cancel.addEventListener('click', function(){ modal.style.display='none'; });
  if (modal)  modal.addEventListener('click',  function(e){ if(e.target===modal) modal.style.display='none'; });
})();