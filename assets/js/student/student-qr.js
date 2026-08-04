/**
 * student-qr.js — QR Code generation and export functionality
 */

let qrInstance = null;

function generateQR() {
    const container = document.getElementById('qrContainer');
    if (!container || typeof qrPayload === 'undefined') return;
    container.innerHTML = '';
    qrInstance = new QRCode(container, {
        text: qrPayload,
        width: 180,
        height: 180,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });
}

function downloadQR() {
    setTimeout(() => {
        const canvas = document.querySelector('#qrContainer canvas');
        if (!canvas) { showToast('QR not ready yet. Please wait a moment.'); return; }

        const dl = document.createElement('canvas');
        dl.width = 420; dl.height = 160;
        const ctx = dl.getContext('2d');

        ctx.fillStyle = '#1e293b';
        ctx.roundRect(0, 0, 420, 160, 16);
        ctx.fill();

        ctx.drawImage(canvas, 12, 12, 136, 136);

        ctx.fillStyle = '#6366f1';
        ctx.font = 'bold 11px Inter, sans-serif';
        ctx.fillText('# ' + (typeof studentNo !== 'undefined' ? studentNo : ''), 162, 30);

        ctx.fillStyle = '#f1f5f9';
        ctx.font = 'bold 16px Inter, sans-serif';
        ctx.fillText(typeof fullName !== 'undefined' ? fullName : '', 162, 52);

        ctx.fillStyle = '#94a3b8';
        ctx.font = '12px Inter, sans-serif';
        ctx.fillText(typeof course !== 'undefined' ? course : '', 162, 72);
        ctx.fillText(typeof orgName !== 'undefined' ? orgName : '', 162, 90);
        ctx.fillText(typeof position !== 'undefined' ? position : '', 162, 108);

        ctx.fillStyle = '#334155';
        ctx.font = '10px Inter, sans-serif';
        ctx.fillText('NAAP Student Organization Portal', 162, 148);

        const a = document.createElement('a');
        a.download = 'naap-qr-' + (typeof studentNo !== 'undefined' ? studentNo : 'card') + '.png';
        a.href = dl.toDataURL('image/png');
        a.click();
        showToast('QR Code downloaded!');
    }, 200);
}

async function shareCard() {
    const canvas = document.querySelector('#qrContainer canvas');
    if (!canvas) { showToast('QR not ready.'); return; }
    if (navigator.share) {
        try {
            const blob = await new Promise(r => canvas.toBlob(r));
            const file = new File([blob], 'my-qr-' + (typeof studentNo !== 'undefined' ? studentNo : '') + '.png', { type: 'image/png' });
            await navigator.share({ title: 'My NAAP Student QR', files: [file] });
        } catch (e) {
            showToast('Could not share. Try downloading instead.');
        }
    } else {
        showToast('Share not supported on this browser. Use Download instead.');
    }
}

function showToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.innerHTML = `<i class='bx bx-check-circle'></i> ${msg}`;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

window.addEventListener('DOMContentLoaded', generateQR);
