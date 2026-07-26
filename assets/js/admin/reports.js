function openDocModal(docTitle, imgSrc) {
      const modal = document.getElementById('docModal');
      const modalTitle = modal.querySelector('.modal-header h3');
      const modalImg = document.getElementById('modalDocImage');
      
      if (docTitle) modalTitle.innerText = docTitle;
      else modalTitle.innerText = 'Document Viewer';
      
      if (imgSrc) modalImg.src = imgSrc;
      else modalImg.src = 'https://picsum.photos/800/1000?random=1';
      
      modal.classList.add('active');
    }

    function closeDocModal() {
      document.getElementById('docModal').classList.remove('active');
    }

    function openDeclineModal(reportName) {
      const modal = document.getElementById('declineModal');
      const docNameEl = document.getElementById('declineReportName');
      document.getElementById('declineRemarks').value = ''; // clear previous
      
      if (reportName) docNameEl.innerText = '"' + reportName + '"';
      else docNameEl.innerText = 'this report';
      
      modal.classList.add('active');
    }

    function closeDeclineModal() {
      document.getElementById('declineModal').classList.remove('active');
    }

    function submitDecline() {
      const remarks = document.getElementById('declineRemarks').value;
      // Here you would typically send the remarks to the backend via AJAX
      alert('Report declined with remarks:\n\n' + remarks);
      closeDeclineModal();
    }

    window.addEventListener('click', (e) => {
      const docModal = document.getElementById('docModal');
      const declineModal = document.getElementById('declineModal');
      
      if (e.target === docModal) {
        closeDocModal();
      }
      if (e.target === declineModal) {
        closeDeclineModal();
      }
    });

/* ── Extracted inline scripts ── */
// ── Export / Preview ────────────────────────────────────────────────
  let _exportContent = '';

  function openExportModal(name, org, date, time, location, status, attended, registered, absent, attPct, spoofed, officers, type) {
    const isPost = type === 'post';
    const title  = isPost ? 'Post-Activity Report' : 'Financial Report';
    const officerList = Array.isArray(officers) && officers.length > 0 ? officers.join('<br>') : 'N/A';
    const officerListPlain = Array.isArray(officers) && officers.length > 0 ? officers.join('; ') : 'N/A';

    let summary = '';
    if (isPost) {
      summary = `The "<strong>${name}</strong>" organized by <strong>${org}</strong> took place on <strong>${date}</strong> at <strong>${time}</strong>`
        + (location && location !== 'N/A' ? ` at <strong>${location}</strong>` : '')
        + `. A total of <strong>${attended}</strong> student(s) attended out of <strong>${registered}</strong> registered`
        + ` (<strong>${attPct}%</strong> attendance rate), <strong style="color:#ef4444">${absent}</strong> absent, <strong>${spoofed}</strong> spoofed attempt(s). Event status: <strong>${status}</strong>.`;
    } else {
      summary = `Financial report for "<strong>${name}</strong>" (organized by <strong>${org}</strong>, held on <strong>${date}</strong>).`
        + ` Attendance rate was <strong>${attPct}%</strong> (${attended}/${registered} participants), ${absent} absent.`
        + ` This report was system-generated based on available event registration and attendance data.`;
    }

    const html = `
      <div style="margin-bottom:.75rem;">
        <span style="font-size:.72rem;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Report Type</span>
        <p style="margin:.25rem 0 0;font-weight:700;font-size:1rem;color:#0f172a;">${title}</p>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-bottom:1rem;">
        <div style="background:#f8fafc;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#94a3b8;margin:0 0 2px;">Event Name</p>
          <p style="font-size:.88rem;font-weight:700;color:#0f172a;margin:0;">${name}</p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#94a3b8;margin:0 0 2px;">Organization</p>
          <p style="font-size:.88rem;font-weight:700;color:#0f172a;margin:0;">${org}</p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#94a3b8;margin:0 0 2px;">Date</p>
          <p style="font-size:.88rem;font-weight:700;color:#0f172a;margin:0;">${date}</p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#94a3b8;margin:0 0 2px;">Time</p>
          <p style="font-size:.88rem;font-weight:700;color:#0f172a;margin:0;">${time}</p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#94a3b8;margin:0 0 2px;">Venue / Location</p>
          <p style="font-size:.88rem;font-weight:700;color:#0f172a;margin:0;">${location}</p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#94a3b8;margin:0 0 2px;">Event Status</p>
          <p style="font-size:.88rem;font-weight:700;color:#0f172a;margin:0;">${status}</p>
        </div>
        <div style="background:#dcfce7;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#166534;margin:0 0 2px;">Attendance</p>
          <p style="font-size:.88rem;font-weight:700;color:#166534;margin:0;">${attended} / ${registered} (${attPct}%)</p>
        </div>
        <div style="background:#fee2e2;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#991b1b;margin:0 0 2px;">Absent</p>
          <p style="font-size:.88rem;font-weight:700;color:#991b1b;margin:0;">${absent}</p>
        </div>
        <div style="background:#fef9c3;border-radius:8px;padding:.65rem;">
          <p style="font-size:.68rem;color:#92400e;margin:0 0 2px;">Spoofed Attempts</p>
          <p style="font-size:.88rem;font-weight:700;color:#92400e;margin:0;">${spoofed}</p>
        </div>
        <div style="background:#f8fafc;border-radius:8px;padding:.65rem;grid-column:1/-1;">
          <p style="font-size:.68rem;color:#94a3b8;margin:0 0 4px;">Organization Officers</p>
          <p style="font-size:.84rem;color:#0f172a;margin:0;line-height:1.6;">${officerList}</p>
        </div>
      </div>
      <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:.85rem 1rem;">
        <p style="font-size:.72rem;font-weight:600;color:#0369a1;margin:0 0 6px;text-transform:uppercase;">System-Generated Summary</p>
        <p style="font-size:.85rem;color:#334155;margin:0;line-height:1.65;">${summary}</p>
      </div>
    `;

    _exportContent = `
      <html><head><title>${title} — ${name}</title>
      </head><body>
      <h1>${title}</h1>
      <p class="subtitle">NAAP OSA Portal — Generated ${new Date().toLocaleString()}</p>
      <table>
        <tr><td>Event Name</td><td>${name}</td></tr>
        <tr><td>Organization</td><td>${org}</td></tr>
        <tr><td>Date</td><td>${date}</td></tr>
        <tr><td>Time</td><td>${time}</td></tr>
        <tr><td>Venue / Location</td><td>${location}</td></tr>
        <tr><td>Event Status</td><td>${status}</td></tr>
        <tr><td>Attendance</td><td class="att">${attended} / ${registered} (${attPct}%)</td></tr>
        <tr><td>Absent</td><td class="abs">${absent}</td></tr>
        <tr><td>Spoofed Attempts</td><td class="spf">${spoofed}</td></tr>
        <tr><td>Officers</td><td>${officerListPlain}</td></tr>
        <tr><td>Report Type</td><td>${title}</td></tr>
      </table>
      <div class="summary-box">
        <p class="summary-label">System-Generated Summary</p>
        <p>${summary.replace(/<\/?strong[^>]*>/g,'')}</p>
      </div>
      <p class="footer">This report was automatically generated by the NAAP OSA Portal system. For official use only.</p>
      </body></html>
    `;

    document.getElementById('exportModalTitle').textContent = title + ' — ' + name;
    document.getElementById('exportModalBody').innerHTML = html;
    document.getElementById('exportModal').style.display = 'flex';
  }

  function closeExportModal() {
    document.getElementById('exportModal').style.display = 'none';
  }

  function exportReport(name, org, date, time, location, status, attended, registered, absent, attPct, spoofed, officers, type) {
    openExportModal(name, org, date, time, location, status, attended, registered, absent, attPct, spoofed, officers, type);
    setTimeout(printExport, 300);
  }

  function printExport() {
    const win = window.open('', '_blank', 'width=800,height=600');
    win.document.write(_exportContent);
    win.document.close();
    win.focus();
    setTimeout(() => win.print(), 500);
  }

  // ── Decline modal ───────────────────────────────────────────────────
  function openDeclineModal(name) {
    document.getElementById('declineReportName').textContent = name;
    document.getElementById('declineRemarks').value = '';
    document.getElementById('declineModal').style.display = 'flex';
  }
  function closeDeclineModal() {
    document.getElementById('declineModal').style.display = 'none';
  }
  function submitDecline() {
    closeDeclineModal();
  }