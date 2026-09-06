<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';


$total_events    = 0;
$ongoing_count   = 0;
$completed_count = 0;
$conflict_count  = 0;
$dbEvents        = [];
$orgs_from_db    = [];

$calYear      = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$calMonth     = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
if ($calMonth < 1) { $calMonth = 12; $calYear--; }
if ($calMonth > 12){ $calMonth = 1;  $calYear++; }
$todayDay     = (date('Y') == $calYear && date('m') == $calMonth) ? (int)date('j') : -1;
$daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $calMonth, $calYear);
$firstWeekday = (int)date('w', mktime(0,0,0,$calMonth,1,$calYear));
$monthName    = date('F Y', mktime(0,0,0,$calMonth,1,$calYear));
$prevMonth    = $calMonth - 1 < 1  ? 12 : $calMonth - 1;
$prevYear     = $calMonth - 1 < 1  ? $calYear - 1 : $calYear;
$nextMonth    = $calMonth + 1 > 12 ? 1  : $calMonth + 1;
$nextYear     = $calMonth + 1 > 12 ? $calYear + 1 : $calYear;

$_GET['action'] = 'get_osa_events';
ob_start();
require_once __DIR__ . '/../../config/API/endpoints/index.php';
header('Content-Type: text/html; charset=UTF-8');
$eventsApiRes    = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$allEvents       = $eventsApiRes['events'] ?? [];
$stats           = $eventsApiRes['stats']  ?? [];
$total_events    = (int)($stats['total_events'] ?? count($allEvents));
$ongoing_count   = (int)($stats['ongoing']      ?? 0);
$completed_count = (int)($stats['completed']    ?? 0);
if ($total_events === 0 && !empty($allEvents)) {
    $total_events = count($allEvents);
}
if ($ongoing_count === 0 && $completed_count === 0 && !empty($allEvents)) {
    foreach ($allEvents as $ev) {
        $st = strtolower(trim((string)($ev['EventStatus'] ?? 'scheduled')));
        if ($st === 'ongoing') $ongoing_count++;
        elseif ($st === 'completed') $completed_count++;
    }
}
require_once __DIR__ . '/../../config/db.php';

// Fetch active organizations for the left sidebar legend & filter
$orgs_from_db = [];
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $orgRes = $conn->query("
            SELECT OrgId, OrgName, Status 
            FROM organization 
            WHERE LOWER(COALESCE(Status, 'active')) = 'active' 
            ORDER BY OrgName ASC
        ");
        if ($orgRes) {
            while ($o = $orgRes->fetch_assoc()) {
                $orgs_from_db[] = $o;
            }
        }
    } catch (Throwable $e) {}
}

// Fallback: extract unique organizations from fetched events
if (empty($orgs_from_db) && !empty($allEvents)) {
    $seenOrgs = [];
    foreach ($allEvents as $ev) {
        $on = trim($ev['OrgName'] ?? '');
        if ($on !== '' && !isset($seenOrgs[$on])) {
            $seenOrgs[$on] = true;
            $orgs_from_db[] = ['OrgName' => $on];
        }
    }
}

// Fallback: system recognized student organizations
if (empty($orgs_from_db)) {
    $defaultOrgs = ['AISERS', 'AMTSO', 'AEROATSO', 'AETSO', 'ELITECH', 'ILAS'];
    foreach ($defaultOrgs as $name) {
        $orgs_from_db[] = ['OrgName' => $name];
    }
}

$conflict_count  = 0;
$dbEvents        = [];

foreach ($allEvents as $r) {
    $evDate = $r['EventDateTime'] ?? '';
    if (!empty($evDate) && date('Y', strtotime($evDate)) == $calYear && date('m', strtotime($evDate)) == $calMonth) {
        $d = (int)date('j', strtotime($evDate));
        $dbEvents[$d][] = $r;
    }
}


$orgColors = ['#f59e0b','#ec4899','#f97316','#3b82f6','#22c55e','#ef4444','#8b5cf6','#06b6d4','#14b8a6','#6366f1'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Calendar</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/calendar.css?<?= time() ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet" />

  <link rel="icon" href="../../assets/img/philsca.png">
  
  <style>
    .org-filter-item {
      cursor: pointer;
      padding: 6px 10px;
      margin-right: 14px;
      border-radius: 6px;
      transition: all 0.15s ease;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .org-filter-item:hover {
      background: #f1f5f9;
      transform: translateX(2px);
    }
    .org-filter-item.active-org-filter {
      background: #eff6ff !important;
      outline: 1.5px solid #93c5fd;
    }
    .org-filter-item.active-org-filter span:last-child {
      color: #1d4ed8 !important;
      font-weight: 700 !important;
    }
  </style>

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
        <li><a href="calendar.php" class="nav active"><ion-icon name="calendar-number-outline"></ion-icon><span>Calendar</span></a></li>
        <li><a href="events.php" class="nav"><ion-icon name="calendar-outline"></ion-icon><span>Events</span></a></li>
        <li><a href="students.php" class="nav"><ion-icon name="people-outline"></ion-icon><span>Students</span></a></li>
        <li><a href="announcement.php" class="nav"><ion-icon name="megaphone-outline"></ion-icon><span>Announcements</span></a></li>
        <li><a href="reports.php" class="nav"><ion-icon name="document-text-outline"></ion-icon><span>Reports</span></a></li>
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
          <h2>Events Calendar</h2>
          <p>Schedule view of all registered student organization events</p>
        </div>
      </div>

      <div class="divider"></div>

      <section class="stats-grid">
        <article class="stat-tile">
          <div>
            <p class="stat-label">Total Events</p>
            <p class="stat-value"><?= (int)$total_events ?></p>
          </div>
          <div class="tile-icon total">
            <ion-icon name="calendar-outline"></ion-icon>
          </div>
        </article>

        <article class="stat-tile">
          <div>
            <p class="stat-label">Ongoing Events</p>
            <p class="stat-value ongoing" style="color:#d97706;"><?= (int)$ongoing_count ?></p>
          </div>
          <div class="tile-icon ongoing" style="background:#fef3c7;color:#d97706;">
            <ion-icon name="time-outline"></ion-icon>
          </div>
        </article>

        <article class="stat-tile">
          <div>
            <p class="stat-label">Completed Events</p>
            <p class="stat-value completed" style="color:#16a34a;"><?= (int)$completed_count ?></p>
          </div>
          <div class="tile-icon completed" style="background:#dcfce7;color:#16a34a;">
            <ion-icon name="checkmark-circle-outline"></ion-icon>
          </div>
        </article>
      </section>

      <div class="calendar-content">
        <div class="left-row">
          
          <div class="legend-list">
            <p class="legend-label">Event Status</p>
            <div class="status-item">
              <span class="dot approved" style="background:#2563eb;"></span>
              Scheduled
            </div>
            <div class="status-item">
              <span class="dot ongoing" style="background:#d97706;"></span>
              Ongoing
            </div>
            <div class="status-item">
              <span class="dot completed" style="background:#16a34a;"></span>
              Completed
            </div>
            <div class="status-item">
              <span class="dot cancelled" style="background:#dc2626;"></span>
              Cancelled / Delayed
            </div>
          </div>

          <div class="legend-list" id="orgLegendCard">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
              <p class="legend-label" style="margin:0;">Organizations</p>
              <button type="button" id="resetOrgFilterBtn" onclick="filterCalendarByOrg('all', null)" style="display:none;background:none;border:none;color:#2563eb;font-size:11.5px;font-weight:700;cursor:pointer;padding:0;">Show All</button>
            </div>
            <?php foreach ($orgs_from_db as $i => $org):
              $color = $orgColors[$i % count($orgColors)];
              $slug  = strtolower(preg_replace('/[^a-z0-9]/i', '', $org['OrgName']));
            ?>
            <div class="status-item org-filter-item" data-org="<?= htmlspecialchars($slug) ?>" onclick="filterCalendarByOrg('<?= htmlspecialchars($slug) ?>', this)" style="cursor:pointer;padding:4px 6px;border-radius:6px;transition:0.15s;" title="Click to filter by <?= htmlspecialchars($org['OrgName']) ?>">
              <span class="org" style="background:<?= $color ?>;width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0;"></span>
              <span style="font-weight:600;font-size:13px;color:#334155;"><?= htmlspecialchars($org['OrgName']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>

        </div>

        <div class="right-row">
<section class="calendar-card">
  <header class="calendar-card__header">
    <p class="calendar-card__month"><?= $monthName ?></p>
    <div class="calendar-card__nav">
      <a href="calendar.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" aria-label="Previous month" style="text-decoration:none;"><button>&#8249;</button></a>
      <a href="calendar.php" aria-label="Today" style="text-decoration:none;"><button>Today</button></a>
      <a href="calendar.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" aria-label="Next month" style="text-decoration:none;"><button>&#8250;</button></a>
    </div>
  </header>
  <div class="calendar-grid">
    <div class="calendar-grid__day">Sun</div><div class="calendar-grid__day">Mon</div><div class="calendar-grid__day">Tue</div><div class="calendar-grid__day">Wed</div><div class="calendar-grid__day">Thu</div><div class="calendar-grid__day">Fri</div><div class="calendar-grid__day">Sat</div>
    <?php for ($pad = 0; $pad < $firstWeekday; $pad++): ?><div class="calendar-grid__cell calendar-grid__empty"></div><?php endfor; ?>
    <?php for ($day = 1; $day <= $daysInMonth; $day++):
      $cls = 'calendar-grid__cell';
      if ($day === $todayDay) $cls .= ' calendar-grid__today';
      if (isset($dbEvents[$day])) $cls .= ' calendar-grid__event';
    ?>
      <div class="<?= $cls ?>">
        <span><?= $day ?></span>
        <?php if (isset($dbEvents[$day])): foreach ($dbEvents[$day] as $ev):
          $orgSlug = strtolower(preg_replace('/[^a-z0-9]/i', '', $ev['OrgName'] ?? ''));
          $stRaw   = strtolower(trim($ev['EventStatus'] ?? 'scheduled'));
          $stClass = 'status-' . ($stRaw === 'upcoming' ? 'scheduled' : $stRaw);
          $t = date('H:i', strtotime($ev['EventDateTime']));
          $evDate = date('F j, Y', strtotime($ev['EventDateTime']));
          $evTime = date('h:i A', strtotime($ev['EventDateTime']));
        ?>
          <p class="event-pill <?= $stClass ?> <?= htmlspecialchars($orgSlug) ?>"
               onclick='openEventModal(
                 <?= json_encode($ev['EventName']) ?>,
                 <?= json_encode($ev['EventDescription'] ?? '') ?>,
                 <?= json_encode($evDate) ?>,
                 <?= json_encode($evTime) ?>,
                 <?= json_encode($ev['EventLocation'] ?? '') ?>,
                 <?= json_encode($ev['OrgName'] ?? '') ?>,
                 <?= json_encode($ev['EventType'] ?? 'General') ?>,
                 <?= json_encode((string)($ev['EventCapacity'] ?? '0')) ?>,
                 <?= json_encode($ev['EventStatus'] ?? 'pending') ?>,
                 "",
                 "None",
                 <?= json_encode($ev['AttendanceMethod'] ?? 'Standard') ?>
               )'>
            <span class="event-time"><?= $t ?></span>
            <span class="event-label"><?= htmlspecialchars(substr($ev['EventName'],0,14)) ?></span>
          </p>
        <?php endforeach; endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</section>

  
  <section style="margin-top: 1.5rem; background: #fff; border-radius: 0; overflow: hidden;">
    <header style="padding: 1rem 1.5rem; background: #fbfbfb;">
      <h3 style="margin: 0; color: #0f172a; font-size: 1.15rem; font-weight: 600;">Upcoming Event Schedules</h3>
    </header>
    <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
      <?php
      $upcomingList = array_values(array_filter($allEvents, function($ev) {
          return !empty($ev['EventDateTime']) && strtotime($ev['EventDateTime']) >= time();
      }));
      if (empty($upcomingList)): ?>
        <p style="color: #64748b; font-size: 0.9rem; margin: 0; text-align: center;">No upcoming events scheduled.</p>
      <?php else: ?>
        <?php foreach (array_slice($upcomingList, 0, 20) as $ue): ?>
          <?php 
            $ueDate    = date('F j, Y', strtotime($ue['EventDateTime']));
            $ueTime    = date('h:i A', strtotime($ue['EventDateTime']));
            $ueOrgSlug = strtolower(preg_replace('/[^a-z0-9]/i', '', $ue['OrgName'] ?: 'osa'));
          ?>
          <div class="upcoming-event-item <?= htmlspecialchars($ueOrgSlug) ?>" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; background: #fff; cursor: pointer; transition: all 0.2s;" 
               onclick='openEventModal(
                 <?= json_encode($ue['EventName']) ?>,
                 <?= json_encode($ue['EventDescription'] ?? "") ?>,
                 <?= json_encode($ueDate) ?>,
                 <?= json_encode($ueTime) ?>,
                 <?= json_encode($ue['EventLocation'] ?? "") ?>,
                 <?= json_encode($ue['OrgName'] ?: "OSA") ?>,
                 <?= json_encode($ue['EventType'] ?? "General") ?>,
                 <?= json_encode((string)($ue['EventCapacity'] ?? "0")) ?>,
                 <?= json_encode($ue['EventStatus'] ?? "pending") ?>,
                 "",
                 "None",
                 <?= json_encode($ue['AttendanceMethod'] ?? "Standard") ?>
               )'
               onmouseover="this.style.borderColor='#94a3b8'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" 
               onmouseout="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none';">
            <div>
              <h4 style="margin: 0 0 0.5rem 0; color: #0f172a; font-size: 1.05rem; font-weight: 700;"><?= htmlspecialchars($ue['EventName']) ?></h4>
              <p style="margin: 0; color: #64748b; font-size: 0.85rem; display: flex; align-items: center; gap: 6px;">
                <ion-icon name="business-outline" style="font-size:1rem;"></ion-icon>
                <?= htmlspecialchars($ue['OrgName'] ?: 'OSA') ?>
              </p>
            </div>
            <div style="text-align: right;">
              <p style="margin: 0 0 0.5rem 0; color: #0f172a; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; justify-content: flex-end; gap: 6px;">
                <ion-icon name="calendar-outline" style="font-size:1.1rem; color:#475569;"></ion-icon>
                <?= $ueDate ?>
              </p>
              <p style="margin: 0; color: #64748b; font-size: 0.85rem; display: flex; align-items: center; justify-content: flex-end; gap: 6px;">
                <ion-icon name="time-outline" style="font-size:1rem;"></ion-icon>
                <?= $ueTime ?>
              </p>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>


  
  <div id="eventModal" class="event-modal" style="display: none;">
    <div class="event-modal-content">
      <div class="modal-header">
        <div class="modal-header-text">
          <h2 id="modalEventTitle">Event Title</h2>
          <p id="modalEventOrg" class="modal-subtitle">Organization Name</p>
        </div>
        <button class="close-modal" aria-label="Close modal">
          <ion-icon name="close-outline"></ion-icon>
        </button>
      </div>

      <div class="modal-body">
        <div class="modal-status-row">
          <span class="status-pill" id="modalEventStatusPill">
            <ion-icon name="information-circle-outline"></ion-icon>
            <span id="modalEventStatus">Status</span>
          </span>
          <span class="org-dot red"></span>
        </div>

        <div class="modal-grid">
          <div class="modal-grid-item">
            <div class="item-label"><ion-icon name="calendar-outline"></ion-icon> Date</div>
            <div class="item-value" id="modalEventDate"></div>
          </div>
          <div class="modal-grid-item">
            <div class="item-label"><ion-icon name="time-outline"></ion-icon> Time</div>
            <div class="item-value" id="modalEventTime"></div>
          </div>
          <div class="modal-grid-item">
            <div class="item-label"><ion-icon name="location-outline"></ion-icon> Venue</div>
            <div class="item-value" id="modalEventLoc"></div>
          </div>
          <div class="modal-grid-item">
            <div class="item-label"><ion-icon name="people-outline"></ion-icon> Expected Attendees</div>
            <div class="item-value" id="modalEventLimit"></div>
          </div>
          <div class="modal-grid-item">
            <div class="item-label"><ion-icon name="pricetag-outline"></ion-icon> Category</div>
            <div class="item-value" id="modalEventType"></div>
          </div>
          <div class="modal-grid-item">
            <div class="item-label"><ion-icon name="document-text-outline"></ion-icon> Requirements</div>
            <div class="item-value" id="modalEventReqs"></div>
          </div>
          <div class="modal-grid-item full-width">
            <div class="item-label"><ion-icon name="qr-code-outline"></ion-icon> Attendance Method</div>
            <div class="item-value" id="modalEventMethod"></div>
          </div>
        </div>

        <div class="modal-desc-section">
          <div class="item-label">Description</div>
          <div class="item-value" id="modalEventDesc"></div>
        </div>
      </div>
    </div>
  </div>
  </main>

  <script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/admin/calendar.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>
  <script>
    function filterCalendarByOrg(orgSlug, el) {
      const allItems    = document.querySelectorAll('.org-filter-item');
      const allPills    = document.querySelectorAll('.event-pill');
      const allUpcoming = document.querySelectorAll('.upcoming-event-item');
      const resetBtn    = document.getElementById('resetOrgFilterBtn');
      const isAlreadyActive = el && el.classList.contains('active-org-filter');

      allItems.forEach(i => i.classList.remove('active-org-filter'));

      if (orgSlug === 'all' || isAlreadyActive) {
        allPills.forEach(p => p.style.display = '');
        allUpcoming.forEach(u => u.style.display = '');
        if (resetBtn) resetBtn.style.display = 'none';
      } else {
        if (el) el.classList.add('active-org-filter');
        if (resetBtn) resetBtn.style.display = 'inline';
        
        allPills.forEach(p => {
          p.style.display = p.classList.contains(orgSlug) ? '' : 'none';
        });

        allUpcoming.forEach(u => {
          u.style.display = u.classList.contains(orgSlug) ? '' : 'none';
        });
      }
    }
  </script>

  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
