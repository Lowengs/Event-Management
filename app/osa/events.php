<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';

ob_start();
$_GET['action'] = 'get_osa_events';
require __DIR__ . '/../../config/API/endpoints/index.php';
$eventsApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
$events = $eventsApiRes['data'] ?? [];
$total_events = (int)($eventsApiRes['stats']['total_events'] ?? count($events));
$upcoming = (int)($eventsApiRes['stats']['upcoming'] ?? 0);
$ongoing = (int)($eventsApiRes['stats']['ongoing'] ?? 0);
$completed = (int)($eventsApiRes['stats']['completed'] ?? 0);
require_once '../../config/db.php';

$allDocs = [];
if (isset($conn) && $conn) {
    $docsRes = $conn->query("SELECT d.*, o.OrgName FROM org_documents d LEFT JOIN organization o ON o.OrgId = d.OrgId ORDER BY d.UploadedAt DESC");
    if ($docsRes) {
        while ($doc = $docsRes->fetch_assoc()) {
            $evId = (int)($doc['EventId'] ?? 0);
            if ($evId > 0) {
                $allDocs[$evId][] = $doc;
            } else {
                $orgIdDoc = (int)($doc['OrgId'] ?? 0);
                if ($orgIdDoc > 0 && !empty($events)) {
                    foreach ($events as $evItem) {
                        if ((int)($evItem['OrgId'] ?? 0) === $orgIdDoc) {
                            $allDocs[(int)$evItem['EventId']][] = $doc;
                        }
                    }
                }
            }
        }
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP OSA Portal - Events</title>

  <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/events_finished.css?<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/admin/osa_events_extra.css?<?= time() ?>" />

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
        <li><a href="events.php" class="nav active"><ion-icon name="calendar-outline"></ion-icon><span>Events</span></a></li>
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
          <h2>Events Management</h2>
          <p>Monitor and manage organization events</p>
        </div>

        <div style="margin-left:auto;display:flex;align-items:center;gap:8px;padding:6px 14px;background:rgba(37,99,235,.1);border:1px solid rgba(37,99,235,.25);border-radius:20px;">
          <ion-icon name="eye-outline" style="color:#60a5fa;font-size:15px;"></ion-icon>
          <span style="font-size:12px;font-weight:600;color:#60a5fa;">View Only</span>
        </div>
      </div>

      <div class="divider"></div>

      <section class="event-stats">
        <article class="statbox">
          <div class="statbox-text">
            <p class="statbox-label">Total Events</p>
            <h3 class="statbox-value total"><?= number_format($total_events) ?></h3>
          </div>
          <div class="statbox-icon primary">
            <ion-icon name="calendar-outline"></ion-icon>
          </div>
        </article>

        <article class="statbox">
          <div class="statbox-text">
            <p class="statbox-label">Upcoming Events</p>
            <h3 class="statbox-value upcoming"><?= number_format($upcoming) ?></h3>
          </div>
          <div class="statbox-icon upcoming">
            <ion-icon name="time-outline"></ion-icon>
          </div>
        </article>

        <article class="statbox">
          <div class="statbox-text">
            <p class="statbox-label">Ongoing Events</p>
            <h3 class="statbox-value ongoing"><?= number_format($ongoing) ?></h3>
          </div>
          <div class="statbox-icon ongoing">
            <ion-icon name="play-circle-outline"></ion-icon>
          </div>
        </article>

        <article class="statbox">
          <div class="statbox-text">
            <p class="statbox-label">Completed Events</p>
            <h3 class="statbox-value completed"><?= number_format($completed) ?></h3>
          </div>
          <div class="statbox-icon completed">
            <ion-icon name="checkmark-circle-outline"></ion-icon>
          </div>
        </article>
      </section>

      <section class="filter-panel">
        <div class="filter-grid">
          <div class="filter-block">
            <div class="filter-title">
              <ion-icon name="funnel-outline"></ion-icon>
              <h4>Filter by Organization</h4>
            </div>

            <div class="input-wrap">
              <ion-icon name="business-outline" class="input-ico"></ion-icon>
              <?php
                $orgOptions = $allOrgs ?? [];
                if (!in_array('General', $orgOptions)) {
                    $orgOptions[] = 'General';
                }
                sort($orgOptions);
              ?>
              <select id="filterOrg" aria-label="Organization filter">
                <option value="all">All Organizations</option>
                <?php foreach($orgOptions as $org): ?>
                  <option value="<?= htmlspecialchars(strtolower($org)) ?>"><?= htmlspecialchars($org) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="filter-block">
            <div class="filter-title">
              <ion-icon name="search-outline"></ion-icon>
              <h4>Search Events</h4>
            </div>

            <div class="input-wrap">
              <ion-icon name="search-outline" class="input-ico"></ion-icon>
              <input type="text" id="filterSearch" placeholder="Search by event name or organization..." />
            </div>
          </div>
        </div>
      </section>

      <section class="events-table">
        <div class="table-card table-modern">
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Event Title <ion-icon name="chevron-down-outline"></ion-icon></th>
                  <th>Organization <ion-icon name="chevron-down-outline"></ion-icon></th>
                  <th>Date <ion-icon class="active-sort" name="chevron-up-outline"></ion-icon></th>
                  <th>Time</th>
                  <th>Place / Location</th>
                  <th>Event Status <ion-icon name="chevron-down-outline"></ion-icon></th>
                  <th>Actions</th>
                </tr>
              </thead>

              <tbody id="eventsTableBody">
              <?php if (empty($events)): ?>
                  <tr><td colspan="7" style="text-align: center; padding: 2rem;">No events found.</td></tr>
              <?php else: ?>
                  <?php foreach($events as $ev):
                      $d = $ev['EventDateTime'] ? new DateTime($ev['EventDateTime']) : null;
                      $dateStr    = $d ? $d->format('Y-m-d') : 'N/A';
                      $timeStr    = $d ? $d->format('h:i A') : 'N/A';
                      $rawStatus  = $ev['EventStatus'] ?: 'Scheduled';
                      $statusDisp = htmlspecialchars(ucfirst(strtolower($rawStatus)));
                      $statusClass= strtolower($rawStatus) === 'completed' ? 'completed' : (strtolower($rawStatus) === 'ongoing' ? 'ongoing' : 'scheduled');
                      $placeDisp  = htmlspecialchars($ev['EventPlace'] ?: ($ev['EventLocation'] ?: 'TBA'));
                      $speakerDisp= htmlspecialchars($ev['EventSpeaker'] ?? '—');
                      $capDisp    = $ev['EventCapacity'] ? number_format($ev['EventCapacity']) : '—';
                      $evId       = (int)$ev['EventId'];
                      $evName     = htmlspecialchars($ev['EventName'], ENT_QUOTES);
                      $evDesc     = htmlspecialchars($ev['EventDescription'] ?? '', ENT_QUOTES);
                      $evOrg      = htmlspecialchars($ev['OrgName'] ?? 'General', ENT_QUOTES);
                      $evLoc      = htmlspecialchars($ev['EventLocation'] ?? '', ENT_QUOTES);
                      $evPic      = htmlspecialchars($ev['EventPicture'] ?? '', ENT_QUOTES);
                  ?>
                <tr data-event-id="<?= $evId ?>">
                  <td>
                    <div class="eventCell">
                      <div class="eventName"><?= htmlspecialchars($ev['EventName']) ?></div>
                      <div class="eventSub muted"><?= htmlspecialchars($ev['EventDescription'] ? mb_strimwidth($ev['EventDescription'],0,50,'…') : 'Event') ?></div>
                    </div>
                  </td>
                  <td><div class="orgCell"><span class="orgDot blue"></span><span><?= htmlspecialchars($ev['OrgName'] ?? 'General') ?></span></div></td>
                  <td><div class="metaCell"><ion-icon name="calendar-clear-outline"></ion-icon><span><?= $dateStr ?></span></div></td>
                  <td><div class="metaCell"><ion-icon name="time-outline"></ion-icon><span><?= $timeStr ?></span></div></td>
                  <td><div class="metaCell"><ion-icon name="location-outline"></ion-icon><span><?= $placeDisp ?></span></div></td>
                  <td><span class="statusPill <?= $statusClass ?>"><?= $statusDisp ?></span></td>
                  <td>
                    <div class="actionIcons">
                      <button class="iconBtn view" title="View Event Details"
                        onclick="openViewModal(<?= $evId ?>, '<?= $evName ?>', '<?= $evDesc ?>', '<?= $evOrg ?>', '<?= $dateStr ?>', '<?= $timeStr ?>', '<?= htmlspecialchars($placeDisp, ENT_QUOTES) ?>', '<?= $evLoc ?>', '<?= $speakerDisp ?>', '<?= $capDisp ?>', '<?= $statusDisp ?>', '<?= $evPic ?>')">
                        <ion-icon name="eye-outline"></ion-icon>
                      </button>
                      <button class="iconBtn docs" title="View & Download Documents"
                        onclick="openDocsModal('<?= $evName ?>', <?= htmlspecialchars(json_encode($allDocs[$evId] ?? []), ENT_QUOTES) ?>)">
                        <ion-icon name="folder-open-outline"></ion-icon>
                      </button>
                      <?php 
                        $hasReport = false;
                        if (!empty($allDocs[$evId])) {
                            foreach ($allDocs[$evId] as $docItem) {
                                $dtLower = strtolower($docItem['DocType'] ?? '');
                                $titleLower = strtolower($docItem['Title'] ?? '');
                                if (strpos($dtLower, 'postactivity') !== false || strpos($dtLower, 'financial') !== false || strpos($titleLower, 'post-activity') !== false || strpos($titleLower, 'financial') !== false) {
                                    $hasReport = true;
                                    break;
                                }
                            }
                        }
                      ?>
                      <?php if (!empty($ev['NoFinancialReport']) || !empty($ev['no_financial_report'])): ?>
                        <a href="reports.php?event_id=<?= $evId ?>" class="iconBtn" style="color:#15803d;background:#ecfdf5;border:1px solid #86efac;" title="No financial involvement recorded for this online event (Click to view reports)">
                          <ion-icon name="checkmark-circle-outline"></ion-icon>
                        </a>
                      <?php elseif ($hasReport): ?>
                        <a href="reports.php?event_id=<?= $evId ?>" class="iconBtn" style="color:#2563eb;background:#eff6ff;border:1px solid #bfdbfe;" title="Post-Activity / Financial Report Uploaded (Click to View Reports)">
                          <ion-icon name="document-text-outline"></ion-icon>
                        </a>
                      <?php else: ?>
                        <a href="reports.php?event_id=<?= $evId ?>" class="iconBtn" style="color:#ea580c;background:#fff7ed;border:1px solid #fdba74;" title="No Post-Activity / Financial Report uploaded yet (Click to View Reports)">
                          <ion-icon name="document-text-outline"></ion-icon>
                        </a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

    </div>
  </main>

  <div id="eventModal" class="event-modal">
    <div class="event-modal-content">
      <div class="modal-header">
        <div class="modal-header-text">
          <h2 id="modalEventTitle">Event Title</h2>
          <p class="modal-subtitle" id="modalEventSub">Event Type</p>
        </div>
        <button class="close-modal" id="closeEventModal">&times;</button>
      </div>
      
      <div class="modal-body">
        <div class="modal-section">
          <h3><ion-icon name="information-circle-outline"></ion-icon> Event Information</h3>
          <div class="modal-grid">
            <div class="modal-grid-item full-width">
              <span class="item-label">Description</span>
              <span class="item-value" data-field="desc">—</span>
            </div>
            <div class="modal-grid-item">
              <span class="item-label">Organizer</span>
              <span class="item-value" id="modalOrgName">—</span>
            </div>
            <div class="modal-grid-item">
              <span class="item-label">Speaker / Facilitator</span>
              <span class="item-value" data-field="speaker">—</span>
            </div>
          </div>
        </div>

        <div class="modal-section">
          <h3><ion-icon name="time-outline"></ion-icon> Schedule</h3>
          <div class="modal-grid">
            <div class="modal-grid-item">
              <span class="item-label">Date</span>
              <span class="item-value" id="modalDate">Date</span>
            </div>
            <div class="modal-grid-item">
              <span class="item-label">Time</span>
              <span class="item-value" id="modalTime">Time</span>
            </div>
            <div class="modal-grid-item">
              <span class="item-label">Location</span>
              <span class="item-value" id="modalLocation">Location</span>
            </div>
            <div class="modal-grid-item">
              <span class="item-label">Status</span>
              <span class="item-value" id="modalStatus">Status</span>
            </div>
          </div>
        </div>

        <div class="modal-section">
          <h3><ion-icon name="people-outline"></ion-icon> Capacity</h3>
          <div class="modal-grid">
            <div class="modal-grid-item">
              <span class="item-label">Max. Participants</span>
              <span class="item-value" data-field="cap">—</span>
            </div>
          </div>
        </div>

        <div class="modal-section">
          <h3><ion-icon name="image-outline"></ion-icon> Event Poster</h3>
          <div class="attachment-list">
            <div class="attachment-item poster-preview" style="display: flex; flex-direction: column; align-items: flex-start; border: none; padding: 0; background: transparent;">
              <img id="modalPosterImg" src="../../assets/img/philsca.png" alt="Event Poster" style="max-width: 100%; border-radius: 8px; margin-top: 4px; max-height: 250px; object-fit: contain; border: 1px solid var(--border, #e2e8f0);">
            </div>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button class="modal-btn outline" id="modalCloseBtn">Close</button>
      </div>
    </div>
  </div>

  <div id="docsModal" class="event-modal">
    <div class="event-modal-content" style="max-width: 500px;">
      <div class="modal-header">
        <div class="modal-header-text">
          <h2 id="docsModalTitle">Documentation</h2>
          <p class="modal-subtitle" id="docsModalSub">Event Attachments & Files</p>
        </div>
        <button class="close-modal" id="closeDocsModal">&times;</button>
      </div>
      
      <div class="modal-body">
        <div class="modal-section">
          <h3><ion-icon name="folder-open-outline"></ion-icon> Available Files</h3>
          <div class="attachment-list" id="docsAttachmentList"></div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button class="modal-btn outline" id="docsModalCloseBtn">Close</button>
      </div>
    </div>
  </div>

  <script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
  <script src="../../assets/js/admin/osa_events.js"></script>
  <script src="../../assets/js/admin/dashboard.js"></script>
  <script src="../../assets/js/logout_confirm.js" defer></script>

  <!-- Ionicons -->
  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
</body>
</html>
