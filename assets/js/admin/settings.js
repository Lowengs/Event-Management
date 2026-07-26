/* ── OSA Settings JS ── */
function saveFinancialReportSetting(enabled) {
  const track  = document.getElementById('finToggleTrack');
  const thumb  = document.getElementById('finToggleThumb');
  const status = document.getElementById('finReportStatus');
  const val    = enabled ? '1' : '0';

  if (track) { track.classList.toggle('on', enabled); track.classList.toggle('off', !enabled); }
  if (thumb) { thumb.classList.toggle('on', enabled); thumb.classList.toggle('off', !enabled); }
  if (status) status.textContent = 'Saving...';

  const fd = new FormData();
  fd.append('key',   'financial_report_required');
  fd.append('value', val);

  fetch('../../config/API/save_system_setting.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (status) {
        status.textContent = d.success
          ? (enabled ? 'Enabled — organizations must now upload a financial report.' : 'Disabled — financial report upload is now optional.')
          : ('Error: ' + d.message);
        status.style.color = d.success ? (enabled ? '#16a34a' : '#64748b') : '#dc2626';
      }
    })
    .catch(() => {
      if (status) { status.textContent = 'Network error. Please try again.'; status.style.color = '#dc2626'; }
    });
}
