<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['org_id'])) { 
    header('Location: ../osa/login.php'); 
    exit; 
}

$orgId = (int)$_SESSION['org_id'];

$stmt = $conn->prepare("SELECT * FROM organization WHERE OrgId = ?");
$stmt->bind_param("i", $orgId);
$stmt->execute();
$orgData = $stmt->get_result()->fetch_assoc();

$orgName = $orgData['OrgName'] ?? 'Organization';
$activePage = 'events';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>NAAP ORG Portal – Events</title>
    <link rel="stylesheet" href="../../assets/css/organization/events.css?v=6">
    <link rel="stylesheet" href="../../assets/css/organization/nav.css">
    <link rel="stylesheet" href="../../assets/css/organization/events_org.css?<?= time() ?>">
    <link rel="icon" href="../../assets/img/philsca.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="dashboard-layout">
    <?php include '_org_sidebar.php'; ?>
    <div class="overlay" id="sidebarOverlay"></div>
    <div class="content-shell">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
                <div class="page-title"><h2>Event Management</h2><p>Create and manage organization events</p></div>
            </div>
        </header>

        <div class="maincontent">
            <div class="divider"></div>
            <section style="padding:16px 24px;">

                
                <div class="stats-grid">
                    <div class="stat-card"><p>Total Events</p><strong class="text-blue" id="statEventsTotal">0</strong></div>
                    <div class="stat-card"><p>Upcoming</p><strong class="text-green" id="statEventsUpcoming">0</strong></div>
                    <div class="stat-card"><p>Ongoing</p><strong class="text-orange" id="statEventsOngoing">0</strong></div>
                    <div class="stat-card"><p>Completed</p><strong class="text-purple" id="statEventsCompleted">0</strong></div>
                </div>

                
                <div class="toolbar-card">
                    <div style="flex:1;min-width:200px;position:relative;display:flex;align-items:center;">
                        <ion-icon name="search-outline" style="position:absolute;left:14px;color:#94a3b8;font-size:18px;"></ion-icon>
                        <input type="search" id="evSearch" placeholder="Search by event name or location..." style="width:100%;height:42px;padding:0 14px 0 40px;border:1px solid #cbd5e1;border-radius:8px;font-size:0.95rem;outline:none;font-family:inherit;color:#0f172a;box-sizing:border-box;">
                    </div>
                    <select class="form-input" id="statusFilter" style="min-width:160px;width:auto;height:42px;margin:0;padding-top:0;padding-bottom:0;box-sizing:border-box;">
                        <option value="">All Status</option>
                        <option value="Scheduled">Scheduled</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Delayed">Delayed</option>
                    </select>
                    <select class="form-input" id="sortFilter" style="min-width:160px;width:auto;height:42px;margin:0;padding-top:0;padding-bottom:0;box-sizing:border-box;">
                        <option value="date-desc">Newest First</option>
                        <option value="date-asc">Oldest First</option>
                        <option value="name-asc">Name (A-Z)</option>
                        <option value="name-desc">Name (Z-A)</option>
                    </select>
                    <button class="secondary-btn" onclick="openTestModal()" style="height:42px;margin:0;padding:0 16px;border-radius:8px;font-weight:600;font-size:0.9rem;white-space:nowrap;box-sizing:border-box;cursor:pointer;background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;display:flex;align-items:center;gap:6px;">
                        <ion-icon name="flask-outline" style="font-size:18px;color:#6366f1;"></ion-icon> Event Test Tool
                    </button>
                    <button class="primary-btn" onclick="window.location.href='add-event_org.php'" style="height:42px;margin:0;padding:0 20px;border-radius:8px;font-weight:600;font-size:0.95rem;white-space:nowrap;box-sizing:border-box;cursor:pointer;">
                        <ion-icon name="add-outline" style="font-size:18px;"></ion-icon> Create New Event
                    </button>
                </div>

                
                <div class="tbl-wrap" style="overflow-x:auto;">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Event Title</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Place / Location</th>
                                <th>Capacity</th>
                                <th>Pre-Registered</th>
                                <th>Event Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="eventsTableBody">
                            <tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">Loading events...</td></tr>
                        </tbody>
                    </table>
                </div>

            </section>
        </div>
    </div>
</div>


<div class="modal-overlay" id="eventFormModal">
    <div class="modal-content" style="max-width:720px;">
        <div class="modal-header">
            <h2 id="eventFormTitle">Create New Event</h2>
            <button class="close-modal" onclick="closeM('eventFormModal')"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <div id="aiAnalyzerPanel" style="background:linear-gradient(135deg,#eff6ff,#f0fdf4);border:1.5px solid #bfdbfe;border-radius:12px;padding:20px;margin-bottom:24px;">
                <p style="font-weight:700;font-size:14px;color:#1e3a8a;margin:0 0 12px 0;display:flex;align-items:center;gap:6px;">
                    <ion-icon name="color-wand-outline"></ion-icon>
                    AI Document Analyzer
                </p>
                <div class="upload-zone" id="aiUploadZone" onclick="document.getElementById('aiDocFile').click()">
                    <input type="file" id="aiDocFile" accept=".pdf,.doc,.docx,.txt,image/*" hidden>
                    <ion-icon name="cloud-upload-outline" style="font-size:28px;margin-bottom:8px;"></ion-icon>
                    <p>Click to upload proposal for auto-fill</p>
                </div>
                <button type="button" class="primary-btn" id="aiAnalyzeBtn" style="display:none;width:100%;margin-top:14px;justify-content:center;">Analyze Document</button>
                <div id="aiAnalyzeResult" style="display:none;margin-top:14px;">
                    <p id="aiSummaryText" style="font-size:13px;color:#334155;line-height:1.5;"></p>
                    <button type="button" class="primary-btn" id="aiApplyBtn" style="width:100%;justify-content:center;margin-top:10px;background:#10b981;border:none;">Apply Data</button>
                </div>
            </div>

            <form id="eventForm">
                <input type="hidden" id="evFormEventId" name="EventId">

                <div class="section-title">Basic Event Information</div>
                <div class="form-grid-2">
                    <div class="col-span-2">
                        <label class="form-label">Event Title *</label>
                        <input type="text" class="form-input" name="EventName" id="evName" required>
                    </div>
                    <div>
                        <label class="form-label">Event Type / Category *</label>
                        <select class="form-input" name="EventType" id="evType" required>
                            <option value="Seminar / Workshop">Seminar / Workshop</option>
                            <option value="General Assembly">General Assembly</option>
                            <option value="Leadership Summit">Leadership Summit</option>
                            <option value="Competition">Competition</option>
                            <option value="Sports Event">Sports Event</option>
                            <option value="Cultural Event">Cultural Event</option>
                            <option value="Community Service">Community Service</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Mode *</label>
                        <select class="form-input" name="EventMode" id="evMode">
                            <option value="On-site">On-site</option>
                            <option value="Online">Online</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Description *</label>
                        <textarea class="form-input" name="EventDescription" id="evDesc" required></textarea>
                    </div>
                </div>

                <div class="section-title">Schedule &amp; Location</div>
                <div class="form-grid-3">
                    <div>
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-input" name="EventDate" id="evDate" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Start Time *</label>
                        <input type="time" class="form-input" name="EventTimeStart" id="evTimeStart" required>
                    </div>
                    <div>
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-input" name="EventTimeEnd" id="evTimeEnd">
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Venue / Location *</label>
                        <input type="text" class="form-input" name="EventPlace" id="evPlace" required>
                    </div>
                </div>

                <div class="section-title">Participants Information</div>
                <div class="form-grid-2">
                    <div class="col-span-2">
                        <label class="form-label">Target Participants *</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px;font-size:0.88rem;">
                            <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="participants[]" value="Students" checked> Students</label>
                            <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="participants[]" value="Officers"> Officers</label>
                            <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="participants[]" value="External Guests"> External Guests</label>
                            <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="participants[]" value="Faculty Members"> Faculty Members</label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Expected Number of Attendees *</label>
                        <input type="number" class="form-input" name="EventCapacity" id="evCapacity" placeholder="e.g. 150" min="1" required>
                    </div>
                    <div>
                        <label class="form-label">Guest Speaker (Optional)</label>
                        <input type="text" class="form-input" name="EventSpeaker" id="evSpeaker" placeholder="Name of guest speaker">
                    </div>
                </div>

                <div class="section-title">Event Documentation</div>
                <div class="form-grid-2">
                    <div>
                        <label class="form-label">Event Proposal / OPLAN Document</label>
                        <input type="file" name="EventProposal" accept=".pdf,.doc,.docx">
                    </div>
                    <div>
                        <label class="form-label">Program Flow</label>
                        <input type="file" name="EventProgramFlow" accept=".pdf,.doc,.docx">
                    </div>
                    <div>
                        <label class="form-label">Poster or Event Pubmat</label>
                        <input type="file" name="EventPicture" id="evPicture" accept="image/*" onchange="previewPoster(this)">
                        <img id="evPosterPreview" class="img-preview" style="display:none;">
                    </div>
                    <div>
                        <label class="form-label">Other Supporting Files</label>
                        <input type="file" name="EventOther" accept="*/*" multiple>
                    </div>
                    <div>
                        <label class="form-label">Financial Report</label>
                        <input type="file" name="FinancialReport" accept=".pdf,.doc,.docx,.xlsx,.xls">
                    </div>
                </div>

                <div class="section-title">Attendance Setup</div>
                <div style="margin-top:10px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:0.9rem;color:#334155;font-weight:500;">
                        <input type="checkbox" name="AttendanceEnabled" id="attEnabled" value="1" style="width:18px;height:18px;">
                        Enable Attendance Tracking for this event
                    </label>
                </div>
                <div style="margin-top:12px;">
                    <label class="form-label">Attendance Method</label>
                    <select class="form-input" name="AttendanceMethod" id="attMethod">
                        <option value="Face Recognition">Face Recognition</option>
                        <option value="QR Code" selected>QR Code</option>
                        <option value="Face & QR">Face Recognition &amp; QR Code</option>
                        <option value="Manual">Manual</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="close-btn-bottom" onclick="closeM('eventFormModal')">Cancel</button>
            <button class="primary-btn" id="saveEventBtn">Submit</button>
        </div>
    </div>
</div>


<div class="modal-overlay" id="eventViewModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Event Details</h2>
            <button class="close-modal" onclick="closeM('eventViewModal')"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <div class="modal-body">
            <div style="display:flex;flex-direction:column;gap:20px;">
                <div>
                    <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Event Title</strong>
                    <div id="viewEvTitle" style="font-size:1.15rem;color:#0f172a;font-weight:600;margin-top:6px;">—</div>
                </div>
                <div>
                    <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Description</strong>
                    <div id="viewEvDesc" style="color:#334155;font-size:0.95rem;margin-top:6px;line-height:1.6;white-space:pre-wrap;">—</div>
                </div>
                <div class="form-grid-3" style="margin-bottom:0;">
                    <div>
                        <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Date</strong>
                        <div class="with-icon" style="margin-top:6px;color:#334155;"><ion-icon name="calendar-outline"></ion-icon> <span id="viewEvDate">—</span></div>
                    </div>
                    <div>
                        <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Time</strong>
                        <div class="with-icon" style="margin-top:6px;color:#334155;"><ion-icon name="time-outline"></ion-icon> <span id="viewEvTime">—</span></div>
                    </div>
                    <div>
                        <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Status</strong>
                        <div class="with-icon" style="margin-top:6px;color:#334155;"><ion-icon name="calendar-clear-outline"></ion-icon> <span id="viewEvStatus">—</span></div>
                    </div>
                    <div>
                        <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Duration</strong>
                        <div class="with-icon" style="margin-top:6px;color:#334155;"><ion-icon name="hourglass-outline"></ion-icon> <span id="viewEvDuration">—</span></div>
                    </div>
                </div>
                <div class="form-grid-2" style="margin-bottom:0;">
                    <div>
                        <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Place / Location</strong>
                        <div class="with-icon" style="margin-top:6px;color:#334155;"><ion-icon name="location-outline"></ion-icon> <span id="viewEvVenue">—</span></div>
                    </div>
                    <div>
                        <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Mode</strong>
                        <div id="viewEvMode" style="margin-top:6px;color:#334155;font-weight:500;">—</div>
                    </div>
                    <div>
                        <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Speaker</strong>
                        <div id="viewEvSpeaker" style="margin-top:6px;color:#334155;">—</div>
                    </div>
                    <div>
                        <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Capacity</strong>
                        <div id="viewEvCapacity" style="margin-top:6px;color:#334155;">—</div>
                    </div>
                </div>
                <div id="viewEvPosterContainer" style="display:none;margin-top:10px;border-top:1px solid #e2e8f0;padding-top:20px;">
                    <strong style="color:#64748b;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.05em;">Event Poster</strong>
                    <img id="viewEvPoster" style="display:block;max-height:260px;max-width:100%;border-radius:12px;margin-top:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="close-btn-bottom" onclick="closeM('eventViewModal')">Close</button>
        </div>
    </div>
</div>


<div id="toast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 24px;color:#fff;border-radius:8px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);z-index:9999;font-weight:500;"></div>


<div class="rs-overlay" id="rescheduleModal" onclick="if(event.target===this)closeReschedule()">
    <div class="rs-box">
        <div class="rs-header">
            <div>
                <h3><ion-icon name="calendar-number-outline" style="vertical-align:middle;margin-right:4px;"></ion-icon> Reschedule Event</h3>
                <p id="rsEventName" style="font-weight:600;color:#0f172a;margin-top:4px;"></p>
                <span class="rs-status-badge" id="rsStatus"></span>
            </div>
            <button class="rs-close" onclick="closeReschedule()"><ion-icon name="close-outline"></ion-icon></button>
        </div>
        <input type="hidden" id="rsEventId">
        <div class="rs-field">
            <label>New Start Date *</label>
            <input type="date" id="rsDate" min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-grid-2" style="gap:12px;margin:0 0 16px 0;">
            <div class="rs-field" style="margin:0;">
                <label>Start Time *</label>
                <input type="time" id="rsTime">
            </div>
            <div class="rs-field" style="margin:0;">
                <label>End Time</label>
                <input type="time" id="rsTimeEnd">
            </div>
        </div>
        <div class="rs-field">
            <label>New Venue / Location *</label>
            <input type="text" id="rsPlace" placeholder="e.g. Main Auditorium, Room 301">
        </div>
        <div class="rs-actions">
            <button class="rs-btn-cancel" onclick="closeReschedule()">Cancel</button>
            <button class="rs-btn-save" id="rsSaveBtn" onclick="saveReschedule()">Save &amp; Set to Scheduled</button>
        </div>
    </div>
</div>


<div class="modal-overlay" id="testStatusModal">
    <div class="modal-content" style="max-width:680px;">
        <div class="modal-header" style="background:linear-gradient(135deg,#4f46e5,#3b82f6);color:#fff;">
            <h2 style="color:#fff;font-size:1.1rem;display:flex;align-items:center;gap:8px;">
                <ion-icon name="flask-outline"></ion-icon> Event Status Test Tool
            </h2>
            <button class="close-modal" onclick="closeM('testStatusModal')" style="color:#fff;">&times;</button>
        </div>
        <div class="modal-body" style="padding:20px;">
            <p style="font-size:0.88rem;color:#64748b;margin-bottom:16px;">
                Toggle any event status freely between <strong>Scheduled</strong>, <strong>Ongoing</strong>, <strong>Delayed</strong>, <strong>Cancelled</strong>, and <strong>Completed</strong> for testing and demonstration.
            </p>
            <div style="max-height:360px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:10px;">
                <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;text-align:left;">
                            <th style="padding:10px 14px;">Event</th>
                            <th style="padding:10px 14px;">Current Status</th>
                            <th style="padding:10px 14px;text-align:right;">Test Status Action</th>
                        </tr>
                    </thead>
                    <tbody id="testStatusList">
                        <tr><td colspan="3" style="text-align:center;padding:20px;color:#94a3b8;">Loading events...</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:16px;text-align:right;">
                <a href="test_events.php" target="_blank" style="font-size:0.85rem;color:#4f46e5;font-weight:600;text-decoration:none;">
                    Open Full-Screen Test Page <ion-icon name="open-outline" style="vertical-align:middle;"></ion-icon>
                </a>
            </div>
        </div>
        <div class="modal-footer">
            <button class="close-btn-bottom" onclick="closeM('testStatusModal')">Done</button>
        </div>
    </div>
</div>

<!-- Upload Post-Activity Report Modal -->
<div class="modal-overlay" id="uploadReportModal">
    <div class="modal-content" style="max-width:550px;">
        <div class="modal-header" style="background:#ea580c;color:#fff;">
            <h2 style="color:#fff;font-size:1.1rem;display:flex;align-items:center;gap:8px;">
                <ion-icon name="cloud-upload-outline"></ion-icon> Upload Post-Activity Report
            </h2>
            <button class="close-modal" onclick="closeM('uploadReportModal')" style="color:#fff;">&times;</button>
        </div>
        <div class="modal-body" style="padding:20px;">
            <form id="uploadReportForm" onsubmit="submitPostActivityReport(event)">
                <input type="hidden" id="reportEventId" name="EventId">
                <input type="hidden" name="DocType" value="PostActivityReport">
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" style="font-weight:600;display:block;margin-bottom:6px;">Event Name</label>
                    <input type="text" class="form-input" id="reportEventNameDisplay" readonly style="background:#f1f5f9;">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" style="font-weight:600;display:block;margin-bottom:6px;">Report Title *</label>
                    <input type="text" class="form-input" id="reportTitle" name="Title" required placeholder="e.g. Post-Activity Report - Aviation Seminar">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" style="font-weight:600;display:block;margin-bottom:6px;">Description / Notes</label>
                    <textarea class="form-input" name="Description" rows="2" placeholder="Optional notes..."></textarea>
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" style="font-weight:600;display:block;margin-bottom:6px;">Select Post-Activity Report File * (PDF, DOCX, JPG, PNG)</label>
                    <input type="file" class="form-input" name="DocFile" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                </div>
                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label" style="font-weight:600;display:block;margin-bottom:6px;">Financial Report File (Optional / PDF, DOCX, XLSX, JPG, PNG)</label>
                    <input type="file" class="form-input" name="FinFile" accept=".pdf,.doc,.docx,.xlsx,.xls,.jpg,.jpeg,.png">
                </div>
                <div style="text-align:right;margin-top:20px;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" class="secondary-btn" onclick="closeM('uploadReportModal')">Cancel</button>
                    <button type="submit" class="primary-btn" id="uploadReportSubmitBtn" style="background:#ea580c;border:none;">Upload Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script src="../../assets/js/org/events_org.js?v=<?= time() ?>"></script>
<script src="../../assets/js/org/org.js"></script>
</body>
</html>