<?php

session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php'); exit;
}

$userId = (int)$_SESSION['student_id'];
$student = $conn->query("
    SELECT u.first_name, u.last_name, u.student_id AS student_no, u.course, u.year_level, u.section
    FROM user u WHERE u.UserId = $userId LIMIT 1
")->fetch_assoc();

if (!$student) { header('Location: login.php'); exit; }

$fullName  = trim($student['first_name'] . ' ' . $student['last_name']);
$studentNo = $student['student_no'] ?? 'N/A';
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
        <canvas id="viewerCanvas"></canvas>
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
        const res  = await fetch('../../config/API/get_student_certificates.php?_t=' + Date.now());
        const data = await res.json();
        renderCerts(data.certificates || []);
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
        // Event Section Header
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
        grid.style.padding = '0'; // reset padding for grouped layout

        eventCerts.forEach(c => {
            const card = document.createElement('div');
            card.className = 'cert-card';
            const imgSrc = '../../' + (c.GeneratedImage || c.TemplateImage);
            const issueDate = new Date(c.IssuedAt).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
            const evDate    = new Date(c.EventDateTime).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });

            card.innerHTML = `
              <div class="cert-card-strip"></div>
              <div class="cert-card-preview">
                <img src="${imgSrc}" alt="${escHtml(c.TemplateName)}" onerror="this.style.display='none'">
                <div class="cert-name-overlay"><span>${escHtml(STUDENT_NAME)}</span></div>
              </div>
              <div class="cert-card-body">
                <div class="cert-org">${escHtml(c.OrgName)}</div>
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

/* ── Certificate Canvas Renderer ─────── */
async function renderCertificate(cert) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            const canvas = document.getElementById('viewerCanvas');
            const MAX_W  = Math.min(window.innerWidth - 48, 900);
            const scale  = MAX_W / img.width;
            canvas.width  = img.width;
            canvas.height = img.height;
            canvas.style.width  = Math.round(img.width  * scale) + 'px';
            canvas.style.height = Math.round(img.height * scale) + 'px';

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);

            // Render each field
            const fields = cert.FieldConfig || [];
            fields.forEach(f => {
                // Resolve placeholders
                let text = (f.value || f.label || '');
                text = text
                    .replace('{{student_name}}', STUDENT_NAME)
                    .replace('{{event_name}}',   cert.EventName)
                    .replace('{{org_name}}',     cert.OrgName)
                    .replace('{{event_date}}',   new Date(cert.EventDateTime).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'}));

                const px = Math.round((f.x || .5) * canvas.width);
                const py = Math.round((f.y || .5) * canvas.height);
                const fs = f.fontSize || 24;

                let fontStr = '';
                if (f.italic) fontStr += 'italic ';
                if (f.bold)   fontStr += 'bold ';
                fontStr += fs + 'px "' + (f.fontFamily || 'Inter') + '", sans-serif';

                ctx.font      = fontStr;
                ctx.fillStyle = f.color || '#1e293b';
                ctx.textAlign = f.align || 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(text, px, py);
            });
            resolve(canvas);
        };
        img.onerror = () => reject(new Error('Failed to load template image'));
        img.src = '../../' + cert.TemplateImage;
    });
}

function openViewer(cert) {
    currentCert = cert;
    document.getElementById('viewerOverlay').classList.add('open');
    document.getElementById('viewerLoading').style.display = 'flex';
    document.getElementById('viewerCanvasWrap').style.display = 'none';
    renderCertificate(cert).then(() => {
        document.getElementById('viewerLoading').style.display = 'none';
        document.getElementById('viewerCanvasWrap').style.display = '';
    }).catch(e => {
        document.getElementById('viewerLoading').innerHTML = '<span style="color:#ef4444;">Failed to render. Template image may be unavailable.</span>';
    });
}

async function openAndDownload(cert) {
    currentCert = cert;
    showToast('Generating certificate…');
    try {
        await renderCertificate(cert);
        downloadViewer();
    } catch(e) {
        showToast('Failed to render certificate image.');
    }
}

function downloadViewer() {
    const canvas = document.getElementById('viewerCanvas');
    if (!canvas || !currentCert) return;
    const a = document.createElement('a');
    a.download = 'certificate-' + (currentCert.EventName || 'NAAP').replace(/\s+/g,'-') + '.png';
    a.href = canvas.toDataURL('image/png');
    a.click();
    showToast('Certificate downloaded!');
}

function closeViewer() {
    document.getElementById('viewerOverlay').classList.remove('open');
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

window.addEventListener('DOMContentLoaded', loadCerts);
</script>
</body>
</html>
