<?php
/**
 * Organization API: GET Detailed Online Events Attendance & Verification Roster
 * Endpoint: /config/API/endpoints/index.php?action=get_online_attendance
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json; charset=utf-8');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    if ($isDirectApiCall) exit;
    return;
}

$orgId = (int)$_SESSION['org_id'];
$eventId = (int)($_GET['EventId'] ?? $_GET['event_id'] ?? 0);

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    if ($isDirectApiCall) exit;
    return;
}

try {
    // 1. Ensure table exists
    $conn->query("CREATE TABLE IF NOT EXISTS student_verification_checks (
      VerificationId INT AUTO_INCREMENT PRIMARY KEY,
      EventId INT NOT NULL,
      UserId INT NOT NULL,
      CheckType VARCHAR(20) NOT NULL,
      TriggeredAt DATETIME NOT NULL,
      CompletedAt DATETIME NOT NULL,
      UNIQUE KEY verification_once (EventId, UserId, CheckType, TriggeredAt)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. Fetch Event Details
    $evStmt = $conn->prepare("
        SELECT EventId, OrgId, EventName, EventDateTime, EndDateTime, EventMode, EventPlace, EventLocation,
               COALESCE(NULLIF(TRIM(EventStatus), ''), 'Scheduled') AS EventStatus,
               AntiSpoofActive, AntiSpoofTriggeredAt, AntiSpoofGraceMinutes,
               PresenceCheckActive, PresenceCheckTriggeredAt, PresenceCheckDurationSec
        FROM event
        WHERE EventId = ? AND OrgId = ?
        LIMIT 1
    ");
    $evStmt->bind_param("ii", $eventId, $orgId);
    $evStmt->execute();
    $event = $evStmt->get_result()->fetch_assoc();
    $evStmt->close();

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Event not found or does not belong to your organization']);
        if ($isDirectApiCall) exit;
        return;
    }

    // Calculate duration requirements (80% stay rule)
    $evStartTs = !empty($event['EventDateTime']) ? strtotime($event['EventDateTime']) : 0;
    $evEndTs   = !empty($event['EndDateTime']) ? strtotime($event['EndDateTime']) : 0;
    $minStaySeconds = ($evStartTs && $evEndTs && $evEndTs > $evStartTs) 
        ? (int)floor(($evEndTs - $evStartTs) * 0.8) 
        : 3600;

    $isAntiSpoofTriggeredEver = !empty($event['AntiSpoofTriggeredAt']);
    $isPresenceTriggeredEver = !empty($event['PresenceCheckTriggeredAt']);

    // 3. Fetch All Students (Registered or Recorded in Attendance)
    $studentsSql = "
        SELECT DISTINCT 
            u.UserId,
            COALESCE(NULLIF(u.student_id, ''), '—') AS student_id,
            u.first_name,
            u.last_name,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS full_name,
            u.Email,
            COALESCE(u.course, 'N/A') AS course,
            COALESCE(u.year_level, 'N/A') AS year_level,
            COALESCE(u.section, 'N/A') AS section,
            COALESCE(u.profile_photo, '') AS profile_picture,
            er.RegistrationId,
            er.DateIssued AS RegisteredAt
        FROM `user` u
        LEFT JOIN eventregistration er ON er.UserId = u.UserId AND er.EventId = ?
        LEFT JOIN attendance a ON a.UserId = u.UserId AND a.EventId = ?
        WHERE (er.EventId = ? OR a.EventId = ?)
        ORDER BY u.last_name ASC, u.first_name ASC
    ";
    $stuStmt = $conn->prepare($studentsSql);
    $stuStmt->bind_param("iiii", $eventId, $eventId, $eventId, $eventId);
    $stuStmt->execute();
    $stuRes = $stuStmt->get_result();
    $students = [];
    while ($row = $stuRes->fetch_assoc()) {
        $students[$row['UserId']] = $row;
    }
    $stuStmt->close();

    // 4. Fetch All Attendance rows for this event
    $attStmt = $conn->prepare("
        SELECT AttendanceId, UserId, ScanType, Timestamp, AttendanceStatus, LogType,
               PresenceChecksPassed, PresenceChecksMissed, LastPresenceCheckAt
        FROM attendance
        WHERE EventId = ?
        ORDER BY Timestamp ASC
    ");
    $attStmt->bind_param("i", $eventId);
    $attStmt->execute();
    $attRes = $attStmt->get_result();
    $attendanceByUser = [];
    while ($ar = $attRes->fetch_assoc()) {
        $uid = $ar['UserId'];
        if (!isset($attendanceByUser[$uid])) {
            $attendanceByUser[$uid] = [];
        }
        $attendanceByUser[$uid][] = $ar;
    }
    $attStmt->close();

    // 5. Fetch All Verification Checks for this event
    $chkStmt = $conn->prepare("
        SELECT VerificationId, UserId, CheckType, TriggeredAt, CompletedAt
        FROM student_verification_checks
        WHERE EventId = ?
        ORDER BY CompletedAt DESC
    ");
    $chkStmt->bind_param("i", $eventId);
    $chkStmt->execute();
    $chkRes = $chkStmt->get_result();
    $checksByUser = [];
    while ($cr = $chkRes->fetch_assoc()) {
        $uid = $cr['UserId'];
        if (!isset($checksByUser[$uid])) {
            $checksByUser[$uid] = [];
        }
        $checksByUser[$uid][] = $cr;
    }
    $chkStmt->close();

    // 6. Process and consolidate student roster
    $roster = [];
    $summary = [
        'total_registered'              => 0,
        'total_attended'                => 0,
        'total_checked_out'             => 0,
        'total_stay_compliant'          => 0,
        'antispoof_completed_count'     => 0,
        'antispoof_not_completed_count' => 0,
        'presence_passed_count'         => 0,
        'presence_missed_count'         => 0
    ];

    foreach ($students as $uid => $s) {
        $isRegistered = !empty($s['RegistrationId']);
        if ($isRegistered) $summary['total_registered']++;

        $userAttList = $attendanceByUser[$uid] ?? [];
        $userChkList = $checksByUser[$uid] ?? [];

        // Determine Check In & Check Out timestamps and methods
        $checkInTime = null;
        $checkOutTime = null;
        $scanMethod = 'Online Facial';
        $attStatus = 'Absent';
        $presenceChecksPassed = 0;
        $presenceChecksMissed = 0;
        $lastPresenceCheckAt = null;

        foreach ($userAttList as $attRow) {
            $logType = strtolower(trim($attRow['LogType'] ?? ''));
            $ts = $attRow['Timestamp'];

            if (!empty($attRow['ScanType'])) {
                $scanMethod = $attRow['ScanType'];
            }
            if (!empty($attRow['AttendanceStatus'])) {
                $attStatus = $attRow['AttendanceStatus'];
            }
            if ($attRow['PresenceChecksPassed'] > $presenceChecksPassed) {
                $presenceChecksPassed = (int)$attRow['PresenceChecksPassed'];
            }
            if ($attRow['PresenceChecksMissed'] > $presenceChecksMissed) {
                $presenceChecksMissed = (int)$attRow['PresenceChecksMissed'];
            }
            if (!empty($attRow['LastPresenceCheckAt'])) {
                $lastPresenceCheckAt = $attRow['LastPresenceCheckAt'];
            }

            if ($logType === 'log in' || empty($checkInTime)) {
                $checkInTime = $ts;
            }
            if ($logType === 'log out') {
                $checkOutTime = $ts;
            }
        }

        $isCheckedIn = !empty($checkInTime);
        $isCheckedOut = !empty($checkOutTime);

        if ($isCheckedIn) {
            $summary['total_attended']++;
            if ($attStatus === 'Absent') $attStatus = 'Present';
        }
        if ($isCheckedOut) {
            $summary['total_checked_out']++;
        }

        // Calculate session stay duration
        $staySeconds = 0;
        $stayFormatted = '—';
        $isStayCompliant = false;

        if ($checkInTime) {
            $inTs = strtotime($checkInTime);
            $outTs = $checkOutTime ? strtotime($checkOutTime) : time();
            $staySeconds = max(0, $outTs - $inTs);
            $isStayCompliant = ($staySeconds >= $minStaySeconds);
            if ($isStayCompliant) {
                $summary['total_stay_compliant']++;
            }

            $h = floor($staySeconds / 3600);
            $m = floor(($staySeconds % 3600) / 60);
            $sec = $staySeconds % 60;
            $stayFormatted = ($h > 0) ? sprintf('%dh %02dm', $h, $m) : sprintf('%dm %02ds', $m, $sec);
        }

        // Evaluate Anti-Spoofing Status
        $asCompletedChecks = array_values(array_filter($userChkList, function($c) {
            $t = strtolower(trim($c['CheckType'] ?? ''));
            return $t === 'antispoof' || $t === 'anti-spoof';
        }));

        $hasAntiSpoofCompleted = count($asCompletedChecks) > 0;
        $antiSpoofCompletedAt = $hasAntiSpoofCompleted ? $asCompletedChecks[0]['CompletedAt'] : null;
        $antiSpoofCount = count($asCompletedChecks);

        $antiSpoofStatus = 'N/A'; // Default if never triggered
        if ($hasAntiSpoofCompleted) {
            $antiSpoofStatus = 'Completed';
            $summary['antispoof_completed_count']++;
        } else {
            if (!empty($event['AntiSpoofActive'])) {
                $antiSpoofStatus = 'Pending';
                $summary['antispoof_not_completed_count']++;
            } elseif ($isAntiSpoofTriggeredEver) {
                $antiSpoofStatus = 'Missed';
                $summary['antispoof_not_completed_count']++;
            } else {
                $antiSpoofStatus = 'Not Triggered';
            }
        }

        // Evaluate Presence / Continuous Monitoring Checks
        $prCompletedChecks = array_values(array_filter($userChkList, function($c) {
            $t = strtolower(trim($c['CheckType'] ?? ''));
            return $t === 'presence' || $t === 'continuous';
        }));

        $hasPresenceCompleted = count($prCompletedChecks) > 0 || $presenceChecksPassed > 0;
        $presenceCompletedAt = count($prCompletedChecks) > 0 ? $prCompletedChecks[0]['CompletedAt'] : $lastPresenceCheckAt;
        $presenceCount = max(count($prCompletedChecks), $presenceChecksPassed);

        $presenceStatus = 'N/A';
        if ($hasPresenceCompleted) {
            $presenceStatus = 'Passed';
            $summary['presence_passed_count']++;
        } else {
            if (!empty($event['PresenceCheckActive'])) {
                $presenceStatus = 'Pending';
                $summary['presence_missed_count']++;
            } elseif ($isPresenceTriggeredEver) {
                $presenceStatus = 'Missed';
                $summary['presence_missed_count']++;
            } else {
                $presenceStatus = 'Not Triggered';
            }
        }

        $roster[] = [
            'user_id'                    => (int)$uid,
            'student_id'                 => $s['student_id'],
            'first_name'                 => $s['first_name'],
            'last_name'                  => $s['last_name'],
            'full_name'                  => $s['full_name'],
            'email'                      => $s['Email'],
            'course'                     => $s['course'],
            'year_level'                 => $s['year_level'],
            'section'                    => $s['section'],
            'profile_picture'            => $s['profile_picture'],
            'is_registered'              => $isRegistered,
            'registered_at'              => $s['RegisteredAt'],
            'is_checked_in'              => $isCheckedIn,
            'check_in_time'              => $checkInTime,
            'is_checked_out'             => $isCheckedOut,
            'check_out_time'             => $checkOutTime,
            'attendance_status'          => $attStatus,
            'scan_method'                => $scanMethod,
            'stay_seconds'               => $staySeconds,
            'stay_formatted'             => $stayFormatted,
            'is_stay_compliant'          => $isStayCompliant,
            'has_antispoof_completed'    => $hasAntiSpoofCompleted,
            'antispoof_completed_at'     => $antiSpoofCompletedAt,
            'antispoof_count'            => $antiSpoofCount,
            'antispoof_status'           => $antiSpoofStatus,
            'has_presence_completed'     => $hasPresenceCompleted,
            'presence_completed_at'      => $presenceCompletedAt,
            'presence_count'             => $presenceCount,
            'presence_status'            => $presenceStatus,
            'verification_history'       => $userChkList
        ];
    }

    echo json_encode([
        'success'           => true,
        'event'             => $event,
        'min_stay_seconds'  => $minStaySeconds,
        'summary'           => $summary,
        'roster'            => $roster,
        'total'             => count($roster)
    ]);
    if ($isDirectApiCall) exit;

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
?>
