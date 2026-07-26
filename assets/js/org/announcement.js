 (function () {
      const filter = document.getElementById("statusFilter");
      const cards = document.querySelectorAll(".announce-card");

      if (!filter || cards.length === 0) return;

      filter.addEventListener("change", function () {
        const selected = this.value;

        cards.forEach((card) => {
          const status = card.getAttribute("data-status");
          const visible = selected === "all" || status === selected;
          card.style.display = visible ? "block" : "none";
        });
      });
    })();

    // Modal Logic
    document.addEventListener("DOMContentLoaded", () => {
      const modal = document.getElementById("addAnnouncementModal");
      const btn = document.querySelector(".add-announcement-btn");
      const closeBtn = document.querySelector(".close-modal");
      const cancelBtn = document.querySelector(".btn-cancel");
      const audienceType = document.getElementById("audienceType");
      const specificOrgGroup = document.getElementById("specificOrgGroup");

      // Toggle Specific Organization dropdown
      if(audienceType && specificOrgGroup) {
        audienceType.addEventListener("change", (e) => {
          if(e.target.value === "Specific Organization") {
            specificOrgGroup.style.display = "flex";
            document.getElementById("specificOrg").required = true;
          } else {
            specificOrgGroup.style.display = "none";
            document.getElementById("specificOrg").required = false;
          }
        });
      }

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