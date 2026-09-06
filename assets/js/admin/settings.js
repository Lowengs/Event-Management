/**
 * NAAP Admin Settings - Password Security & Strength
 */

function showToast(msg, type = 'info') {
  const c = document.getElementById('toastContainer');
  if (!c) return;
  const t = document.createElement('div');
  t.className = 'toast toast-' + (type === 'error' ? 'danger' : type);
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('changePasswordForm');
  const newPwInput = document.getElementById('newPassword');
  const confirmPwInput = document.getElementById('confirmPassword');
  const currentPwInput = document.getElementById('currentPassword');
  const strengthFill = document.getElementById('pwStrengthFill');
  const strengthLabel = document.getElementById('pwStrengthLabel');
  const matchFeedback = document.getElementById('pwMatchFeedback');
  const btn = document.getElementById('changePwBtn');

  const critLength = document.getElementById('critLength');
  const critUpper = document.getElementById('critUpper');
  const critLower = document.getElementById('critLower');
  const critNumber = document.getElementById('critNumber');
  const critSpecial = document.getElementById('critSpecial');

  // 1. Global Bulletproof Password Visibility Toggle (Eye icon)
  window.togglePasswordVisibility = function(a, b) {
    let inputId, btn;
    if (typeof a === 'string') {
      inputId = a;
      btn = b;
    } else {
      btn = a;
      inputId = b;
    }
    const input = typeof inputId === 'string' ? document.getElementById(inputId) : (btn ? btn.parentElement.querySelector('input') : null);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    if (btn) {
      const openSvg = btn.querySelector('.eye-open');
      const closedSvg = btn.querySelector('.eye-closed');
      if (openSvg && closedSvg) {
        openSvg.style.display = isPassword ? 'none' : 'block';
        closedSvg.style.display = isPassword ? 'block' : 'none';
      }
      const icon = btn.querySelector('ion-icon');
      if (icon) {
        icon.setAttribute('name', isPassword ? 'eye-off-outline' : 'eye-outline');
      }
    }
  };

  document.querySelectorAll('.pw-toggle-btn').forEach(toggleBtn => {
    toggleBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = toggleBtn.dataset.target;
      togglePasswordVisibility(targetId, toggleBtn);
    });
  });

  // 2. Real-time Password Strength Evaluator
  function updateCriteriaItem(el, isValid) {
    if (!el) return;
    const icon = el.querySelector('ion-icon');
    if (isValid) {
      el.classList.add('valid');
      el.classList.remove('invalid');
      if (icon) icon.setAttribute('name', 'checkmark-circle-outline');
    } else {
      el.classList.remove('valid');
      el.classList.add('invalid');
      if (icon) icon.setAttribute('name', 'close-circle-outline');
    }
  }

  function evaluatePasswordStrength(password) {
    if (!password) {
      if (strengthFill) {
        strengthFill.style.width = '0%';
        strengthFill.style.backgroundColor = '#ef4444';
      }
      if (strengthLabel) {
        strengthLabel.textContent = 'Too Short';
        strengthLabel.style.background = '#fee2e2';
        strengthLabel.style.color = '#dc2626';
      }
      [critLength, critUpper, critLower, critNumber, critSpecial].forEach(el => updateCriteriaItem(el, false));
      return { score: 0, isValid: false };
    }

    const hasLength = password.length >= 8;
    const hasUpper = /[A-Z]/.test(password);
    const hasLower = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[^A-Za-z0-9]/.test(password);

    updateCriteriaItem(critLength, hasLength);
    updateCriteriaItem(critUpper, hasUpper);
    updateCriteriaItem(critLower, hasLower);
    updateCriteriaItem(critNumber, hasNumber);
    updateCriteriaItem(critSpecial, hasSpecial);

    let score = 0;
    if (hasLength) score++;
    if (hasUpper) score++;
    if (hasLower) score++;
    if (hasNumber) score++;
    if (hasSpecial) score++;
    if (password.length >= 12) score++;

    let percent = 0;
    let label = 'Weak';
    let bg = '#ef4444';
    let badgeBg = '#fee2e2';
    let badgeColor = '#dc2626';

    if (!hasLength) {
      percent = Math.min(25, password.length * 3);
      label = 'Weak (Min. 8 chars)';
      bg = '#ef4444';
      badgeBg = '#fee2e2';
      badgeColor = '#dc2626';
    } else if (score <= 2) {
      percent = 25;
      label = 'Weak';
      bg = '#ef4444';
      badgeBg = '#fee2e2';
      badgeColor = '#dc2626';
    } else if (score === 3) {
      percent = 50;
      label = 'Moderate';
      bg = '#f59e0b';
      badgeBg = '#fef3c7';
      badgeColor = '#d97706';
    } else if (score === 4) {
      percent = 75;
      label = 'Good';
      bg = '#0284c7';
      badgeBg = '#e0f2fe';
      badgeColor = '#0284c7';
    } else {
      percent = 100;
      label = password.length >= 12 ? 'Very Strong' : 'Strong';
      bg = '#16a34a';
      badgeBg = '#dcfce7';
      badgeColor = '#166534';
    }

    if (strengthFill) {
      strengthFill.style.width = percent + '%';
      strengthFill.style.backgroundColor = bg;
    }
    if (strengthLabel) {
      strengthLabel.textContent = label;
      strengthLabel.style.background = badgeBg;
      strengthLabel.style.color = badgeColor;
    }

    const allPassed = hasLength && hasUpper && hasLower && hasNumber && hasSpecial;
    return { score, isValid: allPassed };
  }

  function checkPasswordMatch() {
    if (!matchFeedback || !confirmPwInput || !newPwInput) return;
    const p1 = newPwInput.value;
    const p2 = confirmPwInput.value;

    if (!p2) {
      matchFeedback.style.display = 'none';
      return;
    }

    matchFeedback.style.display = 'block';
    if (p1 === p2) {
      matchFeedback.style.color = '#16a34a';
      matchFeedback.innerHTML = '<ion-icon name="checkmark-circle-outline" style="vertical-align:middle;"></ion-icon> Passwords match';
    } else {
      matchFeedback.style.color = '#dc2626';
      matchFeedback.innerHTML = '<ion-icon name="close-circle-outline" style="vertical-align:middle;"></ion-icon> Passwords do not match';
    }
  }

  if (newPwInput) {
    newPwInput.addEventListener('input', () => {
      evaluatePasswordStrength(newPwInput.value);
      checkPasswordMatch();
    });
  }

  if (confirmPwInput) {
    confirmPwInput.addEventListener('input', checkPasswordMatch);
  }

  function resetStrengthMeter() {
    evaluatePasswordStrength('');
    if (matchFeedback) matchFeedback.style.display = 'none';
  }

  // 3. Change Password Submission & Confirmation
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();

      const curPw = currentPwInput ? currentPwInput.value.trim() : '';
      const newPw = newPwInput ? newPwInput.value : '';
      const confirmPw = confirmPwInput ? confirmPwInput.value : '';

      if (!curPw) {
        if (typeof showModal === 'function') {
          showModal('Please enter your current password.', 'warning', 'Current Password Required');
        } else {
          showToast('Please enter your current password.', 'error');
        }
        return;
      }

      const evalResult = evaluatePasswordStrength(newPw);
      if (!evalResult.isValid) {
        if (typeof showModal === 'function') {
          showModal(
            'New password must meet all superadmin security requirements:<br><ul style="text-align:left;margin:8px 0 0 16px;padding:0;font-size:0.85rem;line-height:1.6;">' +
            '<li>Minimum 8–12 characters</li>' +
            '<li>At least one uppercase letter (A–Z)</li>' +
            '<li>At least one lowercase letter (a–z)</li>' +
            '<li>At least one number (0–9)</li>' +
            '<li>At least one special character (!@#$%^&*...)</li>' +
            '</ul>',
            'warning',
            'Password Requirements Not Met'
          );
        } else {
          showToast('Password must be at least 8 characters with uppercase, lowercase, number, and special character.', 'error');
        }
        return;
      }

      if (newPw !== confirmPw) {
        if (typeof showModal === 'function') {
          showModal('The confirmation password does not match the new password. Please verify and try again.', 'error', 'Passwords Do Not Match');
        } else {
          showToast('New passwords do not match.', 'error');
        }
        return;
      }

      // Prompt Confirmation Modal
      if (typeof showConfirmModal === 'function') {
        showConfirmModal(
          'Are you sure you want to update your password?',
          async () => {
            await executePasswordChange();
          },
          'Change Password?',
          'warning'
        );

        // Customize confirm button text to "Change Password"
        const confirmOkBtn = document.getElementById('customConfirmOkBtn');
        if (confirmOkBtn) {
          confirmOkBtn.textContent = 'Change Password';
          confirmOkBtn.style.background = '#003366';
        }
      } else {
        if (confirm('Change Password?\nAre you sure you want to update your password?')) {
          executePasswordChange();
        }
      }
    });
  }

  async function executePasswordChange() {
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<ion-icon name="hourglass-outline"></ion-icon> Updating…';
    }

    const fd = new FormData(form);
    try {
      const res = await fetch('../../config/API/endpoints/index.php?action=change_admin_password', {
        method: 'POST',
        body: fd
      });
      const data = await res.json();

      if (data.success) {
        if (typeof showModal === 'function') {
          showModal('Your password has been changed.', 'success', 'Password Updated Successfully', () => {
            form.reset();
            resetStrengthMeter();
          });
        } else {
          showToast(data.message || 'Your password has been changed.', 'success');
          form.reset();
          resetStrengthMeter();
        }
      } else {
        if (typeof showModal === 'function') {
          showModal(data.message || 'Failed to update password.', 'error', 'Error');
        } else {
          showToast(data.message || 'Failed to update password.', 'error');
        }
      }
    } catch (err) {
      if (typeof showModal === 'function') {
        showModal('Network error. Please check your connection and try again.', 'error', 'Network Error');
      } else {
        showToast('Network error. Please try again.', 'error');
      }
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<ion-icon name="key-outline"></ion-icon> Update Password';
      }
    }
  }
});