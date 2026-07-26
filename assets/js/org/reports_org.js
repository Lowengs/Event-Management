/* ── Extracted from organization/reports_org.php ── */
(function() {
  fetch('../../config/API/get_org_reports.php').then(r=>r.json()).then(data=>{
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

    // Event Diagram Picker & Render
    const diagSelect = document.getElementById('eventDiagramSelect');
    const diagContainer = document.getElementById('eventDiagramContainer');
    const noDiagMsg = document.getElementById('noDiagramMsg');

    if (diagSelect && rawEventStats.length) {
      diagSelect.innerHTML = '<option value="">Select Event for Diagram Report...</option>' + 
        rawEventStats.map(e => `<option value="${e.EventId}">${e.EventName}</option>`).join('');

      diagSelect.addEventListener('change', function() {
        const evId = this.value;
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
          // Circle circumference = 2 * PI * 50 = ~314
          const offset = 314 - (314 * (pct / 100));
          gaugeFill.style.strokeDashoffset = offset;
        }

        // Update Pre vs Post Test Bar Diagram
        const preVal = parseFloat(ev.pretest_avg) || 0;
        const postVal = parseFloat(ev.posttest_avg) || 0;
        
        document.getElementById('preVal').textContent = preVal ? preVal + '%' : 'N/A';
        document.getElementById('postVal').textContent = postVal ? postVal + '%' : 'N/A';

        const preH = Math.min(80, Math.round((preVal / 100) * 80));
        const postH = Math.min(80, Math.round((postVal / 100) * 80));

        document.getElementById('preBar').style.height = (preH > 0 ? preH : 4) + 'px';
        document.getElementById('postBar').style.height = (postH > 0 ? postH : 4) + 'px';

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
      });
    }

    // Year level table
    const yt=document.getElementById('repYearTable');
    yt.innerHTML='';
    const totalM=data.by_year.reduce((a,r)=>a+parseInt(r.cnt),0)||1;
    data.by_year.forEach(r=>{
      const pct=Math.round(r.cnt/totalM*100);
      yt.innerHTML+=`<tr><td>${r.year_level||'Unknown'}</td><td style="font-weight:600;">${r.cnt}</td>
        <td><div class="rate-bar"><div class="rate-track"><div class="rate-fill" style="width:${pct}%"></div></div><span style="font-size:12px;color:#64748b;">${pct}%</span></div></td></tr>`;
    });
    if(!data.by_year.length) yt.innerHTML='<tr><td colspan="3" style="text-align:center;padding:20px;color:#94a3b8;">No member data.</td></tr>';
  });
})();