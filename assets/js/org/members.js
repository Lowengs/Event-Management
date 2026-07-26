document.addEventListener('DOMContentLoaded', () => {
      const addMemberModal = document.getElementById('addMemberModal');
      const openAddMemberModal = document.getElementById('openAddMemberModal');
      const closeAddMemberModal = document.getElementById('closeAddMemberModal');
      const cancelAddMemberBtn = document.getElementById('cancelAddMemberBtn');

      if (openAddMemberModal) {
        openAddMemberModal.addEventListener('click', () => {
          addMemberModal.classList.add('show');
          document.body.style.overflow = 'hidden';
        });
      }

      const closeModal = () => {
        addMemberModal.classList.remove('show');
        document.body.style.overflow = '';
      };

      if (closeAddMemberModal) closeAddMemberModal.addEventListener('click', closeModal);
      if (cancelAddMemberBtn) cancelAddMemberBtn.addEventListener('click', closeModal);

      if (addMemberModal) {
        addMemberModal.addEventListener('click', (e) => {
          if (e.target === addMemberModal) {
            closeModal();
          }
        });
      }
    });