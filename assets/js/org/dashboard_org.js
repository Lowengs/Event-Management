/**
 * Student Organization Dashboard JS
 * Manages stats rendering, Chart.js analytics graphs, and recent activity feeds
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Immediate render using pre-loaded server data if available
  if (typeof window.INITIAL_ORG_STATS !== 'undefined') {
    const initStats = window.INITIAL_ORG_STATS || {};
    const initMonthly = window.INITIAL_ORG_MONTHLY || [];
    
    // Draw initial charts immediately
    if (typeof Chart !== 'undefined') {
      renderEventsOverviewChart(initStats);
      renderEventsTrendChart(initMonthly);
    }
    
    // Set initial donut
    const attPct = initStats.attendance_rate ?? 100;
    const circ = 2 * Math.PI * 60;
    const arcEl = document.getElementById('donutArc');
    const labelEl = document.getElementById('donutLabel');
    if (arcEl) {
      const fillLen = (circ * attPct) / 100;
      const emptyLen = circ - fillLen;
      arcEl.setAttribute('stroke-dasharray', `${fillLen} ${emptyLen}`);
    }
    if (labelEl) labelEl.textContent = attPct + '%';
  }

  // 2. Fetch fresh dynamic data
  loadOrgDashboardData();
});

function loadOrgDashboardData() {
  fetch('../../config/API/endpoints/index.php?action=get_org_dashboard')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;

      const s = data.stats || {};
      
      // 1. Stat Cards
      const memEl = document.getElementById('statTotalMembers');
      if (memEl) memEl.textContent = s.total_members ?? 0;

      const upEl = document.getElementById('statUpcomingEvents');
      if (upEl) upEl.textContent = s.upcoming_events ?? 0;

      const partPct = s.participation_rate ?? 100;
      const partEl = document.getElementById('statParticipationRate');
      if (partEl) partEl.textContent = partPct + '%';

      const repEl = document.getElementById('statPendingReports');
      if (repEl) repEl.textContent = s.pending_reports ?? 0;

      const totEl = document.getElementById('anaTotal');
      if (totEl) totEl.textContent = s.total_events ?? 0;

      // Today's Attendance Realtime
      const tpEl = document.getElementById('statTodayPresent');
      if (tpEl) tpEl.textContent = s.today_present ?? 0;
      const taEl = document.getElementById('statTodayAbsent');
      if (taEl) taEl.textContent = s.today_absent ?? 0;
      const tlEl = document.getElementById('statTodayLate');
      if (tlEl) tlEl.textContent = s.today_late ?? 0;
      const trEl = document.getElementById('statTodayAttRate');
      if (trEl) trEl.textContent = s.today_attendance_rate ?? '0%';

      // 2. Attendance SVG Donut Chart
      const attPct = s.attendance_rate ?? 100;
      const circ = 2 * Math.PI * 60; // 377
      const arcEl = document.getElementById('donutArc');
      const labelEl = document.getElementById('donutLabel');
      if (arcEl) {
        const fillLen = (circ * attPct) / 100;
        const emptyLen = circ - fillLen;
        arcEl.setAttribute('stroke-dasharray', `${fillLen} ${emptyLen}`);
      }
      if (labelEl) labelEl.textContent = attPct + '%';

      // 3. Chart.js Graphs
      if (typeof Chart !== 'undefined') {
        renderEventsOverviewChart(s);
        renderEventsTrendChart(data.monthly_events || []);
      }

      // 4. Recent Events Feed
      if (data.events && Array.isArray(data.events)) {
        renderRecentEvents(data.events);
      }

      // 5. Recent Announcements / Notifications Feed
      fetchNotifications();
    })
    .catch(err => console.warn('Org Dashboard fetch warning:', err));
}

function renderEventsOverviewChart(stats) {
  const canvas = document.getElementById('eventsOverviewChart');
  if (!canvas) return;

  const existingChart = Chart.getChart(canvas);
  if (existingChart) existingChart.destroy();

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: ['Completed', 'Ongoing', 'Upcoming', 'Cancelled'],
      datasets: [{
        label: 'Events',
        data: [
          stats.completed_events || 0,
          stats.ongoing_events || 0,
          stats.upcoming_events || 0,
          stats.cancelled_events || 0
        ],
        backgroundColor: ['#16a34a', '#f59e0b', '#2563eb', '#ef4444'],
        borderRadius: 8,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148, 163, 184, 0.2)' } }
      }
    }
  });
}

function renderEventsTrendChart(monthlyEvents) {
  const canvas = document.getElementById('eventsTrendChart');
  if (!canvas) return;

  const existingChart = Chart.getChart(canvas);
  if (existingChart) existingChart.destroy();

  const labels = monthlyEvents.map(row => row.label || '');
  const values = monthlyEvents.map(row => Number(row.count) || 0);

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Events Hosted',
        data: values,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37, 99, 235, 0.12)',
        fill: true,
        tension: 0.35,
        pointRadius: 4,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: '#2563eb',
        pointBorderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        x: { grid: { color: 'rgba(148, 163, 184, 0.1)' } },
        y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148, 163, 184, 0.2)' } }
      }
    }
  });
}

function renderRecentEvents(events) {
  const el = document.getElementById('dashboardEventsList');
  if (!el) return;
  el.innerHTML = '';
  
  if (events.length === 0) {
    el.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:28px 20px;">No events recorded yet.</p>';
    return;
  }

  events.slice(0, 5).forEach(ev => {
    const dt = ev.EventDateTime ? new Date(ev.EventDateTime).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'TBA';
    const st = (ev.EventStatus || 'scheduled').toLowerCase();
    const place = ev.EventLocation || ev.EventPlace || 'Campus';
    const rawPic = ev.EventPicture || '';
    const pic = rawPic ? (rawPic.startsWith('http') || rawPic.startsWith('../../') ? rawPic : '../../' + rawPic.replace(/^\/+/, '')) : '../../assets/img/philsca.png';

    el.innerHTML += `
      <div class="event-item" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #f1f5f9;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;min-width:0;flex:1;">
          <img src="${escapeHtml(pic)}" alt="Event" style="width:40px;height:40px;border-radius:10px;object-fit:cover;background:#f1f5f9;flex-shrink:0;" onerror="this.src='../../assets/img/philsca.png'">
          <div style="min-width:0;flex:1;">
            <h5 style="margin:0;font-size:14px;color:#0f172a;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(ev.EventName || 'Untitled')}</h5>
            <p style="margin:2px 0 0;font-size:12px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${dt} &bull; ${escapeHtml(place)}</p>
          </div>
        </div>
        <span class="status-badge ${st}" style="text-transform:capitalize;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;flex-shrink:0;">${escapeHtml(ev.EventStatus || 'Scheduled')}</span>
      </div>`;
  });
}

function fetchNotifications() {
  fetch('../../config/API/endpoints/index.php?action=get_org_announcements')
    .then(r => r.json())
    .then(aData => {
      const nl = document.getElementById('dashboardNotifList');
      if (!nl) return;
      nl.innerHTML = '';

      if (!aData.success || !aData.announcements || aData.announcements.length === 0) {
        nl.innerHTML = '<div class="notification-item"><ion-icon name="checkmark-circle-outline"></ion-icon><div><h5>No recent announcements</h5><p>You are all caught up.</p></div></div>';
        return;
      }

      const icons = { pending: 'time-outline', approved: 'checkmark-circle-outline', rejected: 'close-circle-outline', draft: 'document-outline' };

      aData.announcements.slice(0, 4).forEach(a => {
        const statusIcon = icons[a.Status] || 'notifications-outline';
        const title = escapeHtml(a.Title || 'Announcement');
        const bodySnippet = escapeHtml((a.Body || '').substring(0, 60)) + '…';

        nl.innerHTML += `
          <div class="notification-item">
            <ion-icon name="${statusIcon}"></ion-icon>
            <div>
              <h5>${title}</h5>
              <p>${bodySnippet}</p>
            </div>
          </div>`;
      });
    })
    .catch(() => {});
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}