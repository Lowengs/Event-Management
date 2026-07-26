<?php
/**
 * student-qr.php
 * Dedicated QR Code ID Card page for students.
 * Generates a unique, verifiable QR per student with full card design.
 */
session_start();
require_once '../../config/db.php';

if (empty($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = (int)$_SESSION['student_id'];

$student = $conn->query("
    SELECT u.UserId, u.first_name, u.last_name, u.middle_name,
           u.Email, u.course, u.year_level, u.section, u.student_id AS student_no,
           u.profile_photo, u.Position, u.phone,
           o.OrgName, o.OrgPicture, o.OrgId
    FROM user u
    LEFT JOIN organization o ON o.OrgId = u.OrgId
    WHERE u.UserId = $student_id LIMIT 1
")->fetch_assoc();

if (!$student) { header('Location: login.php'); exit; }

$fullName   = trim($student['first_name'] . ' ' . $student['last_name']);
$initials   = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
$studentNo  = $student['student_no'] ?: 'N/A';
$course     = $student['course']     ?: 'N/A';
$year       = $student['year_level'] ?: '';
$section    = $student['section']    ?: '';
$orgName    = $student['OrgName']    ?: 'NAAP';
$position   = $student['Position']   ?: 'Member';
$email      = $student['Email']      ?: '';

// Resolve profile photo path
$photoSrc = '';
if (!empty($student['profile_photo'])) {
    $p = $student['profile_photo'];
    $resolved = (strpos($p, 'assets/') === 0) ? '../../' . $p : (strpos($p, '../../') === 0 ? $p : '../../' . ltrim($p, '/'));
    $disk = __DIR__ . '/../../' . ltrim(str_replace('../../', '', $resolved), '/');
    if (file_exists($disk)) $photoSrc = $resolved;
}
$hasPhoto = !empty($photoSrc);

// Build the QR payload — compact, parseable JSON
$qrPayload = json_encode([
    'type'       => 'student_qr',
    'user_id'    => $student['UserId'],
    'student_id' => $studentNo,
    'name'       => $fullName,
    'course'     => $course,
    'org'        => $orgName,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Student QR Code | NAAP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <link rel="icon" href="../../assets/img/philsca.png">
    
  <link rel="stylesheet" href="../../assets/css/student/student-qr.css?<?= time() ?>" />
</head>
<body>

    <!-- Top Bar -->
    <div class="topbar">
        <a href="profile-dashboard.php" class="back-btn">
            <i class='bx bx-arrow-back'></i> Back
        </a>
        <div class="title-area">Student ID Card</div>
        <div style="width:60px;"></div>
    </div>

    <!-- ID Card -->
    <div class="id-card" id="idCard">
        <div class="card-header-strip"></div>
        <div class="card-body">
            <!-- School header -->
            <div class="card-school-row">
                <div class="card-school-logo">
                    <img src="../../assets/img/naap logo.png" alt="NAAP" onerror="this.style.display='none'">
                </div>
                <div>
                    <div class="card-school-name">NAAP</div>
                    <div class="card-school-tagline">Student Organization Portal</div>
                </div>
                <div class="card-type-badge">Student ID</div>
            </div>

            <!-- Student info -->
            <div class="card-student-row">
                <div class="card-photo" id="cardPhoto">
                    <?php if ($hasPhoto): ?>
                        <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Photo" onerror="this.parentElement.innerHTML='<?= $initials ?>'">
                    <?php else: ?>
                        <?= $initials ?>
                    <?php endif; ?>
                </div>
                <div class="card-info">
                    <div class="card-name"><?= htmlspecialchars($fullName) ?></div>
                    <div class="card-id"># <?= htmlspecialchars($studentNo) ?></div>
                    <div class="card-detail"><span><?= htmlspecialchars($course) ?></span></div>
                    <div class="card-detail">
                        <?= $year ? htmlspecialchars($year) : '' ?>
                        <?= $section ? ' — Section ' . htmlspecialchars($section) : '' ?>
                    </div>
                    <div class="card-detail"><span><?= htmlspecialchars($position) ?></span></div>
                </div>
            </div>

            <!-- QR Code -->
            <div class="card-qr-section">
                <div class="qr-box" id="qrContainer">
                    <div class="qr-loading">
                        <div class="spinner"></div>
                        <span>Generating…</span>
                    </div>
                </div>
                <div class="qr-info">
                    <div class="qr-label">Smart QR Code</div>
                    <div class="qr-title">Attendance Scan</div>
                    <div class="qr-sub">Present this QR to the event organizer to record your attendance.</div>
                    <div class="qr-verified">
                        <ion-icon name="shield-checkmark-outline"></ion-icon>
                        Verified Student
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="card-footer-org"><?= htmlspecialchars($orgName) ?></div>
            <div class="card-footer-year">AY <?= date('Y') ?>–<?= date('Y') + 1 ?></div>
        </div>
    </div>

    <!-- Actions -->
    <div class="actions-row">
        <button class="btn-action btn-download" onclick="downloadQR()">
            <i class='bx bx-download'></i> Download QR
        </button>
        <button class="btn-action btn-secondary" onclick="shareCard()">
            <i class='bx bx-share-alt'></i> Share
        </button>
    </div>

    <!-- Info box -->
    <div class="info-box">
        <h4><ion-icon name="information-circle-outline"></ion-icon> How to use your QR Code</h4>
        <ul>
            <li>Show this QR code to the event officer at the start of the event.</li>
            <li>They will scan it using the NAAP Organization Portal to record your attendance.</li>
            <li>You can also download and send a screenshot — the upload QR scanner accepts photos too.</li>
            <li>Each QR code is uniquely tied to your student ID and cannot be reused by others.</li>
        </ul>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        const qrPayload = <?= json_encode($qrPayload) ?>;
        const studentNo = <?= json_encode($studentNo) ?>;
        let qrInstance = null;

        function generateQR() {
            const container = document.getElementById('qrContainer');
            container.innerHTML = '';
            qrInstance = new QRCode(container, {
                text: qrPayload,
                width: 94,
                height: 94,
                colorDark: '#1e293b',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }

        function downloadQR() {
            // We need a moment for the QR canvas to be ready
            setTimeout(() => {
                const canvas = document.querySelector('#qrContainer canvas');
                if (!canvas) { showToast('QR not ready yet. Please wait a moment.'); return; }

                // Build a larger canvas with card info for download
                const dl = document.createElement('canvas');
                dl.width = 420; dl.height = 160;
                const ctx = dl.getContext('2d');

                // Background
                ctx.fillStyle = '#1e293b';
                ctx.roundRect(0, 0, 420, 160, 16);
                ctx.fill();

                // Draw QR
                ctx.drawImage(canvas, 12, 12, 136, 136);

                // Text
                ctx.fillStyle = '#6366f1';
                ctx.font = 'bold 11px Inter, sans-serif';
                ctx.fillText('# ' + studentNo, 162, 30);

                ctx.fillStyle = '#f1f5f9';
                ctx.font = 'bold 16px Inter, sans-serif';
                ctx.fillText(<?= json_encode($fullName) ?>, 162, 52);

                ctx.fillStyle = '#94a3b8';
                ctx.font = '12px Inter, sans-serif';
                ctx.fillText(<?= json_encode($course) ?>, 162, 72);
                ctx.fillText(<?= json_encode($orgName) ?>, 162, 90);
                ctx.fillText(<?= json_encode($position) ?>, 162, 108);

                ctx.fillStyle = '#334155';
                ctx.font = '10px Inter, sans-serif';
                ctx.fillText('NAAP Student Organization Portal', 162, 148);

                const a = document.createElement('a');
                a.download = 'naap-qr-' + studentNo + '.png';
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
                    const file = new File([blob], 'my-qr-' + studentNo + '.png', { type: 'image/png' });
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
            t.innerHTML = `<i class='bx bx-check-circle'></i> ${msg}`;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        }

        // Generate QR when page loads
        window.addEventListener('DOMContentLoaded', generateQR);
    </script>
</body>
</html>
