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
    .then(r=>r.json()).then(d=>{ showToast(d.message,d.success); if(d.success){ closeM('editOfficerModal'); loadOfficers(); }});
});

// Assign Officer
function loadAssignMembers() {
  const container = document.getElementById('assignMemberList');
  if (container) container.innerHTML = '<div style="padding:14px;text-align:center;color:#94a3b8;font-size:13px;">Loading members…</div>';

  fetch('../../config/API/endpoints/index.php?action=get_org_members')
    .then(r => r.json())
    .then(data => {
      currentAssignMembers = (data.members || []).filter(m => m.is_officer != 1 && m.is_officer != '1');
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
    .then(r=>r.json()).then(d=>{ showToast(d.message,d.success); if(d.success){ closeM('assignOfficerModal'); loadOfficers(); }});
});

// Add New Officer
document.getElementById('openAddOfficerBtn').addEventListener('click',()=>{
  document.getElementById('addOfficerForm').reset();
  document.getElementById('addOfficerRoleOther').style.display = 'none';
  openM('addOfficerModal');
});

document.getElementById('addOfficerForm').addEventListener('submit',e=>{
  e.preventDefault();
  
  let role = document.getElementById('addOfficerRole').value;
  if(role === 'Others') role = document.getElementById('addOfficerRoleOther').value.trim();
  else role = role.trim();
  
  if(!role) { showToast('Please select or specify a role', false); return; }
  document.getElementById('addOfficerRoleHidden').value = role;

  const btn = document.getElementById('saveAddOfficerBtn');
  btn.disabled=true; btn.textContent='Creating...';
  fetch('../../config/API/endpoints/index.php?action=add_officer',{method:'POST',body:new FormData(e.target)})
    .then(r=>r.json()).then(d=>{
      btn.disabled=false; btn.textContent='Create Officer';
      showToast(d.message,d.success);
      if(d.success){ closeM('addOfficerModal'); loadOfficers(); }
    }).catch(()=>{
      btn.disabled=false; btn.textContent='Create Officer';
      showToast('An error occurred',false);
    });
});

window.addEventListener('click',e=>{
  ['viewOfficerModal','editOfficerModal','assignOfficerModal','addOfficerModal'].forEach(id=>{ const m=document.getElementById(id); if(e.target===m) closeM(id); });
});

loadOfficers();