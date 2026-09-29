<?php
/**
 * NAAP ORG Portal – Event Pre-Registrations Management
 * View, search, filter, and export all students who pre-registered for each specific event.
 */
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['org_id'])) {
    header('Location: ../osa/login.php');
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$orgName = $_SESSION['org_name'] ?? 'Organization';
$activePage = 'preregistrations';

// Fetch pre-registrations data via API router
$requestedEventId = (int)($_GET['event_id'] ?? $_GET['EventId'] ?? 0);
$_GET['action'] = 'get_org_preregistrations';
$_GET['event_id'] = $requestedEventId;

ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$apiOutput = ob_get_clean();
$regData = json_decode($apiOutput, true) ?: [];
header('Content-Type: text/html; charset=UTF-8');

$events        = $regData['events'] ?? [];
$selectedEvent = $regData['selected_event'] ?? null;
$students      = $regData['students'] ?? [];
$selectedId    = $selectedEvent ? (int)$selectedEvent['EventId'] : ($requestedEventId ?: 0);

// Calculate Quick KPIs
$totalPreReg  = count($students);
$attendedCount = 0;
$pendingCount  = 0;
foreach ($students as $stu) {
    if (!empty($stu['has_attended'])) {
        $attendedCount++;
    } else {
        $pendingCount++;
    }
}
$turnoutPct = ($totalPreReg > 0) ? round(($attendedCount / $totalPreReg) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Pre-Registrations – NAAP ORG Portal</title>
    <link rel="stylesheet" href="../../assets/css/organization/nav.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/organization/events.css?v=<?= time() ?>">
    <link rel="icon" href="../../assets/img/philsca.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --surface: #ffffff;
            --bg-page: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-ui: #e2e8f0;
            --radius-md: 14px;
            --radius-lg: 20px;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-page);
            color: var(--text-main);
        }

        .tab-switcher {
            display: inline-flex;
            background: #f1f5f9;
            padding: 5px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            margin-bottom: 22px;
            gap: 6px;
            flex-wrap: wrap;
        }
        .tab-switch-btn {
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tab-switch-btn:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.7);
        }
        .tab-switch-btn.active {
            background: #ffffff;
            color: #2563eb;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
        }

        /* Event Selector Bar */
        .event-tabs-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 20px;
            scrollbar-width: thin;
        }
        .event-tabs-bar::-webkit-scrollbar {
            height: 6px;
        }
        .event-tabs-bar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .event-pill {
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: #475569;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .event-pill:hover {
            border-color: #93c5fd;
            color: #1d4ed8;
            transform: translateY(-1px);
        }
        .event-pill.active {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.25);
        }
        .event-pill .badge {
            background: rgba(0, 0, 0, 0.08);
            color: inherit;
            padding: 2px 7px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        .event-pill.active .badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }

        /* Event Banner Card */
        .event-banner-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            padding: 22px 26px;
            margin-bottom: 22px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }
        .event-banner-info {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .event-banner-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
            font-size: 28px;
            flex-shrink: 0;
        }
        .event-banner-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 6px;
            flex-wrap: wrap;
            font-size: 13px;
            color: #64748b;
        }
        .event-banner-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Stats Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-accent, #2563eb);
        }
        .kpi-label {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }
        .kpi-val {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .kpi-sub {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
            margin-top: 4px;
        }

        /* Filter Toolbar */
        .toolbar-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-md);
            padding: 14px 18px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .search-box {
            flex: 1;
            min-width: 240px;
            position: relative;
            display: flex;
            align-items: center;
        }
        .search-box input {
            width: 100%;
            height: 42px;
            padding: 0 14px 0 38px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13.5px;
            outline: none;
            color: #0f172a;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .search-box input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .search-box ion-icon {
            position: absolute;
            left: 12px;
            font-size: 18px;
            color: #94a3b8;
        }
        .filter-select {
            height: 42px;
            padding: 0 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            color: #334155;
            background: #ffffff;
            font-family: inherit;
            outline: none;
            font-weight: 600;
        }

        .btn-action {
            height: 42px;
            padding: 0 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-export {
            background: #f0fdf4;
            color: #166534;
            border: 1.5px solid #bbf7d0;
        }
        .btn-export:hover {
            background: #dcfce7;
            border-color: #86efac;
            transform: translateY(-1px);
        }
        .btn-print {
            background: #f8fafc;
            color: #475569;
            border: 1.5px solid #cbd5e1;
        }
        .btn-print:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        /* Modern Table */
        .table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }
        .data-table thead tr {
            background: #f8fafc;
            border-bottom: 1.5px solid #e2e8f0;
        }
        .data-table th {
            padding: 14px 18px;
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .data-table td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        .data-table tbody tr:hover {
            background: #f8fafc;
        }
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Student Number Badge */
        .id-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            font-weight: 700;
            color: #1e3a8a;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 4px 9px;
            border-radius: 8px;
            letter-spacing: 0.02em;
            display: inline-block;
        }

        .student-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avatar-initial {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .student-name {
            font-weight: 700;
            color: #0f172a;
            display: block;
        }
        .student-email {
            font-size: 11.5px;
            color: #64748b;
        }

        /* Status Badge */
        .att-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }
        .att-badge.attended {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .att-badge.pending {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Print Mode */
        @media print {
            .sidebar, .topbar, .tab-switcher, .toolbar-panel, .event-tabs-bar, .btn-action {
                display: none !important;
            }
            .content-shell, .maincontent {
                padding: 0 !important;
                margin: 0 !important;
            }
            .event-banner-card, .table-card {
                box-shadow: none !important;
                border: 1px solid #000 !important;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include '_org_sidebar.php'; ?>
    <div class="overlay" id="sidebarOverlay"></div>

    <div class="content-shell">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburgerBtn"><ion-icon name="menu-outline"></ion-icon></button>
                <div class="page-title">
                    <h2>Event Pre-Registrations</h2>
                    <p>Track all students registered per event, verify student ID numbers, and view attendance</p>
                </div>
            </div>
            <div class="topbar-right">
                <a href="../../config/API/endpoints/index.php?action=export_preregistrations&event_id=<?= $selectedId ?>&export=csv" class="btn-action btn-export" title="Export this event's pre-registered students to CSV">
                    <ion-icon name="download-outline"></ion-icon> Export CSV
                </a>
            </div>
        </header>

        <div class="maincontent" style="padding: 20px 24px;">
            <div class="divider"></div>

            <!-- Tab Switcher Navigation -->
            <div class="tab-switcher">
                <a href="events_org.php" class="tab-switch-btn">
                    <ion-icon name="calendar-outline"></ion-icon> All Events
                </a>
                <a href="preregistrations_org.php" class="tab-switch-btn active">
                    <ion-icon name="clipboard-outline"></ion-icon> Pre-Registered Students
                </a>
                <a href="attendance_org.php" class="tab-switch-btn">
                    <ion-icon name="qr-code-outline"></ion-icon> On-Site Attendance
                </a>
                <a href="online_attendance_org.php" class="tab-switch-btn">
                    <ion-icon name="videocam-outline"></ion-icon> Online Attendance
                </a>
            </div>

            <!-- Event Selector Tabs (One tab per event) -->
            <?php if (!empty($events)): ?>
            <div class="event-tabs-bar">
                <?php foreach ($events as $evItem): 
                    $isTabActive = ($selectedId === (int)$evItem['EventId']);
                    $pillCount = (int)($evItem['prereg_count'] ?? 0);
                ?>
                <a href="preregistrations_org.php?event_id=<?= $evItem['EventId'] ?>" class="event-pill <?= $isTabActive ? 'active' : '' ?>">
                    <ion-icon name="calendar-outline"></ion-icon>
                    <span><?= htmlspecialchars($evItem['EventName']) ?></span>
                    <span class="badge"><?= $pillCount ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($selectedEvent): ?>
            <!-- Selected Event Banner Header -->
            <div class="event-banner-card">
                <div class="event-banner-info">
                    <div class="event-banner-icon">
                        <ion-icon name="ribbon-outline"></ion-icon>
                    </div>
                    <div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <h3 style="font-size:1.35rem;font-weight:800;color:#0f172a;margin:0;">
                                <?= htmlspecialchars($selectedEvent['EventName']) ?>
                            </h3>
                            <span class="att-badge <?= strtolower($selectedEvent['EventStatus']) === 'ongoing' ? 'attended' : 'pending' ?>">
                                <?= htmlspecialchars($selectedEvent['EventStatus'] ?: 'Scheduled') ?>
                            </span>
                        </div>
                        <div class="event-banner-meta">
                            <span>
                                <ion-icon name="calendar-outline" style="color:#2563eb;"></ion-icon>
                                <?= $selectedEvent['EventDateTime'] ? date('M d, Y h:i A', strtotime($selectedEvent['EventDateTime'])) : 'TBA' ?>
                            </span>
                            <span>
                                <ion-icon name="location-outline" style="color:#ef4444;"></ion-icon>
                                <?= htmlspecialchars($selectedEvent['EventLocation'] ?: 'On-campus') ?>
                            </span>
                            <span>
                                <ion-icon name="globe-outline" style="color:#10b981;"></ion-icon>
                                <?= htmlspecialchars($selectedEvent['EventMode'] ?: 'On-site') ?>
                            </span>
                            <?php if (!empty($selectedEvent['EventCapacity'])): ?>
                            <span>
                                <ion-icon name="people-outline" style="color:#8b5cf6;"></ion-icon>
                                Capacity: <?= (int)$selectedEvent['EventCapacity'] ?> max
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex;align-items:center;gap:10px;">
                    <a href="events_org.php" class="btn-action btn-print" title="Back to Events">
                        <ion-icon name="arrow-back-outline"></ion-icon> Event Details
                    </a>
                    <button type="button" onclick="window.print()" class="btn-action btn-print">
                        <ion-icon name="print-outline"></ion-icon> Print List
                    </button>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="kpi-grid">
                <div class="kpi-card" style="--card-accent:#2563eb;">
                    <div class="kpi-label">Total Pre-Registered</div>
                    <div class="kpi-val" style="color:#2563eb;"><?= $totalPreReg ?></div>
                    <div class="kpi-sub">Students who reserved a slot</div>
                </div>
                <div class="kpi-card" style="--card-accent:#16a34a;">
                    <div class="kpi-label">Attended / Present</div>
                    <div class="kpi-val" style="color:#16a34a;"><?= $attendedCount ?></div>
                    <div class="kpi-sub">Attendance verified via QR / Face</div>
                </div>
                <div class="kpi-card" style="--card-accent:#d97706;">
                    <div class="kpi-label">Pending Attendance</div>
                    <div class="kpi-val" style="color:#d97706;"><?= $pendingCount ?></div>
                    <div class="kpi-sub">Not yet scanned or attended</div>
                </div>
                <div class="kpi-card" style="--card-accent:#7c3aed;">
                    <div class="kpi-label">Turnout Rate</div>
                    <div class="kpi-val" style="color:#7c3aed;"><?= $turnoutPct ?>%</div>
                    <div class="kpi-sub">Attendance vs Pre-registration</div>
                </div>
            </div>

            <!-- Toolbar: Search & Filters -->
            <div class="toolbar-panel">
                <div class="search-box">
                    <ion-icon name="search-outline"></ion-icon>
                    <input type="text" id="studentSearchInput" placeholder="Search by Student ID number, student name, course, section..." oninput="filterTable()">
                </div>

                <select id="attendanceStatusFilter" class="filter-select" onchange="filterTable()">
                    <option value="">All Attendance Status</option>
                    <option value="attended">Attended Only</option>
                    <option value="pending">Not Yet Attended</option>
                </select>

                <select id="courseFilter" class="filter-select" onchange="filterTable()">
                    <option value="">All Programs / Courses</option>
                    <?php 
                    $courses = array_filter(array_unique(array_column($students, 'course')));
                    sort($courses);
                    foreach ($courses as $c): ?>
                        <option value="<?= htmlspecialchars(strtolower($c)) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="button" class="btn-action btn-print" onclick="resetFilters()">
                    <ion-icon name="refresh-outline"></ion-icon> Reset
                </button>
            </div>

            <!-- Pre-Registered Students Table -->
            <div class="table-card">
                <div style="overflow-x: auto;">
                    <table class="data-table" id="preRegTable">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 170px;">Student ID Number</th>
                                <th>Student Full Name</th>
                                <th>Program &amp; Year</th>
                                <th>Email Address</th>
                                <th>Date Registered</th>
                                <th style="text-align: right;">Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody id="preRegTableBody">
                            <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 48px 20px; color: #94a3b8;">
                                    <ion-icon name="people-outline" style="font-size: 48px; display: block; margin: 0 auto 12px; color: #cbd5e1;"></ion-icon>
                                    <p style="font-weight: 700; font-size: 1.05rem; color: #475569; margin: 0 0 4px;">No Pre-Registered Students Yet</p>
                                    <p style="font-size: 0.85rem; margin: 0;">Students will appear here as soon as they pre-register for this event.</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($students as $idx => $s): 
                                $initials = strtoupper(substr($s['first_name'] ?? 'S', 0, 1) . substr($s['last_name'] ?? 'T', 0, 1));
                                $programYear = trim(($s['course'] ?? '') . ' ' . ($s['year_level'] ? $s['year_level'] . ($s['section'] ?? '') : ''));
                                $hasAttended = !empty($s['has_attended']);
                                $attLabel = $hasAttended ? 'Attended (' . ($s['AttendanceStatus'] ?: 'Present') . ')' : 'Not Yet Attended';
                            ?>
                            <tr class="student-row" 
                                data-student-id="<?= htmlspecialchars(strtolower($s['student_number'] ?? '')) ?>"
                                data-name="<?= htmlspecialchars(strtolower($s['full_name'] ?? '')) ?>"
                                data-email="<?= htmlspecialchars(strtolower($s['Email'] ?? '')) ?>"
                                data-course="<?= htmlspecialchars(strtolower($s['course'] ?? '')) ?>"
                                data-status="<?= $hasAttended ? 'attended' : 'pending' ?>">
                                <td style="font-weight: 700; color: #94a3b8;"><?= $idx + 1 ?></td>
                                <td>
                                    <?php if (!empty($s['student_number'])): ?>
                                        <span class="id-badge"><?= htmlspecialchars($s['student_number']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;font-style:italic;">No ID Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="student-cell">
                                        <div class="avatar-initial"><?= $initials ?></div>
                                        <div>
                                            <span class="student-name"><?= htmlspecialchars($s['full_name']) ?></span>
                                            <span class="student-email"><?= htmlspecialchars($s['Email'] ?? '—') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; color: #1e293b;">
                                        <?= htmlspecialchars($programYear ?: '—') ?>
                                    </span>
                                </td>
                                <td style="color: #64748b;"><?= htmlspecialchars($s['Email'] ?? '—') ?></td>
                                <td style="color: #64748b;">
                                    <?= !empty($s['DateIssued']) ? date('M d, Y', strtotime($s['DateIssued'])) : '—' ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($hasAttended): ?>
                                        <span class="att-badge attended">
                                            <ion-icon name="checkmark-circle"></ion-icon> <?= htmlspecialchars($attLabel) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="att-badge pending">
                                            <ion-icon name="time-outline"></ion-icon> Not Yet Attended
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <!-- No Events Found at all -->
            <div class="table-card" style="padding: 60px 20px; text-align: center;">
                <ion-icon name="calendar-outline" style="font-size: 54px; color: #cbd5e1; display: block; margin: 0 auto 12px;"></ion-icon>
                <h3 style="font-size: 1.2rem; font-weight: 700; color: #334155; margin-bottom: 6px;">No Events Created Yet</h3>
                <p style="color: #64748b; font-size: 0.9rem; max-width: 420px; margin: 0 auto 20px;">
                    Once you create an event, student pre-registrations will be displayed here for each event.
                </p>
                <a href="add-event_org.php" class="btn-action" style="background:#2563eb;color:#fff;display:inline-flex;">
                    <ion-icon name="add-outline"></ion-icon> Create New Event
                </a>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function filterTable() {
    const searchVal = document.getElementById('studentSearchInput').value.toLowerCase().trim();
    const statusVal = document.getElementById('attendanceStatusFilter').value.toLowerCase().trim();
    const courseVal = document.getElementById('courseFilter').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.student-row');

    let visibleCount = 0;
    rows.forEach(row => {
        const idText     = row.getAttribute('data-student-id') || '';
        const nameText   = row.getAttribute('data-name') || '';
        const emailText  = row.getAttribute('data-email') || '';
        const courseText = row.getAttribute('data-course') || '';
        const statusText = row.getAttribute('data-status') || '';

        const matchesSearch = !searchVal || 
                              idText.includes(searchVal) || 
                              nameText.includes(searchVal) || 
                              emailText.includes(searchVal) || 
                              courseText.includes(searchVal);

        const matchesStatus = !statusVal || statusText === statusVal;
        const matchesCourse = !courseVal || courseText === courseVal;

        if (matchesSearch && matchesStatus && matchesCourse) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
}

function resetFilters() {
    document.getElementById('studentSearchInput').value = '';
    document.getElementById('attendanceStatusFilter').value = '';
    document.getElementById('courseFilter').value = '';
    filterTable();
}
</script>
<script src="../../assets/js/org/org.js?v=<?= time() ?>"></script>
</body>
</html>
