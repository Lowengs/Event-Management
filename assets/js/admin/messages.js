/* ── Extracted from osa/messages.php ── */
function filterMessages() {
    const q = document.getElementById('msgSearch').value.toLowerCase();
    document.querySelectorAll('#messagesList .message-item').forEach(item => {
      const name = item.dataset.name || '';
      item.style.display = name.includes(q) ? '' : 'none';
    });
  }
  // Auto-scroll thread to bottom
  const tc = document.getElementById('threadContainer');
  if (tc) tc.scrollTop = tc.scrollHeight;