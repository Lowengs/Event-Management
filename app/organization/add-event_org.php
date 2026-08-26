<?php
session_start();
require_once '../../config/db.php';
if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}
$org_id = (int)$_SESSION['org_id'];

$org_name = $_SESSION['org_name'] ?? 'Organization';
$fin_required = false;
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

  <link rel="stylesheet" href="../../assets/css/organization/add-event_org.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="../../assets/css/organization/nav.css" />
  <link rel="icon" href="../../assets/img/philsca.png" />
  
<script src="../../assets/js/security.js"></script>
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
            <p>Fill in the details to create and submit a new event proposal</p>
          </div>
        </div>
      </header>

      <div class="maincontent">
        <div class="divider"></div>

        <section class="event-form-shell">

          <!-- ── Multi-Step Progress Stepper ────────────────────────── -->
          <div class="stepper-card">
            <div class="stepper-track">
              <div class="stepper-progress-fill" id="stepperProgressFill"></div>
              
              <div class="step-item active" id="stepNode1" onclick="handleStepNodeClick(1)">
                <div class="step-badge">
                  <span class="step-num">1</span>
                  <ion-icon name="checkmark-outline" class="step-icon-check"></ion-icon>
                </div>
                <div class="step-text">
                  <span class="step-label">Basic Info</span>
                  <span class="step-sub">Title & Category</span>
                </div>
              </div>

              <div class="step-item" id="stepNode2" onclick="handleStepNodeClick(2)">
                <div class="step-badge">
                  <span class="step-num">2</span>
                  <ion-icon name="checkmark-outline" class="step-icon-check"></ion-icon>
                </div>
                <div class="step-text">
                  <span class="step-label">Schedule</span>
                  <span class="step-sub">Date & Venue</span>
                </div>
              </div>

              <div class="step-item" id="stepNode3" onclick="handleStepNodeClick(3)">
                <div class="step-badge">
                  <span class="step-num">3</span>
                  <ion-icon name="checkmark-outline" class="step-icon-check"></ion-icon>
                </div>
                <div class="step-text">
                  <span class="step-label">Participants</span>
                  <span class="step-sub">Target & Capacity</span>
                </div>
              </div>

              <div class="step-item" id="stepNode4" onclick="handleStepNodeClick(4)">
                <div class="step-badge">
                  <span class="step-num">4</span>
                  <ion-icon name="checkmark-outline" class="step-icon-check"></ion-icon>
                </div>
                <div class="step-text">
                  <span class="step-label">Documents</span>
                  <span class="step-sub">OPLAN & Program</span>
                </div>
              </div>

              <div class="step-item" id="stepNode5" onclick="handleStepNodeClick(5)">
                <div class="step-badge">
                  <span class="step-num">5</span>
                  <ion-icon name="checkmark-outline" class="step-icon-check"></ion-icon>
                </div>
                <div class="step-text">
                  <span class="step-label">Review</span>
                  <span class="step-sub">Confirmation</span>
                </div>
              </div>
            </div>

            <!-- Mobile Progress Header -->
            <div class="mobile-stepper-bar">
              <div class="mobile-stepper-meta">
                <span class="mobile-stepper-count" id="mobileStepCount">Step 1 of 5</span>
                <span class="mobile-stepper-title" id="mobileStepTitle">Basic Event Information</span>
              </div>
              <div class="mobile-stepper-track">
                <div class="mobile-stepper-progress" id="mobileStepProgress" style="width: 20%;"></div>
              </div>
            </div>
          </div>

          <form id="addEventForm" onsubmit="submitAddEvent(event)">

            <!-- ── STEP 1: Basic Event Information ───────────────────── -->
            <div class="form-step-panel active" id="stepPanel1">
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

              <div class="form-step-footer">
                <a href="events_org.php" class="btn">Cancel</a>
                <button type="button" class="btn primary" onclick="nextStep(1)">
                  Next: Schedule & Location <ion-icon name="arrow-forward-outline"></ion-icon>
                </button>
              </div>
            </div>

            <!-- ── STEP 2: Schedule and Location ─────────────────────── -->
            <div class="form-step-panel" id="stepPanel2">
              <article class="form-section">
                <h3 class="section-title"><ion-icon name="calendar-outline"></ion-icon> Schedule and Location</h3>
                <div class="form-grid-3">
                  <div class="form-group">
                    <label for="eventDate">Event Date *</label>
                    <div class="input-picker-wrapper">
                      <input class="input date-picker-input" id="eventDate" name="EventDate" type="date" min="<?= date('Y-m-d') ?>" required onchange="handleDateChange()" onclick="openNativePicker('eventDate')" />
                      <button type="button" class="picker-trigger-btn" onclick="openNativePicker('eventDate')" aria-label="Open Calendar" title="Open Calendar">
                        <ion-icon name="calendar-outline"></ion-icon>
                      </button>
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="startTime">Start Time *</label>
                    <div class="input-picker-wrapper">
                      <input class="input time-picker-input" id="startTime" name="EventTimeStart" type="time" required onchange="handleStartTimeChange()" onclick="openNativePicker('startTime')" />
                      <button type="button" class="picker-trigger-btn" onclick="openNativePicker('startTime')" aria-label="Open Start Time" title="Open Time Picker">
                        <ion-icon name="time-outline"></ion-icon>
                      </button>
                    </div>
                  </div>
                  <div class="form-group">
                    <label for="endTime">End Time *</label>
                    <div class="input-picker-wrapper">
                      <input class="input time-picker-input" id="endTime" name="EventTimeEnd" type="time" required onchange="handleEndTimeChange()" onclick="openNativePicker('endTime')" />
                      <button type="button" class="picker-trigger-btn" onclick="openNativePicker('endTime')" aria-label="Open End Time" title="Open Time Picker">
                        <ion-icon name="time-outline"></ion-icon>
                      </button>
                    </div>
                    <input type="hidden" name="EventDateTime" id="eventDateTimeHidden" />
                    <input type="hidden" name="EndDateTime" id="endDateTimeHidden" />
                  </div>
                </div>
                <div class="form-group" style="margin-top:12px;">
                  <p class="group-label">Mode of Event *</p>
                  <div class="chips-grid">
                    <label class="chip-card">
                      <input type="radio" name="EventMode" value="On-site" checked onchange="toggleVenueField()" />
                      <span class="chip-content"><ion-icon name="business-outline"></ion-icon> On-site</span>
                    </label>
                    <label class="chip-card">
                      <input type="radio" name="EventMode" value="Online" onchange="toggleVenueField()" />
                      <span class="chip-content"><ion-icon name="videocam-outline"></ion-icon> Online</span>
                    </label>
                    <label class="chip-card">
                      <input type="radio" name="EventMode" value="Hybrid" onchange="toggleVenueField()" />
                      <span class="chip-content"><ion-icon name="git-network-outline"></ion-icon> Hybrid (On-site + Online)</span>
                    </label>
                  </div>
                </div>
                <div class="form-group" id="venueGroup" style="margin-top:10px;">
                  <label for="venue">Venue / Location *</label>
                  <input class="input" id="venue" name="EventPlace" type="text" placeholder="e.g., Main Auditorium, Room 301" required />
                </div>
              </article>

              <div class="form-step-footer">
                <button type="button" class="btn" onclick="prevStep(2)">
                  <ion-icon name="arrow-back-outline"></ion-icon> Back: Basic Info
                </button>
                <button type="button" class="btn primary" onclick="nextStep(2)">
                  Next: Participants <ion-icon name="arrow-forward-outline"></ion-icon>
                </button>
              </div>
            </div>

            <!-- ── STEP 3: Participants Information ──────────────────── -->
            <div class="form-step-panel" id="stepPanel3">
              <article class="form-section">
                <h3 class="section-title"><ion-icon name="people-outline"></ion-icon> Participants Information</h3>
                <div class="form-group">
                  <p class="group-label">Target Participants *</p>
                  <div class="chips-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
                    <label class="chip-card">
                      <input type="radio" name="EventAudience" value="All Members" checked onchange="updateReviewSummary()" />
                      <span class="chip-content" style="padding: 12px 18px; font-size: 14px; font-weight: 600;">
                        <ion-icon name="people-outline" style="font-size: 20px; color: #7c3aed;"></ion-icon> All Members
                      </span>
                    </label>
                    <label class="chip-card">
                      <input type="radio" name="EventAudience" value="All Students" onchange="updateReviewSummary()" />
                      <span class="chip-content" style="padding: 12px 18px; font-size: 14px; font-weight: 600;">
                        <ion-icon name="school-outline" style="font-size: 20px; color: #2563eb;"></ion-icon> All Students
                      </span>
                    </label>
                  </div>
                </div>
                <div class="form-grid-2" style="margin-top:14px;">
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

              <div class="form-step-footer">
                <button type="button" class="btn" onclick="prevStep(3)">
                  <ion-icon name="arrow-back-outline"></ion-icon> Back: Schedule
                </button>
                <button type="button" class="btn primary" onclick="nextStep(3)">
                  Next: Documents <ion-icon name="arrow-forward-outline"></ion-icon>
                </button>
              </div>
            </div>

            <!-- ── STEP 4: Event Documentation ───────────────────────── -->
            <div class="form-step-panel" id="stepPanel4">
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
                    <input id="posterFile" name="EventPicture" class="file-input" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg" onchange="handleFileSelect(this,'posterFileName','posterUploadBox','PNG or JPG (Max 5MB)')" />
                    <label class="upload-box" id="posterUploadBox" for="posterFile">
                      <svg class="upload-svg-icon" style="width:26px;height:26px;min-width:26px;min-height:26px;display:block;margin:0 auto 6px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 367.79h76c55 0 100-29.21 100-83.6s-53-81.47-96-83.6c-8.89-85.30-71-136.8-144-136.8-69 0-113.44 45.79-128 91.2-60 5.7-112 43.42-112 100.8 0 53.4 45 111.6 104 111.6h68"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 255.79l-64-64-64 64"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M256 448.21V207.79"/></svg>
                      <span class="upload-label">Click to upload or drag and drop<br />PNG or JPG (Max 5MB)</span>
                    </label>
                    <small class="file-name" id="posterFileName">No file selected</small>
                  </div>
                  <div class="form-group">
                    <label>Other Supporting Files</label>
                    <input id="supportingFiles" name="EventOther[]" class="file-input" type="file" multiple onchange="handleFileSelect(this,'supportingFilesName','supportingUploadBox','Any file format (Max 10MB)')" />
                    <label class="upload-box" id="supportingUploadBox" for="supportingFiles">
                      <svg class="upload-svg-icon" style="width:26px;height:26px;min-width:26px;min-height:26px;display:block;margin:0 auto 6px;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 367.79h76c55 0 100-29.21 100-83.6s-53-81.47-96-83.6c-8.89-85.30-71-136.8-144-136.8-69 0-113.44 45.79-128 91.2-60 5.7-112 43.42-112 100.8 0 53.4 45 111.6 104 111.6h68"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M320 255.79l-64-64-64 64"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M256 448.21V207.79"/></svg>
                      <span class="upload-label">Click to upload or drag and drop<br />Any file format (Max 10MB)</span>
                    </label>
                    <small class="file-name" id="supportingFilesName">No file selected</small>
                  </div>
                </div>
              </article>

              <div class="form-step-footer">
                <button type="button" class="btn" onclick="prevStep(4)">
                  <ion-icon name="arrow-back-outline"></ion-icon> Back: Participants
                </button>
                <button type="button" class="btn primary" onclick="nextStep(4)">
                  Next: Review & Submit <ion-icon name="arrow-forward-outline"></ion-icon>
                </button>
              </div>
            </div>

            <!-- ── STEP 5: Attendance Setup & Final Review ───────────── -->
            <div class="form-step-panel" id="stepPanel5">
              <article class="form-section">
                <h3 class="section-title"><ion-icon name="camera-outline"></ion-icon> Attendance Setup</h3>
                <div class="form-group">
                  <label class="choice-item" style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;color:#0f172a;cursor:pointer;">
                    <input type="checkbox" name="AttendanceEnabled" id="attendanceCheck" checked style="width:18px;height:18px;accent-color:#2563eb;" onchange="updateReviewSummary()" />
                    <span>Enable Attendance Tracking for this event</span>
                  </label>
                </div>
                <input type="hidden" name="AttendanceMethod" value="Face & QR" />
                <div class="form-group">
                  <p class="note" style="margin-top:8px;">Note: Attendance tracking automatically uses both <strong>QR Code & Face Recognition</strong> with registered student data.</p>
                </div>
              </article>

              <!-- Live Event Proposal Review Card -->
              <article class="form-section" style="margin-top: 20px; border: 1.5px solid #bfdbfe; background: #ffffff;">
                <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e2e8f0;padding-bottom:12px;margin-bottom:18px;">
                  <h3 style="margin:0;font-size:1.15rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:10px;">
                    <ion-icon name="checkmark-done-circle-outline" style="color:#2563eb;font-size:24px;"></ion-icon> Event Summary Review
                  </h3>
                  <span style="font-size:12px;color:#64748b;">Please review your event details before final submission</span>
                </div>

                <div class="review-summary-grid">
                  <div class="review-item">
                    <span class="review-label">Event Title</span>
                    <strong class="review-value" id="revTitle">—</strong>
                  </div>
                  <div class="review-item">
                    <span class="review-label">Category / Type</span>
                    <strong class="review-value" id="revType">—</strong>
                  </div>
                  <div class="review-item">
                    <span class="review-label">Date & Time</span>
                    <strong class="review-value" id="revSchedule">—</strong>
                  </div>
                  <div class="review-item">
                    <span class="review-label">Mode & Location</span>
                    <strong class="review-value" id="revVenue">—</strong>
                  </div>
                  <div class="review-item">
                    <span class="review-label">Expected Attendees</span>
                    <strong class="review-value" id="revCapacity">—</strong>
                  </div>
                  <div class="review-item">
                    <span class="review-label">Target Participants</span>
                    <strong class="review-value" id="revParticipants">—</strong>
                  </div>
                  <div class="review-item">
                    <span class="review-label">Guest Speaker</span>
                    <strong class="review-value" id="revSpeaker">—</strong>
                  </div>
                  <div class="review-item" style="grid-column: 1 / -1;">
                    <span class="review-label">Attached Documents</span>
                    <div id="revDocs" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">—</div>
                  </div>
                </div>
              </article>

              <div class="form-step-footer">
                <button type="button" class="btn" onclick="prevStep(5)">
                  <ion-icon name="arrow-back-outline"></ion-icon> Back: Documents
                </button>
                <button class="btn primary submit-btn" type="submit" id="submitBtn">
                  <ion-icon name="paper-plane-outline"></ion-icon> Submit for Approval
                </button>
              </div>
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
