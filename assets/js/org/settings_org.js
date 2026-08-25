/* ── Extracted from organization/settings_org.php ── */
function showToast(msg, ok = true) {
  let t = document.getElementById('toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'toast';
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 20px;border-radius:10px;color:#fff;font-weight:700;font-size:13.5px;box-shadow:0 10px 30px rgba(0,0,0,0.4);display:none;';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.background = ok ? '#16a34a' : '#dc2626';
  t.style.display = 'block';
  setTimeout(() => { t.style.display = 'none'; }, 3500);
}

// Image previews
const logoInput = document.getElementById('logoInput');
if (logoInput) {
  logoInput.addEventListener('change', e => {
    const f = e.target.files[0];
    if (f) document.getElementById('logoPreview').src = URL.createObjectURL(f);
  });
}

const bannerInput = document.getElementById('bannerInput');
if (bannerInput) {
  bannerInput.addEventListener('change', e => {
    const f = e.target.files[0];
    if (f) document.getElementById('bannerPreview').src = URL.createObjectURL(f);
  });
}

// Profile form
const profileForm = document.getElementById('profileForm');
if (profileForm) {
  profileForm.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type=submit]');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="btn-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.6s linear infinite;margin-right:6px;vertical-align:middle;"></span> Saving Changes...';
    btn.disabled = true;

    try {
      const fd = new FormData(e.target);
      const bannerFile = document.getElementById('bannerInput')?.files[0];
      if (bannerFile) fd.set('OrgBanner', bannerFile);
      const logoFile = document.getElementById('logoInput')?.files[0];
      if (logoFile) fd.set('OrgPicture', logoFile);

      const res = await fetch('../../config/API/endpoints/index.php?action=update_org_settings', {
        method: 'POST',
        body: fd
      });
      const d = await res.json();

      showToast(d.message || (d.success ? 'Profile updated!' : 'Failed to update'), d.success);

      if (d.success) {
        const cacheBuster = Date.now();
        if (d.org_pic) {
          const picUrl = (d.org_pic.startsWith('http') || d.org_pic.startsWith('../../')) ? d.org_pic : '../../' + d.org_pic.replace(/^\/+/, '');
          const logoPreview = document.getElementById('logoPreview');
          if (logoPreview) logoPreview.src = picUrl + '?v=' + cacheBuster;
          document.querySelectorAll('.org-avatar img, .sidebar-logo img').forEach(img => {
            img.src = picUrl + '?v=' + cacheBuster;
          });
        }
        if (d.org_banner) {
          const bannerUrl = (d.org_banner.startsWith('http') || d.org_banner.startsWith('../../')) ? d.org_banner : '../../' + d.org_banner.replace(/^\/+/, '');
          const bannerPreview = document.getElementById('bannerPreview');
          if (bannerPreview) bannerPreview.src = bannerUrl + '?v=' + cacheBuster;
        }
        if (d.org_name) {
          const headerName = document.querySelector('.profile-meta h2');
          if (headerName) headerName.textContent = d.org_name;
        }

        btn.innerHTML = '<ion-icon name="checkmark-circle" style="vertical-align:middle;font-size:16px;margin-right:4px;"></ion-icon> Saved to Database ✓';
        btn.style.background = '#16a34a';
        setTimeout(() => {
          btn.innerHTML = origHtml;
          btn.style.background = '';
          btn.disabled = false;
        }, 2200);
      } else {
        btn.innerHTML = '<ion-icon name="alert-circle-outline" style="vertical-align:middle;font-size:16px;margin-right:4px;"></ion-icon> Update Failed';
        btn.style.background = '#dc2626';
        setTimeout(() => {
          btn.innerHTML = origHtml;
          btn.style.background = '';
          btn.disabled = false;
        }, 2200);
      }
    } catch (err) {
      showToast('Network error while saving settings', false);
      btn.innerHTML = origHtml;
      btn.disabled = false;
    }
  });
}

// Password form
const passwordForm = document.getElementById('passwordForm');
if (passwordForm) {
  passwordForm.addEventListener('submit', async e => {
    e.preventDefault();
    const newPass = document.getElementById('newPass')?.value;
    const conPass = document.getElementById('conPass')?.value;
    if (newPass !== conPass) {
      showToast('Passwords do not match', false);
      return;
    }
    const btn = e.target.querySelector('button[type=submit]');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span class="btn-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.6s linear infinite;margin-right:6px;vertical-align:middle;"></span> Updating Password...';
    btn.disabled = true;

    try {
      const fd = new FormData(e.target);
      const res = await fetch('../../config/API/endpoints/index.php?action=update_org_password', {
        method: 'POST',
        body: fd
      });
      const d = await res.json();
      showToast(d.message || (d.success ? 'Password updated!' : 'Failed to update'), d.success);

      if (d.success) {
        e.target.reset();
        btn.innerHTML = '<ion-icon name="checkmark-circle" style="vertical-align:middle;font-size:16px;margin-right:4px;"></ion-icon> Password Updated ✓';
        btn.style.background = '#16a34a';
        setTimeout(() => {
          btn.innerHTML = origHtml;
          btn.style.background = '';
          btn.disabled = false;
        }, 2200);
      } else {
        btn.innerHTML = '<ion-icon name="alert-circle-outline" style="vertical-align:middle;font-size:16px;margin-right:4px;"></ion-icon> Update Failed';
        btn.style.background = '#dc2626';
        setTimeout(() => {
          btn.innerHTML = origHtml;
          btn.style.background = '';
          btn.disabled = false;
        }, 2200);
      }
    } catch (err) {
      showToast('Network error updating password', false);
      btn.innerHTML = origHtml;
      btn.disabled = false;
    }
  });
}

// Password visibility toggle
document.querySelectorAll('.pw-toggle-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    const targetId = btn.dataset.target;
    const input = targetId ? document.getElementById(targetId) : btn.parentElement.querySelector('input');
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    if (isPassword) {
      input.classList.add('has-pw-toggle');
    } else {
      input.classList.remove('has-pw-toggle');
    }
    const icon = btn.querySelector('ion-icon');
    if (icon) {
      icon.setAttribute('name', isPassword ? 'eye-off-outline' : 'eye-outline');
    }
  });
});
