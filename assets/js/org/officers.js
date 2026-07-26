
    document.addEventListener("DOMContentLoaded", () => {
      const modal = document.getElementById("addOfficerModal");
      const btn = document.querySelector(".addofficerbtn");
      const closeBtn = document.querySelector(".close-modal");
      const cancelBtn = document.querySelector(".btn-cancel");

      // Open Modal
      if(btn && modal) {
        btn.addEventListener("click", () => {
          modal.style.display = "flex";
          document.body.style.overflow = "hidden"; // Prevent background scrolling
        });
      }

      const closeModal = () => {
        if(modal) {
          modal.style.display = "none";
          document.body.style.overflow = ""; // Restore background scrolling
        }
      };

      // Close Modal events
      if(closeBtn) closeBtn.addEventListener("click", closeModal);
      if(cancelBtn) cancelBtn.addEventListener("click", closeModal);

      // Close on overlay click
      window.addEventListener("click", (e) => {
        if (e.target === modal) {
          closeModal();
        }
      });
    });
 