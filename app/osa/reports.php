<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';

$_GET['action'] = 'get_osa_reports';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$reportsApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$currentOrgId  = isset($_GET['org']) ? (int)$_GET['org'] : 0;
$search        = trim($_GET['search'] ?? '');
$statusFilter  = $_GET['status'] ?? '';

$stat_scheduled = (int)($reportsApiRes['stats']['scheduled'] ?? 0);
$stat_ongoing   = (int)($reportsApiRes['stats']['ongoing'] ?? 0);
$stat_completed = (int)($reportsApiRes['stats']['completed'] ?? 0);
$stat_cancelled = (int)($reportsApiRes['stats']['cancelled'] ?? 0);
$stat_delayed   = (int)($reportsApiRes['stats']['delayed'] ?? 0);
$orgs           = $reportsApiRes['orgs'] ?? [];
$events_by_org  = $reportsApiRes['events_by_org'] ?? [];
$officers_by_org= $reportsApiRes['officers_by_org'] ?? [];
$allDocsByEvent = $reportsApiRes['all_docs_by_event'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Reports</title>

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
          <h2>Reports &amp; Documentation</h2>
          <p>Generate official records and export data</p>
        </div>
      </div>
      <div class="divider"></div>

      <div class="stats-cards-row">
        <div class="stat-card yellow">
          <div class="stat-info">
            <p>Scheduled</p>
            <h3><?= (int)$stat_scheduled ?></h3>
          </div>
          <div class="stat-icon yellow">
            <ion-icon name="calendar-outline"></ion-icon>
          </div>
        </div>
        <div class="stat-card green">
          <div class="stat-info">
            <p>Ongoing</p>
            <h3><?= (int)$stat_ongoing ?></h3>
          </div>
          <div class="stat-icon green">
            <ion-icon name="time-outline"></ion-icon>
          </div>
        </div>
        <div class="stat-card blue">
          <div class="stat-info">
            <p>Completed</p>
            <h3><?= (int)$stat_completed ?></h3>
          </div>
          <div class="stat-icon blue">
            <ion-icon name="checkmark-circle-outline"></ion-icon>
          </div>
        </div>
        <div class="stat-card red">
          <div class="stat-info">
            <p>Cancelled / Delayed</p>
            <h3><?= (int)$stat_cancelled ?></h3>
          </div>
          <div class="stat-icon red">
            <ion-icon name="alert-circle-outline"></ion-icon>
          </div>
        </div>
      </div>

      <div class="search-filter-row">
        <form method="GET" class="form-inline-contents">
          <div class="search-box">
            <ion-icon name="search-outline"></ion-icon>
            <input type="text" name="search" placeholder="Search events or reports..." value="<?= htmlspecialchars($search) ?>" />
          </div>

          <div class="filter-box">
            <ion-icon name="business-outline"></ion-icon>
            <select name="org" onchange="this.form.submit()">
              <option value="0" <?= $currentOrgId === 0 ? 'selected' : '' ?>>All Organizations</option>
              <?php foreach ($orgs as $o): ?>
              <option value="<?= (int)$o['OrgId'] ?>" <?= $currentOrgId === (int)$o['OrgId'] ? 'selected' : '' ?>><?= htmlspecialchars($o['OrgName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-box">
            <ion-icon name="funnel-outline"></ion-icon>
            <select name="status" onchange="this.form.submit()">
              <option value="">All Status</option>
              <option value="Scheduled"  <?= $statusFilter === 'Scheduled'  ? 'selected' : '' ?>>Scheduled</option>
              <option value="Ongoing"    <?= $statusFilter === 'Ongoing'    ? 'selected' : '' ?>>Ongoing</option>
              <option value="Completed"  <?= $statusFilter === 'Completed'  ? 'selected' : '' ?>>Completed</option>
              <option value="Cancelled"  <?= $statusFilter === 'Cancelled'  ? 'selected' : '' ?>>Cancelled</option>
              <option value="Delayed"    <?= $statusFilter === 'Delayed'    ? 'selected' : '' ?>>Delayed</option>
            </select>
          </div>
        </form>
      </div>

      <div class="events-accordion-container">
        <?php if (empty($events_by_org)): ?>
          <p class="empty-reports-msg">No events found.</p>
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
            $postSummary = "The \"{$ev['EventName']}\" organized by {$orgName} took place on {$evDate}" .
              (!empty($ev['EventLocation']) ? " at {$ev['EventLocation']}" : '') .
              ". A total of {$attended} student(s) attended out of {$registered} registered ({$attPct}% attendance rate), {$absent} absent." .
              " Event status: {$evStatus}.";
          ?>
          <div class="event-accordion-item">
            <div class="event-summary" onclick="this.parentElement.classList.toggle('expanded')">
              <div class="event-summary-left">
                <ion-icon name="chevron-forward-outline" class="chevron-icon"></ion-icon>
                <ion-icon name="calendar-outline" class="calendar-icon"></ion-icon>
                <div class="event-title-date">
                  <h4><?= htmlspecialchars($ev['EventName']) ?></h4>
                  <p><?= $evDate ?></p>
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
                    <span>Spoofed: <strong>0</strong></span>
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
                    <?php if (strtolower($evStatus) === 'pending'): ?>
                    <button class="btn green-btn"><ion-icon name="checkmark-circle-outline"></ion-icon> Approve</button>
                    <button class="btn red-btn" type="button" onclick="openDeclineModal('<?= htmlspecialchars($ev['EventName'], ENT_QUOTES) ?> - Post-Activity Report')"><ion-icon name="close-circle-outline"></ion-icon> Decline</button>
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
    </div>

    <!-- Document Preview Modal (No Download on View) -->
    <div id="reportDocPreviewModal" class="modal-overlay" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(15,23,42,0.75);backdrop-filter:blur(8px);z-index:99999;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;">
      <div class="modal-content" style="background:#fff;width:min(950px,95vw);height:88vh;border-radius:18px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        
        <!-- Modal Header -->
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
            <a id="reportDocModalDownloadBtn" href="#" download="" class="btn" style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:#fff;padding:6px 14px;border-radius:8px;font-size:12.5px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
              <ion-icon name="download-outline"></ion-icon> Download
            </a>
            <button type="button" onclick="closeReportDocPreview()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:20px;transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
              <ion-icon name="close-outline"></ion-icon>
            </button>
          </div>
        </div>

        <!-- Modal Body -->
        <div id="reportDocModalBody" style="flex:1;background:#f8fafc;overflow:auto;position:relative;display:flex;align-items:center;justify-content:center;min-height:0;">
          <!-- Dynamic viewer content inserted here -->
        </div>

        <!-- Modal Footer -->
        <div style="background:#ffffff;border-top:1px solid #e2e8f0;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
          <span id="reportDocModalMeta" style="font-size:12px;color:#64748b;">Official Event Documentation</span>
          <button type="button" onclick="closeReportDocPreview()" style="background:#f1f5f9;border:1px solid #cbd5e1;color:#334155;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
            Close Preview
          </button>
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
</body>
</html>
