
    const eventsOverviewChartEl = document.getElementById('eventsOverviewChart');
    const attendanceSummaryChartEl = document.getElementById('attendanceSummaryChart');
    const eventsTrendChartEl = document.getElementById('eventsTrendChart');

    if (window.Chart && eventsOverviewChartEl && attendanceSummaryChartEl && eventsTrendChartEl) {
      Chart.defaults.font.family = 'Inter';
      Chart.defaults.color = '#4b5563';

      new Chart(eventsOverviewChartEl, {
        type: 'bar',
        data: {
          labels: ['Completed', 'Cancelled'],
          datasets: [{
            label: 'count',
            data: [10, 2],
            backgroundColor: ['#1fb381', '#ef4444'],
            borderRadius: 8,
            borderSkipped: false
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                boxWidth: 16,
                usePointStyle: true,
                pointStyle: 'rect'
              }
            }
          },
          scales: {
            x: {
              grid: {
                color: 'rgba(148, 163, 184, 0.25)'
              }
            },
            y: {
              beginAtZero: true,
              suggestedMax: 12,
              ticks: {
                stepSize: 3
              },
              grid: {
                color: 'rgba(148, 163, 184, 0.35)'
              }
            }
          }
        }
      });

      new Chart(attendanceSummaryChartEl, {
        type: 'doughnut',
        data: {
          labels: ['Present 75%', 'Late 5%', 'Absent 20%'],
          datasets: [{
            data: [75, 5, 20],
            backgroundColor: ['#12b886', '#f59e0b', '#ef4444'],
            borderWidth: 1,
            borderColor: '#ffffff'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '62%',
          plugins: {
            legend: {
              position: 'right',
              labels: {
                usePointStyle: true,
                boxWidth: 10
              }
            }
          }
        }
      });

      new Chart(eventsTrendChartEl, {
        type: 'line',
        data: {
          labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
          datasets: [{
            label: 'events',
            data: [1, 3, 2, 4.1, 2, 3],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.12)',
            fill: false,
            tension: 0.35,
            pointRadius: 4,
            pointHoverRadius: 5,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#3b82f6',
            pointBorderWidth: 2
          }]
        },
        options: {
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
          },
          scales: {
            x: {
              grid: {
                color: 'rgba(148, 163, 184, 0.28)'
              }
            },
            y: {
              beginAtZero: true,
              suggestedMax: 4.2,
              ticks: {
                stepSize: 1
              },
              grid: {
                color: 'rgba(148, 163, 184, 0.35)'
              }
            }
          }
        }
      });
    }
 



     document.addEventListener('DOMContentLoaded', () => {
        const notificationsModal = document.getElementById('notificationsModal');
        const viewAllNotificationsBtn = document.getElementById('viewAllNotificationsBtn');
        const closeNotificationsModal = document.getElementById('closeNotificationsModal');

        if(viewAllNotificationsBtn) {
            viewAllNotificationsBtn.addEventListener('click', (e) => {
                e.preventDefault();
                notificationsModal.classList.add('show');
            });
        }

        if(closeNotificationsModal) {
            closeNotificationsModal.addEventListener('click', () => {
                notificationsModal.classList.remove('show');
            });
        }

        // Close options if clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === notificationsModal) {
                notificationsModal.classList.remove('show');
            }
        });
    });