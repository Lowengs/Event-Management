<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/img_helpers.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }

$activePage = 'certificates';
$orgId   = (int)$_SESSION['org_id'];
ob_start();
$_GET['action'] = 'get_certificates'; require __DIR__ . '/../../config/API/endpoints/index.php';
$certApiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$events  = $certApiRes['events'] ?? [];
$orgName = $_SESSION['org_name'] ?? 'Organization';

$activeFontName = 'Internal GD Font';
$fontPaths = [
    __DIR__ . '/../../assets/fonts/Inter-Bold.ttf',
    __DIR__ . '/../../assets/fonts/Inter.ttf',
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/Arial.ttf',
    'C:/Windows/Fonts/calibrib.ttf',
    'C:/Windows/Fonts/georgia.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
];
foreach ($fontPaths as $fp) {
    if (file_exists($fp)) {
        $activeFontName = basename($fp);
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Certificate Templates | Org Portal</title>
  <link rel="stylesheet" href="../../assets/css/organization/nav.css">
  <link rel="icon" href="../../assets/img/philsca.png">
  <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@500;700&family=Dancing+Script:wght@600;700&family=Great+Vibes&family=Inter:wght@400;600;700&family=Montserrat:wght@500;700;800&family=Outfit:wght@500;700&family=Playfair+Display:ital,wght@0,600;0,800;1,600&display=swap" rel="stylesheet">
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  
  <link rel="stylesheet" href="../../assets/css/organization/certificate-templates.css?<?= time() ?>" />
</head>
<body>
<div class="dashboard-layout">
  <?php include '_org_sidebar.php'; ?>
  <div class="overlay" id="sidebarOverlay"></div>
  <div class="content-shell">

    <header class="topbar">
      <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn" aria-label="Open menu">
          <ion-icon name="menu-outline"></ion-icon>
        </button>
        <div class="page-title">
          <h2>Certificate Templates</h2>
          <p>Upload and issue certificates for completed events</p>
        </div>
      </div>
      <div class="topbar-right">
        <div class="user-box">
          <img src="<?= imgPathForDepth($orgData['OrgPicture'] ?? '', 2, '../../assets/img/philsca.png') ?>" alt="Org" class="org-logo">
          <div><strong><?= htmlspecialchars($orgName) ?></strong><span>ORG Admin</span></div>
        </div>
      </div>
    </header>

    <div class="page-wrap">

      
      <div class="wz-bar">
        <div class="wz-step"><div class="wz-circle active" id="wc1">1</div><span class="wz-label active" id="wl1">Select Event</span></div>
        <div class="wz-line" id="wln1"></div>
        <div class="wz-step"><div class="wz-circle" id="wc2">2</div><span class="wz-label" id="wl2">Upload Template</span></div>
        <div class="wz-line" id="wln2"></div>
        <div class="wz-step"><div class="wz-circle" id="wc3">3</div><span class="wz-label" id="wl3">Manage Template</span></div>
        <div class="wz-line" id="wln3"></div>
        <div class="wz-step"><div class="wz-circle" id="wc4">4</div><span class="wz-label" id="wl4">Issue Certificate</span></div>
      </div>

      
      <div class="step-view active" id="sv1">
        <div class="card">
          <div class="card-head">
            <ion-icon name="calendar-outline" style="color:#6366f1;font-size:20px;"></ion-icon>
            <h2>Select Completed Event</h2>
          </div>
          <div class="card-body">
            <label class="label" for="evSel">Choose a completed event *</label>
            <select class="sel" id="evSel">
              <?php if (empty($events)): ?>
                <option value="">— No completed events available —</option>
              <?php else: ?>
                <option value="">— Select a completed event —</option>
                <?php foreach ($events as $ev): ?>
                <option value="<?= $ev['EventId'] ?>" data-name="<?= htmlspecialchars($ev['EventName']) ?>">
                  <?= htmlspecialchars($ev['EventName']) ?> — <?= date('M d, Y', strtotime($ev['EventDateTime'])) ?>
                </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
            <div id="s1Toast" class="toast"></div>
            <div class="row-btns">
              <button class="btn btn-primary" id="s1Btn" disabled onclick="goStep(2)">
                Upload New Template <ion-icon name="cloud-upload-outline"></ion-icon>
              </button>
              <button class="btn btn-secondary" id="s1ManageBtn" disabled onclick="goStep(3)" title="Skip upload and go to your saved templates">
                <ion-icon name="albums-outline"></ion-icon> Use Existing Template
              </button>
            </div>
          </div>
        </div>
      </div>

      
      <div class="step-view" id="sv2">
        <div class="card">
          <div class="card-head">
            <ion-icon name="cloud-upload-outline" style="color:#6366f1;font-size:20px;"></ion-icon>
            <h2>Upload Certificate Template</h2>
          </div>
          <div class="card-body">

            <div class="info-box">
              <ion-icon name="information-circle-outline"></ion-icon>
              <div>
                Upload your certificate image. After uploading, <strong>click on the preview</strong> to mark exactly <strong>where the student's name</strong> should appear. The system will overlay each student's name at that spot when generating their certificate.
              </div>
            </div>

            <div style="margin-bottom:12px;font-size:12px;color:#475569;background:#f1f5f9;padding:8px 14px;border-radius:8px;display:flex;align-items:center;gap:6px;width:fit-content;">
              <ion-icon name="text-outline" style="color:#6366f1;font-size:15px;"></ion-icon>
              Active Font Engine: <strong style="color:#0f172a;"><?= htmlspecialchars($activeFontName) ?></strong>
            </div>

            
            <div id="uploadZone" class="upload-zone" ondragover="event.preventDefault()" ondrop="handleDrop(event)">
              <input type="file" id="bgFile" accept="image/png,image/jpeg,image/webp" onchange="handleFile(event)">
              <ion-icon name="image-outline"></ion-icon>
              <h3>Drop your certificate image here</h3>
              <p>or <strong>click to browse</strong> — PNG, JPG, WEBP</p>
              <p style="font-size:11px;color:#94a3b8;">Recommended: A4 landscape (1654 × 1169 px), max 10 MB</p>
            </div>

            
            <div id="previewSection" style="display:none;">
              <p style="font-size:12px;font-weight:700;color:#4f46e5;margin-bottom:8px;display:flex;align-items:center;gap:5px;">
                <ion-icon name="locate-outline"></ion-icon>
                Click on the certificate below to place the <u>student name</u> position
              </p>
              <div class="picker-wrap" id="pickerWrap" onclick="placeMarker(event)">
                <img id="previewImg" src="" alt="Certificate preview">
                <div class="name-marker" id="nameMarker"></div>
              </div>
              <p class="picker-hint">
                <ion-icon name="information-circle-outline"></ion-icon>
                Current position: <span id="posText">Not set — click the image above</span>
              </p>
              <div style="margin-top:10px;">
                <button class="btn btn-secondary" style="font-size:12px;" onclick="clearFile()">
                  <ion-icon name="trash-outline"></ion-icon> Remove Image
                </button>
              </div>
            </div>

            <div class="divider"></div>

            
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-bottom:18px;" id="nameSettings">
              <div>
                <label class="label" for="tplName">Template Name *</label>
                <input type="text" id="tplName" class="inp" placeholder="e.g. Participation Certificate" maxlength="200">
              </div>
              <div>
                <label class="label" for="fontFamily">Font Style</label>
                <select id="fontFamily" class="inp" onchange="if(typeof updateMarkerStyle==='function') updateMarkerStyle();" style="height:42px;cursor:pointer;">
                  <option value="'Great Vibes', cursive" style="font-family:'Great Vibes', cursive;">Great Vibes (Elegant Script)</option>
                  <option value="'Alex Brush', cursive" style="font-family:'Alex Brush', cursive;">Alex Brush (Classic Script)</option>
                  <option value="'Dancing Script', cursive" style="font-family:'Dancing Script', cursive;">Dancing Script (Handwritten)</option>
                  <option value="'Playfair Display', serif" style="font-family:'Playfair Display', serif;">Playfair Display (Serif)</option>
                  <option value="'Cinzel', serif" style="font-family:'Cinzel', serif;">Cinzel (Royal Serif)</option>
                  <option value="'Montserrat', sans-serif" style="font-family:'Montserrat', sans-serif;">Montserrat (Modern Bold)</option>
                  <option value="'Outfit', sans-serif" style="font-family:'Outfit', sans-serif;">Outfit (Clean Modern)</option>
                  <option value="'Inter', sans-serif" style="font-family:'Inter', sans-serif;" selected>Inter (Standard)</option>
                </select>
              </div>
              <div>
                <label class="label" for="fontSize">Font Size (px)</label>
                <input type="number" id="fontSize" class="inp" value="60" min="20" max="200" placeholder="60" oninput="if(typeof updateMarkerStyle==='function') updateMarkerStyle();">
              </div>
              <div>
                <label class="label" for="fontColor">Name Color</label>
                <input type="color" id="fontColor" value="#1e293b" onchange="if(typeof updateMarkerStyle==='function') updateMarkerStyle();" style="height:42px;padding:4px;border:1.5px solid #e2e8f0;border-radius:10px;width:100%;cursor:pointer;background:#f8fafc;">
              </div>
            </div>

            <div id="s2Toast" class="toast"></div>
            <div class="row-btns">
              <button class="btn btn-secondary" onclick="goStep(1)">
                <ion-icon name="arrow-back-outline"></ion-icon> Back
              </button>
              <button class="btn btn-primary" id="s2SaveBtn" onclick="saveTemplate()">
                <ion-icon name="save-outline"></ion-icon> Save Template & Continue
              </button>
            </div>
          </div>
        </div>
      </div>

      
      <div class="step-view" id="sv3">
        <div class="card">
          <div class="card-head">
            <ion-icon name="albums-outline" style="color:#6366f1;font-size:20px;"></ion-icon>
            <h2>Manage Certificate Template</h2>
          </div>
          <div class="card-body">
            <p class="small-lbl" style="margin-bottom:14px;">Saved templates for your organization. You can delete or replace the image.</p>
            <div id="libraryArea">
              <p style="text-align:center;color:#94a3b8;padding:20px;font-size:13px;">Loading…</p>
            </div>

            
            <div id="replaceWrap" style="display:none;margin-top:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px;">
              <p style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                <ion-icon name="refresh-outline" style="color:#6366f1;"></ion-icon> Replace Template Image
              </p>
              <div class="upload-zone" style="padding:22px;" ondragover="event.preventDefault()" ondrop="handleReplaceDrop(event)">
                <input type="file" id="replaceFile" accept="image/png,image/jpeg,image/webp" onchange="handleReplaceSelect(event)">
                <ion-icon name="image-outline"></ion-icon>
                <h3 style="font-size:13px;">Drop new image or click to browse</h3>
              </div>
              <div class="row-btns" style="margin-top:10px;">
                <button class="btn btn-secondary" onclick="document.getElementById('replaceWrap').style.display='none'">Cancel</button>
              </div>
            </div>

            <div id="s3Toast" class="toast"></div>
            <div class="row-btns">
              <button class="btn btn-secondary" onclick="goStep(2)">
                <ion-icon name="arrow-back-outline"></ion-icon> Back
              </button>
              <button class="btn btn-primary" id="s3NextBtn" onclick="goStep(4)">
                Next <ion-icon name="arrow-forward-outline"></ion-icon>
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <div class="step-view" id="sv4">
        <div class="card" style="border-color:rgba(16,185,129,.2);">
          <div class="card-head" style="border-bottom-color:rgba(16,185,129,.15);">
            <ion-icon name="ribbon-outline" style="color:#10b981;font-size:20px;"></ion-icon>
            <h2 style="color:#065f46;">Issue Certificates</h2>
          </div>
          <div class="card-body">
            <p class="small-lbl" style="margin-bottom:16px;">The system will generate a personalized certificate image for each student marked as <strong>Present</strong> at this event.</p>

            <div class="summary-box">
              <h4><ion-icon name="list-outline" style="vertical-align:middle;margin-right:4px;"></ion-icon> Summary</h4>
              <div class="sum-row"><ion-icon name="calendar-outline"></ion-icon> Event: <strong id="issueEvName">—</strong></div>
              <div class="sum-row"><ion-icon name="ribbon-outline"></ion-icon> Template: <strong id="issueTplName">—</strong></div>
              <div class="sum-row"><ion-icon name="people-outline"></ion-icon> Present students: <strong id="issueCount">—</strong></div>
              <div class="sum-row"><ion-icon name="text-outline"></ion-icon> Font Engine: <strong><?= htmlspecialchars($activeFontName) ?></strong></div>
            </div>
            </div>

            <div id="s4Toast" class="toast"></div>
            <div id="issueResult"></div>

            <div class="row-btns">
              <button class="btn btn-secondary" onclick="goStep(3)">
                <ion-icon name="arrow-back-outline"></ion-icon> Back
              </button>
              <button class="btn btn-success" id="issueBtn" onclick="issueCerts()" style="font-size:14px;padding:13px 22px;">
                <ion-icon name="ribbon-outline"></ion-icon> Issue Certificates Now
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../../assets/js/org/org.js"></script>

<script src="../../assets/js/org/certificate-templates.js?v=<?= time() ?>"></script>
</body>
</html>
