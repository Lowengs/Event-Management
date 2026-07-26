/* ── Extracted from osa/dashboard_final.php ── */
function showAllNotifsModal(e) {
        if(e) e.preventDefault();
        document.getElementById('allNotifsModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeAllNotifsModal() {
        document.getElementById('allNotifsModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    function showEventModal(el) {
        document.getElementById('mName').textContent = el.getAttribute('data-name');
        document.getElementById('mTime').textContent = el.getAttribute('data-time');
        document.getElementById('mOrg').textContent  = el.getAttribute('data-org');
        document.getElementById('mLoc').textContent  = el.getAttribute('data-loc');
        document.getElementById('mDesc').textContent = el.getAttribute('data-desc');
        document.getElementById('eventModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeEventModal() {
        document.getElementById('eventModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    function showNotifModal(el) {
        document.getElementById('nTitle').textContent = el.getAttribute('data-title');
        document.getElementById('nOrg').textContent   = el.getAttribute('data-org');
        document.getElementById('nDate').textContent  = el.getAttribute('data-date');
        document.getElementById('nBody').textContent  = el.getAttribute('data-body') || 'No additional details provided.';
        document.getElementById('notifModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeNotifModal() {
        document.getElementById('notifModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        const evMod = document.getElementById('eventModal');
        const notMod = document.getElementById('notifModal');
        const allNotMod = document.getElementById('allNotifsModal');
        if (e.target === evMod) closeEventModal();
        
        // If clicking outside individual notif modal, close it
        if (e.target === notMod) {
            closeNotifModal();
        }
        
        // Only close All Notifs modal if the individual notif modal isn't open
        if (e.target === allNotMod && notMod.style.display !== 'flex') {
            closeAllNotifsModal();
        }
    });