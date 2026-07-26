function openDocModal(docTitle, imgSrc) {
    const modal = document.getElementById('docModal');
    const modalTitle = modal.querySelector('.modal-header h3');
    const modalImg = document.getElementById('modalDocImage');
    
    if (docTitle) modalTitle.innerText = docTitle;
    else modalTitle.innerText = 'Document Viewer';
    
    if (imgSrc) modalImg.src = imgSrc;
    else modalImg.src = 'https://picsum.photos/800/1000?random=' + Math.floor(Math.random() * 100); // Random sample doc images
    
    modal.classList.add('active');
  }

  function closeDocModal() {
    document.getElementById('docModal').classList.remove('active');
  }

  window.addEventListener('click', (e) => {
    const docModal = document.getElementById('docModal');
    if (e.target === docModal) {
      closeDocModal();
    }
  });
  document.addEventListener('DOMContentLoaded', () => {
    const reportModal = document.getElementById('submitReportModal');
    const openBtn = document.getElementById('openSubmitReportModalBtn');
    const closeBtn = document.getElementById('closeReportModalBtn');
    const cancelBtn = document.getElementById('cancelReportBtn');

    const fileInput = document.querySelector('.report-file-input');
    const fileNameDisplay = document.getElementById('reportFileNameDisplay');
    const fileNameText = document.getElementById('reportFileNameText');
    const uploadBox = document.querySelector('.upload-box');

    // Open Modal
    if (openBtn) {
      openBtn.addEventListener('click', () => {
        reportModal.classList.add('show');
        document.body.style.overflow = 'hidden';
      });
    }

    // Close Modal Function
    const closeReportModal = () => {
      reportModal.classList.remove('show');
      document.body.style.overflow = '';
    };

    if (closeBtn) closeBtn.addEventListener('click', closeReportModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeReportModal);

    // Close on outside click
    window.addEventListener('click', (e) => {
      if (e.target === reportModal) {
        closeReportModal();
      }
    });

    // Handle File upload display
    if (fileInput) {
      fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
          fileNameDisplay.style.display = 'flex';
          fileNameText.textContent = this.files[0].name;
          uploadBox.style.borderColor = '#2563eb';
        } else {
          fileNameDisplay.style.display = 'none';
          uploadBox.style.borderColor = '#cbd5e1';
        }
      });
    }
  });