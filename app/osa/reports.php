<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';

$_GET['action'] = 'get_osa_reports';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$reportsApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

$reportType    = strtolower(trim($_GET['category'] ?? $_GET['report_type'] ?? $_GET['type'] ?? 'event'));
$currentOrgId  = isset($_GET['org']) ? (int)$_GET['org'] : 0;
$search        = trim($_GET['search'] ?? '');
$statusFilter  = trim($_GET['status'] ?? '');
$fromDate      = trim($_GET['from_date'] ?? '');
$toDate        = trim($_GET['to_date'] ?? '');

$summary       = $reportsApiRes['summary'] ?? [
    'total_events'        => 0,
    'total_organizations' => 0,
    'total_students'      => 0,
    'total_participants'  => 0,
    'completed_events'    => 0,
    'cancelled_events'    => 0,
];

$orgs            = $reportsApiRes['orgs'] ?? [];
$reportData      = $reportsApiRes['data'] ?? [];
$events_by_org   = $reportsApiRes['events_by_org'] ?? [];
$officers_by_org = $reportsApiRes['officers_by_org'] ?? [];
$allDocsByEvent  = $reportsApiRes['all_docs_by_event'] ?? [];

$categoryLabels = [
    'event'        => 'Event Reports',
    'student'      => 'Student Reports',
    'organization' => 'Organization Reports',
    'announcement' => 'Announcement Reports',
    'attendance'   => 'Attendance Reports',
    'financial'    => 'Financial Reports',
    'audit'        => 'Audit Reports',
];
$activeCategoryName = $categoryLabels[$reportType] ?? 'Event Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Reports &amp; Analytics</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/reports.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/osa/reports.css?v=<?= time() ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="icon" href="../../assets/img/philsca.png">
  <script src="../../assets/js/security.js"></script>
</head>

<body>
  <header>
    <button id="hamburger-btn" class="hamburger" aria-label="Open menu">
      <ion-icon name="menu-outline"></ion-icon>
    </button>
    <h1>NAAP OSA PORTAL</h1>
  </header>

  <main>
    <nav class="navigation" id="sidebar">
      <ul>
        <li>
          <div class="span">
            <div class="logo-border">
              <img src="../../assets/img/philsca.png" alt="NAAP Logo">
            </div>
            <div class="text">
              <h1>NAAP</h1>
              <p>OSA Portal</p>
            </div>
          </div>
        </li>
        <li><a href="dashboard_final.php" class="nav"><ion-icon name="grid-outline"></ion-icon><span>Dashboard</span></a></li>
        <li><a href="organization.php" class="nav"><ion-icon name="business-outline"></ion-icon><span>Organization</span></a></li>
        <li><a href="calendar.php" class="nav"><ion-icon name="calendar-number-outline"></ion-icon><span>Calendar</span></a></li>
        <li><a href="events.php" class="nav"><ion-icon name="calendar-outline"></ion-icon><span>Events</span></a></li>
        <li><a href="students.php" class="nav"><ion-icon name="people-outline"></ion-icon><span>Students</span></a></li>
        <li><a href="announcement.php" class="nav"><ion-icon name="megaphone-outline"></ion-icon><span>Announcements</span></a></li>
        <li><a href="reports.php" class="nav active"><ion-icon name="document-text-outline"></ion-icon><span>Reports</span></a></li>
        <li><a href="audit-trail.php" class="nav"><ion-icon name="analytics-outline"></ion-icon><span>Audit Trail</span></a></li>
        <li><a href="messages.php" class="nav"><ion-icon name="chatbox-outline"></ion-icon><span>Messages</span></a></li>
        <li><a href="settings.php" class="nav"><ion-icon name="cog-outline"></ion-icon><span>Settings</span></a></li>
        <li><a href="../../config/API/endpoints/index.php?action=osa_logout" class="nav"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
      </ul>
    </nav>

    <div class="maincontent">
      <div class="pagebar">
        <a class="back-btn" href="dashboard_final.php" aria-label="Back to dashboard">
          <ion-icon name="arrow-back-outline"></ion-icon>
        </a>

        <div class="pagebar-text">
          <h2>Reports &amp; Analytics Engine</h2>
          <p>Official institutional records, cross-category generation, and data export</p>
        </div>
      </div>
      <div class="divider"></div>

      <!-- Top 6 Report Summary Metric Cards -->
      <div class="reports-summary-grid">
        <div class="reports-summary-card blue">
          <div class="reports-summary-info">
            <p>Total Events</p>
            <h3><?= number_format((int)$summary['total_events']) ?></h3>
          </div>
          <div class="reports-summary-icon">
            <ion-icon name="calendar-outline"></ion-icon>
          </div>
        </div>

        <div class="reports-summary-card purple">
          <div class="reports-summary-info">
            <p>Total Organizations</p>
            <h3><?= number_format((int)$summary['total_organizations']) ?></h3>
          </div>
          <div class="reports-summary-icon">
            <ion-icon name="business-outline"></ion-icon>
          </div>
        </div>

        <div class="reports-summary-card cyan">
          <div class="reports-summary-info">
            <p>Total Students</p>
            <h3><?= number_format((int)$summary['total_students']) ?></h3>
          </div>
          <div class="reports-summary-icon">
            <ion-icon name="people-outline"></ion-icon>
          </div>
        </div>

        <div class="reports-summary-card emerald">
          <div class="reports-summary-info">
            <p>Total Participants</p>
            <h3><?= number_format((int)$summary['total_participants']) ?></h3>
          </div>
          <div class="reports-summary-icon">
            <ion-icon name="person-add-outline"></ion-icon>
          </div>
        </div>

        <div class="reports-summary-card green">
          <div class="reports-summary-info">
            <p>Completed Events</p>
            <h3><?= number_format((int)$summary['completed_events']) ?></h3>
          </div>
          <div class="reports-summary-icon">
            <ion-icon name="checkmark-circle-outline"></ion-icon>
          </div>
        </div>

        <div class="reports-summary-card red">
          <div class="reports-summary-info">
            <p>Cancelled Events</p>
            <h3><?= number_format((int)$summary['cancelled_events']) ?></h3>
          </div>
          <div class="reports-summary-icon">
            <ion-icon name="close-circle-outline"></ion-icon>
          </div>
        </div>
      </div>

      <!-- Reports Controls & Filter Form -->
      <div class="reports-controls-panel">
        <form method="GET" class="reports-filter-form" id="reportsForm">
          <div class="reports-filter-grid">
            <div class="filter-item">
              <label for="filterCategory"><ion-icon name="albums-outline"></ion-icon> Report Type / Category</label>
              <select name="category" id="filterCategory" onchange="this.form.submit()">
                <option value="event" <?= $reportType === 'event' ? 'selected' : '' ?>>Event Reports</option>
                <option value="student" <?= $reportType === 'student' ? 'selected' : '' ?>>Student Reports</option>
                <option value="organization" <?= $reportType === 'organization' ? 'selected' : '' ?>>Organization Reports</option>
                <option value="announcement" <?= $reportType === 'announcement' ? 'selected' : '' ?>>Announcement Reports</option>
                <option value="attendance" <?= $reportType === 'attendance' ? 'selected' : '' ?>>Attendance Reports</option>
                <option value="financial" <?= $reportType === 'financial' ? 'selected' : '' ?>>Financial Reports</option>
                <option value="audit" <?= $reportType === 'audit' ? 'selected' : '' ?>>Audit Reports</option>
              </select>
            </div>

            <div class="filter-item">
              <label for="filterOrg"><ion-icon name="business-outline"></ion-icon> Organization</label>
              <select name="org" id="filterOrg" onchange="this.form.submit()">
                <option value="0" <?= $currentOrgId === 0 ? 'selected' : '' ?>>All Organizations</option>
                <?php foreach ($orgs as $o): ?>
                <option value="<?= (int)$o['OrgId'] ?>" <?= $currentOrgId === (int)$o['OrgId'] ? 'selected' : '' ?>><?= htmlspecialchars($o['OrgName']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="filter-item">
              <label for="filterStatus"><ion-icon name="funnel-outline"></ion-icon> Status</label>
              <select name="status" id="filterStatus" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php if ($reportType === 'student'): ?>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active / Verified</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <?php elseif ($reportType === 'announcement'): ?>
                <option value="Published" <?= $statusFilter === 'Published' ? 'selected' : '' ?>>Published</option>
                <option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <?php else: ?>
                <option value="Scheduled" <?= $statusFilter === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                <option value="Ongoing"   <?= $statusFilter === 'Ongoing'   ? 'selected' : '' ?>>Ongoing</option>
                <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="Delayed"   <?= $statusFilter === 'Delayed'   ? 'selected' : '' ?>>Delayed</option>
                <?php endif; ?>
              </select>
            </div>

            <div class="filter-item">
              <label for="filterFrom"><ion-icon name="calendar-outline"></ion-icon> From Date</label>
              <input type="date" name="from_date" id="filterFrom" value="<?= htmlspecialchars($fromDate) ?>">
            </div>

            <div class="filter-item">
              <label for="filterTo"><ion-icon name="calendar-outline"></ion-icon> To Date</label>
              <input type="date" name="to_date" id="filterTo" value="<?= htmlspecialchars($toDate) ?>">
            </div>

            <div class="filter-item">
              <label for="filterSearch"><ion-icon name="search-outline"></ion-icon> Search Keywords</label>
              <input type="text" name="search" id="filterSearch" placeholder="Search records..." value="<?= htmlspecialchars($search) ?>">
            </div>
          </div>

          <div class="reports-actions-row">
            <div class="active-category-indicator">
              <span style="font-weight:700; color:#0f172a; font-size:0.95rem;"><?= htmlspecialchars($activeCategoryName) ?></span>
              <span style="color:#64748b; font-size:0.84rem; margin-left:6px;">(<?= count($reportData) ?> records found)</span>
            </div>

            <div class="action-buttons-group">
              <button type="submit" class="btn-report-action btn-generate">
                <ion-icon name="refresh-outline"></ion-icon> Generate Report
              </button>
              <button type="button" class="btn-report-action btn-export-pdf" onclick="exportReportPDF()">
                <ion-icon name="document-text-outline"></ion-icon> Export PDF
              </button>
              <button type="button" class="btn-report-action btn-export-csv" onclick="exportCurrentReportCSV('<?= htmlspecialchars($reportType) ?>')">
                <ion-icon name="download-outline"></ion-icon> Export Excel / CSV
              </button>
              <button type="button" class="btn-report-action btn-print-report" onclick="window.print()">
                <ion-icon name="print-outline"></ion-icon> Print
              </button>
            </div>
          </div>
        </form>
      </div>

      <!-- ═══ REPORT DATA VIEWS ═══ -->

      <?php if ($reportType === 'event'): ?>
      <!-- ── 1. EVENT REPORTS VIEW (Accordion + Documentation) ── -->
      <div class="events-accordion-container">
        <?php if (empty($events_by_org)): ?>
          <p class="empty-reports-msg">No event records found matching your filters.</p>
        <?php else: ?>
        <?php foreach ($events_by_org as $orgName => $evList): ?>
          <div class="accordion-header">
            <h2><?= htmlspecialchars($orgName) ?> Events (<?= count($evList) ?>)</h2>
          </div>

          <?php foreach ($evList as $ev):
            $evStatus = $ev['EventStatus'] ?? 'Scheduled';
            $statusMap = [
              'Scheduled' => ['cls'=>'yellow', 'icon'=>'calendar-outline'],
              'Ongoing'   => ['cls'=>'green',  'icon'=>'time-outline'],
              'Completed' => ['cls'=>'blue',   'icon'=>'checkmark-circle-outline'],
              'Cancelled' => ['cls'=>'red',    'icon'=>'close-circle-outline'],
              'Delayed'   => ['cls'=>'orange', 'icon'=>'hourglass-outline'],
            ];
            $statusCls  = $statusMap[$evStatus]['cls']  ?? 'yellow';
            $statusIcon = $statusMap[$evStatus]['icon'] ?? 'calendar-outline';
            $evDate     = !empty($ev['EventDateTime']) ? date('F j, Y', strtotime($ev['EventDateTime'])) : 'N/A';
            $evTime     = !empty($ev['EventDateTime']) ? date('g:i A', strtotime($ev['EventDateTime'])) : 'N/A';
            $attended   = (int)$ev['attended'];
            $registered = (int)$ev['registered'];
            $absent     = max(0, $registered - $attended);
            $attPct     = $registered > 0 ? round(($attended / $registered) * 100) : 0;
            $orgOfficers = $officers_by_org[$orgName] ?? [];
            $officersStr = !empty($orgOfficers) ? implode('; ', $orgOfficers) : 'N/A';
            $officersJson = json_encode($orgOfficers);

            $evId       = (int)$ev['EventId'];
            $postDoc    = $allDocsByEvent[$evId]['postactivityreport'] ?? null;
            $finDoc     = $allDocsByEvent[$evId]['financialreport'] ?? null;

            $hasPostDoc = !empty($postDoc['FilePath']);
            $hasFinDoc  = !empty($finDoc['FilePath']);
            $noFinancialInvolvement = !empty($ev['NoFinancialReport']) || !empty($ev['no_financial_report']);
          ?>
          <div class="event-accordion-item">
            <div class="event-summary" onclick="this.parentElement.classList.toggle('expanded')">
              <div class="event-summary-left">
                <ion-icon name="chevron-forward-outline" class="chevron-icon"></ion-icon>
                <ion-icon name="calendar-outline" class="calendar-icon"></ion-icon>
                <div class="event-title-date">
                  <h4><?= htmlspecialchars($ev['EventName']) ?></h4>
                  <p><?= $evDate ?> &bull; <?= htmlspecialchars($ev['EventLocation'] ?? 'Venue N/A') ?></p>
                </div>
              </div>
              <span class="badge <?= $statusCls ?> with-icon" style="font-size:.72rem;padding:3px 10px;border-radius:20px;">
                <ion-icon name="<?= $statusIcon ?>"></ion-icon> <?= htmlspecialchars($evStatus) ?>
              </span>
            </div>

            <div class="event-details">
              <!-- Post-Activity Report -->
              <div class="report-card">
                <div class="report-card-header">
                  <div class="report-card-title-block">
                    <ion-icon name="document-text-outline" class="doc-icon"></ion-icon>
                    <div>
                      <h5>Post-Activity Report</h5>
                      <p>Complete documentation including attendance, photos, and outcomes</p>
                    </div>
                  </div>
                  <div class="report-card-status">
                    <?php if ($hasPostDoc): ?>
                    <span class="badge blue with-icon"><ion-icon name="checkmark-circle-outline"></ion-icon> Completed</span>
                    <?php else: ?>
                    <span class="badge orange with-icon" style="background:#fff7ed;color:#c2410c;border:1px solid #ffedd5;"><ion-icon name="alert-circle-outline"></ion-icon> Not Uploaded</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="report-card-meta-row">
                  <div class="meta-info">
                    <span><ion-icon name="calendar-outline"></ion-icon> Date: <?= $evDate ?></span>
                    <span><ion-icon name="time-outline"></ion-icon> Time: <?= $evTime ?></span>
                    <span>Attended: <strong><?= $attended ?></strong></span>
                    <span>Absent: <strong style="color:#ef4444;"><?= $absent ?></strong></span>
                    <span>Registered: <?= $registered ?> (<?= $attPct ?>%)</span>
                    <span>Venue: <?= htmlspecialchars($ev['EventLocation'] ?? 'N/A') ?></span>
                  </div>
                  <div class="report-actions">
                    <?php if ($hasPostDoc): 
                      $postExt = !empty($postDoc['FilePath']) ? strtolower(pathinfo($postDoc['FilePath'], PATHINFO_EXTENSION)) : 'pdf';
                      $postDownloadName = $ev['EventName'] . ' - Post-Activity Report' . ($postExt ? '.' . $postExt : '');
                      $postDocPath = !empty($postDoc['FilePath']) ? ltrim($postDoc['FilePath'], '/') : '';
                    ?>
                    <button type="button" class="icon-action-btn" title="View Uploaded Post-Activity Report"
                      onclick="openReportDocPreview(
                        '../../<?= htmlspecialchars($postDocPath) ?>',
                        <?= htmlspecialchars(json_encode($ev['EventName'] . ' - Post-Activity Report')) ?>,
                        '<?= htmlspecialchars($postExt) ?>',
                        <?= htmlspecialchars(json_encode($postDownloadName)) ?>,
                        <?= htmlspecialchars(json_encode($orgName)) ?>
                      )">
                      <ion-icon name="eye-outline"></ion-icon>
                    </button>
                    <a href="../../<?= htmlspecialchars($postDocPath) ?>" download="<?= htmlspecialchars($postDownloadName) ?>" class="icon-action-btn" title="Download Post-Activity Report">
                      <ion-icon name="download-outline"></ion-icon>
                    </a>
                    <?php else: ?>
                    <button class="icon-action-btn" type="button"
                      onclick="openExportModal(
                        <?= json_encode($ev['EventName']) ?>,
                        <?= json_encode($orgName) ?>,
                        <?= json_encode($evDate) ?>,
                        <?= json_encode($evTime) ?>,
                        <?= json_encode($ev['EventLocation'] ?? 'N/A') ?>,
                        <?= json_encode($evStatus) ?>,
                        <?= $attended ?>, <?= $registered ?>, <?= $absent ?>, <?= $attPct ?>,
                        0,
                        <?= $officersJson ?>,
                        'post'
                      )">
                      <ion-icon name="eye-outline"></ion-icon>
                    </button>
                    <button class="icon-action-btn" type="button"
                      onclick="exportReport(
                        <?= json_encode($ev['EventName']) ?>,
                        <?= json_encode($orgName) ?>,
                        <?= json_encode($evDate) ?>,
                        <?= json_encode($evTime) ?>,
                        <?= json_encode($ev['EventLocation'] ?? 'N/A') ?>,
                        <?= json_encode($evStatus) ?>,
                        <?= $attended ?>, <?= $registered ?>, <?= $absent ?>, <?= $attPct ?>,
                        0,
                        <?= $officersJson ?>,
                        'post'
                      )">
                      <ion-icon name="download-outline"></ion-icon>
                    </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Financial Report -->
              <div class="report-card">
                <div class="report-card-header">
                  <div class="report-card-title-block">
                    <ion-icon name="cash-outline" class="doc-icon"></ion-icon>
                    <div>
                      <h5>Financial Report</h5>
                      <p>Budget utilization and expense breakdown</p>
                    </div>
                  </div>
                  <div class="report-card-status">
                    <?php if ($hasFinDoc): ?>
                    <span class="badge blue with-icon"><ion-icon name="checkmark-circle-outline"></ion-icon> Completed</span>
                    <?php elseif ($noFinancialInvolvement): ?>
                    <span class="badge green with-icon"><ion-icon name="checkmark-circle-outline"></ion-icon> No financial involvement</span>
                    <?php else: ?>
                    <span class="badge orange with-icon" style="background:#fff7ed;color:#c2410c;border:1px solid #ffedd5;"><ion-icon name="alert-circle-outline"></ion-icon> Not Uploaded</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="report-card-meta-row">
                  <div class="meta-info">
                    <span><ion-icon name="calendar-outline"></ion-icon> Date: <?= $evDate ?></span>
                    <span>Type: <?= $noFinancialInvolvement ? 'No financial involvement' : 'Financial Statement' ?></span>
                    <span>Organization: <?= htmlspecialchars($orgName) ?></span>
                  </div>
                  <div class="report-actions">
                    <?php if ($hasFinDoc): 
                      $finExt = !empty($finDoc['FilePath']) ? strtolower(pathinfo($finDoc['FilePath'], PATHINFO_EXTENSION)) : 'pdf';
                      $finDownloadName = $ev['EventName'] . ' - Financial Report' . ($finExt ? '.' . $finExt : '');
                      $finDocPath = !empty($finDoc['FilePath']) ? ltrim($finDoc['FilePath'], '/') : '';
                    ?>
                    <button type="button" class="icon-action-btn" title="View Uploaded Financial Report"
                      onclick="openReportDocPreview(
                        '../../<?= htmlspecialchars($finDocPath) ?>',
                        <?= htmlspecialchars(json_encode($ev['EventName'] . ' - Financial Report')) ?>,
                        '<?= htmlspecialchars($finExt) ?>',
                        <?= htmlspecialchars(json_encode($finDownloadName)) ?>,
                        <?= htmlspecialchars(json_encode($orgName)) ?>
                      )">
                      <ion-icon name="eye-outline"></ion-icon>
                    </button>
                    <a href="../../<?= htmlspecialchars($finDocPath) ?>" download="<?= htmlspecialchars($finDownloadName) ?>" class="icon-action-btn" title="Download Uploaded Financial Report">
                      <ion-icon name="download-outline"></ion-icon>
                    </a>
                    <?php elseif (!$noFinancialInvolvement): ?>
                    <button class="icon-action-btn" type="button"
                      onclick="openExportModal(
                        <?= json_encode($ev['EventName']) ?>,
                        <?= json_encode($orgName) ?>,
                        <?= json_encode($evDate) ?>,
                        <?= json_encode($evTime) ?>,
                        <?= json_encode($ev['EventLocation'] ?? 'N/A') ?>,
                        <?= json_encode($evStatus) ?>,
                        <?= $attended ?>, <?= $registered ?>, <?= $absent ?>, <?= $attPct ?>,
                        0,
                        <?= $officersJson ?>,
                        'financial'
                      )">
                      <ion-icon name="eye-outline"></ion-icon>
                    </button>
                    <button class="icon-action-btn" type="button"
                      onclick="exportReport(
                        <?= json_encode($ev['EventName']) ?>,
                        <?= json_encode($orgName) ?>,
                        <?= json_encode($evDate) ?>,
                        <?= json_encode($evTime) ?>,
                        <?= json_encode($ev['EventLocation'] ?? 'N/A') ?>,
                        <?= json_encode($evStatus) ?>,
                        <?= $attended ?>, <?= $registered ?>, <?= $absent ?>, <?= $attPct ?>,
                        0,
                        <?= $officersJson ?>,
                        'financial'
                      )">
                      <ion-icon name="download-outline"></ion-icon>
                    </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php elseif ($reportType === 'student'): ?>
      <!-- ── 2. STUDENT REPORTS VIEW ── -->
      <div class="reports-table-card">
        <div class="reports-table-header">
          <h3>Registered Students Master List</h3>
          <span>Displaying official academic, verification, and contact records</span>
        </div>
        <div class="reports-table-responsive">
          <table class="reports-table">
            <thead>
              <tr>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Course &amp; Year</th>
                <th>Email Address</th>
                <th>Phone (Local)</th>
                <th>Organization</th>
                <th>AI Verification</th>
                <th>Account Status</th>
                <th>Date Registered</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($reportData)): ?>
              <tr><td colspan="9" style="text-align:center;color:#64748b;padding:2rem;">No student records found.</td></tr>
              <?php else: foreach ($reportData as $stu):
                $fullName = trim(($stu['first_name'] ?? '') . ' ' . ($stu['middle_name'] ? $stu['middle_name'][0] . '. ' : '') . ($stu['last_name'] ?? ''));
                $courseSec = trim(($stu['course'] ?? '') . ' ' . ($stu['year_level'] ?? '') . ' ' . ($stu['section'] ?? ''));
                $rawPhone = $stu['phone'] ?? '';
                $digits = preg_replace('/\D/', '', $rawPhone);
                if (str_starts_with($digits, '63')) $digits = '0' . substr($digits, 2);
                elseif (!str_starts_with($digits, '0') && strlen($digits) === 10) $digits = '0' . $digits;
                $formattedPhone = $digits ?: 'N/A';

                $vStatus = strtolower($stu['verification_status'] ?? '');
                $vCls = 'yellow'; $vLabel = 'Pending Review';
                if (in_array($vStatus, ['ai_verified', 'approved', 'verified'])) { $vCls = 'blue'; $vLabel = 'AI Verified'; }
                elseif (in_array($vStatus, ['rejected', 'failed'])) { $vCls = 'red'; $vLabel = 'Rejected'; }

                $accStatus = strtolower($stu['status'] ?? 'pending');
                $accCls = ($accStatus === 'active' && $vCls === 'blue') ? 'green' : 'yellow';
                $accLabel = ($accStatus === 'active' && $vCls === 'blue') ? 'Active' : 'Pending';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($stu['student_id'] ?? 'N/A') ?></strong></td>
                <td><?= htmlspecialchars($fullName) ?></td>
                <td><?= htmlspecialchars($courseSec ?: 'N/A') ?></td>
                <td><?= htmlspecialchars($stu['Email'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($formattedPhone) ?></td>
                <td><?= htmlspecialchars($stu['OrgName'] ?? 'Unassigned') ?></td>
                <td>
                  <span class="badge <?= $vCls ?>">
                    <?= htmlspecialchars($vLabel) ?> <?= !empty($stu['ai_verification_score']) ? '(' . (int)$stu['ai_verification_score'] . '%)' : '' ?>
                  </span>
                </td>
                <td><span class="badge <?= $accCls ?>"><?= htmlspecialchars($accLabel) ?></span></td>
                <td><?= !empty($stu['created_at']) ? date('M j, Y g:i A', strtotime($stu['created_at'])) : 'N/A' ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($reportType === 'organization'): ?>
      <!-- ── 3. ORGANIZATION REPORTS VIEW ── -->
      <div class="reports-table-card">
        <div class="reports-table-header">
          <h3>Recognized Student Organizations</h3>
          <span>Membership metrics, officer structures, and activity statistics</span>
        </div>
        <div class="reports-table-responsive">
          <table class="reports-table">
            <thead>
              <tr>
                <th>Organization Name</th>
                <th>Category / Type</th>
                <th>Total Members</th>
                <th>Officers</th>
                <th>Total Events</th>
                <th>Completed Events</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($reportData)): ?>
              <tr><td colspan="6" style="text-align:center;color:#64748b;padding:2rem;">No organization records found.</td></tr>
              <?php else: foreach ($reportData as $org): ?>
              <tr>
                <td><strong><?= htmlspecialchars($org['OrgName'] ?? '') ?></strong></td>
                <td><?= htmlspecialchars($org['OrgType'] ?? 'Academic') ?></td>
                <td><span class="badge cyan"><?= number_format((int)($org['total_members'] ?? 0)) ?> Students</span></td>
                <td><span class="badge purple"><?= number_format((int)($org['total_officers'] ?? 0)) ?> Officers</span></td>
                <td><strong><?= number_format((int)($org['total_events'] ?? 0)) ?></strong></td>
                <td><span class="badge green"><?= number_format((int)($org['completed_events'] ?? 0)) ?> Completed</span></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($reportType === 'announcement'): ?>
      <!-- ── 4. ANNOUNCEMENT REPORTS VIEW ── -->
      <div class="reports-table-card">
        <div class="reports-table-header">
          <h3>Broadcast Announcements Log</h3>
          <span>Official announcements published across campus departments</span>
        </div>
        <div class="reports-table-responsive">
          <table class="reports-table">
            <thead>
              <tr>
                <th>Title</th>
                <th>Organization / Issuer</th>
                <th>Status</th>
                <th>Date Posted</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($reportData)): ?>
              <tr><td colspan="4" style="text-align:center;color:#64748b;padding:2rem;">No announcements found.</td></tr>
              <?php else: foreach ($reportData as $ann): 
                $dateVal = $ann['DatePosted'] ?? $ann['created_at'] ?? '';
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($ann['Title'] ?? 'Untitled') ?></strong></td>
                <td><?= htmlspecialchars($ann['OrgName'] ?? 'Office of Student Affairs') ?></td>
                <td><span class="badge <?= strtolower($ann['Status'] ?? '') === 'published' ? 'green' : 'yellow' ?>"><?= htmlspecialchars($ann['Status'] ?? 'Published') ?></span></td>
                <td><?= !empty($dateVal) ? date('M j, Y g:i A', strtotime($dateVal)) : 'N/A' ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($reportType === 'attendance'): ?>
      <!-- ── 5. ATTENDANCE REPORTS VIEW ── -->
      <div class="reports-table-card">
        <div class="reports-table-header">
          <h3>Event Attendance Master Log</h3>
          <span>Participant check-in records, facial verification status, and timestamps</span>
        </div>
        <div class="reports-table-responsive">
          <table class="reports-table">
            <thead>
              <tr>
                <th>Event Name</th>
                <th>Organization</th>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Course &amp; Year</th>
                <th>Check-In Time</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($reportData)): ?>
              <tr><td colspan="6" style="text-align:center;color:#64748b;padding:2rem;">No attendance records found for this period.</td></tr>
              <?php else: foreach ($reportData as $att): 
                $attName = trim(($att['first_name'] ?? '') . ' ' . ($att['last_name'] ?? ''));
                $attCourse = trim(($att['course'] ?? '') . ' ' . ($att['year_level'] ?? ''));
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($att['EventName'] ?? 'N/A') ?></strong></td>
                <td><?= htmlspecialchars($att['OrgName'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($att['student_id'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($attName) ?></td>
                <td><?= htmlspecialchars($attCourse ?: 'N/A') ?></td>
                <td><span class="badge green"><?= !empty($att['CheckInTime']) ? date('M j, Y g:i A', strtotime($att['CheckInTime'])) : 'Recorded' ?></span></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($reportType === 'financial'): ?>
      <!-- ── 6. FINANCIAL REPORTS VIEW ── -->
      <div class="reports-table-card">
        <div class="reports-table-header">
          <h3>Financial Accountability &amp; Budget Statements</h3>
          <span>Event expenses, budget utilization, and financial clearance tracking</span>
        </div>
        <div class="reports-table-responsive">
          <table class="reports-table">
            <thead>
              <tr>
                <th>Event Name</th>
                <th>Organization</th>
                <th>Event Date</th>
                <th>Location / Venue</th>
                <th>Financial Status</th>
                <th>Uploaded Document</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($reportData)): ?>
              <tr><td colspan="6" style="text-align:center;color:#64748b;padding:2rem;">No financial records found.</td></tr>
              <?php else: foreach ($reportData as $fin): 
                $noFin = !empty($fin['no_financial_report']) || !empty($fin['NoFinancialReport']);
                $evDate = !empty($fin['EventDateTime']) ? date('M j, Y g:i A', strtotime($fin['EventDateTime'])) : 'N/A';
                $finDoc = $fin['financial_doc'] ?? null;
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($fin['EventName'] ?? '') ?></strong></td>
                <td><?= htmlspecialchars($fin['OrgName'] ?? '') ?></td>
                <td><?= $evDate ?></td>
                <td><?= htmlspecialchars($fin['EventLocation'] ?? 'N/A') ?></td>
                <td>
                  <?php if ($noFin): ?>
                  <span class="badge green">No Financial Involvement</span>
                  <?php elseif (!empty($finDoc)): ?>
                  <span class="badge blue">Document Uploaded</span>
                  <?php else: ?>
                  <span class="badge yellow">Pending Submission</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($finDoc['FilePath'])): ?>
                  <a href="../../<?= htmlspecialchars(ltrim($finDoc['FilePath'], '/')) ?>" download class="btn-report-action btn-export-pdf" style="padding:4px 10px;font-size:.78rem;">
                    <ion-icon name="download-outline"></ion-icon> Download
                  </a>
                  <?php else: ?>
                  <span style="color:#94a3b8;font-size:0.8rem;">None</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php elseif ($reportType === 'audit'): ?>
      <!-- ── 7. AUDIT REPORTS VIEW ── -->
      <div class="reports-table-card">
        <div class="reports-table-header">
          <h3>System Audit Trail &amp; Security Log</h3>
          <span>Complete record of institutional transactions, verification events, and logins</span>
        </div>
        <div class="reports-table-responsive">
          <table class="reports-table">
            <thead>
              <tr>
                <th>Timestamp</th>
                <th>User / Actor</th>
                <th>User Type</th>
                <th>Module</th>
                <th>Action</th>
                <th>IP Address</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($reportData)): ?>
              <tr><td colspan="7" style="text-align:center;color:#64748b;padding:2rem;">No audit logs found.</td></tr>
              <?php else: foreach ($reportData as $log): ?>
              <tr>
                <td style="white-space:nowrap;"><?= !empty($log['Date']) ? date('M j, Y g:i:s A', strtotime($log['Date'])) : 'N/A' ?></td>
                <td><strong><?= htmlspecialchars($log['ActorName'] ?? 'System') ?></strong></td>
                <td><span class="badge cyan"><?= htmlspecialchars(ucfirst($log['ActorType'] ?? 'User')) ?></span></td>
                <td><span class="badge purple"><?= htmlspecialchars($log['module'] ?? 'System') ?></span></td>
                <td><strong><?= htmlspecialchars($log['Action'] ?? '') ?></strong></td>
                <td><code><?= htmlspecialchars($log['IPAddress'] ?? '127.0.0.1') ?></code></td>
                <td><?= htmlspecialchars($log['description'] ?? '') ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <!-- Document Preview Modal -->
    <div id="reportDocPreviewModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(15,23,42,0.75);backdrop-filter:blur(8px);z-index:99999;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;">
      <div class="modal-content" style="background:#fff;width:min(950px,95vw);height:88vh;border-radius:18px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        <div style="background:linear-gradient(135deg,#1e40af,#3b82f6);padding:18px 24px;display:flex;align-items:center;justify-content:space-between;color:#fff;flex-shrink:0;">
          <div style="display:flex;align-items:center;gap:12px;overflow:hidden;">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <ion-icon name="document-text-outline" style="font-size:22px;color:#fff;"></ion-icon>
            </div>
            <div style="overflow:hidden;">
              <h3 id="reportDocModalTitle" style="margin:0;font-size:1.05rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Document Preview</h3>
              <p id="reportDocModalSub" style="margin:2px 0 0;font-size:0.75rem;color:rgba(255,255,255,0.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Organization Report</p>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <a id="reportDocModalDownloadBtn" href="#" download="" class="btn" style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:#fff;padding:6px 14px;border-radius:8px;font-size:12.5px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-weight:600;transition:all 0.2s;">
              <ion-icon name="download-outline"></ion-icon> Download
            </a>
            <button type="button" onclick="closeReportDocPreview()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:20px;">
              <ion-icon name="close-outline"></ion-icon>
            </button>
          </div>
        </div>
        <div id="reportDocModalBody" style="flex:1;background:#f8fafc;overflow:auto;position:relative;display:flex;align-items:center;justify-content:center;min-height:0;"></div>
        <div style="background:#ffffff;border-top:1px solid #e2e8f0;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
          <span id="reportDocModalMeta" style="font-size:12px;color:#64748b;">Official Event Documentation</span>
          <button type="button" onclick="closeReportDocPreview()" style="background:#f1f5f9;border:1px solid #cbd5e1;color:#334155;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Close Preview</button>
        </div>
      </div>
    </div>
    
    <div id="exportModal" class="modal-overlay modal-export-wrap">
      <div class="modal-content modal-export-content">
        <div class="modal-header">
          <h3 id="exportModalTitle">Report Preview</h3>
          <button class="close-modal-btn" type="button" onclick="closeExportModal()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body modal-body-export">
          <div id="exportModalBody"></div>
          <div class="modal-actions-right">
            <button type="button" onclick="closeExportModal()" class="btn-secondary-custom">Close</button>
            <button type="button" id="exportPrintBtn" onclick="printExport()" class="btn-primary-custom">
              <ion-icon name="download-outline" class="btn-icon-prefix"></ion-icon> Export / Print
            </button>
          </div>
        </div>
      </div>
    </div>

    <div id="declineModal" class="modal-overlay modal-decline-wrap">
      <div class="modal-content modal-decline-content">
        <div class="modal-header">
          <h3>Decline Report</h3>
          <button class="close-modal-btn" type="button" onclick="closeDeclineModal()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body modal-body-decline">
          <p class="decline-instruction">Please provide remarks on why <strong id="declineReportName">this report</strong> is being declined:</p>
          <textarea id="declineRemarks" rows="4" class="decline-textarea" placeholder="Enter your remarks here..."></textarea>
          <div class="modal-actions-right">
            <button type="button" onclick="closeDeclineModal()" class="btn-secondary-custom">Cancel</button>
            <button type="button" onclick="submitDecline()" class="btn-danger-custom">Confirm Decline</button>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.1.15/dist/docx-preview.min.js"></script>
  <script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
  <script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/admin/reports.js?v=<?= time() ?>"></script>
  
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>

  <!-- Client-Side CSV and PDF Export Engine -->
  <script>
    function exportReportPDF() {
      window.print();
    }

    function exportCurrentReportCSV(category) {
      const table = document.querySelector('.reports-table');
      if (table) {
        let csv = [];
        const rows = table.querySelectorAll('tr');
        for (let i = 0; i < rows.length; i++) {
          let row = [], cols = rows[i].querySelectorAll('td, th');
          for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/\s+/g, ' ').trim();
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
          }
          csv.push(row.join(','));
        }
        triggerDownloadCSV(csv.join('\r\n'), `OSA_${category.toUpperCase()}_Report`);
        return;
      }

      // Event accordion export
      const eventItems = document.querySelectorAll('.event-accordion-item');
      if (eventItems.length) {
        let csv = [
          ['"Event Name"', '"Date"', '"Status"', '"Attended"', '"Registered"', '"Absent"', '"Venue"'].join(',')
        ];
        eventItems.forEach(item => {
          const name = (item.querySelector('.event-title-date h4')?.innerText || '').replace(/"/g, '""');
          const date = (item.querySelector('.event-title-date p')?.innerText || '').replace(/"/g, '""');
          const status = (item.querySelector('.event-summary .badge')?.innerText || '').trim().replace(/"/g, '""');
          csv.push(`"${name}","${date}","${status}","","","",""`);
        });
        triggerDownloadCSV(csv.join('\r\n'), 'OSA_EVENT_REPORTS');
        return;
      }

      alert('No data available to export.');
    }

    function triggerDownloadCSV(csvContent, baseFilename) {
      const csvString = '\uFEFF' + csvContent;
      const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${baseFilename}_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }
  </script>
</body>
</html>
