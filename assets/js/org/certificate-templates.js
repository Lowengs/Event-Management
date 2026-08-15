/* ── State ── */
let selEvId   = null, selEvName = '';
let bgFile    = null;
let nameXpct  = 0.5, nameYpct = 0.45;  // relative 0–1
let markerSet = false;
let savedTplId   = null, savedTplName = '';
let curStep = 1;

/* ── Wizard ── */
function goStep(n) {
  curStep = n;
  document.querySelectorAll('.step-view').forEach(v => v.classList.remove('active'));
  document.getElementById('sv' + n).classList.add('active');
  for (let i = 1; i <= 4; i++) {
    const c = document.getElementById('wc' + i);
    const l = document.getElementById('wl' + i);
    if (!c || !l) continue;
    c.classList.remove('active','done');
    l.classList.remove('active','done');
    if (i < n)      { c.classList.add('done'); l.classList.add('done'); c.textContent='✓'; }
    else if (i===n) { c.classList.add('active'); l.classList.add('active'); c.textContent=i; }
    else            { c.textContent=i; }
  }
  for (let i=1;i<=3;i++) document.getElementById('wln'+i).classList.toggle('done', i < n);
  if (n===3) loadLibrary();
  if (n===4) loadSummary();
  window.scrollTo({top:0, behavior:'smooth'});
}

/* ── Step 1 ── */
document.getElementById('evSel').addEventListener('change', function() {
  selEvId   = this.value;
  selEvName = this.options[this.selectedIndex]?.getAttribute('data-name') || '';
  document.getElementById('s1Btn').disabled       = !selEvId;
  document.getElementById('s1ManageBtn').disabled = !selEvId;
  document.getElementById('issueEvName').textContent = selEvName || '—';
});

/* ── Step 2: Upload ── */
function handleDrop(e) { e.preventDefault(); const f=e.dataTransfer.files[0]; if(f&&f.type.startsWith('image/')) loadPreview(f); }
function handleFile(e) { const f=e.target.files[0]; if(f) loadPreview(f); }
function loadPreview(f) {
  bgFile = f;
  const reader = new FileReader();
  reader.onload = ev => {
    document.getElementById('previewImg').src = ev.target.result;
    document.getElementById('uploadZone').style.display = 'none';
    document.getElementById('previewSection').style.display = '';
    markerSet = false;
    document.getElementById('nameMarker').style.display='none';
    document.getElementById('posText').textContent = 'Not set — click the image above';
  };
  reader.readAsDataURL(f);
}
function clearFile() {
  bgFile=null; markerSet=false;
  document.getElementById('bgFile').value='';
  document.getElementById('uploadZone').style.display='';
  document.getElementById('previewSection').style.display='none';
  document.getElementById('nameMarker').style.display='none';
}

function updateMarkerStyle() {
  const marker = document.getElementById('nameMarker');
  if (!marker) return;
  const fsize = document.getElementById('fontSize')?.value || 60;
  const fcolor = document.getElementById('fontColor')?.value || '#1e293b';
  const ffamily = document.getElementById('fontFamily')?.value || "'Inter', sans-serif";
  marker.style.fontSize = Math.max(14, Math.round(fsize * 0.35)) + 'px';
  marker.style.color = fcolor;
  marker.style.fontFamily = ffamily;
  marker.textContent = 'Sample Student Name';
}

function placeMarker(e) {
  const wrap = document.getElementById('pickerWrap');
  const img  = document.getElementById('previewImg');
  const rect = wrap.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;
  nameXpct = x / rect.width;
  nameYpct = y / rect.height;
  markerSet = true;

  const marker = document.getElementById('nameMarker');
  marker.style.left = (nameXpct * 100) + '%';
  marker.style.top  = (nameYpct * 100) + '%';
  marker.style.display = 'block';
  updateMarkerStyle();

  document.getElementById('posText').textContent =
    `X=${Math.round(nameXpct*100)}%, Y=${Math.round(nameYpct*100)}% — click again to reposition`;
}

async function saveTemplate() {
  const name  = document.getElementById('tplName').value.trim();
  const fsize = document.getElementById('fontSize').value;
  const fclr  = document.getElementById('fontColor').value;
  const ffamily = document.getElementById('fontFamily')?.value || "'Inter', sans-serif";
  if (!name)  { showToast('s2Toast','Template name is required','err'); return; }
  if (!bgFile){ showToast('s2Toast','Please upload a certificate image first','err'); return; }
  if (!markerSet){ showToast('s2Toast','Please click on the image to set the name position','err'); return; }

  const btn = document.getElementById('s2SaveBtn');
  btn.disabled=true; btn.innerHTML='<ion-icon name="hourglass-outline"></ion-icon> Saving…';

  const fd = new FormData();
  fd.append('TemplateName', name);
  fd.append('TemplateImage', bgFile);
  fd.append('NameX',    nameXpct.toFixed(4));
  fd.append('NameY',    nameYpct.toFixed(4));
  fd.append('FontSize', fsize);
  fd.append('FontColor', fclr);
  fd.append('FontFamily', ffamily);
  if (selEvId) {
    fd.append('EventId', selEvId);
  }

  try {
    const res  = await fetch('../../config/API/endpoints/index.php?action=save_certificate_template', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) {
      savedTplId   = data.template_id;
      savedTplName = name;
      document.getElementById('issueTplName').textContent = name;
      showToast('s2Toast','✅ Template saved!','ok');
      setTimeout(() => goStep(3), 800);
    } else {
      showToast('s2Toast','❌ ' + data.message,'err');
    }
  } catch(e) {
    showToast('s2Toast','❌ Could not reach server. Check that XAMPP is running and you\'re logged in.','err');
    console.error(e);
  } finally {
    btn.disabled=false;
    btn.innerHTML='<ion-icon name="save-outline"></ion-icon> Save Template & Continue';
  }
}

/* ── Step 3: Library ── */
async function loadLibrary() {
  const area = document.getElementById('libraryArea');
  area.innerHTML='<p style="text-align:center;color:#94a3b8;padding:20px;font-size:13px;">Loading…</p>';
  try {
    const res  = await fetch('../../config/API/endpoints/index.php?action=get_certificate_templates');
    const data = await res.json();
    document.getElementById('s3NextBtn').disabled = !data.templates?.length;
    if (!data.templates?.length) {
      area.innerHTML='<div style="background:#fff8f0;border:1px solid #fed7aa;border-radius:11px;padding:16px;font-size:13px;color:#92400e;">No templates yet. Go back and upload one.</div>';
      return;
    }
    area.innerHTML='';
    
    // Filter templates for this event or unassigned templates
    const eventTemplates = data.templates.filter(t => !t.EventId || t.EventId == selEvId);

    if (eventTemplates.length === 0) {
      area.innerHTML='<div style="background:#fff8f0;border:1px solid #fed7aa;border-radius:11px;padding:16px;font-size:13px;color:#92400e;">No templates found for this event. Go back and upload one.</div>';
      return;
    }

    eventTemplates.forEach(t => {
      if (!savedTplId) { savedTplId=t.TemplateId; savedTplName=t.TemplateName; document.getElementById('issueTplName').textContent=t.TemplateName; }
      const div = document.createElement('div');
      div.className='tpl-card';
      
      div.innerHTML=`
        <div style="position:relative;">
          <img class="tpl-thumb" src="../../${escH(t.TemplateImage||'')}" onerror="this.style.display='none';this.nextSibling.style.display='flex'" alt="">
          <div class="tpl-ph" style="display:none"><ion-icon name="ribbon-outline"></ion-icon></div>
        </div>
        <div class="tpl-info">
          <h4>${escH(t.TemplateName)}</h4>
          <p>Saved ${new Date(t.CreatedAt).toLocaleDateString()}</p>
        </div>
        <div class="tpl-acts">
          <button class="btn btn-warn" style="font-size:12px;padding:7px 11px;" onclick="showReplace(${t.TemplateId})">
            <ion-icon name="cloud-upload-outline"></ion-icon> Replace
          </button>
          <button class="btn btn-danger" style="font-size:12px;padding:7px 11px;" onclick="deleteTpl(${t.TemplateId},this)">
            <ion-icon name="trash-outline"></ion-icon> Delete
          </button>
        </div>`;
      area.appendChild(div);
    });
  } catch(e) {
    area.innerHTML='<p style="color:#dc2626;font-size:13px;">Failed to load templates.</p>';
  }
}

function showReplace(id) {
  const w = document.getElementById('replaceWrap');
  w.dataset.tplId = id;
  w.style.display = '';
  w.scrollIntoView({behavior:'smooth'});
}
function handleReplaceDrop(e) { e.preventDefault(); const f=e.dataTransfer.files[0]; if(f&&f.type.startsWith('image/')) doReplace(f); }
function handleReplaceSelect(e) { const f=e.target.files[0]; if(f) doReplace(f); }
async function doReplace(file) {
  const id = document.getElementById('replaceWrap').dataset.tplId;
  if (!id) return;
  const fd=new FormData(); fd.append('TemplateId',id); fd.append('TemplateImage',file); fd.append('TemplateName','_keep_');
  const res=await fetch('../../config/API/endpoints/index.php?action=save_certificate_template',{method:'POST',body:fd});
  const data=await res.json();
  if(data.success){ document.getElementById('replaceWrap').style.display='none'; showToast('s3Toast','✅ Image replaced!','ok'); loadLibrary(); }
  else showModal('Failed: '+data.message, 'error', 'Error');
}
async function deleteTpl(id, btn) {
  showConfirmModal('Delete this template? This cannot be undone.', async function() {
    btn.disabled=true;
    const fd=new FormData(); fd.append('TemplateId',id);
    const res=await fetch('../../config/API/endpoints/index.php?action=delete_certificate_template',{method:'POST',body:fd});
    const data=await res.json();
    if(data.success){ if(savedTplId==id){savedTplId=null;savedTplName='';} loadLibrary(); }
    else{ showModal('Failed: '+data.message, 'error', 'Error'); btn.disabled=false; }
  }, 'Delete Certificate Template', 'danger');
}

/* ── Step 4: Issue ── */
async function loadSummary() {
  if (!selEvId) return;
  document.getElementById('issueCount').textContent='…';
  try {
    const res=await fetch(`../../config/API/endpoints/index.php?action=get_event_participants&event_id=${selEvId}&filter=present`);
    const d=await res.json();
    const cnt = d.count ?? d.total ?? (Array.isArray(d.participants)?d.participants.length:'—');
    document.getElementById('issueCount').textContent = cnt + ' student(s)';
  } catch{ document.getElementById('issueCount').textContent='—'; }
}

async function issueCerts() {
  if (!selEvId)   { showToast('s4Toast','Select an event first (Step 1)','err'); return; }
  if (!savedTplId){ showToast('s4Toast','Save a template first (Step 2)','err'); return; }
  const btn=document.getElementById('issueBtn');
  btn.disabled=true; btn.innerHTML='<ion-icon name="hourglass-outline"></ion-icon> Generating…';
  const fd=new FormData(); fd.append('EventId',selEvId); fd.append('TemplateId',savedTplId);
  try {
    const res=await fetch('../../config/API/endpoints/index.php?action=issue_certificates',{method:'POST',body:fd});
    const data=await res.json();
    if(data.success){
      showToast('s4Toast','Certificates issued! ' + data.message,'ok');
      document.getElementById('issueResult').innerHTML=`
        <div style="padding:14px;background:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0;margin-top:8px;">
          <div style="font-size:14px;font-weight:800;color:#15803d;margin-bottom:6px;"><ion-icon name="school-outline" style="vertical-align:middle;font-size:16px;margin-right:4px;"></ion-icon> Certificates Generated!</div>
          <div style="font-size:13px;color:#166534;margin-bottom:2px;">Event: <strong>${escH(data.event||selEvName)}</strong></div>
          <div style="font-size:13px;color:#166534;margin-bottom:2px;">Issued: <strong>${data.issued}</strong> new &nbsp;|&nbsp; Already existed: <strong>${data.skipped}</strong></div>
          <div style="font-size:13px;color:#166534;margin-bottom:2px;">Font Used: <strong>${escH(data.font_name || 'System Font')}</strong></div>
          ${!data.font_found?'<div style="font-size:12px;color:#92400e;margin-top:6px;"><ion-icon name="warning-outline" style="vertical-align:middle;margin-right:3px;"></ion-icon> No TTF font found on server. Name overlay may not render. Please contact admin to install a font file.</div>':''}
        </div>`;
      setTimeout(()=> { document.getElementById('issueResult').innerHTML=''; }, 8000);
    } else {
      showToast('s4Toast','Error: '+data.message,'err');
    }
  } catch(e) {
    showToast('s4Toast','Network error — check XAMPP is running','err');
  } finally {
    btn.disabled=false;
    btn.innerHTML='<ion-icon name="ribbon-outline"></ion-icon> Issue Certificates Now';
  }
}

/* ── Helpers ── */
function showToast(id, msg, type) {
  const el=document.getElementById(id);
  el.textContent=msg; el.className='toast show '+type;
  setTimeout(()=>el.classList.remove('show'),5000);
}
function escH(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }