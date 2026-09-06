<?php

session_start();

if (empty($_SESSION['student_id'])) {
    header('Location: login.php'); exit;
}

$userId = (int)$_SESSION['student_id'];

// Fetch student profile via API
ob_start();
$_GET['action'] = 'get_student_profile'; require __DIR__ . '/../../config/API/endpoints/index.php';
$profApi = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$student = $profApi['data'] ?? null;

if (!$student) { header('Location: login.php'); exit; }

$fullName  = trim($student['first_name'] . ' ' . $student['last_name']);
$studentNo = $student['student_id'] ?? 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates | NAAP Student Portal</title>
    <meta name="description" content="View and download your earned certificates from NAAP organization events.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&family=Dancing+Script:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <link rel="icon" href="../../assets/img/philsca.png">
    
    <link rel="stylesheet" href="../../assets/css/student/my-certificates.css?<?= time() ?>" />
    <script src="../../assets/js/security.js"></script>
</head>
<body>

<div class="topbar">
    <a href="profile-dashboard.php" class="back-btn">
        <i class='bx bx-arrow-back'></i> Back
    </a>
    <div class="topbar-title">My Certificates</div>
    <div style="width:80px;"></div>
</div>

<div class="page-hero">
    <div class="hero-badge">
        <ion-icon name="ribbon-outline"></ion-icon>
        Achievement Records
    </div>
    <h1 class="hero-title">My Certificates</h1>
    <p class="hero-sub">Certificates you've earned from NAAP organization events. Click any to view and download.</p>
</div>

<div id="certsContainer">
    <div class="spinner-wrap">
        <div class="spinner"></div>
        <span style="font-size:13px;color:#64748b;">Loading your certificates…</span>
    </div>
</div>

<div class="viewer-overlay" id="viewerOverlay" onclick="if(event.target===this)closeViewer()">
    <div class="viewer-loading" id="viewerLoading">
        <div class="spinner"></div>
        <span>Rendering certificate…</span>
    </div>
    <div class="viewer-canvas-wrap" id="viewerCanvasWrap" style="display:none;">
        <img id="viewerImg" alt="Certificate" style="display:none; width:100%; height:auto; border-radius:14px;" />
        <canvas id="viewerCanvas" style="display:none;"></canvas>
    </div>
    <div class="viewer-actions">
        <button class="viewer-btn viewer-btn-dl" onclick="downloadViewer()">
            <i class='bx bx-download'></i> Download PNG
        </button>
        <button class="viewer-btn viewer-btn-close" onclick="closeViewer()">
            <ion-icon name="close-outline"></ion-icon> Close
        </button>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const STUDENT_NAME = <?= json_encode($fullName) ?>;
const STUDENT_NO   = <?= json_encode($studentNo) ?>;
let currentCert    = null;

/* ── Load Certs ─────────────────────────── */
async function loadCerts() {
    try {
        const res  = await fetch('../../config/API/endpoints/index.php?action=get_student_certificates&_t=' + Date.now());
        const data = await res.json();
        renderCerts(data.certificates || data.data || []);
    } catch(e) {
        document.getElementById('certsContainer').innerHTML = `
            <div class="empty-state">
                <ion-icon name="warning-outline"></ion-icon>
                <h3>Could not load certificates</h3>
                <p>Please check your connection and try again.</p>
            </div>`;
    }
}

function renderCerts(certs) {
    const container = document.getElementById('certsContainer');
    if (!certs || certs.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <ion-icon name="ribbon-outline"></ion-icon>
                <h3>No certificates yet</h3>
                <p>Attend and complete NAAP events to earn certificates. They'll appear here once issued by your organization.</p>
            </div>`;
        return;
    }

    // Group certificates by EventName
    const groups = {};
    certs.forEach(c => {
        const evName = c.EventName || 'Other Events';
        if (!groups[evName]) groups[evName] = [];
        groups[evName].push(c);
    });

    container.innerHTML = '';

    for (const [eventName, eventCerts] of Object.entries(groups)) {
        const section = document.createElement('div');
        section.style.marginBottom = '40px';
        
        const header = document.createElement('h3');
        header.style.color = '#e2e8f0';
        header.style.marginBottom = '16px';
        header.style.fontSize = '18px';
        header.style.borderBottom = '1px solid rgba(255,255,255,0.1)';
        header.style.paddingBottom = '10px';
        header.innerHTML = `<ion-icon name="calendar-outline" style="vertical-align:middle;margin-right:6px;color:#a78bfa;"></ion-icon> ${escHtml(eventName)}`;
        section.appendChild(header);

        const grid = document.createElement('div');
        grid.className = 'certs-grid';
        grid.style.padding = '0';

        eventCerts.forEach(c => {
            const card = document.createElement('div');
            card.className = 'cert-card';
            const rawImg = c.GeneratedImage || c.CertificateURL || c.TemplateImage || '';
            const cleanImg = String(rawImg).replace(/^(\.\.\/)+/, '').replace(/^\//, '');
            const imgSrc = cleanImg ? ('../../' + cleanImg) : '';
            const issueDate = new Date(c.IssuedAt).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
            const evDate    = new Date(c.EventDateTime).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });

            card.innerHTML = `
              <div class="cert-card-strip"></div>
              <div class="cert-card-preview">
                <img src="${imgSrc}" alt="${escHtml(c.TemplateName || 'Certificate')}" onerror="this.style.display='none'">
              </div>
              <div class="cert-card-body">
                <div class="cert-org">${escHtml(c.OrgName || 'NAAP')}</div>
                <div class="cert-meta">
                  <div class="cert-meta-item"><ion-icon name="calendar-outline"></ion-icon> ${evDate}</div>
                  <div class="cert-meta-item"><ion-icon name="checkmark-circle-outline" style="color:#10b981;"></ion-icon> Issued ${issueDate}</div>
                </div>
                <div class="cert-code">Code: ${escHtml(c.CertCode).substring(0,20)}…</div>
                <div class="cert-actions" style="margin-top:12px;">
                  <button class="cert-btn cert-btn-view" onclick='openViewer(${JSON.stringify(c).replace(/'/g,"\\'")})'> 
                    <ion-icon name="eye-outline"></ion-icon> Preview
                  </button>
                  <button class="cert-btn cert-btn-dl" onclick='openAndDownload(${JSON.stringify(c).replace(/'/g,"\\'")})'> 
                    <i class='bx bx-download'></i> Download
                  </button>
                </div>
              </div>
            `;
            grid.appendChild(card);
        });

        section.appendChild(grid);
        container.appendChild(section);
    }
}

function openViewer(cert) {
    currentCert = cert;
    const overlay = document.getElementById('viewerOverlay');
    const loading = document.getElementById('viewerLoading');
    const wrap = document.getElementById('viewerCanvasWrap');
    const viewerImg = document.getElementById('viewerImg');
    const canvas = document.getElementById('viewerCanvas');

    overlay.classList.add('open');
    loading.style.display = 'flex';
    loading.innerHTML = '<div class="spinner"></div><span>Loading certificate…</span>';
    wrap.style.display = 'none';
    if (viewerImg) viewerImg.style.display = 'none';
    if (canvas) canvas.style.display = 'none';

    // Prioritize pre-generated personalized certificate image
    const rawImg = cert.GeneratedImage || cert.CertificateURL || cert.TemplateImage || '';
    const cleanImg = String(rawImg).replace(/^(\.\.\/)+/, '').replace(/^\//, '');

    if (cleanImg && viewerImg) {
        const fullSrc = '../../' + cleanImg;
        viewerImg.onload = () => {
            loading.style.display = 'none';
            wrap.style.display = 'flex';
            viewerImg.style.display = 'block';
            if (canvas) canvas.style.display = 'none';
        };
        viewerImg.onerror = () => {
            fallbackRenderCanvas(cert);
        };
        viewerImg.src = fullSrc;
        return;
    }

    fallbackRenderCanvas(cert);
}

function fallbackRenderCanvas(cert) {
    const loading = document.getElementById('viewerLoading');
    const wrap = document.getElementById('viewerCanvasWrap');
    const viewerImg = document.getElementById('viewerImg');
    const canvas = document.getElementById('viewerCanvas');

    renderCertificate(cert).then(() => {
        loading.style.display = 'none';
        wrap.style.display = 'flex';
        if (viewerImg) viewerImg.style.display = 'none';
        if (canvas) canvas.style.display = 'block';
    }).catch(e => {
        console.error('Certificate render error:', e);
        loading.innerHTML = '<span style="color:#ef4444;"><ion-icon name="alert-circle-outline"></ion-icon> Certificate preview is currently unavailable.</span>';
    });
}

async function renderCertificate(cert) {
    return new Promise((resolve, reject) => {
        const rawImg = cert.GeneratedImage || cert.CertificateURL || cert.TemplateImage || '';
        const imagePath = String(rawImg).replace(/^(\.\.\/)+/, '').replace(/^\//, '');
        if (!imagePath) {
            reject(new Error('Certificate image is unavailable'));
            return;
        }

        const img = new Image();
        img.onload = () => {
            const canvas = document.getElementById('viewerCanvas');
            if (!canvas) { resolve(); return; }
            const MAX_W  = Math.min(window.innerWidth - 48, 900);
            const scale  = MAX_W / (img.width || 900);
            canvas.width  = img.width;
            canvas.height = img.height;
            canvas.style.width  = Math.round(img.width  * scale) + 'px';
            canvas.style.height = Math.round(img.height * scale) + 'px';

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);

            // If it's a blank template without GeneratedImage, overlay fields
            if (!cert.GeneratedImage) {
                let fields = cert.FieldConfig || [];
                if (typeof fields === 'string') {
                    try { fields = JSON.parse(fields); } catch(e) { fields = []; }
                }
                (fields || []).forEach(f => {
                    let text = (f.value || f.label || '');
                    text = text
                        .replace('{{student_name}}', STUDENT_NAME)
                        .replace('{{event_name}}',   cert.EventName || '')
                        .replace('{{org_name}}',     cert.OrgName || '')
                        .replace('{{event_date}}',   cert.EventDateTime ? new Date(cert.EventDateTime).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'}) : '');

                    const px = Math.round((f.x || .5) * canvas.width);
                    const py = Math.round((f.y || .5) * canvas.height);
                    const fs = Math.round((f.fontSize || 24) * (canvas.width / 1000));

                    let fontStr = '';
                    if (f.italic) fontStr += 'italic ';
                    if (f.bold)   fontStr += 'bold ';
                    fontStr += (fs || 24) + 'px "' + (f.fontFamily || 'Inter') + '", sans-serif';

                    ctx.font      = fontStr;
                    ctx.fillStyle = f.color || '#1e293b';
                    ctx.textAlign = f.align || 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(text, px, py);
                });
            }
            resolve(canvas);
        };
        img.onerror = () => reject(new Error('Failed to load image: ' + imagePath));
        img.src = '../../' + imagePath;
    });
}

function openAndDownload(cert) {
    currentCert = cert;
    const rawImg = cert.GeneratedImage || cert.CertificateURL || cert.TemplateImage || '';
    const cleanImg = String(rawImg).replace(/^(\.\.\/)+/, '').replace(/^\//, '');
    const filename = 'certificate-' + (cert.EventName || 'NAAP').replace(/[^a-zA-Z0-9_-]/g, '_') + '.png';

    // If direct image file exists, download immediately
    if (cleanImg) {
        showToast('Downloading certificate…');
        const a = document.createElement('a');
        a.href = '../../' + cleanImg;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        showToast('Certificate downloaded!');
        return;
    }

    // Fallback: render to canvas and download data URL
    showToast('Generating certificate…');
    renderCertificate(cert).then(() => {
        downloadViewer();
    }).catch(e => {
        showToast('Failed to download certificate.');
    });
}

function downloadViewer() {
    if (!currentCert) return;
    const rawImg = currentCert.GeneratedImage || currentCert.CertificateURL || currentCert.TemplateImage || '';
    const cleanImg = String(rawImg).replace(/^(\.\.\/)+/, '').replace(/^\//, '');
    const filename = 'certificate-' + (currentCert.EventName || 'NAAP').replace(/[^a-zA-Z0-9_-]/g, '_') + '.png';

    if (cleanImg) {
        const a = document.createElement('a');
        a.href = '../../' + cleanImg;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        showToast('Certificate downloaded!');
        return;
    }

    const canvas = document.getElementById('viewerCanvas');
    if (!canvas) return;
    try {
        const a = document.createElement('a');
        a.download = filename;
        a.href = canvas.toDataURL('image/png');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        showToast('Certificate downloaded!');
    } catch(err) {
        console.error('Canvas export error:', err);
    }
}

function closeViewer() {
    const overlay = document.getElementById('viewerOverlay');
    if (overlay) overlay.classList.remove('open');
    currentCert = null;
}

function showToast(msg) {
    const t = document.getElementById('toast');
    t.innerHTML = `<i class='bx bx-check-circle'></i> ${msg}`;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeViewer();
});

window.addEventListener('DOMContentLoaded', () => {
    localStorage.setItem('student_dismissed_certificates', 'true');
    loadCerts();
});
</script>
</body>
</html>
