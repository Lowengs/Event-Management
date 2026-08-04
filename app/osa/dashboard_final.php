<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

$osa_name = htmlspecialchars($_SESSION['osa_name'] ?? 'Administrator');


// Fetch OSA Dashboard Data via API Endpoint
ob_start();
$_GET['action'] = 'get_osa_dashboard'; require __DIR__ . '/../../config/API/endpoints/index.php';
$dashApiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

$stats          = $dashApiRes['stats']         ?? [];
$total_students  = (int)($stats['total_students']  ?? 0);
$active_orgs     = (int)($stats['active_orgs']     ?? 0);
$upcoming_events = (int)($stats['upcoming_events'] ?? 0);
$recent_events   = $dashApiRes['recent_events'] ?? [];


$notifications     = $dashApiRes['notifications']     ?? [];
$all_notifications = $dashApiRes['all_notifications'] ?? [];
$unread_count      = (int)($dashApiRes['stats']['unread_count'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Dashboard</title>

  
  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?<?= time() ?>" />

  
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
        <li><a href="dashboard_final.php" class="nav active"><ion-icon name="grid-outline"></ion-icon><span>Dashboard</span></a></li>
                <li><a href="organization.php" class="nav"><ion-icon name="business-outline"></ion-icon><span>Organization</span></a></li>
                <li><a href="calendar.php" class="nav"><ion-icon name="calendar-number-outline"></ion-icon><span>Calendar</span></a></li>
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

      
      <div class="mainheader">
        <div class="titleheader">
          <h2>Dashboard</h2>
          <p>Welcome back, <?= $osa_name ?></p>
        </div>

        <div class="header-right">
          <a href="#" aria-label="Notifications" onclick="showAllNotifsModal(event)" style="position: relative; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
            <ion-icon name="notifications-outline" style="font-size: 24px; color: #1e293b;"></ion-icon>
            <?php if($unread_count > 0): ?>
              <span style="position: absolute; top: -4px; right: -4px; background: #ef4444; color: #fff; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; font-weight: bold; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 0 2px #fff;">
                <?= $unread_count > 99 ? '99+' : $unread_count ?>
              </span>
            <?php endif; ?>
          </a>

          <div class="user-container">
            <div class="user-img-border">
              <img src="../../assets/img/philsca.png" alt="User">
            </div>
            <div class="header-right-text">
              <h3><?= $osa_name ?></h3>
              <p>OSA Office</p>
            </div>
          </div>
        </div>
      </div>

    
      <div class="stat-card-container">
        <div class="stat-card">
          <div class="stat-left">
            <div class="stat-icon blue">
              <ion-icon name="people-outline" class="stat-color"></ion-icon>
            </div>
            <div class="stat-text">
              <h3 id="osaTotalStudents"><?= number_format($total_students) ?></h3>
              <p>Total Students</p>
            </div>
          </div>
          <div class="stat-meta"><ion-icon name="trending-up-outline"></ion-icon> Active</div>
        </div>

        <div class="stat-card">
          <div class="stat-left">
            <div class="stat-icon green">
              <ion-icon name="grid-outline" class="stat-color"></ion-icon>
            </div>
            <div class="stat-text">
              <h3 id="osaActiveOrgs"><?= number_format($active_orgs) ?></h3>
              <p>Active Orgs</p>
            </div>
          </div>
          <div class="stat-meta muted">Registered</div>
        </div>

        <div class="stat-card">
          <div class="stat-left">
            <div class="stat-icon purple">
              <ion-icon name="calendar-clear-outline" class="stat-color"></ion-icon>
            </div>
            <div class="stat-text">
              <h3 id="osaUpcomingEvents"><?= number_format($upcoming_events) ?></h3>
              <p>Upcoming Events</p>
            </div>
          </div>
          <div class="stat-meta muted">Scheduled</div>
        </div>
      </div>


      <div class="dashboard-panel">
        <section class="quick-actions">
          <h4 class="panel-title">Quick Actions</h4>

          <div class="qa-list">
            <a class="qa-item blue" href="organization.php">
              <div class="qa-icon blue"><ion-icon name="add-outline"></ion-icon></div>
              <div class="qa-text">
                <h5>Create Organization</h5>
                <p>Add new Organization</p>
              </div>
            </a>

            <a class="qa-item green" href="reports.php">
              <div class="qa-icon green"><ion-icon name="document-text-outline"></ion-icon></div>
              <div class="qa-text">
                <h5>Export Post Event Report</h5>
                <p>Download attendance data</p>
              </div>
            </a>

            <a class="qa-item purple" href="reports.php">
              <div class="qa-icon purple"><ion-icon name="wallet-outline"></ion-icon></div>
              <div class="qa-text">
                <h5>Export Financial Report</h5>
                <p>Download financial data</p>
              </div>
            </a>

            <a href="announcement.php" class="qa-item orange">
              <div class="qa-icon orange"><ion-icon name="megaphone-outline"></ion-icon></div>
              <div class="qa-text">
                <h5>Broadcast</h5>
                <p>Broadcast Announcements</p>
              </div>
            </a>

            

          </div>

          <div class="notification-container">
            <div class="notif-text">
              <h5>Notifications</h5>
              <a href="announcement.php">View All</a>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="notification-card">
                    <p>No recent announcements</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                <div class="notification-card" style="cursor: pointer;"
                     data-title="<?= htmlspecialchars($notif['Title']) ?>"
                     data-org="<?= htmlspecialchars($notif['OrgName'] ?? 'OSA') ?>"
                     data-date="<?= date('F j, Y, g:i A', strtotime($notif['CreatedAt'])) ?>"
                     data-body="<?= htmlspecialchars($notif['Body']) ?>"
                     onclick="showNotifModal(this)">
                    <p><?= htmlspecialchars($notif['Title']) ?><br>
                      <span><?= htmlspecialchars($notif['OrgName'] ?? 'OSA') ?></span>
                    </p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <section class="recent-events">
          <h4 class="panel-title">Recent Events</h4>

          <div class="events-list">
            <?php if (empty($recent_events)): ?>
                <p style="padding: 1rem; color: #64748b; font-size: 0.9rem;">No upcoming events found.</p>
            <?php else: ?>
                <?php foreach ($recent_events as $ev): 
                    $date = new DateTime($ev['EventDateTime']);
                    $rawStatus = $ev['EventStatus'] ?? 'Scheduled';
                    $badgeText = ucfirst($rawStatus);
                    
                    if (strtolower($rawStatus) === 'completed') {
                        $badgeClass = 'done';
                    } elseif (strtolower($rawStatus) === 'cancelled') {
                        $badgeClass = 'cancelled';
                    } elseif (strtolower($rawStatus) === 'ongoing') {
                        $badgeClass = 'ongoing';
                    } else {
                        $badgeClass = 'scheduled';
                    }
                    
                    
                    $regCount = (int)($ev['reg_count'] ?? $ev['registered_count'] ?? $ev['RegisteredCount'] ?? 0);
                    $attCount = (int)($ev['attended_count'] ?? $ev['AttendedCount'] ?? 0);
                    
                    if ($regCount > 0) {
                        $attPct = round(($attCount / $regCount) * 100);
                        if ($attPct > 100) $attPct = 100;
                    } else if ($attCount > 0) {
                        $attPct = 100;
                    } else {
                        $attPct = 0;
                    }
                ?>
                <a class="event-item" href="events.php">
                  <div class="event-left">
                    <h5><?= htmlspecialchars($ev['EventName']) ?></h5>
                    <div class="event-meta">
                      <span><ion-icon name="calendar-outline"></ion-icon> <?= $date->format('M j, Y') ?></span>
                      <span><ion-icon name="people-outline"></ion-icon> <?= $attCount ?> / <?= max($regCount, $attCount) ?></span>
                    </div>
                    <div style="margin-top:6px;background:#f1f5f9;border-radius:999px;height:5px;width:100%;">
                      <div style="width:<?= $attPct ?>%;background:#3b82f6;border-radius:999px;height:5px;"></div>
                    </div>
                    <span style="font-size:10px;color:#64748b;"><?= $attPct ?>% attendance</span>
                  </div>
                  <div class="badge <?= $badgeClass ?>"><?= $badgeText ?></div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
          </div>

                    <?php
            $calYear      = (int)date('Y');
            $calMonth     = (int)date('m');
            $todayDay     = (int)date('j');
            $daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $calMonth, $calYear);
            $firstWeekday = (int)date('w', mktime(0,0,0,$calMonth,1,$calYear));
            $calEvents = [];
            $allCalEvents = $dashApiRes['calendar_events'] ?? [];
            foreach ($allCalEvents as $dateStr => $eList) {
                foreach ($eList as $er) {
                    $d = (int)date('j', strtotime($er['EventDateTime']));
                    $place = $er['EventPlace'] ?: ($er['EventLocation'] ?: 'TBA');
                    $desc  = $er['EventDescription'] ?: ($er['EventDetails'] ?: 'No description provided.');
                    $calEvents[$d][] = [
                        'name'     => $er['EventName'],
                        'time'     => date('H:i', strtotime($er['EventDateTime'])),
                        'full_time'=> date('F j, Y, g:i A', strtotime($er['EventDateTime'])),
                        'org'      => strtolower(preg_replace('/[^a-z0-9]/i','',$er['OrgName']??'')),
                        'org_name' => $er['OrgName'] ?? 'Unknown Org',
                        'loc'      => $place,
                        'desc'     => $desc
                    ];
                }
            }
          ?>
          <div class="dashboard-calendar">
            <section class="calendar-card">
              <header class="calendar-card__header">
                <p class="calendar-card__month"><?= date('F Y') ?></p>
              </header>
              <div class="calendar-grid">
                <div class="calendar-grid__day">Sun</div><div class="calendar-grid__day">Mon</div><div class="calendar-grid__day">Tue</div><div class="calendar-grid__day">Wed</div><div class="calendar-grid__day">Thu</div><div class="calendar-grid__day">Fri</div><div class="calendar-grid__day">Sat</div>
                <?php for($pad=0;$pad<$firstWeekday;$pad++): ?><div class="calendar-grid__cell calendar-grid__empty"></div><?php endfor; ?>
                <?php for($day=1;$day<=$daysInMonth;$day++):
                  $cls='calendar-grid__cell';
                  if($day===$todayDay) $cls.=' calendar-grid__today';
                  if(isset($calEvents[$day])) $cls.=' calendar-grid__event';
                ?><div class="<?= $cls ?>"><span><?= $day ?></span><?php if(isset($calEvents[$day])): foreach($calEvents[$day] as $ce): ?>
                <p class="event-pill <?= htmlspecialchars($ce['org']) ?>" 
                   style="cursor: pointer;"
                   data-name="<?= htmlspecialchars($ce['name']) ?>"
                   data-time="<?= htmlspecialchars($ce['full_time']) ?>"
                   data-org="<?= htmlspecialchars($ce['org_name']) ?>"
                   data-loc="<?= htmlspecialchars($ce['loc']) ?>"
                   data-desc="<?= htmlspecialchars($ce['desc']) ?>"
                   onclick="showEventModal(this)">
                   <span class="event-time"><?= $ce['time'] ?></span><span class="event-label"><?= htmlspecialchars(substr($ce['name'],0,12)) ?></span>
                </p><?php endforeach; endif; ?></div><?php endfor; ?>
              </div>
            </section>
          </div>
        </section>
      </div>
      
    
    </div>
 
    

      


    
   
  </main>

  
  <div id="eventModal" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; padding:25px; border-radius:12px; width:450px; max-width:90%; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <span onclick="closeEventModal()" style="position:absolute; right:20px; top:20px; font-size:24px; cursor:pointer; color:#64748b;">&times;</span>
        <h3 id="mName" style="margin:0 0 5px 0; color:#0f172a; font-size: 20px;">Event Name</h3>
        <p id="mOrg" style="color:#64748b; font-size:14px; margin:0 0 15px 0; font-weight:500;">Organization</p>
        
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px; color:#334155; font-size:14px;">
            <ion-icon name="time-outline" style="color:#3b82f6; font-size:18px;"></ion-icon>
            <span id="mTime">Time</span>
        </div>
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:20px; color:#334155; font-size:14px;">
            <ion-icon name="location-outline" style="color:#3b82f6; font-size:18px;"></ion-icon>
            <span id="mLoc">Location</span>
        </div>
        
        <hr style="border:none; border-top:1px solid #e2e8f0; margin-bottom:15px;">
        <h4 style="margin:0 0 10px 0; font-size:15px; color:#0f172a;">Description</h4>
        <p id="mDesc" style="font-size:14px; color:#475569; line-height:1.6; margin:0; max-height:150px; overflow-y:auto;"></p>
    </div>
  </div>

    
  <div id="notifModal" class="modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#fff; padding:25px; border-radius:12px; width:450px; max-width:90%; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <span onclick="closeNotifModal()" style="position:absolute; right:20px; top:20px; font-size:24px; cursor:pointer; color:#64748b;">&times;</span>
        <h3 id="nTitle" style="margin:0 0 5px 0; color:#0f172a; font-size: 20px;">Notification Title</h3>
        <p id="nOrg" style="color:#64748b; font-size:14px; margin:0 0 15px 0; font-weight:500;">Organization</p>
        
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:20px; color:#334155; font-size:14px;">
            <ion-icon name="time-outline" style="color:#3b82f6; font-size:18px;"></ion-icon>
            <span id="nDate">Date</span>
        </div>
        
        <hr style="border:none; border-top:1px solid #e2e8f0; margin-bottom:15px;">
        <h4 style="margin:0 0 10px 0; font-size:15px; color:#0f172a;">Details</h4>
        <p id="nBody" style="font-size:14px; color:#475569; line-height:1.6; margin:0; max-height:250px; overflow-y:auto; white-space:pre-wrap;"></p>
    </div>
  </div>

  
  <div id="allNotifsModal" class="modal" style="display:none; position:fixed; z-index:9998; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div style="background:#f8fafc; padding:25px; border-radius:12px; width:500px; max-width:90%; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.2); display:flex; flex-direction:column; max-height:80vh;">
        <span onclick="closeAllNotifsModal()" style="position:absolute; right:20px; top:20px; font-size:24px; cursor:pointer; color:#64748b;">&times;</span>
        <h3 style="margin:0 0 15px 0; color:#0f172a; font-size: 20px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
            <ion-icon name="notifications" style="color:#3b82f6; margin-right:5px; vertical-align:middle;"></ion-icon> All Notifications
            <?php if($unread_count > 0): ?>
                <span style="font-size:12px; background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; vertical-align:middle; margin-left:5px; font-weight:normal;"><?= $unread_count ?> New</span>
            <?php endif; ?>
        </h3>
        
        <div style="overflow-y:auto; flex-grow:1; display:flex; flex-direction:column; gap:10px; padding-right:5px;">
            <?php if (empty($all_notifications)): ?>
                <p style="color:#64748b; text-align:center; padding:20px;">No notifications found.</p>
            <?php else: ?>
                <?php foreach ($all_notifications as $an): ?>
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:15px; cursor:pointer; transition:transform 0.2s, box-shadow 0.2s;"
                     onmouseover="this.style.boxShadow='0 2px 10px rgba(0,0,0,0.05)'; this.style.transform='translateY(-2px)'"
                     onmouseout="this.style.boxShadow='none'; this.style.transform='translateY(0)'"
                     data-title="<?= htmlspecialchars($an['Title']) ?>"
                     data-org="<?= htmlspecialchars($an['OrgName'] ?? 'OSA') ?>"
                     data-date="<?= date('F j, Y, g:i A', strtotime($an['CreatedAt'])) ?>"
                     data-body="<?= htmlspecialchars($an['Body']) ?>"
                     onclick="showNotifModal(this)">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <h4 style="margin:0; font-size:15px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:70%;"><?= htmlspecialchars($an['Title']) ?></h4>
                        <span style="font-size:11px; color:#94a3b8; white-space:nowrap;"><?= date('M j', strtotime($an['CreatedAt'])) ?></span>
                    </div>
                    <p style="margin:0; font-size:13px; color:#64748b;"><ion-icon name="business-outline" style="vertical-align:text-bottom; margin-right:4px;"></ion-icon><?= htmlspecialchars($an['OrgName'] ?? 'OSA') ?></p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
  </div>

  

<script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/osa/osa_api_loader.js"></script>

  
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  <script src="../../assets/js/admin/dashboard_final.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>
</body>
</html>



