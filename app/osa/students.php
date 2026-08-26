<?php
$required_role = 'osa';
require_once '../../config/session_guard.php';
require_once '../../config/db.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP OSA Portal - Students</title>
    <link rel="stylesheet" href="../../assets/css/admin/dashboard_final.css?<?= time() ?>" />
    <link rel="stylesheet" href="../../assets/css/admin/students.css?<?= time() ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
                <li><a href="students.php" class="nav active"><ion-icon name="people-outline"></ion-icon><span>Students</span></a></li>
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
                <h2>Students Management</h2>
                <p>Monitor and verify student registrations</p>
            </div>
        </div>

        <div class="divider"></div>

        <div class="summary-cards">
            <div class="summary-card">
                <div class="label">Total Registered Students</div>
                <div class="value" id="osaTotalStudents2">...</div>
            </div>
            <div class="summary-card ilas">
                <div class="label">ILAS Students</div>
                <div class="value" id="osaIlas">...</div>
            </div>
            <div class="summary-card ics">
                <div class="label">ICS Students</div>
                <div class="value" id="osaIcs">...</div>
            </div>
            <div class="summary-card inet">
                <div class="label">INET Students</div>
                <div class="value" id="osaInet">...</div>
            </div>
        </div>

        <div class="filters-section">
            <input type="text" id="stuSearch" class="search-bar" placeholder="Search by Student ID or Name">
            <div class="filter-row">
                <select id="stuCourse" class="filter-dropdown">
                    <option value="all">All Courses / Programs</option>
                    <option value="bsait">BSAIT</option>
                    <option value="bsais">BSAIS</option>
                    <option value="aamt">AAMT</option>
                    <option value="aaet">AAET</option>
                    <option value="bsamt">BSAMT</option>
                    <option value="bsaet">BSAET</option>
                    <option value="bsaero">BSAERO</option>
                    <option value="bsat">BSAT</option>
                    <option value="bsavtour">BSAVTOUR</option>
                    <option value="bsavcomm">BSAVCOMM</option>
                    <option value="bsavsec">BSAVSEC</option>
                    <option value="bsavssm">BSAVSSM</option>
                </select>
                <select id="stuYear" class="filter-dropdown">
                    <option value="all">All Year Levels</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
                <select id="stuStatus" class="filter-dropdown">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>

        <div class="table-section">
            <div class="table-header">
                <span class="title">All Students</span>
                <span class="badge" id="studentsTotalBadge">...</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Name / Student ID</th>
                        <th>Email</th>
                        <th>Course / Program</th>
                        <th>Year-Section</th>
                        <th>Organization</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <tr><td colspan="8" style="text-align:center; padding: 2rem;">Loading students...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="footer-pagination">
            <div class="footer-text">Showing <span id="studentsCountText">0</span> students</div>
            <div class="pagination-controls">
                <button class="page-btn active">1</button>
            </div>
        </div>
        </div>
    </main>

    
    <!-- Comprehensive Student Information Modal -->
    <div id="studentModal" class="student-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.6);z-index:99999;align-items:center;justify-content:center;padding:16px;">
        <div class="student-modal-content" style="background:#ffffff;border-radius:18px;width:100%;max-width:620px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 50px rgba(0,0,0,0.25);border:1px solid #e2e8f0;animation:modalFadeIn 0.25s ease;">
            <div class="modal-header" style="padding:18px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border-radius:18px 18px 0 0;">
                <div class="modal-header-text">
                    <h2 style="margin:0;font-size:1.2rem;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
                        <ion-icon name="person-circle-outline" style="color:#2563eb;font-size:24px;"></ion-icon>
                        Student Profile Details
                    </h2>
                    <p class="modal-subtitle" style="margin:2px 0 0;font-size:0.85rem;color:#475569;font-weight:500;">Comprehensive student and verification records</p>
                </div>
                <button class="close-modal" id="closeStudentModal" style="background:none;border:none;font-size:1.6rem;color:#64748b;cursor:pointer;padding:0;line-height:1;">&times;</button>
            </div>
            
            <div class="modal-body" style="padding:22px 24px;">
                <!-- Profile Header Banner -->
                <div style="display:flex;align-items:center;gap:18px;padding:14px 18px;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:14px;margin-bottom:20px;">
                    <img src="../../assets/img/philsca.png" alt="Profile Picture" class="modal-profile-img" id="modalStudentPhoto" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid #ffffff;box-shadow:0 4px 10px rgba(0,0,0,0.08);background:#fff;flex-shrink:0;">
                    <div style="min-width:0;flex:1;">
                        <h3 id="modalStudentName" style="margin:0;font-size:1.15rem;font-weight:800;color:#0f172a;line-height:1.2;">—</h3>
                        <p id="modalStudentUsername" style="margin:3px 0 6px;font-size:0.85rem;color:#475569;font-weight:600;">@username</p>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span id="modalStudentStatusBadge" style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;background:#dcfce7;color:#15803d;">Active</span>
                            <span id="modalStudentVerifBadge" style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;background:#e0e7ff;color:#4338ca;">Verified</span>
                        </div>
                    </div>
                </div>

                <!-- Section: Academic & Organization -->
                <h4 style="margin:0 0 10px;font-size:0.85rem;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Academic &amp; Organization</h4>
                <div class="modal-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px;">
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Student ID</span>
                        <span class="item-value" id="modalStudentId" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Course / Program</span>
                        <span class="item-value" id="modalStudentCourse" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Year &amp; Section</span>
                        <span class="item-value" id="modalStudentYearSection" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Organization</span>
                        <span class="item-value" id="modalStudentOrg" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Position / Officer</span>
                        <span class="item-value" id="modalStudentOfficer" style="font-size:14px;font-weight:700;color:#0f172a;">Student Member</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Date Registered</span>
                        <span class="item-value" id="modalStudentJoined" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                </div>

                <!-- Section: Personal & Contact Information -->
                <h4 style="margin:0 0 10px;font-size:0.85rem;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Contact &amp; Personal Info</h4>
                <div class="modal-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px;">
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Email Address</span>
                        <span class="item-value" id="modalStudentEmail" style="font-size:14px;font-weight:700;color:#0f172a;word-break:break-all;">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Contact Number</span>
                        <span class="item-value" id="modalStudentContact" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                    <div class="modal-grid-item full-width" style="grid-column:1 / -1;">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">Address</span>
                        <span class="item-value" id="modalStudentAddress" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                </div>

                <!-- Section: Verification & COR Document -->
                <h4 style="margin:0 0 10px;font-size:0.85rem;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:4px;">Verification &amp; Documents</h4>
                <div class="modal-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">AI Verification Score</span>
                        <span class="item-value" id="modalStudentAiScore" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">COR Status</span>
                        <span class="item-value" id="modalStudentCorStatus" style="font-size:14px;font-weight:700;color:#0f172a;">—</span>
                    </div>
                    <div class="modal-grid-item full-width" style="grid-column:1 / -1;margin-top:4px;">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:4px;">Certificate of Registration (COR) Document</span>
                        <div id="modalStudentCorWrap">
                            <span id="modalStudentCorNone" style="font-size:13px;color:#64748b;font-weight:600;">No file uploaded</span>
                            <div id="modalStudentCorFrameWrap" style="display:none;border:1px solid #cbd5e1;border-radius:12px;overflow:hidden;background:#f8fafc;margin-top:6px;">
                                <iframe id="modalStudentCorFrame" src="" style="width:100%;height:350px;border:none;display:block;"></iframe>
                                <img id="modalStudentCorImg" src="" alt="COR" style="max-width:100%;max-height:350px;object-fit:contain;display:none;margin:0 auto;padding:10px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-grid-item full-width" id="modalStudentAiDetailsWrap" style="grid-column:1 / -1;display:none;">
                        <span class="item-label" style="font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;display:block;margin-bottom:2px;">AI Validation Details</span>
                        <span class="item-value" id="modalStudentAiDetails" style="font-size:13px;color:#334155;background:#f8fafc;padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;display:block;">—</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="padding:14px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;background:#f8fafc;border-radius:0 0 18px 18px;">
                <button class="modal-btn outline" id="modalCloseStudentBtn" style="padding:8px 20px;border-radius:10px;background:#ffffff;border:1px solid #cbd5e1;color:#0f172a;font-weight:700;cursor:pointer;">Close</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/custom_modal.js?v=<?= time() ?>"></script>
    <script src="../../assets/js/admin/students.js"></script>
    <script src="../../assets/js/admin/dashboard.js"></script>
    
    <script src="../../assets/js/osa/osa_api_loader.js?v=<?= time() ?>"></script>
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../../assets/js/logout_confirm.js" defer></script>
</body>
</html>
