function openDocModal(docTitle, imgSrc) {
  const modal = document.getElementById('docModal');
  if (!modal) return;
  const modalTitle = modal.querySelector('.modal-header h3');
  const modalImg = document.getElementById('modalDocImage');
  
  if (docTitle && modalTitle) modalTitle.innerText = docTitle;
  else if (modalTitle) modalTitle.innerText = 'Document Viewer';
  
  if (imgSrc && modalImg) modalImg.src = imgSrc;
  else if (modalImg) modalImg.src = 'https://picsum.photos/800/1000?random=1';
  
  modal.classList.add('active');
}

function closeDocModal() {
  const modal = document.getElementById('docModal');
  if (modal) modal.classList.remove('active');
}

// ── In-Page Document Preview Modal (Prevents downloading on click) ────
function openReportDocPreview(filePath, title, ext, downloadName, orgName) {
  const modal = document.getElementById('reportDocPreviewModal');
  const titleEl = document.getElementById('reportDocModalTitle');
  const subEl = document.getElementById('reportDocModalSub');
  const bodyEl = document.getElementById('reportDocModalBody');
  const dlBtn = document.getElementById('reportDocModalDownloadBtn');
  const metaEl = document.getElementById('reportDocModalMeta');

  if (!modal || !bodyEl) return;

  const cleanExt = (ext || filePath.split('.').pop() || 'pdf').toLowerCase().replace(/^\./, '');
  const cleanTitle = title || 'Document Preview';
  const cleanDlName = downloadName || `${cleanTitle}.${cleanExt}`;

  if (titleEl) titleEl.textContent = cleanTitle;
  if (subEl) subEl.textContent = `${orgName ? orgName + ' • ' : ''}${cleanExt.toUpperCase()} Document`;
  if (dlBtn) {
    dlBtn.href = filePath;
    dlBtn.setAttribute('download', cleanDlName);
  }
  if (metaEl) metaEl.textContent = `File format: .${cleanExt.toUpperCase()} | Filename: ${cleanDlName}`;

  bodyEl.innerHTML = '';

  if (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'].includes(cleanExt)) {
    bodyEl.innerHTML = `
      <div style="display:flex;align-items:center;justify-content:center;height:100%;padding:20px;box-sizing:border-box;">
        <img src="${filePath}" alt="${cleanTitle}" style="max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;box-shadow:0 10px 25px rgba(0,0,0,0.12);">
      </div>`;
  } else if (cleanExt === 'pdf') {
    bodyEl.innerHTML = `
      <iframe src="${filePath}" style="width:100%;height:100%;border:none;"></iframe>`;
  } else if (['docx', 'doc'].includes(cleanExt)) {
    bodyEl.innerHTML = `
      <div style="width:100%;height:100%;overflow:auto;padding:24px;box-sizing:border-box;display:flex;justify-content:center;background:#f1f5f9;">
        <div id="reportDocxTarget" style="background:#ffffff;border:1px solid #e2e8f0;width:100%;max-width:850px;min-height:100%;padding:40px 48px;box-sizing:border-box;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.06);color:#1e293b;">
          <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;color:#64748b;">
            <ion-icon name="sync-outline" style="font-size:38px;color:#2563eb;margin-bottom:12px;animation:spin 1s linear infinite;"></ion-icon>
            <p style="margin:0;font-size:14px;font-weight:600;">Loading and rendering Word document...</p>
          </div>
        </div>
      </div>`;

    const targetEl = document.getElementById('reportDocxTarget');
    if (typeof docx !== 'undefined' && docx.renderAsync) {
      fetch(filePath)
        .then(response => {
          if (!response.ok) throw new Error('File fetch failed');
          return response.arrayBuffer();
        })
        .then(buffer => {
          targetEl.innerHTML = '';
          docx.renderAsync(buffer, targetEl, null, {
            className: 'docx',
            inWrapper: false,
            breakPages: true,
            useBase64URL: true
          }).catch(err => {
            console.error('Docx render error:', err);
            renderDocxFallback(targetEl, filePath, cleanDlName, cleanExt);
          });
        })
        .catch(err => {
          console.error('Fetch docx error:', err);
          renderDocxFallback(targetEl, filePath, cleanDlName, cleanExt);
        });
    } else {
      renderDocxFallback(targetEl, filePath, cleanDlName, cleanExt);
    }
  } else {
    renderDocxFallback(bodyEl, filePath, cleanDlName, cleanExt);
  }

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
window.openReportDocPreview = openReportDocPreview;

function closeReportDocPreview() {
  const modal = document.getElementById('reportDocPreviewModal');
  if (modal) modal.style.display = 'none';
  const bodyEl = document.getElementById('reportDocModalBody');
  if (bodyEl) bodyEl.innerHTML = '';
  document.body.style.overflow = '';
}
window.closeReportDocPreview = closeReportDocPreview;

function renderDocxFallback(container, filePath, displayTitle, ext) {
  if (!container) return;
  container.innerHTML = `
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:50px 20px;text-align:center;color:#475569;">
      <ion-icon name="document-text-outline" style="font-size:58px;color:#2563eb;margin-bottom:12px;"></ion-icon>
      <h4 style="margin:0 0 6px;font-size:17px;color:#0f172a;font-weight:700;">${ext.toUpperCase()} Document</h4>
      <p style="margin:0 0 18px;max-width:440px;font-size:13px;line-height:1.5;color:#64748b;">
        Inline document preview could not be loaded directly. You can download the official copy below:
      </p>
      <a href="${filePath}" download="${displayTitle}" style="display:inline-flex;align-items:center;gap:8px;padding:11px 22px;background:#2563eb;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:13.5px;box-shadow:0 4px 12px rgba(37,99,235,0.25);">
        <ion-icon name="download-outline" style="font-size:18px;"></ion-icon> Download ${displayTitle}
      </a>
    </div>`;
}

function openDeclineModal(reportName) {
  const modal = document.getElementById('declineModal');
  const docNameEl = document.getElementById('declineReportName');
  if (document.getElementById('declineRemarks')) {
    document.getElementById('declineRemarks').value = '';
  }
  
  if (reportName) docNameEl.innerText = '"' + reportName + '"';
  else docNameEl.innerText = 'this report';
  
  if (modal) modal.style.display = 'flex';
}

function closeDeclineModal() {
  const modal = document.getElementById('declineModal');
  if (modal) modal.style.display = 'none';
}

window.addEventListener('click', (e) => {
  const docModal = document.getElementById('docModal');
  const declineModal = document.getElementById('declineModal');
  const previewModal = document.getElementById('reportDocPreviewModal');
  const exportModal = document.getElementById('exportModal');
  if (e.target === docModal) closeDocModal();
  if (e.target === declineModal) closeDeclineModal();
  if (e.target === previewModal) closeReportDocPreview();
  if (e.target === exportModal) closeExportModal();
});

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
    <style>
      body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 30px; color: #0f172a; }
      h1 { font-size: 22px; margin: 0 0 4px; color: #1e40af; }
      .subtitle { color: #64748b; font-size: 13px; margin: 0 0 20px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
      td { padding: 8px 12px; border: 1px solid #e2e8f0; font-size: 13px; }
      td:first-child { font-weight: 600; background: #f8fafc; width: 30%; }
      .summary-box { background: #eff6ff; border: 1px solid #bfdbfe; padding: 14px; border-radius: 8px; font-size: 13px; line-height: 1.6; }
      .summary-label { font-weight: 700; color: #1e40af; margin: 0 0 6px; }
      .footer { margin-top: 30px; font-size: 11px; color: #94a3b8; text-align: center; }
    </style>
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
      <tr><td>Attendance</td><td>${attended} / ${registered} (${attPct}%)</td></tr>
      <tr><td>Absent</td><td>${absent}</td></tr>
      <tr><td>Spoofed Attempts</td><td>${spoofed}</td></tr>
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

  const modalTitle = document.getElementById('exportModalTitle');
  const modalBody = document.getElementById('exportModalBody');
  const modal = document.getElementById('exportModal');

  if (modalTitle) modalTitle.textContent = title + ' — ' + name;
  if (modalBody) modalBody.innerHTML = html;
  if (modal) modal.style.display = 'flex';
}

function closeExportModal() {
  const modal = document.getElementById('exportModal');
  if (modal) modal.style.display = 'none';
}

function exportReport(name, org, date, time, location, status, attended, registered, absent, attPct, spoofed, officers, type) {
  openExportModal(name, org, date, time, location, status, attended, registered, absent, attPct, spoofed, officers, type);
  setTimeout(printExport, 300);
}

function printExport() {
  const win = window.open('', '_blank', 'width=800,height=600');
  if (!win) {
    alert('Please allow popups to print/export this report.');
    return;
  }
  win.document.write(_exportContent);
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 500);
}

function submitDecline() {
  closeDeclineModal();
}