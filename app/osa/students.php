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
</head>
<body>
    <div class="mobile-message">
        <ion-icon name="desktop-outline"></ion-icon>
        <h2>Desktop Mode Required</h2>
        <p>Please use a desktop computer or switch to desktop mode to access the Students Management page for the best experience.</p>
    </div>

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

    
    <div id="studentModal" class="student-modal">
        <div class="student-modal-content">
            <div class="modal-header">
                <div class="modal-header-text">
                    <h2>Student Information</h2>
                    <p class="modal-subtitle">Basic Profile Details</p>
                </div>
                <button class="close-modal" id="closeStudentModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="profile-photo-container" style="text-align: center; margin-bottom: 20px;">
                    <img src="../../assets/img/philsca.png" alt="Profile Picture" class="modal-profile-img" id="modalStudentPhoto" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0; margin-bottom: 10px;">
                </div>

                <div class="modal-grid">
                    <div class="modal-grid-item">
                        <span class="item-label">STUDENT ID</span>
                        <span class="item-value" id="modalStudentId">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label">FULL NAME</span>
                        <span class="item-value" id="modalStudentName">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label">COURSE / PROGRAM</span>
                        <span class="item-value" id="modalStudentCourse">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label">YEAR LEVEL</span>
                        <span class="item-value" id="modalStudentYear">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label">SECTION</span>
                        <span class="item-value" id="modalStudentSection">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label">EMAIL ADDRESS</span>
                        <span class="item-value" id="modalStudentEmail">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label">CONTACT NUMBER</span>
                        <span class="item-value" id="modalStudentContact">—</span>
                    </div>
                    <div class="modal-grid-item">
                        <span class="item-label">ORGANIZATION</span>
                        <span class="item-value" id="modalStudentOrg">—</span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button class="modal-btn outline" id="modalCloseStudentBtn">Close</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/admin/students.js"></script>
    <script src="../../assets/js/admin/dashboard.js"></script>
    
    <script src="../../assets/js/osa/osa_api_loader.js?v=<?= time() ?>"></script>
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../../assets/js/logout_confirm.js" defer></script>
</body>
</html>
