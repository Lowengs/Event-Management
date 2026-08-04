<?php
/**
 * qr-scanner.php
 * Dedicated QR Code Scanner for Student Organizations.
 * Supports both live webcam scanning and image file upload.
 */
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) { header('Location: ../osa/login.php'); exit; }

$orgId   = (int)$_SESSION['org_id'];
$orgData = [
    'OrgName' => $_SESSION['org_name'] ?? 'Organization',
    'OrgPicture' => $_SESSION['org_logo'] ?? ''
];
$activePage = 'attendance';

$_GET['action'] = 'get_org_events';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$evApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$events = $evApiRes['data'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner | NAAP Org Portal</title>
    <link rel="stylesheet" href="../../assets/css/organization/nav.css">
    <link rel="icon" href="../../assets/img/philsca.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
    
  <link rel="stylesheet" href="../../assets/css/organization/qr-scanner.css?<?= time() ?>" />
</head>
<body>
<div class="dashboard-layout">
    <?php include '_org_sidebar.php'; ?>
    <div class="overlay" id="sidebarOverlay"></div>
    <div class="content-shell">
        <div class="page-container">

            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <ion-icon name="qr-code-outline" style="color:#6366f1;font-size:26px;"></ion-icon>
                    QR Code Scanner
                </h1>
                <p>Scan a student's QR code to record their event attendance instantly.</p>
            </div>

            <!-- Event Selector -->
            <div class="event-select-card">
                <label>Select Event</label>
                <div class="event-select-row">
                    <select class="event-sel" id="eventSelect">
                        <option value="">— Choose an event —</option>
                        <?php foreach ($events as $ev): ?>
                        <option
                            value="<?= $ev['EventId'] ?>"
                            data-status="<?= htmlspecialchars(strtolower($ev['EventStatus'])) ?>"
                        >
                            <?= htmlspecialchars($ev['EventName']) ?>
                            (<?= date('M d', strtotime($ev['EventDateTime'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="eventStatusBadge" class="event-status-badge badge-none">No Event</div>
                    <a href="attendance_org.php" style="text-decoration:none;">
                        <button class="btn btn-primary" style="width:auto;padding:11px 18px;gap:6px;">
                            <ion-icon name="list-outline"></ion-icon> View Log
                        </button>
                    </a>
                </div>
            </div>

            <!-- Scanner Grid -->
            <div class="scanner-grid">

                <!-- ── Camera Live Scanner ──────────────────── -->
                <div class="scanner-card" id="camCard">
                    <div class="scanner-card-header">
                        <div class="scanner-icon icon-cam">
                            <ion-icon name="videocam-outline"></ion-icon>
                        </div>
                        <div>
                            <div class="scanner-card-title">Camera Scanner</div>
                            <div class="scanner-card-sub">Live webcam QR detection</div>
                        </div>
                    </div>
                    <div class="scanner-card-body">
                        <div class="cam-wrap">
                            <video id="camVideo" autoplay muted playsinline></video>
                            <canvas id="camCanvas" style="display:none;"></canvas>
                            <div class="cam-overlay" id="camOverlay">
                                <div class="scan-frame">
                                    <span></span>
                                    <div class="scan-line" id="scanLine" style="display:none;"></div>
                                </div>
                            </div>
                            <div class="cam-placeholder" id="camPlaceholder">
                                <ion-icon name="camera-outline"></ion-icon>
                                <p>Camera not started</p>
                            </div>
                        </div>
                        <div id="camStatus" class="scan-status"></div>
                        <button id="camBtn" class="btn btn-primary" onclick="toggleCamera()" disabled>
                            <ion-icon name="camera-outline"></ion-icon> Start Camera
                        </button>
                    </div>
                </div>

                <!-- ── Upload QR Image ─────────────────────── -->
                <div class="scanner-card" id="uploadCard">
                    <div class="scanner-card-header">
                        <div class="scanner-icon icon-upload">
                            <ion-icon name="image-outline"></ion-icon>
                        </div>
                        <div>
                            <div class="scanner-card-title">Upload QR Image</div>
                            <div class="scanner-card-sub">Upload a QR photo or screenshot</div>
                        </div>
                    </div>
                    <div class="scanner-card-body">
                        <div id="uploadZone" class="upload-zone" ondragover="return false" ondrop="handleDrop(event)">
                            <input type="file" id="qrFileInput" accept="image/*" onchange="handleUpload(event)">
                            <ion-icon name="cloud-upload-outline"></ion-icon>
                            <p>Drop an image here or <strong>browse to upload</strong><br>
                               (JPG, PNG, screenshots — any QR image)</p>
                        </div>
                        <div id="uploadPreview" class="upload-preview" style="display:none;">
                            <img id="uploadImg" src="" alt="QR Preview">
                            <button class="reset-btn" onclick="resetUpload()">✕ Clear</button>
                        </div>
                        <div id="uploadStatus" class="scan-status"></div>
                        <button id="uploadScanBtn" class="btn btn-blue" onclick="scanUploadedImage()" disabled>
                            <ion-icon name="scan-outline"></ion-icon> Decode QR
                        </button>
                    </div>
                </div>

            </div><!-- /.scanner-grid -->

            <!-- Recent Scans -->
            <div class="log-card">
                <h3>
                    <ion-icon name="checkmark-done-outline" style="color:#6366f1;"></ion-icon>
                    Recent Scans — This Session
                </h3>
                <div id="logEmpty" class="log-empty">No students scanned yet this session.</div>
                <table class="log-table" id="logTable" style="display:none;">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Method</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="logBody"></tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<!-- ── Confirmation Modal ─────────────────────────── -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-strip"></div>
        <div class="modal-content">
            <div class="modal-title">
                <ion-icon name="person-circle-outline" style="color:#6366f1;font-size:22px;"></ion-icon>
                Verify & Confirm Attendance
            </div>

            <div class="student-card-preview">
                <div class="preview-photo" id="modalPhoto">??</div>
                <div class="preview-info">
                    <div class="preview-name" id="modalName">—</div>
                    <div class="preview-id"  id="modalId">—</div>
                    <div class="preview-detail" id="modalCourse">—</div>
                    <div class="preview-detail" id="modalOrg">—</div>
                </div>
            </div>

            <div class="modal-anti-spoof-note">
                <ion-icon name="eye-outline"></ion-icon>
                <span>
                    <strong>Anti-Spoofing Check:</strong> Please visually confirm that the
                    student standing before you matches the photo and name displayed above
                    before recording attendance.
                </span>
            </div>

            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" onclick="closeModal()">
                    <ion-icon name="close-outline"></ion-icon> Cancel
                </button>
                <button class="modal-btn modal-btn-confirm" id="modalConfirmBtn" onclick="confirmAttendance()">
                    <ion-icon name="checkmark-circle-outline"></ion-icon> Record Attendance
                </button>
            </div>
        </div>
    </div>
</div>


  <script src="../../assets/js/org/qr-scanner.js"></script>
</body>
</html>
