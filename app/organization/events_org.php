<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['org_id'])) { 
    header('Location: ../osa/login.php'); 
    exit; 
}


$orgId = (int)$_SESSION['org_id'];

ob_start();
$_GET['action'] = 'get_org_events'; require __DIR__ . '/../../config/API/endpoints/index.php';
$evApiRes = json_decode(ob_get_clean(), true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$eventsList = $evApiRes['data'] ?? [];

$orgName = $_SESSION['org_name'] ?? 'Organization';
$activePage = 'events';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>NAAP ORG Portal – Events</title>
    <link rel="stylesheet" href="../../assets/css/organization/events.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/organization/nav.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/organization/add-event_org.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/organization/events_org.css?v=<?= time() ?>">
    <link rel="icon" href="../../assets/img/philsca.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
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
                    <!--<button class="secondary-btn" onclick="openTestModal()" style="height:42px;margin:0;padding:0 16px;border-radius:8px;font-weight:600;font-size:0.9rem;white-space:nowrap;box-sizing:border-box;cursor:pointer;background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;display:flex;align-items:center;gap:6px;">
                        <ion-icon name="flask-outline" style="font-size:18px;color:#6366f1;"></ion-icon> Event Test Tool
                    </button>-->

                    <button type="button" class="secondary-btn" id="btnLiveRefresh" onclick="loadEvents(true)" title="Live Sync / Refresh Events" style="height:42px;margin:0;padding:0 14px;border-radius:8px;font-weight:600;font-size:0.9rem;white-space:nowrap;box-sizing:border-box;cursor:pointer;background:#f8fafc;color:#475569;border:1px solid #cbd5e1;display:flex;align-items:center;gap:6px;">
                        <ion-icon name="sync-outline" id="syncIcon" style="font-size:18px;color:#2563eb;"></ion-icon> <span id="syncText">Live Sync</span>
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
                                <th>Date &amp; Time</th>
                                <th>Place / Location</th>
                                <th>Event Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="eventsTableBody">
                            <tr><td colspan="5" style="text-align:center;padding:40px;color:#94a3b8;">Loading events...</td></tr>
                        </tbody>
                    </table>
                </div>

            </section>
        </div>
    </div>
</div>


<div class="modal-overlay" id="eventFormModal">
    <div class="modal-content" style="max-width:740px;width:95%;border-radius:20px;overflow:hidden;padding:0;display:flex;flex-direction:column;max-height:90vh;background:#fff;">

        <!-- Gradient Header -->
        <div id="eventFormHeaderBar" style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 60%,#8b5cf6 100%);padding:24px 28px 20px;position:relative;flex-shrink:0;">
            <button class="close-modal" type="button" onclick="closeM('eventFormModal')" style="position:absolute;top:14px;right:16px;color:#fff;background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;">
                <ion-icon name="close-outline"></ion-icon>
            </button>
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:46px;height:46px;background:rgba(255,255,255,0.18);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <ion-icon id="eventFormHeaderIcon" name="calendar-outline" style="font-size:24px;color:#fff;"></ion-icon>
                </div>
                <div>
                    <p style="margin:0;font-size:11px;font-weight:600;color:rgba(255,255,255,0.65);text-transform:uppercase;letter-spacing:0.08em;">Event Management</p>
                    <h2 id="eventFormTitle" style="margin:2px 0 0;font-size:1.15rem;font-weight:700;color:#fff;">Create New Event</h2>
                </div>
            </div>
        </div>

        <!-- Form Body -->
        <div class="modal-body" style="padding:20px 28px;overflow-y:auto;flex:1 1 auto;min-height:0;max-height:calc(90vh - 140px);">
            <form id="eventForm" onsubmit="submitEventForm(event); return false;">
                <input type="hidden" id="evFormEventId" name="EventId">

                <!-- Section: Basic Info -->
                <div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;padding-bottom:10px;border-bottom:2px solid #ede9fe;">
                    <div style="width:32px;height:32px;background:#ede9fe;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="information-circle-outline" style="font-size:18px;color:#7c3aed;"></ion-icon>
                    </div>
                    <span style="font-size:13px;font-weight:700;color:#4c1d95;text-transform:uppercase;letter-spacing:0.05em;">Basic Event Information</span>
                </div>
                <div class="form-grid-2" style="margin-bottom:18px;">
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
                        <select class="form-input" name="EventMode" id="evMode" onchange="handleModeChange(this.value)">
                            <option value="On-site">On-site</option>
                            <option value="Online">Online</option>
                            <option value="Hybrid">Hybrid</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Target Audience / Eligibility *</label>
                        <select class="form-input" name="Audience" id="evAudience" required>
                            <option value="all">🌐 All Students (Open to all students)</option>
                            <option value="members">🔒 Members Only (Organization members only)</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Description *</label>
                        <textarea class="form-input" name="EventDescription" id="evDesc" required></textarea>
                    </div>
                </div>

                <!-- Section: Schedule & Location -->
                <div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;padding-bottom:10px;border-bottom:2px solid #dbeafe;">
                    <div style="width:32px;height:32px;background:#dbeafe;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="calendar-outline" style="font-size:18px;color:#2563eb;"></ion-icon>
                    </div>
                    <span style="font-size:13px;font-weight:700;color:#1e3a8a;text-transform:uppercase;letter-spacing:0.05em;">Schedule &amp; Location</span>
                </div>
                <div class="form-grid-3" style="margin-bottom:18px;">
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

                <!-- Section: Additional Details -->
                <div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;padding-bottom:10px;border-bottom:2px solid #dcfce7;">
                    <div style="width:32px;height:32px;background:#dcfce7;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="people-outline" style="font-size:18px;color:#16a34a;"></ion-icon>
                    </div>
                    <span style="font-size:13px;font-weight:700;color:#14532d;text-transform:uppercase;letter-spacing:0.05em;">Additional Details</span>
                </div>
                <div class="form-grid-2" style="margin-bottom:18px;">
                    <div>
                        <label class="form-label">Target Audience / Capacity</label>
                        <input type="number" class="form-input" name="EventCapacity" id="evCapacity" placeholder="e.g. 100">
                    </div>
                    <div>
                        <label class="form-label">Guest Speaker / Resource Person</label>
                        <input type="text" class="form-input" name="EventSpeaker" id="evSpeaker" placeholder="e.g. Dr. John Doe">
                    </div>
                </div>

                <!-- Section: Required Documents (Uploads) -->
                <div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;padding-bottom:10px;border-bottom:2px solid #fef3c7;">
                    <div style="width:32px;height:32px;background:#fef3c7;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="cloud-upload-outline" style="font-size:18px;color:#d97706;"></ion-icon>
                    </div>
                    <span style="font-size:13px;font-weight:700;color:#78350f;text-transform:uppercase;letter-spacing:0.05em;">Required Documents &amp; Pubmat</span>
                </div>
                <div class="form-grid-2" style="margin-bottom:18px;">
                    <div>
                        <label class="form-label">Event Proposal</label>
                        <input type="file" id="evProposal" name="EventProposal" class="file-input" accept=".pdf,.doc,.docx" onchange="handleFileSelect(this, 'evProposalName', 'evProposalBox', 'PDF, DOC, DOCX (Max 10MB)')">
                        <label class="upload-box" id="evProposalBox" for="evProposal">
                            <ion-icon name="cloud-upload-outline" class="upload-svg-icon" style="font-size:22px;"></ion-icon>
                            <span class="upload-label">Click to upload file<br /><span style="font-size:11px;color:#94a3b8;">PDF, DOC, DOCX (Max 10MB)</span></span>
                        </label>
                        <small class="file-name" id="evProposalName">No file selected</small>
                    </div>
                    <div>
                        <label class="form-label">Poster or Event Pubmat (Images Only)</label>
                        <input type="file" name="EventPicture" id="evPicture" class="file-input" accept="image/png, image/jpeg, image/jpg, image/gif, image/webp" onchange="previewPoster(this)">
                        <label class="upload-box" id="evPictureBox" for="evPicture">
                            <ion-icon name="image-outline" class="upload-svg-icon" style="font-size:22px;"></ion-icon>
                            <span class="upload-label">Click to upload pubmat<br /><span style="font-size:11px;color:#94a3b8;">PNG, JPG, WEBP (Max 5MB)</span></span>
                        </label>
                        <small class="file-name" id="evPictureName">No image selected</small>
                        <img id="evPosterPreview" class="img-preview" style="display:none;margin-top:8px;max-height:160px;width:100%;max-width:320px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;">
                    </div>
                    <div>
                        <label class="form-label">Program Flow</label>
                        <input type="file" id="evProgramFlow" name="EventProgramFlow" class="file-input" accept=".pdf,.doc,.docx" onchange="handleFileSelect(this, 'evProgramFlowName', 'evProgramFlowBox', 'PDF, DOC, DOCX (Max 10MB)')">
                        <label class="upload-box" id="evProgramFlowBox" for="evProgramFlow">
                            <ion-icon name="document-text-outline" class="upload-svg-icon" style="font-size:22px;"></ion-icon>
                            <span class="upload-label">Click to upload file<br /><span style="font-size:11px;color:#94a3b8;">PDF, DOC, DOCX (Max 10MB)</span></span>
                        </label>
                        <small class="file-name" id="evProgramFlowName">No file selected</small>
                    </div>
                    <div>
                        <label class="form-label">Other Supporting Files</label>
                        <input type="file" id="evOther" name="EventOther" class="file-input" accept="*/*" multiple onchange="handleFileSelect(this, 'evOtherName', 'evOtherBox', 'Any file format (Max 10MB)')">
                        <label class="upload-box" id="evOtherBox" for="evOther">
                            <ion-icon name="folder-open-outline" class="upload-svg-icon" style="font-size:22px;"></ion-icon>
                            <span class="upload-label">Click to upload supporting files<br /><span style="font-size:11px;color:#94a3b8;">Any file format (Max 10MB)</span></span>
                        </label>
                        <small class="file-name" id="evOtherName">No files selected</small>
                    </div>
                </div>

                <!-- Section: Attendance Setup -->
                <div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;padding-bottom:10px;border-bottom:2px solid #fee2e2;">
                    <div style="width:32px;height:32px;background:#fee2e2;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="scan-outline" style="font-size:18px;color:#dc2626;"></ion-icon>
                    </div>
                    <span style="font-size:13px;font-weight:700;color:#7f1d1d;text-transform:uppercase;letter-spacing:0.05em;">Attendance Setup</span>
                </div>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin-bottom:4px;">
                    <label style="display:flex;align-items:center;gap:10px;font-size:0.9rem;color:#334155;font-weight:600;cursor:pointer;">
                        <input type="checkbox" name="AttendanceEnabled" id="attEnabled" value="1" style="width:18px;height:18px;">
                        Enable Attendance Tracking for this event
                    </label>
                    <input type="hidden" name="AttendanceMethod" id="attMethod" value="Face & QR">
                    <p style="margin:10px 0 0;font-size:0.8rem;color:#64748b;">Note: Attendance tracking automatically uses both <strong>QR Code &amp; Face Recognition</strong>.</p>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 28px;flex-shrink:0;">
            <button type="button" class="close-btn-bottom" onclick="closeM('eventFormModal')">Cancel</button>
            <button type="button" class="primary-btn" id="saveEventBtn" onclick="submitEventForm(event)" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;">
                <ion-icon name="save-outline"></ion-icon> Submit
            </button>
        </div>
    </div>
</div>


<div class="modal-overlay" id="eventViewModal">
    <div class="modal-content" style="max-width:640px;width:95%;border-radius:20px;overflow:hidden;padding:0;display:flex;flex-direction:column;max-height:90vh;background:#fff;">

        <!-- Gradient Header -->
        <div id="viewEvHeader" style="background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 60%,#3b82f6 100%);padding:24px 28px 20px;position:relative;flex-shrink:0;">
            <button class="close-modal" onclick="closeM('eventViewModal')" style="position:absolute;top:16px;right:16px;color:#fff;background:rgba(255,255,255,0.15);border:none;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;">
                <ion-icon name="close-outline"></ion-icon>
            </button>
            <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="width:48px;height:48px;background:rgba(255,255,255,0.2);border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <ion-icon name="calendar-outline" style="font-size:24px;color:#fff;"></ion-icon>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="margin:0 0 4px;font-size:11px;font-weight:600;color:rgba(255,255,255,0.65);text-transform:uppercase;letter-spacing:0.08em;">Event Details</p>
                    <h2 id="viewEvTitle" style="margin:0;font-size:1.25rem;font-weight:700;color:#fff;line-height:1.3;word-break:break-word;">—</h2>
                    <div style="margin-top:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span id="viewEvStatusBadge" style="display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,0.2);color:#fff;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600;">
                            <ion-icon name="ellipse" style="font-size:8px;"></ion-icon> —
                        </span>
                        <span id="viewEvModeBadge" style="display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,0.12);color:rgba(255,255,255,0.85);border-radius:20px;padding:4px 12px;font-size:12px;font-weight:500;">—</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="modal-body" style="padding:24px 28px;background:#fff;overflow-y:auto;flex:1 1 auto;min-height:0;max-height:calc(90vh - 140px);">

            <!-- Description -->
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;margin-bottom:20px;">
                <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">Description</p>
                <div id="viewEvDesc" style="color:#334155;font-size:0.92rem;line-height:1.6;white-space:pre-wrap;margin-top:4px;">—</div>
            </div>

            <!-- Schedule Row -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px;">
                <div style="background:#eff6ff;border-radius:12px;padding:14px;text-align:center;">
                    <ion-icon name="calendar-outline" style="font-size:20px;color:#2563eb;display:block;margin:0 auto 6px;"></ion-icon>
                    <p style="margin:0;font-size:10px;font-weight:700;color:#93c5fd;text-transform:uppercase;letter-spacing:0.05em;">Date</p>
                    <p id="viewEvDate" style="margin:4px 0 0;font-size:13px;font-weight:700;color:#1e3a8a;">—</p>
                </div>
                <div style="background:#f0fdf4;border-radius:12px;padding:14px;text-align:center;">
                    <ion-icon name="time-outline" style="font-size:20px;color:#16a34a;display:block;margin:0 auto 6px;"></ion-icon>
                    <p style="margin:0;font-size:10px;font-weight:700;color:#86efac;text-transform:uppercase;letter-spacing:0.05em;">Start Time</p>
                    <p id="viewEvTime" style="margin:4px 0 0;font-size:13px;font-weight:700;color:#14532d;">—</p>
                </div>
                <div style="background:#fef3c7;border-radius:12px;padding:14px;text-align:center;">
                    <ion-icon name="time-outline" style="font-size:20px;color:#d97706;display:block;margin:0 auto 6px;"></ion-icon>
                    <p style="margin:0;font-size:10px;font-weight:700;color:#fcd34d;text-transform:uppercase;letter-spacing:0.05em;">End Time</p>
                    <p id="viewEvEndTime" style="margin:4px 0 0;font-size:13px;font-weight:700;color:#78350f;">—</p>
                </div>
            </div>

            <!-- Details Grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:flex;align-items:flex-start;gap:10px;">
                    <div style="width:36px;height:36px;background:#fef2f2;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="location-outline" style="font-size:18px;color:#ef4444;"></ion-icon>
                    </div>
                    <div>
                        <p style="margin:0;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Place / Location</p>
                        <p id="viewEvVenue" style="margin:4px 0 0;font-size:13px;font-weight:600;color:#0f172a;">—</p>
                    </div>
                </div>
                <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:flex;align-items:flex-start;gap:10px;">
                    <div style="width:36px;height:36px;background:#fdf4ff;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="hourglass-outline" style="font-size:18px;color:#a855f7;"></ion-icon>
                    </div>
                    <div>
                        <p style="margin:0;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Duration</p>
                        <p id="viewEvDuration" style="margin:4px 0 0;font-size:13px;font-weight:600;color:#0f172a;">—</p>
                    </div>
                </div>
                <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:flex;align-items:flex-start;gap:10px;">
                    <div style="width:36px;height:36px;background:#fff7ed;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="mic-outline" style="font-size:18px;color:#f97316;"></ion-icon>
                    </div>
                    <div>
                        <p style="margin:0;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Speaker</p>
                        <p id="viewEvSpeaker" style="margin:4px 0 0;font-size:13px;font-weight:600;color:#0f172a;">—</p>
                    </div>
                </div>
                <div style="border:1px solid #e2e8f0;border-radius:12px;padding:14px;display:flex;align-items:flex-start;gap:10px;">
                    <div style="width:36px;height:36px;background:#f0fdf4;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <ion-icon name="people-outline" style="font-size:18px;color:#16a34a;"></ion-icon>
                    </div>
                    <div>
                        <p style="margin:0;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.05em;">Capacity</p>
                        <p id="viewEvCapacity" style="margin:4px 0 0;font-size:13px;font-weight:600;color:#0f172a;">—</p>
                    </div>
                </div>
            </div>

            <!-- Pre-Registered pill -->
            <div style="background:linear-gradient(90deg,#eff6ff,#e0f2fe);border:1px solid #bae6fd;border-radius:12px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <ion-icon name="person-add-outline" style="font-size:20px;color:#0284c7;"></ion-icon>
                    <span style="font-size:13px;font-weight:600;color:#0369a1;">Pre-Registered Students</span>
                </div>
                <span id="viewEvPreReg" style="font-size:18px;font-weight:800;color:#0ea5e9;">0</span>
            </div>

            <!-- Poster -->
            <div id="viewEvPosterContainer" style="display:none;border-top:1px solid #e2e8f0;padding-top:16px;">
                <p style="margin:0 0 10px;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.06em;">Event Poster</p>
                <img id="viewEvPoster" style="display:block;max-height:260px;max-width:100%;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,0.12);">
            </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 28px;flex-shrink:0;">
            <button class="close-btn-bottom" onclick="closeM('eventViewModal')" style="border-radius:10px;">Close</button>
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
    <div class="modal-content" style="max-width:680px;width:95%;border-radius:20px;overflow:hidden;padding:0;display:flex;flex-direction:column;max-height:90vh;background:#fff;">
        <div class="modal-header" style="background:linear-gradient(135deg,#4f46e5,#3b82f6);color:#fff;flex-shrink:0;">
            <h2 style="color:#fff;font-size:1.1rem;display:flex;align-items:center;gap:8px;">
                <ion-icon name="flask-outline"></ion-icon> Event Status Test Tool
            </h2>
            <button class="close-modal" onclick="closeM('testStatusModal')" style="color:#fff;">&times;</button>
        </div>
        <div class="modal-body" style="padding:20px 24px;overflow-y:auto;flex:1 1 auto;min-height:0;max-height:calc(90vh - 140px);">
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
        <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 28px;flex-shrink:0;">
            <button class="close-btn-bottom" onclick="closeM('testStatusModal')">Done</button>
        </div>
    </div>
</div>

<!-- Report uploader: one report is submitted at a time. -->
<div class="modal-overlay" id="uploadReportModal" style="backdrop-filter: blur(8px); background: rgba(15, 23, 42, 0.75);">
    <div class="modal-content" style="max-width: 580px; width: 95%; border-radius: 20px; overflow: hidden !important; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4); background: #ffffff; display: flex; flex-direction: column; max-height: 90vh;">
        <!-- Edit Event Style Gradient Header -->
        <div id="uploadReportModalHeader" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 60%, #8b5cf6 100%); padding: 22px 28px 18px; position: relative; flex-shrink: 0;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 46px; height: 46px; background: rgba(255, 255, 255, 0.18); border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <ion-icon name="cloud-upload-outline" style="font-size: 24px; color: #fff;"></ion-icon>
                    </div>
                    <div>
                        <p style="margin:0;font-size:11px;font-weight:600;color:rgba(255,255,255,0.75);text-transform:uppercase;letter-spacing:0.08em;">Event Documentation</p>
                        <h2 style="color: #ffffff; font-size: 1.2rem; font-weight: 700; margin: 2px 0 0;">Upload Event Report</h2>
                    </div>
                </div>
                <button type="button" onclick="closeM('uploadReportModal')" style="background: rgba(255,255,255,0.15); border: none; color: #ffffff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">
                    <ion-icon name="close-outline" style="font-size: 20px;"></ion-icon>
                </button>
            </div>
        </div>

        <div class="modal-body" style="padding: 24px 28px; overflow-y: auto !important; flex: 1 1 auto; min-height: 0; max-height: calc(90vh - 140px);">
            <form id="uploadReportForm" onsubmit="submitPostActivityReport(event)">
                <input type="hidden" id="reportEventId" name="EventId">
                <input type="hidden" id="reportDocType" name="DocType" value="PostActivityReport">

                <div style="display:flex;gap:10px;margin-bottom:18px;">
                    <button type="button" class="report-type-toggle active" data-report-type="PostActivityReport" onclick="selectReportUploadType('PostActivityReport')" style="flex:1;padding:11px;border:1.5px solid #7c3aed;border-radius:12px;background:#ede9fe;color:#5b21b6;font-weight:700;font-size:0.88rem;cursor:pointer;transition:all 0.2s;">Post-Activity Report</button>
                    <button type="button" class="report-type-toggle" data-report-type="FinancialReport" onclick="selectReportUploadType('FinancialReport')" style="flex:1;padding:11px;border:1.5px solid #cbd5e1;border-radius:12px;background:#fff;color:#475569;font-weight:700;font-size:0.88rem;cursor:pointer;transition:all 0.2s;">Financial Report</button>
                </div>
                
                <!-- Target Event Banner -->
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px;">
                    <ion-icon name="calendar-outline" style="font-size: 20px; color: #6366f1;"></ion-icon>
                    <div style="flex: 1;">
                        <span style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; color: #94a3b8; display: block;">Target Event</span>
                        <input type="text" id="reportEventNameDisplay" readonly style="background: transparent; border: none; font-size: 0.95rem; font-weight: 700; color: #0f172a; width: 100%; outline: none; padding: 0;">
                    </div>
                </div>

                <!-- Already Uploaded Notice -->
                <div id="alreadyUploadedNotice" style="display:none; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 12px; padding: 12px 16px; margin-bottom: 18px; display: flex; align-items: flex-start; gap: 10px;">
                    <ion-icon name="checkmark-circle" style="font-size: 20px; color: #16a34a; flex-shrink: 0; margin-top: 1px;"></ion-icon>
                    <div style="flex: 1;">
                        <span id="alreadyUploadedNoticeTitle" style="display: block; font-weight: 700; color: #166534; font-size: 0.85rem; margin-bottom: 2px;">Report Already Uploaded</span>
                        <span id="alreadyUploadedNoticeDesc" style="font-size: 0.78rem; color: #15803d; line-height: 1.35; display: block;">You have already submitted a report for this event. Submitting a new file below will update your upload if you misclicked the wrong file.</span>
                    </div>
                </div>

                <!-- Report Title -->
                <div class="form-group" style="margin-bottom: 16px;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                        <span>Report Title</span> <span style="color: #7c3aed;">*</span>
                    </label>
                    <input type="text" class="form-input" id="reportTitle" name="Title" required placeholder="e.g. Post-Activity Report - Aviation Seminar" style="width: 100%; border-radius: 10px; padding: 10px 14px; border: 1.5px solid #cbd5e1; font-size: 0.9rem; transition: border-color 0.2s;" onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#cbd5e1'">
                </div>

                <!-- Description / Notes -->
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="font-size: 0.85rem; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">Description / Executive Summary</label>
                    <textarea class="form-input" name="Description" rows="2" placeholder="Brief summary of event outcome or report notes..." style="width: 100%; border-radius: 10px; padding: 10px 14px; border: 1.5px solid #cbd5e1; font-size: 0.9rem; transition: border-color 0.2s;" onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#cbd5e1'"></textarea>
                </div>

                <!-- Upload Dropzone (Edit Event Style) -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="font-size: 0.85rem; font-weight: 700; color: #334155; display: block; margin-bottom: 6px;">
                        <span id="reportFileLabel">Post-Activity Report File</span> <span style="color: #7c3aed;">*</span>
                    </label>
                    <input type="file" id="reportDocFileInput" name="DocFile" class="file-input" required accept=".pdf,.doc,.docx,.xlsx,.jpg,.jpeg,.png" onchange="handleFileSelect(this, 'reportDocFileName', 'reportDocFileBox', 'PDF, DOCX, XLSX, JPG, PNG (Max 25MB)')">
                    <label class="upload-box" id="reportDocFileBox" for="reportDocFileInput">
                        <ion-icon name="cloud-upload-outline" class="upload-svg-icon" style="font-size:24px;color:#6366f1;"></ion-icon>
                        <span class="upload-label">Click to upload file<br /><span style="font-size:11px;color:#94a3b8;">PDF, DOCX, XLSX, JPG, PNG (Max 25MB)</span></span>
                    </label>
                    <small class="file-name" id="reportDocFileName" style="display:block;margin-top:6px;font-size:12px;color:#64748b;font-weight:600;">No file selected</small>
                </div>
            </form>
        </div>

        <div class="modal-footer" style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:16px 28px;flex-shrink:0;display:flex;justify-content:flex-end;gap:12px;">
            <button type="button" class="close-btn-bottom" onclick="closeM('uploadReportModal')">Cancel</button>
            <button type="button" class="primary-btn" id="uploadReportSubmitBtn" onclick="document.getElementById('uploadReportForm').requestSubmit()" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);border:none;">
                <ion-icon name="cloud-upload-outline"></ion-icon> Upload Report
            </button>
        </div>
    </div>
</div>

<script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
<script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
<script>
    var initialEventsData = <?= json_encode($eventsList) ?>;
</script>
<script src="../../assets/js/org/org.js?v=<?= time() ?>"></script>
<script src="../../assets/js/org/events_org.js?v=<?= time() ?>"></script>
</body>
</html>
