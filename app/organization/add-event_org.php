<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}
$org_id = (int)$_SESSION['org_id'];

// Fetch the organization name
$org_name = 'Organization';
$stmt = $conn->prepare("SELECT OrgName FROM organization WHERE OrgID = ?");
if ($stmt) {
    $stmt->bind_param("i", $org_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $org_name = $row['OrgName'];
    }
    $stmt->close();
}

// Fetch financial report requirement setting
$fin_required = false;
$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    SettingKey VARCHAR(100) NOT NULL PRIMARY KEY,
    SettingValue VARCHAR(500) NOT NULL DEFAULT '',
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$conn->query("INSERT IGNORE INTO system_settings (SettingKey, SettingValue) VALUES ('financial_report_required', '0')");
$r_fs = $conn->query("SELECT SettingValue FROM system_settings WHERE SettingKey = 'financial_report_required' LIMIT 1");
if ($r_fs && $row_fs = $r_fs->fetch_assoc()) $fin_required = $row_fs['SettingValue'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NAAP ORG Portal - Add Event</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="../../assets/css/organization/add-event_org.css" />
  <link rel="stylesheet" href="../../assets/css/organization/nav.css" />
  <link rel="icon" href="../../assets/img/philsca.png" />
  
</head>
<body>
  <div class="dashboard-layout">
    <?php include '_org_sidebar.php'; ?>

    <div class="overlay" id="sidebarOverlay"></div>

    <div class="content-shell">
      <header class="topbar">
        <div class="topbar-left">
          <button class="hamburger" id="hamburgerBtn" aria-label="Open menu">
            <ion-icon name="menu-outline"></ion-icon>
          </button>
          <a class="back-btn" href="events_org.php" aria-label="Back to dashboard">
            <ion-icon name="arrow-back-outline"></ion-icon>
          </a>
          <div class="page-title">
            <h2>Create New Event</h2>
            <p>Fill in the details to create a new event</p>
          </div>
        </div>
      </header>

      <div class="maincontent">
        <div class="divider"></div>

        <section class="event-form-shell">
          <form id="addEventForm" onsubmit="submitAddEvent(event)">
            <article class="form-section">
              <h3 class="section-title"><ion-icon name="document-text-outline"></ion-icon> Basic Event Information</h3>
              <div class="form-group">
                <label for="eventTitle">Event Title *</label>
                <input class="input" id="eventTitle" name="EventName" type="text" placeholder="Enter event title" required />
              </div>
              <div class="form-grid-2">
                <div class="form-group">
                  <label for="eventType">Event Type / Category *</label>
                  <select class="select" id="eventType" name="EventType" required>
                    <option value="">Select event type</option>
                    <option value="Seminar / Workshop">Seminar / Workshop</option>
                    <option value="General Assembly">General Assembly</option>
                    <option value="Leadership Summit">Leadership Summit</option>
                    <option value="Competition">Competition</option>
                    <option value="Sports Event">Sports Event</option>
                    <option value="Cultural Event">Cultural Event</option>
                    <option value="Community Service">Community Service</option>
                    <option value="Induction Ceremony">Induction Ceremony</option>
                    <option value="Team Building">Team Building</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="orgName">Organization Name</label>
                  <input class="input" id="orgName" type="text" value="<?php echo htmlspecialchars($org_name); ?>" readonly style="background:#f8fafc; cursor:not-allowed;" />
                </div>
              </div>
              <div class="form-group">
                <label for="eventDescription">Event Description *</label>
                <textarea class="textarea" id="eventDescription" name="EventDescription" placeholder="Provide a detailed description of the event" required></textarea>
              </div>
            </article>

            <article class="form-section">
              <h3 class="section-title"><ion-icon name="calendar-outline"></ion-icon> Schedule and Location</h3>
              <div class="form-grid-3">
                <div class="form-group">
                  <label for="eventDate">Event Date *</label>
                  <input class="input" id="eventDate" type="date" min="<?= date('Y-m-d') ?>" required />
                </div>
                <div class="form-group">
                  <label for="startTime">Start Time *</label>
                  <input class="input" id="startTime" type="time" required />
                </div>
                <div class="form-group">
                  <label for="endTime">End Time *</label>
                  <input class="input" id="endTime" type="time" required />
                  <input type="hidden" name="EventDateTime" id="eventDateTimeHidden" />
                </div>
              </div>
              <div class="form-group">
                <p class="group-label">Mode of Event *</p>
                <div class="choice-stack">
                  <label class="choice-item"><input type="radio" name="EventMode" value="On-site" checked onchange="toggleVenueField()" /> On-site</label>
                  <label class="choice-item"><input type="radio" name="EventMode" value="Online" onchange="toggleVenueField()" /> Online</label>
                  <label class="choice-item"><input type="radio" name="EventMode" value="Hybrid" onchange="toggleVenueField()" /> Hybrid (On-site + Online)</label>
                </div>
              </div>
              <div class="form-group" id="venueGroup">
                <label for="venue">Venue / Location *</label>
                <input class="input" id="venue" name="EventPlace" type="text" placeholder="e.g., Main Auditorium, Room 301" required />
              </div>
            </article>

            <article class="form-section">
              <h3 class="section-title"><ion-icon name="people-outline"></ion-icon> Participants Information</h3>
              <div class="form-group">
                <p class="group-label">Target Participants *</p>
                <div class="choice-stack">
                  <label class="choice-item"><input type="checkbox" name="participants[]" value="Students" checked /> Students</label>
                  <label class="choice-item"><input type="checkbox" name="participants[]" value="Officers" /> Officers</label>
                  <label class="choice-item"><input type="checkbox" name="participants[]" value="External Guests" /> External Guests</label>
                  <label class="choice-item"><input type="checkbox" name="participants[]" value="Faculty Members" /> Faculty Members</label>
                </div>
              </div>
              <div class="form-grid-2">
                <div class="form-group">
                  <label for="expectedAttendees">Expected Number of Attendees *</label>
                  <input class="input" id="expectedAttendees" name="EventCapacity" type="number" min="1" placeholder="e.g. 150" required />
                </div>
                <div class="form-group">
                  <label for="guestSpeaker">Guest Speaker (Optional)</label>
                  <input class="input" id="guestSpeaker" name="EventSpeaker" type="text" placeholder="Name of guest speaker or resource person" />
                </div>
              </div>
            </article>

            <article class="form-section">
              <h3 class="section-title"><ion-icon name="cloud-upload-outline"></ion-icon> Event Documentation</h3>
              <div class="form-grid-2">
                <div class="form-group">
                  <label>Event Proposal / OPLAN Document *</label>
                  <input id="oplanFile" name="EventProposal" class="file-input" type="file" accept=".pdf,.doc,.docx" onchange="handleFileSelect(this,'oplanFileName','oplanUploadBox','PDF, DOC, DOCX (Max 10MB)')" />
                  <label class="upload-box" id="oplanUploadBox" for="oplanFile">
                    <svg class="upload-svg-icon" style="width:26px;height:26px;min-width:26px;min-height:26px;display:block;margin:0 auto 6px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 367.79h76c55 0 100-29.21 100-83.6s-53-81.47-96-83.6c-8.89-85.30-71-136.8-144-136.8-69 0-113.44 45.79-128 91.2-60 5.7-112 43.42-112 100.8 0 53.4 45 111.6 104 111.6h68"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 255.79l-64-64-64 64"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M256 448.21V207.79"/></svg>
                    <span class="upload-label">Click to upload or drag and drop<br />PDF, DOC, DOCX (Max 10MB)</span>
                  </label>
                  <small class="file-name" id="oplanFileName">No file selected</small>
                </div>
                <div class="form-group">
                  <label>Program Flow *</label>
                  <input id="programFlowFile" name="EventProgramFlow" class="file-input" type="file" accept=".pdf,.doc,.docx" onchange="handleFileSelect(this,'programFlowFileName','programFlowUploadBox','PDF, DOC, DOCX (Max 10MB)')" />
                  <label class="upload-box" id="programFlowUploadBox" for="programFlowFile">
                    <svg class="upload-svg-icon" style="width:26px;height:26px;min-width:26px;min-height:26px;display:block;margin:0 auto 6px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 367.79h76c55 0 100-29.21 100-83.6s-53-81.47-96-83.6c-8.89-85.30-71-136.8-144-136.8-69 0-113.44 45.79-128 91.2-60 5.7-112 43.42-112 100.8 0 53.4 45 111.6 104 111.6h68"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 255.79l-64-64-64 64"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M256 448.21V207.79"/></svg>
                    <span class="upload-label">Click to upload or drag and drop<br />PDF, DOC, DOCX (Max 10MB)</span>
                  </label>
                  <small class="file-name" id="programFlowFileName">No file selected</small>
                </div>
              </div>

              <div class="form-grid-2" style="margin-top:16px;">
                <div class="form-group">
                  <label>Poster or Event Pubmat</label>
                  <input id="posterFile" name="EventPicture" class="file-input" type="file" accept=".png,.jpg,.jpeg,.pdf" onchange="handleFileSelect(this,'posterFileName','posterUploadBox','PNG, JPG, PDF (Max 5MB)')" />
                  <label class="upload-box" id="posterUploadBox" for="posterFile">
                    <svg class="upload-svg-icon" style="width:26px;height:26px;min-width:26px;min-height:26px;display:block;margin:0 auto 6px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 367.79h76c55 0 100-29.21 100-83.6s-53-81.47-96-83.6c-8.89-85.30-71-136.8-144-136.8-69 0-113.44 45.79-128 91.2-60 5.7-112 43.42-112 100.8 0 53.4 45 111.6 104 111.6h68"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 255.79l-64-64-64 64"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M256 448.21V207.79"/></svg>
                    <span class="upload-label">Click to upload or drag and drop<br />PNG, JPG, PDF (Max 5MB)</span>
                  </label>
                  <small class="file-name" id="posterFileName">No file selected</small>
                </div>
                <div class="form-group">
                  <label>Other Supporting Files</label>
                  <input id="supportingFiles" name="EventOther" class="file-input" type="file" multiple onchange="handleFileSelect(this,'supportingFilesName','supportingUploadBox','Any file format (Max 10MB)')" />
                  <label class="upload-box" id="supportingUploadBox" for="supportingFiles">
                    <svg class="upload-svg-icon" style="width:26px;height:26px;min-width:26px;min-height:26px;display:block;margin:0 auto 6px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 367.79h76c55 0 100-29.21 100-83.6s-53-81.47-96-83.6c-8.89-85.30-71-136.8-144-136.8-69 0-113.44 45.79-128 91.2-60 5.7-112 43.42-112 100.8 0 53.4 45 111.6 104 111.6h68"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 255.79l-64-64-64 64"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M256 448.21V207.79"/></svg>
                    <span class="upload-label">Click to upload or drag and drop<br />Any file format (Max 10MB)</span>
                  </label>
                  <small class="file-name" id="supportingFilesName">No file selected</small>
                </div>
              </div>
            </article>

            <article class="form-section">
              <h3 class="section-title"><ion-icon name="camera-outline"></ion-icon> Attendance Setup</h3>
              <div class="form-group">
                <label class="choice-item"><input type="checkbox" name="AttendanceEnabled" checked /> Enable Attendance Tracking for this event</label>
              </div>
              <div class="form-group">
                <p class="group-label">Attendance Method</p>
                <div class="choice-stack">
                  <label class="choice-item"><input type="radio" name="AttendanceMethod" value="Face Recognition" checked /> Face Recognition</label>
                  <label class="choice-item"><input type="radio" name="AttendanceMethod" value="QR Code" /> QR Code</label>
                  <label class="choice-item"><input type="radio" name="AttendanceMethod" value="Face & QR" /> Face Recognition & QR Code</label>
                  <label class="choice-item"><input type="radio" name="AttendanceMethod" value="Manual" /> Manual</label>
                </div>
                <p class="note">Note: Face recognition will use the registered face data from the Members database.</p>
              </div>
            </article>

            <div class="form-actions">
              <button class="btn primary" type="submit" id="submitBtn">Submit for Approval</button>
              <button class="btn" type="button" onclick="window.location.href='events_org.php'">Cancel</button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </div>

  <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
  <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
  <script src="../../assets/js/org/org.js"></script>
  <script src="../../assets/js/org/add-event.js?v=<?= time() ?>"></script>
</body>
</html>
