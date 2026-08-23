/* ── Extracted from organization/officers_org.php ── */
function openM(id){ document.getElementById(id).classList.add('active'); document.body.style.overflow='hidden'; }
function closeM(id){ document.getElementById(id).classList.remove('active'); document.body.style.overflow=''; }
function showToast(msg,ok=true){ const t=document.getElementById('toast'); t.textContent=msg; t.style.background=ok?'#16a34a':'#dc2626'; t.style.display='block'; setTimeout(()=>t.style.display='none',3000); }

function toggleOtherInput(selId, inpId) {
  const sel = document.getElementById(selId);
  const inp = document.getElementById(inpId);
  if(sel.value === 'Others') {
    inp.style.display = 'block';
    inp.focus();
  } else {
    inp.style.display = 'none';
  }
}

let currentAssignMembers = [];
let currentOfficersList = [];

const STANDARD_ROLES = [
  'President',
  'Vice President - Internal affairs',
  'Vice President - External affairs',
  'Secretary',
  'Treasurer',
  'Auditor',
  'PIO',
  'Peace Officer'
];

function updateRoleSelectOptions(selectId, excludeUserId = null) {
  const sel = document.getElementById(selectId);
  if (!sel) return;

  const assignedMap = {};
  currentOfficersList.forEach(o => {
    if (excludeUserId && String(o.UserId) === String(excludeUserId)) return;
    const r = (o.officer_role || '').trim();
    if (r) {
      const name = `${o.first_name || ''} ${o.last_name || ''}`.trim() || 'another officer';
      assignedMap[r.toLowerCase()] = name;
    }
  });

  Array.from(sel.options).forEach(opt => {
    if (!opt.value || opt.value === 'Others') {
      opt.disabled = false;
      return;
    }
    const valLower = opt.value.toLowerCase();
    if (assignedMap[valLower]) {
      opt.disabled = true;
      opt.textContent = `${opt.value} (Assigned to ${assignedMap[valLower]})`;
    } else {
      opt.disabled = false;
      opt.textContent = opt.value;
    }
  });
}

function escapeHtml(str) {
  return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function renderAssignMemberList(members) {
  const container = document.getElementById('assignMemberList');
  if (!container) return;
  
  if (!members || members.length === 0) {
    container.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:13.5px;">No eligible members found</div>';
    return;
  }
  
  const selectedVal = document.getElementById('assignMemberSelect').value;
  
  container.innerHTML = members.map(m => {
    const isSelected = String(m.UserId) === String(selectedVal);
    const initials = (((m.first_name ? m.first_name[0] : '') + (m.last_name ? m.last_name[0] : '')).toUpperCase()) || 'M';
    const sid = m.student_id ? m.student_id : 'N/A';
    
    return `
      <div class="custom-member-item ${isSelected ? 'selected' : ''}" onclick="selectAssignMember('${m.UserId}')">
        <div class="member-avatar-pill">${initials}</div>
        <div class="member-info-col">
          <span class="member-fullname">${escapeHtml(m.first_name)} ${escapeHtml(m.last_name)}</span>
        </div>
        <div class="member-check-icon">
          <ion-icon name="${isSelected ? 'checkmark-circle' : 'ellipse-outline'}"></ion-icon>
        </div>
      </div>
    `;
  }).join('');
}

function selectAssignMember(userId) {
  document.getElementById('assignMemberSelect').value = userId;
  renderAssignMemberList(currentAssignMembers);
}

function filterAssignMembers() {
  const query = (document.getElementById('assignMemberSearch').value || '').toLowerCase().trim();
  if (!query) {
    renderAssignMemberList(currentAssignMembers);
    return;
  }
  const filtered = currentAssignMembers.filter(m => {
    const name = `${m.first_name || ''} ${m.last_name || ''}`.toLowerCase();
    const sid = (m.student_id || '').toLowerCase();
    return name.includes(query) || sid.includes(query);
  });
  renderAssignMemberList(filtered);
}

function loadOfficers(){
  fetch('../../config/API/endpoints/index.php?action=get_org_officers').then(r=>r.json()).then(data=>{
    const officers = data.data || data.officers || [];
    currentOfficersList = officers;
    document.getElementById('statOfficersTotal').textContent = data.total || officers.length;
    const tbody = document.getElementById('officersTableBody');
    tbody.innerHTML='';
    if(!officers.length){
      tbody.innerHTML='<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">No officers assigned yet. Use "Assign Officer" to get started.</td></tr>';
      return;
    }
    officers.forEach(o=>{
      const fname = o.first_name || 'Officer';
      const lname = o.last_name  || '';
      const name = fname + ' ' + lname;
      const ini  = (fname[0]||'') + (lname[0]||'');
      tbody.innerHTML+=`<tr>
        <td class="name-cell" data-label="Officer"><span class="avatar">${ini.toUpperCase()}</span><span>${name}</span></td>
        <td data-label="Student ID">${o.student_id||'N/A'}</td>
        <td data-label="Email">${o.Email||'N/A'}</td>
        <td data-label="Year Level">${o.year_level||'N/A'}</td>
        <td data-label="Role"><span class="status-badge active">${o.officer_role||'Officer'}</span></td>
        <td class="member-actions" data-label="">
          <button class="action-btn view-btn" onclick='viewOfficer(${JSON.stringify(o)})'><ion-icon name="eye-outline"></ion-icon></button>
          <button class="action-btn" style="background:#f0f9ff;color:#0369a1;" onclick='editOfficer(${o.UserId},"${o.officer_role||''}")'><ion-icon name="create-outline"></ion-icon></button>
          <button class="action-btn decline-btn" onclick="removeOfficer(${o.UserId})"><ion-icon name="person-remove-outline"></ion-icon></button>
        </td>
      </tr>`;
    });
  });
}

function viewOfficer(o){
  const name=o.first_name+' '+o.last_name;
  document.getElementById('voName').textContent=name;
  document.getElementById('voRole').textContent=o.officer_role||'Officer';
  document.getElementById('voAvatar').textContent=(o.first_name[0]+(o.last_name[0]||'')).toUpperCase();
  document.getElementById('voSid').textContent=o.student_id||'N/A';
  document.getElementById('voEmail').textContent=o.Email||'N/A';
  document.getElementById('voYear').textContent=o.year_level||'N/A';
  openM('viewOfficerModal');
}

function editOfficer(id,role){
  document.getElementById('editOfficerUserId').value=id;
  updateRoleSelectOptions('editOfficerRole', id);

  const roleSelect = document.getElementById('editOfficerRole');
  const roleOther = document.getElementById('editOfficerRoleOther');
  
  let found = false;
  Array.from(roleSelect.options).forEach(opt => {
    if (opt.value === role) found = true;
  });

  if (found) {
    roleSelect.value = role;
    roleOther.style.display = 'none';
    roleOther.value = '';
  } else if (role) {
    roleSelect.value = 'Others';
    roleOther.style.display = 'block';
    roleOther.value = role;
  } else {
    roleSelect.value = '';
    roleOther.style.display = 'none';
    roleOther.value = '';
  }
  openM('editOfficerModal');
}

function removeOfficer(id){
  showConfirmModal('Are you sure you want to remove this officer? Their officer position will be revoked.', function() {
    const fd = new FormData();
    fd.append('UserId', id);
    fd.append('officer_role', '');
    fetch('../../config/API/endpoints/index.php?action=update_officer_role', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (typeof showModal === 'function') {
          showModal(d.message || 'Officer removed successfully', d.success ? 'success' : 'error', 'Officer Removed');
        } else {
          showToast(d.message, d.success);
        }
        if (d.success) loadOfficers();
      })
      .catch(e => {
        if (typeof showModal === 'function') showModal('Error removing officer: ' + e.message, 'error');
        else showToast('Error removing officer', false);
      });
  }, 'Remove Officer', 'danger');
}

document.getElementById('saveOfficerRoleBtn').addEventListener('click',()=>{
  const id=document.getElementById('editOfficerUserId').value;
  let role=document.getElementById('editOfficerRole').value;
  if(role === 'Others') role = document.getElementById('editOfficerRoleOther').value.trim();
  else role = role.trim();
  
  const fd=new FormData(); fd.append('UserId',id); fd.append('officer_role',role);
  fetch('../../config/API/endpoints/index.php?action=update_officer_role',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{ 
      if (!d.success) {
        if (typeof showModal === 'function') showModal(d.message, 'warning', 'Role Assignment');
        else showToast(d.message, false);
      } else {
        showToast(d.message, true);
        closeM('editOfficerModal'); 
        loadOfficers(); 
      }
    })
    .catch(e => {
      showToast('Error updating officer role', false);
    });
});

// Assign Officer
function loadAssignMembers() {
  const container = document.getElementById('assignMemberList');
  if (container) container.innerHTML = '<div style="padding:14px;text-align:center;color:#94a3b8;font-size:13px;">Loading members…</div>';

  fetch('../../config/API/endpoints/index.php?action=get_org_members')
    .then(r => r.json())
    .then(data => {
      // Exclude members who are already officers
      currentAssignMembers = (data.members || []).filter(m => m.is_officer != 1 && m.is_officer != '1' && !m.officer_role);
      renderAssignMemberList(currentAssignMembers);
    })
    .catch(err => {
      console.error('Error loading members:', err);
      if (container) container.innerHTML = '<div style="padding:14px;text-align:center;color:#ef4444;font-size:13px;">Failed to load members</div>';
    });
}

document.getElementById('openAssignOfficerBtn').addEventListener('click',()=>{
  document.getElementById('assignMemberSearch').value = '';
  document.getElementById('assignMemberSelect').value = '';
  document.getElementById('assignOfficerRole').value = '';
  document.getElementById('assignOfficerRoleOther').value = '';
  document.getElementById('assignOfficerRoleOther').style.display = 'none';
  
  updateRoleSelectOptions('assignOfficerRole', null);
  openM('assignOfficerModal');
  loadAssignMembers();
});

document.getElementById('saveAssignOfficerBtn').addEventListener('click',()=>{
  const id=document.getElementById('assignMemberSelect').value;
  let role=document.getElementById('assignOfficerRole').value;
  if(role === 'Others') role = document.getElementById('assignOfficerRoleOther').value.trim();
  else role = role.trim();
  
  if(!id||!role){ showToast('Please select a member and enter a role',false); return; }
  const fd=new FormData(); fd.append('UserId',id); fd.append('officer_role',role);
  fetch('../../config/API/endpoints/index.php?action=update_officer_role',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{ 
      if (!d.success) {
        if (typeof showModal === 'function') showModal(d.message, 'warning', 'Role Assignment');
        else showToast(d.message, false);
      } else {
        showToast(d.message, true);
        closeM('assignOfficerModal'); 
        loadOfficers(); 
      }
    })
    .catch(e => {
      showToast('Error assigning officer', false);
    });
});

window.addEventListener('click',e=>{
  ['viewOfficerModal','editOfficerModal','assignOfficerModal'].forEach(id=>{ const m=document.getElementById(id); if(e.target===m) closeM(id); });
});

loadOfficers();