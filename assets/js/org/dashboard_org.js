/* ── Extracted from organization/dashboard_org.php ── */
(function() {
  fetch('../../config/API/get_org_dashboard.php')
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      const s = data.stats;
      document.getElementById('statTotalMembers').textContent = s.total_members;
      document.getElementById('statUpcomingEvents').textContent = s.upcoming_events;
      document.getElementById('statAttRate').textContent = s.attendance_rate + '%';
      document.getElementById('statPendingReports').textContent = s.pending_reports;
      document.getElementById('anaTotal').textContent = s.total_events;

      // Donut chart
      const pct = s.attendance_rate;
      const circ = 2 * Math.PI * 60;
      document.getElementById('donutArc').setAttribute('stroke-dasharray', `${circ*pct/100} ${circ*(1-pct/100)}`);
      document.getElementById('donutLabel').textContent = pct + '%';

      if (window.Chart) {
        const commonOptions = {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                usePointStyle: true,
                boxWidth: 10
              }
            }
          }
        };

        const overviewCanvas = document.getElementById('eventsOverviewChart');
        if (overviewCanvas) {
          new Chart(overviewCanvas, {
            type: 'bar',
            data: {
              labels: ['Completed', 'Ongoing', 'Upcoming', 'Cancelled'],
              datasets: [{
                label: 'Events',
                data: [
                  s.completed_events || 0,
                  s.ongoing_events || 0,
                  s.upcoming_events || 0,
                  s.cancelled_events || 0
                ],
                backgroundColor: ['#16a34a', '#f59e0b', '#3b82f6', '#ef4444'],
                borderRadius: 10,
                borderSkipped: false
              }]
            },
            options: {
              ...commonOptions,
              scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148, 163, 184, 0.28)' } }
              }
            }
          });
        }

        const trendCanvas = document.getElementById('eventsTrendChart');
        if (trendCanvas) {
          const trendLabels = (data.monthly_events || []).map(row => row.label);
          const trendValues = (data.monthly_events || []).map(row => Number(row.count) || 0);

          new Chart(trendCanvas, {
            type: 'line',
            data: {
              labels: trendLabels,
              datasets: [{
                label: 'Events',
                data: trendValues,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.12)',
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 5,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 2
              }]
            },
            options: {
              ...commonOptions,
              scales: {
                x: { grid: { color: 'rgba(148, 163, 184, 0.18)' } },
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(148, 163, 184, 0.28)' } }
              }
            }
          });
        }
      }

      // Recent events
      const el = document.getElementById('dashboardEventsList');
      el.innerHTML = '';
      if (!data.events.length) {
        el.innerHTML = '<p style="color:#94a3b8;text-align:center;padding:20px;">No events yet.</p>';
      } else {
        data.events.forEach(ev => {
          const dt = ev.EventDateTime ? new Date(ev.EventDateTime).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : 'TBA';
          const s = (ev.EventStatus||'scheduled').toLowerCase();
          el.innerHTML += `
            <div class="event-item">
              <div class="event-left">
                <h5>${ev.EventName}</h5>
                <div class="event-meta">
                  <span><ion-icon name="calendar-outline"></ion-icon> ${dt}</span>
                  <span><ion-icon name="location-outline"></ion-icon> ${ev.EventLocation||'TBA'}</span>
                </div>
              </div>
              <div class="badge ${s}">${ev.EventStatus||'Scheduled'}</div>
            </div>`;
        });
      }

      // Notifications (announcements)
      fetch('../../config/API/get_org_announcements.php')
        .then(r=>r.json()).then(aData=>{
          const nl = document.getElementById('dashboardNotifList');
          nl.innerHTML = '';
          if(!aData.success||!aData.announcements.length){
            nl.innerHTML='<div class="notification-item"><ion-icon name="checkmark-circle-outline"></ion-icon><div><h5>No notifications</h5><p>You are up to date.</p></div></div>';
          } else {
            aData.announcements.slice(0,4).forEach(a=>{
              const icons = {pending:'time-outline',approved:'checkmark-circle-outline',rejected:'close-circle-outline',draft:'document-outline'};
              nl.innerHTML+=`<div class="notification-item"><ion-icon name="${icons[a.Status]||'notifications-outline'}"></ion-icon><div><h5>${a.Title}</h5><p>${a.Body.substring(0,60)}…</p></div></div>`;
            });
          }
        });
    });
})();