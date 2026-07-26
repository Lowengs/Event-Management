/* ── Extracted from organization/settings_org.php ── */
function showToast(msg,ok=true){ const t=document.getElementById('toast'); t.textContent=msg; t.style.background=ok?'#16a34a':'#dc2626'; t.style.display='block'; setTimeout(()=>t.style.display='none',3500); }

// Image previews
document.getElementById('logoInput').addEventListener('change',e=>{
  const f=e.target.files[0]; if(f) document.getElementById('logoPreview').src=URL.createObjectURL(f);
});
document.getElementById('bannerInput').addEventListener('change',e=>{
  const f=e.target.files[0]; if(f) document.getElementById('bannerPreview').src=URL.createObjectURL(f);
});

// Profile form
document.getElementById('profileForm').addEventListener('submit',e=>{
  e.preventDefault();
  const btn=e.target.querySelector('button[type=submit]'); btn.textContent='Saving…'; btn.disabled=true;
  const fd=new FormData(e.target);
  // Add banner file if selected
  const bannerFile=document.getElementById('bannerInput').files[0];
  if(bannerFile) fd.set('OrgBanner',bannerFile);
  fetch('../../config/API/update_org_settings.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      showToast(d.message,d.success);
      btn.textContent='Save Profile'; btn.disabled=false;
      if(d.success&&d.org_pic) document.getElementById('logoPreview').src='../../'+d.org_pic;
    });
});

// Password form
document.getElementById('passwordForm').addEventListener('submit',e=>{
  e.preventDefault();
  if(document.getElementById('newPass').value!==document.getElementById('conPass').value){ showToast('Passwords do not match',false); return; }
  const btn=e.target.querySelector('button[type=submit]'); btn.textContent='Updating…'; btn.disabled=true;
  const fd=new FormData(e.target);
  fetch('../../config/API/update_org_password.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      showToast(d.message,d.success);
      btn.textContent='Update Password'; btn.disabled=false;
      if(d.success) e.target.reset();
    });
});