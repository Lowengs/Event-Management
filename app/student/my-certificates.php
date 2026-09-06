<?php

session_start();

if (empty($_SESSION['student_id'])) {
    header('Location: login.php'); exit;
}

setcookie('student_dismissed_certificates', '1', time() + 86400 * 30, '/');
$_COOKIE['student_dismissed_certificates'] = '1';

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
        <iframe id="viewerPdf" style="display:none; width:100%; height:75vh; border:none; border-radius:14px;"></iframe>
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

function resolveCertUrl(rawPath) {
    if (!rawPath) return '';
    const s = String(rawPath).trim();
    if (s.startsWith('http://') || s.startsWith('https://') || s.startsWith('data:') || s.startsWith('blob:')) {
        return s;
    }
    const clean = s.replace(/^(\.\.\/)+/, '').replace(/^\//, '');
    return '../../' + clean;
}

function triggerBlobDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 3000);
}

function downloadFileUrl(fileUrl, filename) {
    showToast('Downloading certificate…');
    fetch(fileUrl)
        .then(res => {
            if (!res.ok) throw new Error('Fetch status: ' + res.status);
            return res.blob();
        })
        .then(blob => {
            triggerBlobDownload(blob, filename);
            showToast('Certificate downloaded!');
        })
        .catch(() => {
            const a = document.createElement('a');
            a.href = fileUrl;
            a.download = filename;
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            showToast('Certificate downloaded!');
        });
}

function openViewer(cert) {
    currentCert = cert;
    const overlay = document.getElementById('viewerOverlay');
    const loading = document.getElementById('viewerLoading');
    const wrap = document.getElementById('viewerCanvasWrap');
    const viewerImg = document.getElementById('viewerImg');
    const viewerPdf = document.getElementById('viewerPdf');
    const canvas = document.getElementById('viewerCanvas');

    overlay.classList.add('open');
    loading.style.display = 'flex';
    loading.innerHTML = '<div class="spinner"></div><span>Loading certificate…</span>';
    wrap.style.display = 'none';
    if (viewerImg) viewerImg.style.display = 'none';
    if (viewerPdf) viewerPdf.style.display = 'none';
    if (canvas) canvas.style.display = 'none';

    const candidateRaw = cert.GeneratedImage || cert.CertificateURL || '';
    const candidateUrl = resolveCertUrl(candidateRaw);

    // If it's a PDF document
    if (candidateUrl && candidateUrl.toLowerCase().endsWith('.pdf')) {
        if (viewerPdf) {
            viewerPdf.src = candidateUrl;
            loading.style.display = 'none';
            wrap.style.display = 'flex';
            viewerPdf.style.display = 'block';
            return;
        }
    }

    const cId = cert.CertificateId || cert.CertId || 0;
    const streamUrl = cId ? `../../config/API/endpoints/index.php?action=download_certificate&cert_id=${cId}&preview=1` : candidateUrl;

    if (streamUrl && viewerImg) {
        viewerImg.onload = () => {
            loading.style.display = 'none';
            wrap.style.display = 'flex';
            viewerImg.style.display = 'block';
            if (canvas) canvas.style.display = 'none';
            if (viewerPdf) viewerPdf.style.display = 'none';
        };
        viewerImg.onerror = () => {
            fallbackRenderCanvas(cert);
        };
        viewerImg.src = streamUrl;
        return;
    }

    fallbackRenderCanvas(cert);
}

function fallbackRenderCanvas(cert) {
    const loading = document.getElementById('viewerLoading');
    const wrap = document.getElementById('viewerCanvasWrap');
    const viewerImg = document.getElementById('viewerImg');
    const viewerPdf = document.getElementById('viewerPdf');
    const canvas = document.getElementById('viewerCanvas');

    renderCertificate(cert).then(() => {
        loading.style.display = 'none';
        wrap.style.display = 'flex';
        if (viewerImg) viewerImg.style.display = 'none';
        if (viewerPdf) viewerPdf.style.display = 'none';
        if (canvas) canvas.style.display = 'block';
    }).catch(e => {
        console.error('Certificate render error:', e);
        loading.innerHTML = '<span style="color:#ef4444;"><ion-icon name="alert-circle-outline"></ion-icon> Certificate preview is currently unavailable.</span>';
    });
}

async function renderCertificate(cert) {
    return new Promise((resolve) => {
        const tplRaw = cert.TemplateImage || cert.GeneratedImage || cert.CertificateURL || '';
        const imagePath = resolveCertUrl(tplRaw);

        const canvas = document.getElementById('viewerCanvas');
        if (!canvas) { resolve(); return; }

        const drawDiplomaFallback = () => {
            const w = 1200;
            const h = 750;
            const MAX_W = Math.min(window.innerWidth - 48, 900);
            const scale = MAX_W / w;
            canvas.width = w;
            canvas.height = h;
            canvas.style.width = Math.round(w * scale) + 'px';
            canvas.style.height = Math.round(h * scale) + 'px';
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fdfcf8';
            ctx.fillRect(0, 0, w, h);
            ctx.strokeStyle = '#0f172a';
            ctx.lineWidth = 6;
            ctx.strokeRect(30, 30, w - 60, h - 60);
            ctx.strokeStyle = '#c59b27';
            ctx.lineWidth = 2;
            ctx.strokeRect(40, 40, w - 80, h - 80);

            ctx.fillStyle = '#64748b';
            ctx.font = '16px "Inter", Arial, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('NATIONAL ASSOCIATION OF AVIATION PERSONNEL', w / 2, 110);
            ctx.fillStyle = '#2563eb';
            ctx.font = 'bold 16px "Inter", Arial, sans-serif';
            ctx.fillText(String(cert.OrgName || 'STUDENT AFFAIRS & EVENT MANAGEMENT').toUpperCase(), w / 2, 140);
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 36px "Inter", Arial, sans-serif';
            ctx.fillText('CERTIFICATE OF RECOGNITION', w / 2, 220);
            ctx.fillStyle = '#64748b';
            ctx.font = '16px "Inter", Arial, sans-serif';
            ctx.fillText('This certificate is proudly awarded to', w / 2, 290);
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 44px "Inter", Arial, sans-serif';
            ctx.fillText(STUDENT_NAME, w / 2, 365);
            ctx.fillStyle = '#64748b';
            ctx.font = '16px "Inter", Arial, sans-serif';
            ctx.fillText('for active participation and completion of', w / 2, 430);
            ctx.fillStyle = '#0f172a';
            ctx.font = 'bold 24px "Inter", Arial, sans-serif';
            ctx.fillText('"' + (cert.EventName || 'Event') + '"', w / 2, 480);
            ctx.fillStyle = '#64748b';
            ctx.font = '15px "Inter", Arial, sans-serif';
            const dateStr = cert.EventDateTime ? new Date(cert.EventDateTime).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'}) : '';
            ctx.fillText('Conferred on ' + (dateStr || 'Concluded Event'), w / 2, 540);
            ctx.fillStyle = '#c59b27';
            ctx.font = '13px monospace';
            ctx.fillText('Certificate ID: ' + (cert.CertCode || 'NAAP-CERT'), w / 2, 680);
            resolve(canvas);
        };

        if (!imagePath || imagePath.toLowerCase().endsWith('.pdf')) {
            drawDiplomaFallback();
            return;
        }

        const img = new Image();
        img.onload = () => {
            const MAX_W  = Math.min(window.innerWidth - 48, 900);
            const scale  = MAX_W / (img.width || 900);
            canvas.width  = img.width;
            canvas.height = img.height;
            canvas.style.width  = Math.round(img.width  * scale) + 'px';
            canvas.style.height = Math.round(img.height * scale) + 'px';

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);

            let fields = cert.FieldConfig || [];
            if (typeof fields === 'string') {
                try { fields = JSON.parse(fields); } catch(e) { fields = []; }
            }

            let nameDrawn = false;
            if (Array.isArray(fields) && fields.length > 0) {
                fields.forEach(f => {
                    let text = (f.value || f.label || '');
                    if (f.id === 'student_name' || (f.label && String(f.label).toLowerCase().includes('name'))) {
                        text = STUDENT_NAME;
                        nameDrawn = true;
                    } else {
                        text = String(text)
                            .replace(/\{\{student_name\}\}/gi, STUDENT_NAME)
                            .replace(/\{\{event_name\}\}/gi,   cert.EventName || '')
                            .replace(/\{\{org_name\}\}/gi,     cert.OrgName || '')
                            .replace(/\{\{event_date\}\}/gi,   cert.EventDateTime ? new Date(cert.EventDateTime).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'}) : '');
                    }

                    let rawX = parseFloat(f.x !== undefined ? f.x : 0.5);
                    let rawY = parseFloat(f.y !== undefined ? f.y : 0.48);
                    let xPct = rawX > 1 ? (rawX / 100) : rawX;
                    let yPct = rawY > 1 ? (rawY / 100) : rawY;

                    const px = Math.round(xPct * canvas.width);
                    const py = Math.round(yPct * canvas.height);
                    const baseFontSize = parseInt(f.fontSize || 46, 10);
                    const fs = Math.round(baseFontSize * (canvas.width / 1200));

                    let fontStr = '';
                    if (f.italic) fontStr += 'italic ';
                    if (f.bold)   fontStr += 'bold ';
                    fontStr += (fs || 32) + 'px "' + (f.fontFamily || 'Inter') + '", Arial, sans-serif';

                    ctx.font      = fontStr;
                    ctx.fillStyle = f.color || '#1e293b';
                    ctx.textAlign = f.align || 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(text, px, py);
                });
            }

            if (!nameDrawn && STUDENT_NAME) {
                const fs = Math.round(48 * (canvas.width / 1200));
                ctx.font = 'bold ' + fs + 'px "Inter", Arial, sans-serif';
                ctx.fillStyle = '#1e293b';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(STUDENT_NAME, Math.round(canvas.width * 0.5), Math.round(canvas.height * 0.48));
            }

            resolve(canvas);
        };
        img.onerror = () => {
            drawDiplomaFallback();
        };
        img.src = imagePath;
    });
}

function openAndDownload(cert) {
    currentCert = cert;
    const cId = cert.CertificateId || cert.CertId || 0;
    if (cId) {
        showToast('Downloading certificate…');
        window.location.href = `../../config/API/endpoints/index.php?action=download_certificate&cert_id=${cId}`;
        return;
    }
    const candidateRaw = cert.GeneratedImage || cert.CertificateURL || '';
    const candidateUrl = resolveCertUrl(candidateRaw);
    if (candidateUrl) {
        window.location.href = candidateUrl;
    }
}

function downloadViewer() {
    if (!currentCert) return;
    const cId = currentCert.CertificateId || currentCert.CertId || 0;
    if (cId) {
        showToast('Downloading certificate…');
        window.location.href = `../../config/API/endpoints/index.php?action=download_certificate&cert_id=${cId}`;
        return;
    }
    if (!currentCert) return;
    const baseName = (currentCert.EventName || 'NAAP').replace(/[^a-zA-Z0-9_-]/g, '_');
    const filename = 'certificate-' + baseName;

    const canvas = document.getElementById('viewerCanvas');
    const viewerImg = document.getElementById('viewerImg');
    const viewerPdf = document.getElementById('viewerPdf');

    // 1. If canvas is currently showing, export the canvas directly (guarantees student name)
    if (canvas && canvas.style.display !== 'none' && canvas.width > 0) {
        showToast('Exporting certificate…');
        canvas.toBlob(blob => {
            if (blob) {
                triggerBlobDownload(blob, filename + '.png');
                showToast('Certificate downloaded!');
            } else {
                const a = document.createElement('a');
                a.href = canvas.toDataURL('image/png');
                a.download = filename + '.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                showToast('Certificate downloaded!');
            }
        }, 'image/png');
        return;
    }

    // 2. If PDF viewer is displaying
    if (viewerPdf && viewerPdf.style.display !== 'none' && viewerPdf.src) {
        downloadFileUrl(viewerPdf.src, filename + '.pdf');
        return;
    }

    // 3. If direct image is displaying and loaded successfully
    if (viewerImg && viewerImg.style.display !== 'none' && viewerImg.src) {
        downloadFileUrl(viewerImg.src, filename + '.png');
        return;
    }

    // Fallback: render canvas then download
    showToast('Generating certificate…');
    renderCertificate(currentCert).then(c => {
        c.toBlob(blob => {
            if (blob) {
                triggerBlobDownload(blob, filename + '.png');
                showToast('Certificate downloaded!');
            } else {
                const a = document.createElement('a');
                a.href = c.toDataURL('image/png');
                a.download = filename + '.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                showToast('Certificate downloaded!');
            }
        }, 'image/png');
    }).catch(() => {
        const candidateUrl = resolveCertUrl(currentCert.GeneratedImage || currentCert.CertificateURL || currentCert.TemplateImage || '');
        if (candidateUrl) downloadFileUrl(candidateUrl, filename + (candidateUrl.toLowerCase().endsWith('.pdf') ? '.pdf' : '.png'));
    });
}

function closeViewer() {
    const overlay = document.getElementById('viewerOverlay');
    if (overlay) overlay.classList.remove('open');
    const viewerPdf = document.getElementById('viewerPdf');
    if (viewerPdf) viewerPdf.src = 'about:blank';
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
    document.cookie = "student_dismissed_certificates=1; path=/; max-age=2592000; SameSite=Lax";
    loadCerts();
});
</script>
</body>
</html>
