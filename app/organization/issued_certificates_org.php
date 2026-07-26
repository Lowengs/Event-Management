<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/audit.php';

// Check organization login
if (!isset($_SESSION['org_id'])) {
    header("Location: ../../index.php");
    exit();
}

$orgId = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT * FROM organization WHERE OrgId = $orgId LIMIT 1")->fetch_assoc();
if (!$orgData) {
    die("Organization not found.");
}

$activePage = 'issued_certs';

// Fetch all issued certificates for this org
$certs = [];
$cq = $conn->query("
    SELECT c.CertId, c.CertCode, c.IssuedAt, c.GeneratedImage,
           e.EventName, e.EventDateTime,
           u.first_name, u.last_name, u.student_id, u.profile_photo,
           t.TemplateName, t.TemplateImage
    FROM certificates c
    JOIN event e ON e.EventId = c.EventId
    JOIN user u ON u.UserId = c.UserId
    JOIN certificate_templates t ON t.TemplateId = c.TemplateId
    WHERE e.OrgId = $orgId
    ORDER BY e.EventDateTime DESC, c.IssuedAt DESC
");

if ($cq) {
    while ($row = $cq->fetch_assoc()) {
        $certs[] = $row;
    }
}

// Group certificates by EventName
$certsByEvent = [];
foreach ($certs as $c) {
    $evName = !empty($c['EventName']) ? $c['EventName'] : 'Other Events';
    $certsByEvent[$evName][] = $c;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Issued Certificates - ORG Portal</title>
  
  <link rel="stylesheet" href="../../assets/css/index.css?v=<?= time() ?>">
  <link rel="stylesheet" href="../../assets/css/dashboard.css?v=<?= time() ?>">
  <link rel="stylesheet" href="../../assets/css/organization/org-portal.css?v=<?= time() ?>">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Dancing+Script:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  <link rel="icon" href="../../assets/img/philsca.png">

  
  <link rel="stylesheet" href="../../assets/css/organization/issued_certificates_org.css?<?= time() ?>" />
</head>
<body>

<div class="dashboard-container">
  <?php include '_org_sidebar.php'; ?>

  <main class="main-content">
    <div class="page-header">
      <div class="page-title">
        <h2><ion-icon name="medal" style="color:#6366f1;"></ion-icon> Issued Certificates</h2>
        <p>View all certificates that have been generated and distributed to students.</p>
      </div>
      <div>
        <a href="certificate-templates.php" class="btn-action btn-dl" style="padding:10px 20px;text-decoration:none;">
          <ion-icon name="add-circle-outline"></ion-icon> Issue New Certificates
        </a>
      </div>
    </div>

    <?php if (empty($certsByEvent)): ?>
      <div class="empty-state">
        <ion-icon name="ribbon-outline"></ion-icon>
        <h3>No Certificates Issued Yet</h3>
        <p>You have not issued any certificates for your events. Go to Certificate Templates to get started.</p>
      </div>
    <?php else: ?>
      
      <?php foreach ($certsByEvent as $eventName => $eventCerts): ?>
        <div class="event-group">
          <div class="event-header">
            <h3><ion-icon name="calendar-outline" style="color:#a78bfa;"></ion-icon> <?= htmlspecialchars($eventName) ?></h3>
            <span class="event-badge"><?= count($eventCerts) ?> Issued</span>
          </div>

          <div class="certs-grid">
            <?php foreach ($eventCerts as $c): 
                $imgSrc = !empty($c['GeneratedImage']) ? '../../' . $c['GeneratedImage'] : '../../' . $c['TemplateImage'];
                $issueDate = date('M j, Y • g:i A', strtotime($c['IssuedAt']));
                $studentName = trim($c['first_name'] . ' ' . $c['last_name']);
                
                $avatar = '';
                if (!empty($c['profile_photo'])) {
                    $p = $c['profile_photo'];
                    $src = (strpos($p, '../../') === 0) ? $p : '../../' . ltrim($p, '/');
                    $avatar = '<img src="' . htmlspecialchars($src) . '" onerror="this.style.display=\'none\'">';
                } else {
                    $avatar = strtoupper(substr($c['first_name'], 0, 1));
                }
            ?>
            <div class="cert-card">
              <div class="cert-img-wrap">
                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Certificate" loading="lazy" onerror="this.style.display='none'">
                <div class="cert-overlay">
                  <div class="student-badge">
                    <div class="student-avatar"><?= $avatar ?></div>
                    <div>
                      <div class="student-name"><?= htmlspecialchars($studentName) ?></div>
                      <div class="student-id"><?= htmlspecialchars($c['student_id'] ?? 'N/A') ?></div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="cert-info">
                <div class="cert-meta">
                  <span><ion-icon name="document-text-outline"></ion-icon> <?= htmlspecialchars($c['TemplateName']) ?></span>
                  <span><ion-icon name="time-outline"></ion-icon> <?= $issueDate ?></span>
                </div>
                <div class="cert-code">
                  Code: <?= htmlspecialchars($c['CertCode']) ?>
                </div>
                <div class="cert-actions">
                  <button class="btn-action btn-view" onclick="viewCert('<?= htmlspecialchars($imgSrc) ?>')">
                    <ion-icon name="eye-outline"></ion-icon> View
                  </button>
                  <a href="<?= htmlspecialchars($imgSrc) ?>" download="Certificate_<?= htmlspecialchars(str_replace(' ','_',$studentName)) ?>.jpg" class="btn-action btn-dl" style="text-decoration:none;">
                    <ion-icon name="download-outline"></ion-icon> Download
                  </a>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php endif; ?>
  </main>
</div>

<!-- Image Viewer Modal -->
<div class="viewer-overlay" id="viewerOverlay">
  <div class="viewer-box">
    <div class="viewer-close" onclick="closeViewer()"><ion-icon name="close"></ion-icon></div>
    <img id="viewerImg" src="" alt="Certificate View">
  </div>
</div>



  <script src="../../assets/js/org/issued_certificates_org.js"></script>
</body>
</html>
