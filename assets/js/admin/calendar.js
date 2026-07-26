
    var modal = document.getElementById("eventModal");
    var span = document.getElementsByClassName("close-modal")[0];

    function openEventModal(title, desc, date, time, loc, org, type, limit, status, img, reqs, method) {
      document.getElementById("modalEventTitle").innerText = title;
      document.getElementById("modalEventDesc").innerText = desc || "N/A";
      document.getElementById("modalEventDate").innerText = date || "N/A";
      document.getElementById("modalEventTime").innerText = time || "N/A";
      document.getElementById("modalEventLoc").innerText = loc || "N/A";
      document.getElementById("modalEventOrg").innerText = org || "N/A";
      document.getElementById("modalEventType").innerText = type || "N/A";
      document.getElementById("modalEventLimit").innerText = limit || "N/A";
      document.getElementById("modalEventReqs").innerText = reqs || "N/A";
      document.getElementById("modalEventMethod").innerText = method || "N/A";

      // Status Pill logic
      var statusEl = document.getElementById("modalEventStatus");
      var statusPill = document.getElementById("modalEventStatusPill");
      var statusIcon = statusPill.querySelector("ion-icon");
      
      statusEl.innerText = status || "Unknown";
      statusPill.className = "status-pill"; // Reset
      statusIcon.setAttribute("name", "information-circle-outline"); // Default

      const st = (status || "").toLowerCase();
      if (st === "scheduled") {
        statusPill.classList.add("open");
        statusIcon.setAttribute("name", "calendar-outline");
      } else if (st === "ongoing") {
        statusPill.classList.add("pending");
        statusIcon.setAttribute("name", "time-outline");
      } else if (st === "completed") {
        statusPill.classList.add("open");
        statusIcon.setAttribute("name", "checkmark-circle-outline");
      } else if (st === "cancelled" || st === "delayed") {
        statusPill.classList.add("closed");
        statusIcon.setAttribute("name", "close-circle-outline");
      } else {
        statusIcon.setAttribute("name", "information-circle-outline");
      }

      modal.style.display = "flex";
      document.body.style.overflow = "hidden";
    }

    span.onclick = function() {
      modal.style.display = "none";
      document.body.style.overflow = "";
    }

    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
        document.body.style.overflow = "";
      }
    }
