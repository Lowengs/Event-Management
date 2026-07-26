<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';


$nowStr = date('Y-m-d H:i:s');
$conn->query("UPDATE event SET EventStatus = 'Ongoing' WHERE EventStatus = 'Scheduled' AND EventDateTime <= '$nowStr' AND (EndDateTime >= '$nowStr' OR EndDateTime IS NULL)");
$conn->query("UPDATE event SET EventStatus = 'Completed' WHERE EventStatus IN ('Scheduled', 'Ongoing') AND ((EndDateTime IS NOT NULL AND EndDateTime <= '$nowStr') OR (EndDateTime IS NULL AND DATE_ADD(EventDateTime, INTERVAL 2 HOUR) <= '$nowStr'))");


$currentOrgId = isset($_GET['org']) ? (int)$_GET['org'] : 0; 


$orgs = [];
$r_orgs = $conn->query("SELECT OrgId, OrgName FROM organization ORDER BY OrgName ASC");
if ($r_orgs) while ($row = $r_orgs->fetch_assoc()) $orgs[] = $row;


$where_org = $currentOrgId > 0 ? "AND e.OrgId = $currentOrgId" : "";

$stat_scheduled  = $conn->query("SELECT COUNT(*) FROM event e WHERE e.EventStatus = 'Scheduled'  $where_org")->fetch_row()[0] ?? 0;
$stat_ongoing    = $conn->query("SELECT COUNT(*) FROM event e WHERE e.EventStatus = 'Ongoing'    $where_org")->fetch_row()[0] ?? 0;
$stat_completed  = $conn->query("SELECT COUNT(*) FROM event e WHERE e.EventStatus = 'Completed'  $where_org")->fetch_row()[0] ?? 0;
$stat_cancelled  = $conn->query("SELECT COUNT(*) FROM event e WHERE e.EventStatus IN ('Cancelled','Delayed') $where_org")->fetch_row()[0] ?? 0;


$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "
    SELECT e.EventId, e.EventName, e.EventDetails, e.EventDescription, e.EventDateTime, e.EndDateTime,
           e.EventLocation, e.EventPlace, e.EventStatus, e.AttendanceMethod, e.EventPicture,
           o.OrgId, o.OrgName,
           (SELECT COUNT(*) FROM attendance a WHERE a.EventId = e.EventId) AS attended,
           (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS registered
    FROM event e
    LEFT JOIN organization o ON e.OrgId = o.OrgId
    WHERE 1=1
";
if ($currentOrgId > 0) $sql .= " AND e.OrgId = $currentOrgId";
if ($search !== '')    $sql .= " AND (e.EventName LIKE '%" . $conn->real_escape_string($search) . "%' OR o.OrgName LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($statusFilter !== '') $sql .= " AND e.EventStatus = '" . $conn->real_escape_string($statusFilter) . "'";
$sql .= " ORDER BY o.OrgName ASC, e.EventDateTime DESC";

$events_raw = [];
$res = $conn->query($sql);
if ($res) while ($row = $res->fetch_assoc()) $events_raw[] = $row;


$events_by_org = [];
foreach ($events_raw as $ev) {
    $org = $ev['OrgName'] ?? 'Unassigned';
    $events_by_org[$org][] = $ev;
}


$officers_by_org = [];
$r_off = $conn->query("
    SELECT u.first_name, u.last_name, u.officer_role, o.OrgName
    FROM user u
    JOIN organization o ON o.OrgId = u.OrgId
    WHERE u.is_officer = 1
    ORDER BY u.officer_role, u.last_name
");
if ($r_off) while ($row = $r_off->fetch_assoc()) {
    $officers_by_org[$row['OrgName']][] = trim($row['first_name'].' '.$row['last_name']) . ($row['officer_role'] ? ' ('.$row['officer_role'].')' : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Reports</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/reports.css?v=<?= time() ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet" />

  <link rel="icon" href="../../assets/img/philsca.png">

  
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
        <li><a href="../../config/API/osa_logout.php" class="nav"><ion-icon name="log-out-outline"></ion-icon><span>Logout</span></a></li>
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
            <ion-icon name="close-circle-outline"></ion-icon>
          </div>
        </div>
      </div>

      
      <div class="search-filter-row">
        <form method="GET" style="display:contents;">
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
          <p style="padding:2rem;color:#64748b;text-align:center;">No events found.</p>
        <?php else: ?>
        <?php foreach ($events_by_org as $orgName => $evList): ?>
          <div class="accordion-header">
            <h2><?= htmlspecialchars($orgName) ?> Events (<?= count($evList) ?>)</h2>
          </div>

          <?php foreach ($evList as $ev):
            $evStatus   = $ev['EventStatus'] ?? 'Scheduled';
            $statusMap  = [
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
                    <span class="badge <?= $statusCls ?> with-icon"><ion-icon name="<?= $statusIcon ?>"></ion-icon> <?= htmlspecialchars($evStatus) ?></span>
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
                    <?php if (strtolower($evStatus) === 'pending'): ?>
                    <button class="btn green-btn"><ion-icon name="checkmark-circle-outline"></ion-icon> Approve</button>
                    <button class="btn red-btn" type="button" onclick="openDeclineModal('<?= htmlspecialchars($ev['EventName'], ENT_QUOTES) ?> - Post-Activity Report')"><ion-icon name="close-circle-outline"></ion-icon> Decline</button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              
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
                    <span class="badge <?= $statusCls ?> with-icon"><ion-icon name="<?= $statusIcon ?>"></ion-icon> <?= htmlspecialchars($evStatus) ?></span>
                  </div>
                </div>
                <div class="report-card-meta-row">
                  <div class="meta-info">
                    <span><ion-icon name="calendar-outline"></ion-icon> Date: <?= $evDate ?></span>
                    <span>Type: Financial Statement</span>
                    <span>Organization: <?= htmlspecialchars($orgName) ?></span>
                  </div>
                  <div class="report-actions">
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

    
    <div id="exportModal" class="modal-overlay" style="display:none;">
      <div class="modal-content" style="max-width:600px;">
        <div class="modal-header">
          <h3 id="exportModalTitle">Report Preview</h3>
          <button class="close-modal-btn" type="button" onclick="closeExportModal()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body" style="flex-direction:column;align-items:stretch;background:#fff;min-height:auto;padding:1.5rem;">
          <div id="exportModalBody"></div>
          <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1.25rem;">
            <button type="button" onclick="closeExportModal()" style="padding:8px 16px;border:1px solid #e2e8f0;background:#fff;border-radius:6px;cursor:pointer;font-weight:600;color:#334155;">Close</button>
            <button type="button" id="exportPrintBtn" onclick="printExport()" style="padding:8px 20px;border:none;background:#003366;color:#fff;border-radius:6px;cursor:pointer;font-weight:600;">
              <ion-icon name="download-outline" style="vertical-align:middle;margin-right:4px;"></ion-icon> Export / Print
            </button>
          </div>
        </div>
      </div>
    </div>

    
    <div id="declineModal" class="modal-overlay" style="display:none;">
      <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
          <h3>Decline Report</h3>
          <button class="close-modal-btn" type="button" onclick="closeDeclineModal()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body" style="flex-direction: column; align-items: stretch; background: #fff; min-height: auto;">
          <p style="margin-top: 0; margin-bottom: 12px; color: #64748b; font-size: 14px;">Please provide remarks on why <strong id="declineReportName">this report</strong> is being declined:</p>
          <textarea id="declineRemarks" rows="4" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; font-family: inherit; font-size: 14px; outline: none; resize: vertical;" placeholder="Enter your remarks here..."></textarea>
          <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 16px;">
            <button type="button" onclick="closeDeclineModal()" style="padding: 8px 16px; border: 1px solid #e2e8f0; background: #fff; border-radius: 6px; cursor: pointer; font-weight: 600; color: #334155;">Cancel</button>
            <button type="button" onclick="submitDecline()" style="padding: 8px 16px; border: none; background: #dc2626; color: #fff; border-radius: 6px; cursor: pointer; font-weight: 600;">Confirm Decline</button>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/admin/reports.js"></script>
  
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
