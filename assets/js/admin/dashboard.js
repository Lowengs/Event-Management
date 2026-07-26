
    (function(){
      const btn = document.getElementById('hamburger-btn');
      const nav = document.getElementById('sidebar');

      if(!btn || !nav) return;

      btn.addEventListener('click', () => {
        nav.classList.toggle('active');
      });

    
      document.addEventListener('click', (e) => {
        const isMobile = window.matchMedia('(max-width: 1024px)').matches;
        if(!isMobile) return;
        if(nav.classList.contains('active') && !nav.contains(e.target) && e.target !== btn && !btn.contains(e.target)){
          nav.classList.remove('active');
        }
      });
    })();





