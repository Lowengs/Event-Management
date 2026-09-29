<?php
/**
 * Organization API: GET Event Pre-Registrations & Per-Event CSV Export
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
    // 1. Fetch organization events using sp_GetOrgEvents or robust fallback query
    $orgEvents = [];
    if ($orgId > 0) {
        try {
            if ($spStmt = $conn->prepare("CALL sp_GetOrgEvents(?)")) {
                $spStmt->bind_param("i", $orgId);
                $spStmt->execute();
                $spRes = $spStmt->get_result();
                if ($spRes) {
                    while ($r = $spRes->fetch_assoc()) {
                        $orgEvents[] = $r;
                    }
                }
                $spStmt->close();
                while ($conn->more_results() && $conn->next_result()) { ; }
            }
        } catch (\Throwable $e) {}

        if (empty($orgEvents)) {
            $fallbackQuery = "
                SELECT e.*, o.OrgName
                FROM `event` e
                LEFT JOIN `organization` o ON o.OrgId = e.OrgId
                WHERE e.OrgId = $orgId
                ORDER BY e.EventDateTime DESC
            ";
            $fRes = $conn->query($fallbackQuery);
            if ($fRes) {
                while ($r = $fRes->fetch_assoc()) {
                    $orgEvents[] = $r;
                }
            }
        }
    }

    $eventIds = array_filter(array_map(function($ev) {
        return (int)($ev['EventId'] ?? 0);
    }, $orgEvents));

    $studentsByEvent = [];
    $allStudents = [];

    if (!empty($eventIds)) {
        $inClause = implode(',', $eventIds);
        $userCheck = $conn->query("SHOW COLUMNS FROM `user` LIKE 'student_id'");
        $hasStudentIdCol = ($userCheck && $userCheck->num_rows > 0);

        $studentIdSelect = $hasStudentIdCol ? "COALESCE(u.student_id, u.UserId)" : "u.UserId";

        $stuSql = "
            SELECT 
                er.RegistrationId,
                er.EventId,
                er.UserId,
                er.DateIssued,
                e.EventName,
                e.EventDateTime,
                e.EventMode,
                e.EventStatus,
                $studentIdSelect AS student_number,
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
                a.AttendanceId,
                a.AttendanceStatus,
                a.Timestamp AS attendance_time
            FROM `eventregistration` er
            JOIN `event` e ON e.EventId = er.EventId
            LEFT JOIN `user` u ON u.UserId = er.UserId
            LEFT JOIN `attendance` a ON (a.EventId = er.EventId AND a.UserId = er.UserId)
            WHERE er.EventId IN ($inClause)
        ";

        if ($selectedEventId > 0) {
            $stuSql .= " AND er.EventId = " . (int)$selectedEventId;
        }

        if (!empty($search)) {
            $escapedSearch = $conn->real_escape_string($search);
            $stuSql .= " AND (
                u.first_name LIKE '%$escapedSearch%' OR 
                u.last_name LIKE '%$escapedSearch%' OR 
                u.Email LIKE '%$escapedSearch%' OR 
                u.course LIKE '%$escapedSearch%' OR
                CONCAT(u.first_name, ' ', u.last_name) LIKE '%$escapedSearch%'";
            if ($hasStudentIdCol) {
                $stuSql .= " OR u.student_id LIKE '%$escapedSearch%'";
            }
            $stuSql .= ")";
        }

        $stuSql .= " ORDER BY er.DateIssued DESC, er.RegistrationId DESC";

        $stuRes = $conn->query($stuSql);
        if ($stuRes) {
            $seenKeys = [];
            while ($row = $stuRes->fetch_assoc()) {
                $evId = (int)$row['EventId'];
                $uId  = (int)$row['UserId'];
                $key = $evId . '_' . $uId;
                if (isset($seenKeys[$key])) {
                    continue;
                }
                $seenKeys[$key] = true;

                $first = trim($row['first_name'] ?? '');
                $last  = trim($row['last_name'] ?? '');
                $mid   = trim($row['middle_name'] ?? '');
                $midInitial = !empty($mid) ? (strtoupper(substr($mid, 0, 1)) . '. ') : '';

                $fullName = trim($first . ' ' . $midInitial . $last);

                if (empty($fullName)) {
                    $fullName = trim($row['full_name_col'] ?? '');
                }
                if (empty($fullName)) {
                    $fullName = trim($row['username'] ?? '');
                }
                if (empty($fullName) && !empty($row['Email'])) {
                    $parts = explode('@', $row['Email']);
                    $fullName = ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
                }
                if (empty($fullName)) {
                    $sNum = trim($row['student_number'] ?? '');
                    $fullName = !empty($sNum) ? ('Student (' . $sNum . ')') : ('Student #' . $uId);
                }

                $row['full_name'] = $fullName;
                $row['has_attended'] = !empty($row['AttendanceId']);
                if (empty($row['AttendanceStatus'])) {
                    $row['AttendanceStatus'] = $row['has_attended'] ? 'Present' : 'Pending';
                }

                $allStudents[] = $row;
                if (!isset($studentsByEvent[$evId])) {
                    $studentsByEvent[$evId] = [];
                }
                $studentsByEvent[$evId][] = $row;
            }
        }
    }

    // Attach student count to each event
    foreach ($orgEvents as &$ev) {
        $evId = (int)$ev['EventId'];
        $ev['prereg_count'] = count($studentsByEvent[$evId] ?? []);
    }
    unset($ev);

    // 2. Handle CSV Export
    if ($isExport) {
        $targetEvent = null;
        if ($selectedEventId > 0) {
            foreach ($orgEvents as $e) {
                if ((int)$e['EventId'] === $selectedEventId) {
                    $targetEvent = $e;
                    break;
                }
            }
        }

        $eventTitleSafe = $targetEvent ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $targetEvent['EventName']) : 'All_Events';
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
            'Course / Program',
            'Year Level',
            'Section',
            'Email Address',
            'Event Name',
            'Event Schedule',
            'Pre-Registration Date',
            'Attendance Status',
            'Attendance Timestamp'
        ]);

        $exportList = ($selectedEventId > 0) ? ($studentsByEvent[$selectedEventId] ?? []) : $allStudents;

        $idx = 1;
        foreach ($exportList as $s) {
            fputcsv($output, [
                $idx++,
                $s['student_number'] ?? '',
                $s['full_name'] ?? '',
                $s['course'] ?? '',
                $s['year_level'] ?? '',
                $s['section'] ?? '',
                $s['Email'] ?? '',
                $s['EventName'] ?? ($targetEvent['EventName'] ?? 'N/A'),
                $s['EventDateTime'] ?? ($targetEvent['EventDateTime'] ?? ''),
                $s['DateIssued'] ?? '',
                !empty($s['has_attended']) ? 'Attended / Present' : 'Pending Attendance',
                $s['attendance_time'] ?? ''
            ]);
        }

        fclose($output);
        exit;
    }

    if ($isDirectApiCall) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'events' => $orgEvents,
            'students' => $allStudents,
            'students_by_event' => $studentsByEvent,
            'total_students' => count($allStudents),
            'total_events' => count($orgEvents)
        ]);
        exit;
    }

} catch (\Throwable $e) {
    if ($isExport) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "Error generating CSV export: " . $e->getMessage();
        exit;
    }
    if ($isDirectApiCall) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
