 document.addEventListener('DOMContentLoaded', () => {
      const uploadDocModal = document.getElementById('uploadDocModal');
      
      const closeUploadModalBtns = [
        document.getElementById('closeUploadModal'),
        document.getElementById('cancelUploadBtn')
      ];
      
      const openUploadModalBtn = document.getElementById('openUploadModalBtn');
      const fileInput = document.querySelector('.docFileInput');
      const fileNameDisplay = document.getElementById('selectedFileName');
      const fileNameText = document.querySelector('.file-name-text');

      // Open Upload modal
      if (openUploadModalBtn) {
        openUploadModalBtn.addEventListener('click', () => {
          uploadDocModal.classList.add('show');
          document.body.style.overflow = 'hidden';
        });
      }

      // Close Upload modal
      const closeUploadModal = () => {
        uploadDocModal.classList.remove('show');
        document.body.style.overflow = '';
      };

      closeUploadModalBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', closeUploadModal);
      });

      // Handle File Input Change
      if (fileInput) {
        fileInput.addEventListener('change', function() {
          if (this.files && this.files.length > 0) {
            fileNameDisplay.style.display = 'flex';
            fileNameText.textContent = this.files[0].name;
            document.querySelector('.file-upload-box').style.borderColor = '#2563eb';
          } else {
            fileNameDisplay.style.display = 'none';
            document.querySelector('.file-upload-box').style.borderColor = '#cbd5e1';
          }
        });
      }

      // Close on outside click
      window.addEventListener('click', (e) => {
        if (e.target === uploadDocModal) closeUploadModal();
      });
    });