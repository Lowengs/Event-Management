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
$conflict_count  = 0;
$orgs_from_db    = [];
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

          <div class="legend-list">
            <p class="legend-label">Organizations</p>
            <?php foreach ($orgs_from_db as $i => $org):
              $color = $orgColors[$i % count($orgColors)];
              $slug  = strtolower(preg_replace('/[^a-z0-9]/i', '', $org['OrgName']));
            ?>
            <div class="status-item">
              <span class="org" style="background:<?= $color ?>;width:10px;height:10px;border-radius:50%;display:inline-block;"></span>
              <?= htmlspecialchars($org['OrgName']) ?>
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
            $ueDate = date('F j, Y', strtotime($ue['EventDateTime']));
            $ueTime = date('h:i A', strtotime($ue['EventDateTime']));
          ?>
          <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: flex-start; background: #fff; cursor: pointer; transition: all 0.2s;" 
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


  <script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/admin/calendar.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>

  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
