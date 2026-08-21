/**
 * Online Event Attendance: 2-Column Anti-Spoofing Verification Roster
 * Column 1: Students who passed anti-spoofing
 * Column 2: Students who did not pass anti-spoofing
 */

let currentRoster = [];
let currentEvent = null;
let autoRefreshInterval = null;
let isFetching = false;

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/[&<>"']/g, m => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  })[m]);
}

function formatTime(dateTimeStr) {
  if (!dateTimeStr) return '—';
  const d = new Date(dateTimeStr);
  if (isNaN(d.getTime())) return dateTimeStr;
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function getSelectedEventId() {
  const sel = document.getElementById('eventSelect');
  return sel ? parseInt(sel.value, 10) : 0;
}

async function loadOnlineAttendance(specifiedEventId = null) {
  if (isFetching) return;
  const eventId = specifiedEventId || getSelectedEventId();
  if (!eventId) {
    renderEmptyTwoColumns('Please select an online event.');
    return;
  }

  isFetching = true;
  try {
    const res = await fetch(`../../config/API/endpoints/index.php?action=get_online_attendance&EventId=${encodeURIComponent(eventId)}`);
    const data = await res.json();

    if (!data.success) {
      renderEmptyTwoColumns(data.message || 'Unable to load attendance records.');
      return;
    }

    currentEvent = data.event;
    currentRoster = data.roster || [];

    renderTwoColumns();
  } catch (err) {
    console.error('Error fetching online attendance:', err);
  } finally {
    isFetching = false;
  }
}

function renderTwoColumns() {
  const passedTbody = document.getElementById('passedRosterBody');
  const missingTbody = document.getElementById('missingRosterBody');
  const passedBadge = document.getElementById('badgePassedCount');
  const missingBadge = document.getElementById('badgeMissingCount');

  if (!passedTbody || !missingTbody) return;

  const searchVal = (document.getElementById('rosterSearchInput')?.value || '').toLowerCase().trim();

  // Filter roster by search term
  const searchedRoster = currentRoster.filter(s => {
    if (!searchVal) return true;
    const matchName = (s.full_name || '').toLowerCase().includes(searchVal);
    const matchId = (s.student_id || '').toLowerCase().includes(searchVal);
    const matchCourse = (s.course || '').toLowerCase().includes(searchVal);
    const matchSection = (s.section || '').toLowerCase().includes(searchVal);
    return matchName || matchId || matchCourse || matchSection;
  });

  // Separate into Passed vs Did Not Pass
  const passedList = searchedRoster.filter(s => s.has_antispoof_completed);
  const missingList = searchedRoster.filter(s => !s.has_antispoof_completed);

  if (passedBadge) passedBadge.textContent = `${passedList.length} Student${passedList.length === 1 ? '' : 's'}`;
  if (missingBadge) missingBadge.textContent = `${missingList.length} Student${missingList.length === 1 ? '' : 's'}`;

  // 1. Render Passed Column
  if (passedList.length === 0) {
    passedTbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:30px;color:#94a3b8;font-size:12.5px;">No students have passed anti-spoofing for this event yet.</td></tr>`;
  } else {
    let passedHtml = '';
    passedList.forEach((s, idx) => {
      const avatarSrc = s.profile_picture ? (s.profile_picture.startsWith('http') ? s.profile_picture : `../../uploads/profile/${s.profile_picture}`) : '../../assets/img/philsca.png';

      const checkInTimeText = s.check_in_time ? formatTime(s.check_in_time) : '—';
      const verifiedAtText = s.antispoof_completed_at ? formatTime(s.antispoof_completed_at) : 'Verified ✓';

      passedHtml += `
        <tr style="border-bottom:1px solid #f1f5f9;transition:background 0.15s ease;" onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
          <td style="padding:10px 14px;color:#64748b;font-weight:600;">${idx + 1}</td>
          <td style="padding:10px 14px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <img src="${escapeHtml(avatarSrc)}" alt="Photo" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:1.5px solid #86efac;" onerror="this.src='../../assets/img/philsca.png'">
              <div>
                <strong style="color:#0f172a;font-size:13px;display:block;">${escapeHtml(s.full_name)}</strong>
                <small style="color:#2563eb;font-weight:700;font-size:11px;">${escapeHtml(s.student_id)}</small>
                <small style="color:#64748b;font-size:10.5px;"> &bull; ${escapeHtml(s.course)} - ${escapeHtml(s.year_level)} ${escapeHtml(s.section)}</small>
              </div>
            </div>
          </td>
          <td style="padding:10px 14px;font-weight:600;color:#334155;">${checkInTimeText}</td>
          <td style="padding:10px 14px;">
            <span class="badge-status success" style="font-size:11px;">
              <ion-icon name="checkmark-circle"></ion-icon> ${verifiedAtText}
            </span>
          </td>
          <td style="padding:10px 14px;">
            <span class="badge-status info" style="font-size:11px;">
              ${escapeHtml(s.attendance_status || 'Present')}
            </span>
          </td>
        </tr>
      `;
    });
    passedTbody.innerHTML = passedHtml;
  }

  // 2. Render Missing / Did Not Pass Column
  if (missingList.length === 0) {
    missingTbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;font-size:12.5px;">All attendees have completed anti-spoofing verification!</td></tr>`;
  } else {
    let missingHtml = '';
    missingList.forEach((s, idx) => {
      const avatarSrc = s.profile_picture ? (s.profile_picture.startsWith('http') ? s.profile_picture : `../../uploads/profile/${s.profile_picture}`) : '../../assets/img/philsca.png';

      // Anti-spoofing state badge
      let stateBadge = '';
      if (s.antispoof_status === 'Pending') {
        stateBadge = `<span class="badge-status warning" style="font-size:11px;"><ion-icon name="time-outline"></ion-icon> Challenge Pending</span>`;
      } else if (s.antispoof_status === 'Missed') {
        stateBadge = `<span class="badge-status danger" style="font-size:11px;"><ion-icon name="close-circle"></ion-icon> Missed / Incomplete</span>`;
      } else {
        stateBadge = `<span class="badge-status neutral" style="font-size:11px;"><ion-icon name="help-circle-outline"></ion-icon> Not Triggered / Pending</span>`;
      }

      // Attendance check-in status
      let attText = s.is_checked_in 
        ? `<span style="color:#15803d;font-weight:700;">Checked In (${formatTime(s.check_in_time)})</span>`
        : `<span style="color:#94a3b8;">Not Checked In</span>`;

      missingHtml += `
        <tr style="border-bottom:1px solid #f1f5f9;transition:background 0.15s ease;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
          <td style="padding:10px 14px;color:#64748b;font-weight:600;">${idx + 1}</td>
          <td style="padding:10px 14px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <img src="${escapeHtml(avatarSrc)}" alt="Photo" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:1.5px solid #fca5a5;" onerror="this.src='../../assets/img/philsca.png'">
              <div>
                <strong style="color:#0f172a;font-size:13px;display:block;">${escapeHtml(s.full_name)}</strong>
                <small style="color:#dc2626;font-weight:700;font-size:11px;">${escapeHtml(s.student_id)}</small>
                <small style="color:#64748b;font-size:10.5px;"> &bull; ${escapeHtml(s.course)} - ${escapeHtml(s.year_level)} ${escapeHtml(s.section)}</small>
              </div>
            </div>
          </td>
          <td style="padding:10px 14px;font-size:12px;">${attText}</td>
          <td style="padding:10px 14px;">${stateBadge}</td>
        </tr>
      `;
    });
    missingTbody.innerHTML = missingHtml;
  }
}

function renderEmptyTwoColumns(msg) {
  const passedTbody = document.getElementById('passedRosterBody');
  const missingTbody = document.getElementById('missingRosterBody');
  if (passedTbody) passedTbody.innerHTML = `<tr><td colspan="5" style="text-align:center;padding:30px;color:#94a3b8;">${escapeHtml(msg)}</td></tr>`;
  if (missingTbody) missingTbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;">${escapeHtml(msg)}</td></tr>`;
}

// Auto-Refresh
function initAutoRefresh() {
  if (autoRefreshInterval) clearInterval(autoRefreshInterval);
  autoRefreshInterval = setInterval(() => {
    if (!isFetching) {
      loadOnlineAttendance();
    }
  }, 8000);
}

window.addEventListener('DOMContentLoaded', () => {
  loadOnlineAttendance();
  initAutoRefresh();
});
