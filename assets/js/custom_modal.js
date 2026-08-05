/**
 * Modern Custom Modal Alert System
 * Replaces browser native alert() with a responsive, accessible modal dialog.
 */
(function() {
  function createModalDOM() {
    if (document.getElementById('customAlertModalOverlay')) return;

    const overlay = document.createElement('div');
    overlay.id = 'customAlertModalOverlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(15,23,42,0.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:99999;display:none;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;opacity:0;transition:opacity 0.25s ease;';

    overlay.innerHTML = `
      <div id="customAlertModalBox" style="background:#ffffff;border-radius:16px;max-width:440px;width:100%;padding:24px;box-shadow:0 20px 40px rgba(0,0,0,0.2);transform:scale(0.9);transition:transform 0.25s ease;font-family:'Inter',system-ui,sans-serif;color:#1e293b;position:relative;box-sizing:border-box;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
          <div id="customAlertIconContainer" style="width:40px;height:40px;border-radius:10px;background:#eff6ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;">
            <span id="customAlertIconText" style="font-size:20px;line-height:1;">ℹ️</span>
          </div>
          <h3 id="customAlertTitle" style="margin:0;font-size:1.1rem;font-weight:700;color:#0f172a;line-height:1.3;">Notification</h3>
        </div>
        <p id="customAlertMessage" style="margin:0 0 20px;font-size:0.92rem;color:#475569;line-height:1.5;white-space:pre-wrap;word-break:break-word;"></p>
        <div style="display:flex;justify-content:flex-end;">
          <button id="customAlertBtn" style="padding:9px 22px;background:#2563eb;color:#ffffff;border:none;border-radius:10px;font-size:0.88rem;font-weight:600;cursor:pointer;transition:all 0.2s ease;outline:none;box-shadow:0 4px 12px rgba(37,99,235,0.25);">
            OK
          </button>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);

    const btn = document.getElementById('customAlertBtn');
    if (btn) {
      btn.addEventListener('mouseenter', () => btn.style.transform = 'translateY(-1px)');
      btn.addEventListener('mouseleave', () => btn.style.transform = 'translateY(0)');
    }
  }

  window.showModal = function(message, type = 'info', title = '', callback = null) {
    if (!document.body) {
      document.addEventListener('DOMContentLoaded', () => window.showModal(message, type, title, callback));
      return;
    }

    createModalDOM();
    const overlay = document.getElementById('customAlertModalOverlay');
    const modalBox = document.getElementById('customAlertModalBox');
    const msgEl = document.getElementById('customAlertMessage');
    const titleEl = document.getElementById('customAlertTitle');
    const iconContainer = document.getElementById('customAlertIconContainer');
    const iconText = document.getElementById('customAlertIconText');
    const btn = document.getElementById('customAlertBtn');

    let defaultTitle = 'Notification';
    if (type === 'error' || type === 'danger') defaultTitle = 'Error';
    else if (type === 'success') defaultTitle = 'Success';
    else if (type === 'warning') defaultTitle = 'Warning';

    titleEl.textContent = title || defaultTitle;
    msgEl.textContent = message || '';

    if (type === 'error' || type === 'danger') {
      iconContainer.style.background = '#fef2f2';
      iconContainer.style.color = '#ef4444';
      if (iconText) iconText.textContent = '⚠️';
      btn.style.background = '#ef4444';
      btn.style.boxShadow = '0 4px 12px rgba(239,68,68,0.25)';
    } else if (type === 'success') {
      iconContainer.style.background = '#ecfdf5';
      iconContainer.style.color = '#10b981';
      if (iconText) iconText.textContent = '✅';
      btn.style.background = '#10b981';
      btn.style.boxShadow = '0 4px 12px rgba(16,185,129,0.25)';
    } else if (type === 'warning') {
      iconContainer.style.background = '#fffbeb';
      iconContainer.style.color = '#f59e0b';
      if (iconText) iconText.textContent = '⚠️';
      btn.style.background = '#f59e0b';
      btn.style.boxShadow = '0 4px 12px rgba(245,158,11,0.25)';
    } else {
      iconContainer.style.background = '#eff6ff';
      iconContainer.style.color = '#2563eb';
      if (iconText) iconText.textContent = 'ℹ️';
      btn.style.background = '#2563eb';
      btn.style.boxShadow = '0 4px 12px rgba(37,99,235,0.25)';
    }

    overlay.style.display = 'flex';
    requestAnimationFrame(() => {
      overlay.style.opacity = '1';
      modalBox.style.transform = 'scale(1)';
    });

    function close() {
      overlay.style.opacity = '0';
      modalBox.style.transform = 'scale(0.9)';
      setTimeout(() => {
        overlay.style.display = 'none';
        btn.removeEventListener('click', close);
        if (typeof callback === 'function') callback();
      }, 250);
    }

    btn.onclick = close;
  };

  // Backwards compatibility alias
  window.showAlertModal = function(message, title = 'Notification', type = 'info', callback = null) {
    window.showModal(message, type, title, callback);
  };

  // Global browser alert override to ensure no native alerts bypass the modal
  window.alert = function(message) {
    window.showModal(message, 'info');
  };
})();
