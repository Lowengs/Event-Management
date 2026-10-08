<?php
/**
 * NAAP ORG Portal – Event Pre-Registrations Management
 * Accordion layout matching documents_org.php with per-event attendee rosters and CSV export.
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

// 1. Fetch organization events (matching documents_org.php exactly)
$_GET['action'] = 'get_org_events';
ob_start();
require __DIR__ . '/../../config/API/endpoints/index.php';
$evApiRes = json_decode(ob_get_clean() ?: '[]', true) ?: [];
header('Content-Type: text/html; charset=UTF-8');
$events = $evApiRes['data'] ?? [];

// Fallback if get_org_events returned empty
if (empty($events) && isset($conn) && $conn) {
    try {
        $fQ = $conn->query("
            SELECT e.*, o.OrgName 
            FROM event e 
            LEFT JOIN organization o ON o.OrgId = e.OrgId 
            WHERE e.OrgId = $orgId 
            ORDER BY e.EventDateTime DESC
        ");
        if ($fQ) {
            while ($r = $fQ->fetch_assoc()) {
                $events[] = $r;
            }
        }
    } catch (\Throwable $e) {}
}

// 1b. Also include events this org's students registered for through the org
//     (eventregistration.OrgId = this org) even when event.OrgId differs or is
//     NULL — previously those registrations were silently dropped.
if (isset($conn) && $conn) {
    $knownIds = [];
    foreach ($events as $ev) {
        $knownIds[(int)($ev['EventId'] ?? 0)] = true;
    }
    try {
        $extraQ = $conn->query("
            SELECT DISTINCT e.*, o.OrgName
            FROM eventregistration er
            JOIN event e ON e.EventId = er.EventId
            LEFT JOIN organization o ON o.OrgId = e.OrgId
            WHERE er.OrgId = $orgId
        ");
        if ($extraQ) {
            while ($r = $extraQ->fetch_assoc()) {
                $id = (int)$r['EventId'];
                if (!isset($knownIds[$id])) {
                    $events[] = $r;
                    $knownIds[$id] = true;
                }
            }
        }
    } catch (\Throwable $e) {}
    // Keep newest events first after merging
    usort($events, function ($a, $b) {
        return strcmp((string)($b['EventDateTime'] ?? ''), (string)($a['EventDateTime'] ?? ''));
    });
}

// 2. Fetch all pre-registered students for these events
$eventIds = array_values(array_unique(array_filter(array_map(function($ev) {
    return (int)($ev['EventId'] ?? 0);
}, $events))));

$studentsByEvent = [];
$allStudents = [];
$totalAttended = 0;
$totalPending  = 0;

if (!empty($eventIds) && isset($conn) && $conn) {
    $inList = implode(',', $eventIds);
    $userColCheck = $conn->query("SHOW COLUMNS FROM `user` LIKE 'student_id'");
    $hasStudentId = ($userColCheck && $userColCheck->num_rows > 0);
    $studentIdField = $hasStudentId ? "COALESCE(u.student_id, '')" : "''";

    $regSql = "
        SELECT 
            er.RegistrationId,
            er.EventId,
            er.UserId,
            er.DateIssued,
            e.EventName,
            e.EventDateTime,
            e.EventMode,
            e.EventStatus,
            $studentIdField AS student_number,
            COALESCE(u.first_name, '') AS first_name,
            COALESCE(u.last_name, '') AS last_name,
            COALESCE(u.middle_name, '') AS middle_name,
            COALESCE(u.Name, '') AS full_name_col,
            COALESCE(u.username, '') AS username,
            COALESCE(u.Email, '') AS Email,
            COALESCE(u.course, '') AS course,
            COALESCE(u.year_level, '') AS year_level,
            COALESCE(u.section, '') AS section,
            COALESCE(u.profile_photo, '') AS profile_photo,
            att.AttendanceId,
            COALESCE(att.AttendanceStatus, '') AS AttendanceStatus,
            att.Timestamp AS attendance_time
        FROM eventregistration er
        JOIN event e ON e.EventId = er.EventId
        LEFT JOIN `user` u ON u.UserId = er.UserId
        LEFT JOIN attendance att ON (att.EventId = er.EventId AND att.UserId = er.UserId)
        WHERE er.EventId IN ($inList)
        ORDER BY er.DateIssued DESC, er.RegistrationId DESC
    ";

    // A failing roster query used to silently hide every registrant; fall back
    // to a minimal query so registrations stay visible even if a column differs.
    try {
        $regRes = $conn->query($regSql);
    } catch (\Throwable $e) {
        $regRes = false;
    }
    if (!$regRes) {
        error_log('preregistrations_org roster query failed: ' . $conn->error);
        try {
            $regRes = $conn->query("
                SELECT er.RegistrationId, er.EventId, er.UserId, er.DateIssued,
                       e.EventName, e.EventDateTime, e.EventMode, e.EventStatus,
                       COALESCE(u.student_id, '') AS student_number,
                       COALESCE(u.first_name, '') AS first_name,
                       COALESCE(u.last_name, '') AS last_name,
                       COALESCE(u.middle_name, '') AS middle_name,
                       '' AS full_name_col,
                       COALESCE(u.username, '') AS username,
                       COALESCE(u.Email, '') AS Email,
                       COALESCE(u.course, '') AS course,
                       COALESCE(u.year_level, '') AS year_level,
                       COALESCE(u.section, '') AS section,
                       COALESCE(u.profile_photo, '') AS profile_photo,
                       NULL AS AttendanceId, '' AS AttendanceStatus, NULL AS attendance_time
                  FROM eventregistration er
                  JOIN event e ON e.EventId = er.EventId
                  LEFT JOIN `user` u ON u.UserId = er.UserId
                 WHERE er.EventId IN ($inList)
                 ORDER BY er.DateIssued DESC, er.RegistrationId DESC
            ");
        } catch (\Throwable $e) {
            $regRes = false;
        }
    }
    if ($regRes) {
        $seen = [];
        while ($row = $regRes->fetch_assoc()) {
            $eId = (int)$row['EventId'];
            $uId = (int)$row['UserId'];
            $key = $eId . '_' . $uId;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $first = trim($row['first_name'] ?? '');
            $last  = trim($row['last_name'] ?? '');
            $mid   = trim($row['middle_name'] ?? '');
            $midInitial = !empty($mid) ? (strtoupper(substr($mid, 0, 1)) . '. ') : '';

            $name = trim($first . ' ' . $midInitial . $last);

            if (empty($name)) {
                $name = trim($row['full_name_col'] ?? '');
            }
            if (empty($name)) {
                $name = trim($row['username'] ?? '');
            }
            if (empty($name) && !empty($row['Email'])) {
                $parts = explode('@', $row['Email']);
                $name = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
            }
            if (empty($name)) {
                $sNum = trim($row['student_number'] ?? '');
                $name = !empty($sNum) ? ('Student (' . $sNum . ')') : ('Student #' . $uId);
            }

            $row['full_name'] = $name;

            // Student ID Number display
            $sId = trim($row['student_number'] ?? '');
            if (empty($sId)) {
                $sId = 'STU-' . str_pad($uId, 5, '0', STR_PAD_LEFT);
            }
            $row['display_student_id'] = $sId;

            $row['has_attended'] = !empty($row['AttendanceId']);
            if (empty($row['AttendanceStatus'])) {
                $row['AttendanceStatus'] = $row['has_attended'] ? 'Present' : 'Pending';
            }

            if ($row['has_attended']) {
                $totalAttended++;
            } else {
                $totalPending++;
            }

            $allStudents[] = $row;
            if (!isset($studentsByEvent[$eId])) {
                $studentsByEvent[$eId] = [];
            }
            $studentsByEvent[$eId][] = $row;
        }
    }
}

// 3. Handle Per-Event Direct CSV Download
if (isset($_GET['export_event'])) {
    $expEventId = (int)$_GET['export_event'];
    $expEvent = null;
    foreach ($events as $ev) {
        if ((int)$ev['EventId'] === $expEventId) {
            $expEvent = $ev;
            break;
        }
    }

    $expList = $studentsByEvent[$expEventId] ?? [];
    $titleSafe = $expEvent ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $expEvent['EventName']) : "Event_{$expEventId}";
    $filename = "PreRegistered_Students_" . $titleSafe . "_" . date('Y-m-d') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

    fputcsv($out, [
        '#',
        'Student ID Number',
        'Student Name',
        'Course / Program',
        'Year Level',
        'Section',
        'Email Address',
        'Event Title',
        'Event Schedule',
        'Date Pre-Registered',
        'Attendance Status',
        'Check-In Timestamp'
    ]);

    $c = 1;
    foreach ($expList as $s) {
        fputcsv($out, [
            $c++,
            $s['student_number'] ?? '',
            $s['full_name'] ?? '',
            $s['course'] ?? '',
            $s['year_level'] ?? '',
            $s['section'] ?? '',
            $s['Email'] ?? '',
            $expEvent['EventName'] ?? '',
            $expEvent['EventDateTime'] ?? '',
            $s['DateIssued'] ?? '',
            !empty($s['has_attended']) ? 'Attended / Present' : 'Pending Attendance',
            $s['attendance_time'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

$totalPreRegAll = count($allStudents);
$turnoutOverall = ($totalPreRegAll > 0) ? round(($totalAttended / $totalPreRegAll) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP ORG Portal – Pre-Registered Students</title>
    <link rel="stylesheet" href="../../assets/css/organization/nav.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../assets/css/organization/documents_org.css?v=<?= time() ?>" />
    <link rel="icon" href="../../assets/img/philsca.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <script type="module" src="../../assets/js/lib/ionicons/ionicons.esm.js"></script>
    <script nomodule src="../../assets/js/lib/ionicons/ionicons.js"></script>
    <script src="../../assets/js/security.js"></script>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        .page-actions { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
        
        /* Navigation Tabs */
        .tab-switcher { display: inline-flex; background: #f1f5f9; padding: 4px; border-radius: 12px; border: 1px solid #e2e8f0; gap: 4px; }
        .tab-switch-btn { padding: 8px 18px; border-radius: 9px; font-size: 13px; font-weight: 700; text-decoration: none; color: #64748b; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .tab-switch-btn:hover { color: #0f172a; background: rgba(255,255,255,0.6); }
        .tab-switch-btn.active { background: #ffffff; color: #2563eb; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        
        /* Stats Grid */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin-bottom: 22px; }
        .kpi-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .kpi-card p { margin: 0 0 6px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        .kpi-card strong { font-size: 1.65rem; font-weight: 800; }
        .text-blue { color: #2563eb; }
        .text-emerald { color: #059669; }
        .text-amber { color: #d97706; }
        .text-purple { color: #7c3aed; }

        /* Search Filter Panel */
        .search-filter-panel { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .search-field { flex: 1; min-width: 260px; position: relative; display: flex; align-items: center; }
        .search-field ion-icon { position: absolute; left: 14px; font-size: 18px; color: #94a3b8; pointer-events: none; }
        .search-field input { width: 100%; height: 42px; padding: 0 14px 0 42px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.92rem; outline: none; background: #fff; font-family: inherit; }
        .search-field input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
        .filter-select { height: 42px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 0.9rem; outline: none; background: #fff; font-family: inherit; color: #334155; }
        .filter-select:focus { border-color: #2563eb; }

        /* Per Event Buttons in Accordion Summary */
        .event-summary-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
        .badge-student-count { font-size: 12px; background: #eff6ff; color: #1d4ed8; padding: 5px 12px; border-radius: 20px; font-weight: 700; border: 1px solid #bfdbfe; white-space: nowrap; }
        .btn-export-per-event { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: #f0fdf4; color: #15803d; border: 1.5px solid #86efac; border-radius: 9px; font-size: 12.5px; font-weight: 700; text-decoration: none; cursor: pointer; transition: all 0.2s ease; white-space: nowrap; }
        .btn-export-per-event:hover { background: #16a34a; color: #ffffff; border-color: #16a34a; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(22,163,74,0.2); }
        .btn-print-per-event { display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 9px; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; }
        .btn-print-per-event:hover { background: #e2e8f0; color: #0f172a; }

        /* Accordion items */
        .event-accordion-item { border: 1.5px solid #e2e8f0; border-radius: 14px; background: #ffffff; margin-bottom: 16px; overflow: hidden; transition: box-shadow 0.2s, border-color 0.2s; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .event-accordion-item:hover { border-color: #cbd5e1; }
        .event-accordion-item.expanded { border-color: #93c5fd; box-shadow: 0 8px 24px rgba(37,99,235,0.06); }
        .event-summary { display: flex; justify-content: space-between; align-items: center; padding: 18px 22px; cursor: pointer; user-select: none; background: #ffffff; transition: background 0.15s ease; gap: 14px; }
        .event-summary:hover { background: #f8fafc; }
        .event-summary-left { display: flex; align-items: center; gap: 14px; min-width: 0; flex: 1; }
        .chevron-icon { font-size: 20px; color: #94a3b8; transition: transform 0.25s ease; flex-shrink: 0; }
        .event-accordion-item.expanded .chevron-icon { transform: rotate(90deg); color: #2563eb; }
        .calendar-icon { font-size: 26px; color: #2563eb; flex-shrink: 0; }
        .event-title-date { min-width: 0; }
        .event-title-date h4 { margin: 0 0 3px; font-size: 1.05rem; font-weight: 700; color: #0f172a; line-height: 1.3; }
        .event-title-date p { margin: 0; font-size: 0.83rem; color: #64748b; line-height: 1.4; }

        /* Event Details Content */
        .event-details { display: none; padding: 18px 22px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; flex-direction: column; gap: 14px; }
        .event-accordion-item.expanded .event-details { display: flex; }

        /* Student Table */
        .tbl-responsive { width: 100%; overflow-x: auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .student-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem; }
        .student-table th { background: #f8fafc; color: #475569; font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 16px; border-bottom: 1.5px solid #e2e8f0; white-space: nowrap; }
        .student-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; color: #1e293b; }
        .student-table tr:last-child td { border-bottom: none; }
        .student-table tr:hover td { background: #fdfdfe; }

        .id-badge { font-family: 'JetBrains Mono', monospace; font-size: 0.84rem; font-weight: 700; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.03em; display: inline-block; }
        .user-cell { display: flex; align-items: center; gap: 10px; }
        .avatar-circle { width: 34px; height: 34px; border-radius: 50%; background: #ede9fe; color: #6d28d9; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
        .user-meta .name { font-weight: 600; color: #0f172a; line-height: 1.25; }
        .user-meta .email { font-size: 0.78rem; color: #64748b; }
        
        .pill-attended { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .pill-pending { display: inline-flex; align-items: center; gap: 4px; padding: 3px 9px; border-radius: 20px; font-size: 11.5px; font-weight: 700; background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        .empty-event-roster { padding: 32px 20px; text-align: center; background: #ffffff; border-radius: 12px; border: 1.5px dashed #cbd5e1; }
        .empty-event-roster ion-icon { font-size: 38px; color: #94a3b8; margin-bottom: 6px; }
        .empty-event-roster p { margin: 0; font-size: 0.9rem; color: #64748b; font-weight: 500; }

        @media (max-width: 768px) {
            .event-summary { flex-direction: column; align-items: flex-start; }
            .event-summary-right { width: 100%; justify-content: flex-start; flex-wrap: wrap; margin-top: 8px; }
            .search-filter-panel { flex-direction: column; align-items: stretch; }
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
                    <p>Track all registered students per event, verify student ID numbers, and track attendance status</p>
                </div>
            </div>
        </header>

        <div class="maincontent">
            <div class="divider"></div>

            <section style="padding:16px 24px;">

                <!-- Tab Switcher: Events vs Pre-Registrations vs Attendance -->
                <div class="page-actions">
                    <?php include __DIR__ . '/_org_tabs.php'; ?>

                </div>

                <!-- KPI Overview Grid -->
                <div class="kpi-grid">
                    <div class="kpi-card">
                        <p>Total Events</p>
                        <strong class="text-blue"><?= count($events) ?></strong>
                    </div>
                    <div class="kpi-card">
                        <p>Total Pre-Registered</p>
                        <strong class="text-purple"><?= $totalPreRegAll ?></strong>
                    </div>
                    <div class="kpi-card">
                        <p>Attended / Present</p>
                        <strong class="text-emerald"><?= $totalAttended ?></strong>
                    </div>
                    <div class="kpi-card">
                        <p>Pending Attendance</p>
                        <strong class="text-amber"><?= $totalPending ?></strong>
                    </div>
                </div>

                <!-- Search & Filters -->
                <div class="search-filter-panel">
                    <div class="search-field">
                        <ion-icon name="search-outline"></ion-icon>
                        <input type="search" id="liveSearchInput" placeholder="Search by student ID number, full name, or course..." oninput="filterAttendees()">
                    </div>
                    <select class="filter-select" id="eventSelectFilter" onchange="filterAttendees()">
                        <option value="">All Events (<?= count($events) ?>)</option>
                        <?php foreach ($events as $ev): ?>
                        <option value="<?= (int)$ev['EventId'] ?>">
                            <?= htmlspecialchars($ev['EventName']) ?> (<?= count($studentsByEvent[(int)$ev['EventId']] ?? []) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select class="filter-select" id="statusSelectFilter" onchange="filterAttendees()">
                        <option value="">All Statuses</option>
                        <option value="attended">Attended / Present</option>
                        <option value="pending">Pending Attendance</option>
                    </select>
                    <button type="button" class="btn-print-per-event" onclick="resetFilters()" style="height:42px;padding:0 14px;">
                        <ion-icon name="refresh-outline"></ion-icon> Reset
                    </button>
                </div>

                <!-- Section Title -->
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <h3 style="margin:0;font-size:1.15rem;font-weight:700;color:#0f172a;">
                        Events &amp; Pre-Registered Attendees (<?= count($events) ?>)
                    </h3>
                    <div style="display:flex;gap:8px;">
                        <button type="button" onclick="expandAllAccordions(true)" class="btn-print-per-event" style="font-size:12px;padding:5px 10px;">
                            Expand All
                        </button>
                        <button type="button" onclick="expandAllAccordions(false)" class="btn-print-per-event" style="font-size:12px;padding:5px 10px;">
                            Collapse All
                        </button>
                    </div>
                </div>

                <!-- ═══ Accordion Cards Container (Matching documents_org.php) ═══ -->
                <div class="events-accordion-container" id="eventsAccordionContainer">
                    <?php if (empty($events)): ?>
                        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:48px 24px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.02);">
                            <ion-icon name="calendar-outline" style="font-size:52px;color:#94a3b8;display:block;margin:0 auto 12px;"></ion-icon>
                            <h3 style="font-size:1.2rem;font-weight:700;color:#0f172a;margin:0 0 6px;">No Events Created Yet</h3>
                            <p style="color:#64748b;font-size:0.92rem;max-width:420px;margin:0 auto 20px;">
                                Once you create an event, student pre-registrations will be displayed here for each event like the documents page.
                            </p>
                            <a href="add-event_org.php" style="display:inline-flex;align-items:center;gap:6px;padding:10px 22px;background:#2563eb;color:#ffffff;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.9rem;">
                                <ion-icon name="add-outline" style="font-size:18px;"></ion-icon> Create New Event
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($events as $idx => $ev): 
                            $evId = (int)$ev['EventId'];
                            $evStudents = $studentsByEvent[$evId] ?? [];
                            $evDateFormatted = !empty($ev['EventDateTime']) ? date('M j, Y', strtotime($ev['EventDateTime'])) : 'Schedule TBA';
                            $evTimeFormatted = !empty($ev['EventDateTime']) ? date('h:i A', strtotime($ev['EventDateTime'])) : '';
                            $evPlace = $ev['EventPlace'] ?? $ev['EventLocation'] ?? 'Location TBA';
                            $evMode  = $ev['EventMode'] ?? 'On-site';
                            $evStatus = $ev['EventStatus'] ?? 'Scheduled';
                        ?>
                        <div class="event-accordion-item <?= (!empty($evStudents) || $idx === 0) ? 'expanded' : '' ?>" id="accordion-event-<?= $evId ?>" data-event-id="<?= $evId ?>">
                            
                            <!-- Accordion Summary (Header) -->
                            <div class="event-summary" onclick="toggleAccordion(<?= $evId ?>)">
                                <div class="event-summary-left">
                                    <ion-icon name="chevron-forward-outline" class="chevron-icon"></ion-icon>
                                    <ion-icon name="calendar-outline" class="calendar-icon"></ion-icon>
                                    <div class="event-title-date">
                                        <h4><?= htmlspecialchars($ev['EventName'] ?? 'Untitled Event') ?></h4>
                                        <p>
                                            <span><?= $evDateFormatted ?><?= $evTimeFormatted ? " &bull; $evTimeFormatted" : '' ?></span>
                                            &bull; <strong style="color:#0284c7;"><?= count($evStudents) ?></strong> student(s) pre-registered
                                            &bull; <span><?= htmlspecialchars($evMode) ?></span>
                                            <?php if (!empty($evPlace) && $evPlace !== 'Location TBA'): ?>
                                                &bull; <span><?= htmlspecialchars($evPlace) ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="event-summary-right" onclick="event.stopPropagation()">
                                    <span class="badge-student-count">
                                        <?= count($evStudents) ?> Student(s)
                                    </span>
                                </div>
                            </div>

                            <!-- Accordion Details (Students List) -->
                            <div class="event-details" id="event-details-<?= $evId ?>">
                                
                                <?php if (empty($evStudents)): ?>
                                    <div class="empty-event-roster">
                                        <ion-icon name="people-outline"></ion-icon>
                                        <p style="font-weight:700;color:#0f172a;margin-bottom:3px;">No Pre-Registered Students Yet</p>
                                        <p style="font-size:0.83rem;">When students register for <strong><?= htmlspecialchars($ev['EventName']) ?></strong>, their student ID number and name will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="tbl-responsive">
                                        <table class="student-table" id="table-event-<?= $evId ?>">
                                            <thead>
                                                <tr>
                                                    <th style="width:45px;">#</th>
                                                    <th style="width:160px;">Student ID Number</th>
                                                    <th>Student Name</th>
                                                    <th>Course / Program</th>
                                                    <th>Year &amp; Section</th>
                                                    <th>Date Registered</th>
                                                    <th>Attendance Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($evStudents as $sIdx => $stu): 
                                                    $initials = '';
                                                    if (!empty($stu['first_name']) && !empty($stu['last_name'])) {
                                                        $initials = strtoupper(substr($stu['first_name'], 0, 1) . substr($stu['last_name'], 0, 1));
                                                    } elseif (!empty($stu['full_name'])) {
                                                        $words = preg_split('/\s+/', trim($stu['full_name']));
                                                        if (count($words) >= 2) {
                                                            $initials = strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
                                                        } else {
                                                            $initials = strtoupper(substr($stu['full_name'], 0, 2));
                                                        }
                                                    }
                                                    if (empty($initials)) $initials = 'ST';
                                                    $regDate = !empty($stu['DateIssued']) ? date('M j, Y', strtotime($stu['DateIssued'])) : '—';
                                                    $isAttended = !empty($stu['has_attended']);
                                                    $attTime = (!empty($stu['attendance_time']) && $stu['attendance_time'] !== '0000-00-00 00:00:00') ? date('h:i A', strtotime($stu['attendance_time'])) : '';
                                                ?>
                                                <tr class="student-row" 
                                                    data-id="<?= htmlspecialchars(strtolower($stu['display_student_id'] ?? $stu['student_number'] ?? '')) ?>" 
                                                    data-name="<?= htmlspecialchars(strtolower($stu['full_name'] ?? '')) ?>" 
                                                    data-course="<?= htmlspecialchars(strtolower($stu['course'] ?? '')) ?>"
                                                    data-status="<?= $isAttended ? 'attended' : 'pending' ?>">
                                                    <td><?= $sIdx + 1 ?></td>
                                                    <td>
                                                        <span class="id-badge"><?= htmlspecialchars($stu['display_student_id'] ?? $stu['student_number'] ?? 'N/A') ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="user-cell">
                                                            <div class="avatar-circle"><?= htmlspecialchars($initials) ?></div>
                                                            <div class="user-meta">
                                                                <div class="name" style="font-weight:700;color:#0f172a;font-size:0.95rem;"><?= htmlspecialchars($stu['full_name']) ?></div>
                                                                <div class="email"><?= htmlspecialchars($stu['Email'] ?: 'No email') ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><strong><?= htmlspecialchars($stu['course'] ?: 'N/A') ?></strong></td>
                                                    <td><?= htmlspecialchars(trim(($stu['year_level'] ? $stu['year_level'] . ' - ' : '') . $stu['section']) ?: '—') ?></td>
                                                    <td><?= $regDate ?></td>
                                                    <td>
                                                        <?php if ($isAttended): ?>
                                                            <span class="pill-attended">
                                                                <ion-icon name="checkmark-circle"></ion-icon> Attended<?= $attTime ? " ($attTime)" : '' ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="pill-pending">
                                                                <ion-icon name="time-outline"></ion-icon> Pending
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </section>
        </div>
    </div>
</div>

<script>
// Toggle Accordion expand/collapse
function toggleAccordion(eventId) {
    const item = document.getElementById('accordion-event-' + eventId);
    if (item) {
        item.classList.toggle('expanded');
    }
}

// Expand or Collapse All Accordions
function expandAllAccordions(expand) {
    document.querySelectorAll('.event-accordion-item').forEach(el => {
        if (expand) {
            el.classList.add('expanded');
        } else {
            el.classList.remove('expanded');
        }
    });
}

// Real-Time Filter for Attendees and Events
function filterAttendees() {
    const searchVal = (document.getElementById('liveSearchInput')?.value || '').toLowerCase().trim();
    const eventFilterVal = (document.getElementById('eventSelectFilter')?.value || '').trim();
    const statusFilterVal = (document.getElementById('statusSelectFilter')?.value || '').toLowerCase().trim();

    document.querySelectorAll('.event-accordion-item').forEach(acc => {
        const evId = acc.getAttribute('data-event-id');
        let matchesEvent = !eventFilterVal || evId === eventFilterVal;

        if (!matchesEvent) {
            acc.style.display = 'none';
            return;
        }

        const rows = acc.querySelectorAll('.student-row');
        let visibleRowsInEvent = 0;

        rows.forEach(row => {
            const stuId = row.getAttribute('data-id') || '';
            const stuName = row.getAttribute('data-name') || '';
            const stuCourse = row.getAttribute('data-course') || '';
            const stuStatus = row.getAttribute('data-status') || '';

            const matchesSearch = !searchVal || stuId.includes(searchVal) || stuName.includes(searchVal) || stuCourse.includes(searchVal);
            const matchesStatus = !statusFilterVal || stuStatus === statusFilterVal;

            if (matchesSearch && matchesStatus) {
                row.style.display = '';
                visibleRowsInEvent++;
            } else {
                row.style.display = 'none';
            }
        });

        // If there's an active text search or status filter, auto-expand matching accordion
        if (searchVal || statusFilterVal) {
            if (visibleRowsInEvent > 0) {
                acc.style.display = '';
                acc.classList.add('expanded');
            } else {
                // If the event title itself matches the search query, keep it visible
                const titleText = acc.querySelector('h4')?.textContent.toLowerCase() || '';
                if (titleText.includes(searchVal)) {
                    acc.style.display = '';
                } else {
                    acc.style.display = 'none';
                }
            }
        } else {
            acc.style.display = '';
        }
    });
}

function resetFilters() {
    if (document.getElementById('liveSearchInput')) document.getElementById('liveSearchInput').value = '';
    if (document.getElementById('eventSelectFilter')) document.getElementById('eventSelectFilter').value = '';
    if (document.getElementById('statusSelectFilter')) document.getElementById('statusSelectFilter').value = '';
    filterAttendees();
}

// Print Specific Event Attendee Roster
function printEventRoster(eventId) {
    const acc = document.getElementById('accordion-event-' + eventId);
    if (!acc) return;
    const title = acc.querySelector('h4')?.textContent || 'Event';
    const subtitle = acc.querySelector('.event-title-date p')?.textContent || '';
    const tableHtml = acc.querySelector('.tbl-responsive')?.innerHTML || '<p>No pre-registered students.</p>';

    const printWin = window.open('', '_blank', 'width=900,height=650');
    if (!printWin) {
        alert('Please allow popups to print attendee roster.');
        return;
    }

    printWin.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Pre-Registration Roster - ${title}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 24px; color: #111; }
                .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 18px; }
                h1 { margin: 0 0 4px; font-size: 20px; color: #1e3a8a; }
                p { margin: 2px 0; font-size: 13px; color: #555; }
                table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 12px; }
                th, td { border: 1px solid #ccc; padding: 8px 10px; text-align: left; }
                th { background: #f0f4f8; font-weight: bold; }
                .id-badge { font-family: monospace; font-weight: bold; }
                .pill-attended { color: green; font-weight: bold; }
                .pill-pending { color: #b45309; }
                .avatar-circle { display: none; }
                .user-cell { display: block; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?= htmlspecialchars($orgName) ?></h1>
                <p><strong>Event:</strong> ${title}</p>
                <p>${subtitle}</p>
                <p style="font-size:11px;color:#888;">Generated on: ${new Date().toLocaleString()}</p>
            </div>
            ${tableHtml}
            <script>window.onload = function() { window.print(); }<\/script>
        </body>
        </html>
    `);
    printWin.document.close();
}
</script>
</body>
</html>
