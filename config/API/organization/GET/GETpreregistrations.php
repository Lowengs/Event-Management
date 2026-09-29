<?php
/**
 * Organization API: GET Event Pre-Registrations & CSV Export
 * Endpoint: /config/API/endpoints/index.php?action=get_org_preregistrations
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));

if (empty($_SESSION['org_id']) && empty($_SESSION['osa_id']) && empty($_SESSION['admin_id'])) {
    if ($isDirectApiCall) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Organization login required']);
        exit;
    }
    return;
}

$orgId = (int)($_SESSION['org_id'] ?? $_GET['org_id'] ?? 0);
$selectedEventId = (int)($_GET['event_id'] ?? $_GET['EventId'] ?? 0);
$search = trim($_GET['search'] ?? $_GET['q'] ?? '');
$isExport = (!empty($_GET['export']) && strtolower($_GET['export']) === 'csv');

try {
    // 1. Fetch all events belonging to this organization with pre-registration counts
    $eventsQuery = "
        SELECT 
            e.EventId,
            e.EventName,
            e.EventDateTime,
            e.EndDateTime,
            e.EventLocation,
            e.EventMode,
            e.EventStatus,
            e.EventCapacity,
            COUNT(DISTINCT er.RegistrationId) AS prereg_count
        FROM `event` e
        LEFT JOIN `eventregistration` er ON er.EventId = e.EventId
        WHERE e.OrgId = ?
        GROUP BY e.EventId
        ORDER BY e.EventDateTime DESC
    ";

    $eventsStmt = $conn->prepare($eventsQuery);
    $orgEvents = [];
    if ($eventsStmt) {
        $eventsStmt->bind_param("i", $orgId);
        $eventsStmt->execute();
        $res = $eventsStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $orgEvents[] = $row;
        }
        $eventsStmt->close();
    }

    // Determine target event
    $selectedEvent = null;
    if ($selectedEventId > 0) {
        foreach ($orgEvents as $ev) {
            if ((int)$ev['EventId'] === $selectedEventId) {
                $selectedEvent = $ev;
                break;
            }
        }
    }

    // Default to first event if not specified and not requesting "all"
    if (!$selectedEvent && !empty($orgEvents) && $selectedEventId !== -1 && empty($_GET['view_all'])) {
        $selectedEvent = $orgEvents[0];
        $selectedEventId = (int)$selectedEvent['EventId'];
    }

    // 2. Query Pre-Registered Students
    $sql = "
        SELECT 
            er.RegistrationId,
            er.EventId,
            er.UserId,
            er.DateIssued,
            e.EventName,
            e.EventDateTime,
            e.EventLocation,
            e.EventStatus,
            COALESCE(u.student_id, '') AS student_number,
            u.first_name,
            u.last_name,
            u.middle_name,
            u.Email,
            COALESCE(u.course, '') AS course,
            COALESCE(u.year_level, '') AS year_level,
            COALESCE(u.section, '') AS section,
            u.profile_photo,
            a.AttendanceId,
            a.AttendanceStatus,
            a.Timestamp AS attendance_time,
            a.LogType AS attendance_log_type,
            a.ScanType AS attendance_scan_type
        FROM `eventregistration` er
        JOIN `user` u ON u.UserId = er.UserId
        JOIN `event` e ON e.EventId = er.EventId
        LEFT JOIN `attendance` a ON a.EventId = er.EventId AND a.UserId = er.UserId
        WHERE e.OrgId = ?
    ";

    $params = [$orgId];
    $types  = 'i';

    if ($selectedEventId > 0) {
        $sql .= " AND er.EventId = ?";
        $params[] = $selectedEventId;
        $types   .= 'i';
    }

    if (!empty($search)) {
        $sql .= " AND (
            u.student_id LIKE ? OR 
            u.first_name LIKE ? OR 
            u.last_name LIKE ? OR 
            u.Email LIKE ? OR 
            u.course LIKE ? OR 
            CONCAT(u.first_name, ' ', u.last_name) LIKE ?
        )";
        $sTerm = "%$search%";
        $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
        $types .= 'ssssss';
    }

    $sql .= " ORDER BY er.DateIssued DESC, u.last_name ASC";

    $stmt = $conn->prepare($sql);
    $students = [];

    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $seenUserIds = [];
        while ($row = $result->fetch_assoc()) {
            $key = $row['EventId'] . '_' . $row['UserId'];
            // If student has multiple attendance records for same event, coalesce into single attendee row
            if (isset($seenUserIds[$key])) {
                continue;
            }
            $seenUserIds[$key] = true;

            $fullName = trim(($row['first_name'] ?? '') . ' ' . (!empty($row['middle_name']) ? substr($row['middle_name'], 0, 1) . '. ' : '') . ($row['last_name'] ?? ''));
            $row['full_name'] = $fullName ?: 'Student #' . $row['UserId'];
            $row['has_attended'] = !empty($row['AttendanceId']);
            $students[] = $row;
        }
        $stmt->close();
    }

    // 3. Handle CSV Export
    if ($isExport) {
        $eventTitleSafe = $selectedEvent ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $selectedEvent['EventName']) : 'All_Events';
        $filename = "PreRegistered_Students_" . $eventTitleSafe . "_" . date('Y-m-d') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            '#',
            'Student ID Number',
            'Full Name',
            'Course',
            'Year Level',
            'Section',
            'Email Address',
            'Target Event',
            'Date Pre-Registered',
            'Attendance Status',
            'Attendance Timestamp'
        ]);

        $idx = 1;
        foreach ($students as $stu) {
            fputcsv($output, [
                $idx++,
                $stu['student_number'],
                $stu['full_name'],
                $stu['course'],
                $stu['year_level'],
                $stu['section'],
                $stu['Email'],
                $stu['EventName'],
                $stu['DateIssued'] ? date('M d, Y', strtotime($stu['DateIssued'])) : '—',
                $stu['has_attended'] ? 'Attended (' . ($stu['AttendanceStatus'] ?: 'Present') . ')' : 'Not Yet Attended',
                $stu['attendance_time'] ? date('M d, Y h:i A', strtotime($stu['attendance_time'])) : '—'
            ]);
        }
        fclose($output);
        exit;
    }

    // 4. Return JSON
    if ($isDirectApiCall) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'        => true,
            'events'         => $orgEvents,
            'selected_event' => $selectedEvent,
            'students'       => $students,
            'count'          => count($students),
            'total_events'   => count($orgEvents)
        ]);
        exit;
    }

} catch (Throwable $e) {
    if ($isDirectApiCall) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
?>
