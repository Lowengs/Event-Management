/* ── Extracted from organization/reports_org.php ── */
(function() {
  fetch('../../config/API/endpoints/index.php?action=get_org_reports').then(r=>r.json()).then(data=>{
    if(!data.success) return;
    const s=data.summary;
    document.getElementById('repTotalEvents').textContent=s.totalEvents;
    document.getElementById('repTotalMembers').textContent=s.totalMembers;
    document.getElementById('repTotalAttended').textContent=s.totalAttended;
    document.getElementById('repAttRate').textContent=s.attRate+'%';

    // Bar chart (events per month)
    const svg=document.getElementById('repBarChart');
    if(data.monthly&&data.monthly.length){
      const max=Math.max(...data.monthly.map(m=>m.cnt),1);
      const W=350, H=150, pad=40, barW=Math.min(40,W/data.monthly.length-10);
      const xStep=W/data.monthly.length;
      svg.innerHTML=`<line x1="${pad}" y1="${H}" x2="${pad+W}" y2="${H}" stroke="#e2e8f0" stroke-width="1"/>`;
      data.monthly.forEach((m,i)=>{
        const barH=Math.max(4,(m.cnt/max)*(H-20));
        const x=pad+i*xStep+(xStep-barW)/2;
        svg.innerHTML+=`<rect x="${x}" y="${H-barH}" width="${barW}" height="${barH}" fill="#3b82f6" rx="4"/>
          <text x="${x+barW/2}" y="${H+14}" text-anchor="middle" font-size="10" fill="#64748b">${m.mo}</text>
          <text x="${x+barW/2}" y="${H-barH-4}" text-anchor="middle" font-size="10" fill="#374151">${m.cnt}</text>`;
      });
    }

    let rawEventStats = data.event_stats || [];

    function renderEventTable() {
      const et = document.getElementById('repEventTable');
      const q = (document.getElementById('repEventSearch')?.value || '').toLowerCase().trim();
      const st = (document.getElementById('repStatusFilter')?.value || '').toLowerCase().trim();

      const filtered = rawEventStats.filter(e => {
        const nameMatch = !q || (e.EventName || '').toLowerCase().includes(q);
        const statusMatch = !st || (e.EventStatus || '').toLowerCase() === st;
        return nameMatch && statusMatch;
      });

      et.innerHTML = '';
      if (!filtered.length) {
        et.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">No matching events found.</td></tr>';
      } else {
        filtered.forEach(e => {
          const cap = parseInt(e.EventCapacity) || 0;
          const att = parseInt(e.attended) || 0;
          const rate = cap > 0 ? Math.round(att / cap * 100) : 0;
          const dt = e.EventDateTime ? new Date(e.EventDateTime).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
          et.innerHTML += `<tr>
            <td style="font-weight:600;">${e.EventName}</td>
            <td>${dt}</td><td>${cap || '—'}</td><td>${att}</td>
            <td><div class="rate-bar"><div class="rate-track"><div class="rate-fill" style="width:${rate}%"></div></div><span style="font-size:12px;font-weight:700;color:#3b82f6;min-width:36px;">${rate}%</span></div></td>
            <td><span class="status-badge ${(e.EventStatus || '').toLowerCase()}">${e.EventStatus || '—'}</span></td>
          </tr>`;
        });
      }
    }

    renderEventTable();
    document.getElementById('repEventSearch')?.addEventListener('input', renderEventTable);
    document.getElementById('repStatusFilter')?.addEventListener('change', renderEventTable);

    // Searchable Select Component for Event Diagram Report
    const comboInput = document.getElementById('eventDiagramComboInput');
    const comboDropdown = document.getElementById('eventDiagramComboDropdown');
    const diagSelect = document.getElementById('eventDiagramSelect');
    const diagContainer = document.getElementById('eventDiagramContainer');
    const noDiagMsg = document.getElementById('noDiagramMsg');

    if (comboInput && comboDropdown) {
      function renderComboOptions(query = '', showAll = false) {
        const q = showAll ? '' : query.toLowerCase().trim();
        const matches = rawEventStats.filter(e => !q || (e.EventName || '').toLowerCase().includes(q));

        if (!rawEventStats.length) {
          comboDropdown.innerHTML = '<div style="padding:12px;color:#94a3b8;font-size:12px;text-align:center;">No events found for organization</div>';
        } else if (!matches.length) {
          comboDropdown.innerHTML = '<div style="padding:12px;color:#94a3b8;font-size:12px;text-align:center;">No matching events found</div>';
        } else {
          comboDropdown.innerHTML = matches.map(e => `
            <div class="combo-opt" data-id="${e.EventId}" data-name="${e.EventName}" 
                 style="padding:10px 14px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;background:#fff;"
                 onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
              <span>${e.EventName}</span>
              <span class="status-badge ${(e.EventStatus || 'scheduled').toLowerCase()}" style="font-size:10px;padding:2px 6px;">${e.EventStatus || 'Scheduled'}</span>
            </div>
          `).join('');
        }
      }

      comboInput.addEventListener('click', (e) => {
        e.stopPropagation();
        renderComboOptions('', true);
        comboDropdown.style.display = 'block';
      });

      comboInput.addEventListener('focus', (e) => {
        renderComboOptions('', true);
        comboDropdown.style.display = 'block';
      });

      comboInput.addEventListener('input', () => {
        renderComboOptions(comboInput.value, false);
        comboDropdown.style.display = 'block';
      });

      comboDropdown.addEventListener('click', (e) => {
        const opt = e.target.closest('.combo-opt');
        if (!opt) return;
        const id = opt.getAttribute('data-id');
        const name = opt.getAttribute('data-name');
        comboInput.value = name;
        if (diagSelect) diagSelect.value = id;
        comboDropdown.style.display = 'none';

        renderDiagram(id);
      });

      document.addEventListener('click', (e) => {
        if (!comboInput.contains(e.target) && !comboDropdown.contains(e.target)) {
          comboDropdown.style.display = 'none';
        }
      });
    }

    function renderDiagram(evId) {
        const ev = rawEventStats.find(x => String(x.EventId) === String(evId));

        if (!ev) {
          if (diagContainer) diagContainer.style.display = 'none';
          if (noDiagMsg) noDiagMsg.style.display = 'block';
          return;
        }

        if (noDiagMsg) noDiagMsg.style.display = 'none';
        if (diagContainer) diagContainer.style.display = 'block';

        const cap = parseInt(ev.EventCapacity) || 1;
        const att = parseInt(ev.attended) || 0;
        const pct = Math.min(100, Math.round((att / cap) * 100));

        // Update Turnout Gauge
        const gaugeFill = document.getElementById('turnoutGaugeFill');
        const gaugeText = document.getElementById('turnoutGaugeText');
        const diagAttended = document.getElementById('diagAttended');
        const diagCapacity = document.getElementById('diagCapacity');

        if (gaugeText) gaugeText.textContent = pct + '%';
        if (diagAttended) diagAttended.textContent = att;
        if (diagCapacity) diagCapacity.textContent = ev.EventCapacity || '—';

        if (gaugeFill) {
          const offset = 314 - (314 * (pct / 100));
          gaugeFill.style.strokeDashoffset = offset;
        }

        const registered = parseInt(ev.registered) || 0;
        const participation = registered > 0 ? Math.min(100, Math.round((att / registered) * 100)) : 0;
        const participationFill = document.getElementById('participationGaugeFill');
        const participationText = document.getElementById('participationGaugeText');
        const participationLabel = document.getElementById('participationText');
        if (participationFill) participationFill.style.strokeDashoffset = 314 - (314 * participation / 100);
        if (participationText) participationText.textContent = participation + '%';
        if (participationLabel) participationLabel.textContent = `${att} of ${registered} registered participants attended`;

        // Update Pre vs Post Test Bar Diagram (capped cleanly to prevent collision with header)
        const preVal = parseFloat(ev.pretest_avg) || 0;
        const postVal = parseFloat(ev.posttest_avg) || 0;
        
        const preValEl = document.getElementById('preVal');
        const postValEl = document.getElementById('postVal');
        if (preValEl) preValEl.textContent = preVal > 0 ? preVal.toFixed(0) + '%' : (ev.pretest_avg ? '0%' : 'N/A');
        if (postValEl) postValEl.textContent = postVal > 0 ? postVal.toFixed(0) + '%' : (ev.posttest_avg ? '0%' : 'N/A');

        const preH = Math.min(48, Math.round((preVal / 100) * 48));
        const postH = Math.min(48, Math.round((postVal / 100) * 48));

        const preBar = document.getElementById('preBar');
        const postBar = document.getElementById('postBar');
        if (preBar) preBar.style.height = (preH > 0 ? preH : 4) + 'px';
        if (postBar) postBar.style.height = (postH > 0 ? postH : 4) + 'px';

        const gainEl = document.getElementById('scoreGainText');
        if (gainEl) {
          if (postVal && preVal) {
            const diff = (postVal - preVal).toFixed(1);
            gainEl.textContent = diff >= 0 ? `+${diff}% Score Improvement` : `${diff}% Difference`;
            gainEl.style.color = diff >= 0 ? '#16a34a' : '#dc2626';
          } else {
            gainEl.textContent = 'Assessment score data logged upon test completion';
            gainEl.style.color = '#64748b';
          }
        }

        // Update Anti-Spoofing & Live Monitoring Stats Card
        const antiSpoofCount = parseInt(ev.antispoof_count) || 0;
        const presenceCount = parseInt(ev.presence_count) || 0;
        const presencePassed = parseInt(ev.total_presence_passed) || 0;
        const presenceMissed = parseInt(ev.total_presence_missed) || 0;

        const diagAntiSpoofEl = document.getElementById('diagAntiSpoofCount');
        const diagPresenceEl = document.getElementById('diagPresenceCount');
        const diagMonSummaryEl = document.getElementById('diagMonitoringSummary');

        if (diagAntiSpoofEl) diagAntiSpoofEl.textContent = `${antiSpoofCount} Completed`;
        if (diagPresenceEl) diagPresenceEl.textContent = `${presenceCount || presencePassed} Completed`;
        if (diagMonSummaryEl) {
          const totalPings = (presencePassed + presenceMissed) || presenceCount;
          if (totalPings > 0 || antiSpoofCount > 0) {
            diagMonSummaryEl.textContent = `${presenceMissed} missed • Rate: ${totalPings > 0 ? Math.round((presencePassed / totalPings) * 100) : 100}%`;
          } else {
            diagMonSummaryEl.textContent = 'Live verification stats logged';
          }
        }
    }

    // Year level table
    const yt = document.getElementById('repYearTable');
    if (yt && data.by_year) {
      yt.innerHTML = '';
      const totalM = data.by_year.reduce((a, r) => a + parseInt(r.cnt), 0) || 1;
      data.by_year.forEach(r => {
        const pct = Math.round(r.cnt / totalM * 100);
        yt.innerHTML += `<tr><td>${r.year_level || 'Unknown'}</td><td style="font-weight:600;">${r.cnt}</td>
          <td><div class="rate-bar"><div class="rate-track"><div class="rate-fill" style="width:${pct}%"></div></div><span style="font-size:12px;color:#64748b;">${pct}%</span></div></td></tr>`;
      });
      if (!data.by_year.length) yt.innerHTML = '<tr><td colspan="3" style="text-align:center;padding:20px;color:#94a3b8;">No member data.</td></tr>';
    }
  });
})();
