<?php
/**
 * Organization API: Delete Attendance Record
 * Endpoint: /config/API/endpoints/index.php?action=DELETEattendance
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true) ?: [];

$attendanceId = (int)(
    $_POST['AttendanceId'] ??
    $_POST['attendance_id'] ??
    $_GET['AttendanceId'] ??
    $_GET['attendance_id'] ??
    $json['AttendanceId'] ??
    $json['attendance_id'] ??
    0
);

if (!$attendanceId) {
    echo json_encode(['success' => false, 'message' => 'Attendance ID is required']);
    exit;
}

try {
    // Fetch attendee and event details prior to deletion for audit log
    $attInfo = [];
    $cRes = $conn->query("SELECT a.UserId, a.EventId, e.EventName, u.first_name, u.last_name, a.ScanType, a.LogType 
                          FROM attendance a 
                          LEFT JOIN event e ON e.EventId = a.EventId 
                          LEFT JOIN `user` u ON u.UserId = a.UserId 
                          WHERE a.AttendanceId = $attendanceId LIMIT 1");
    if ($cRes && $cRow = $cRes->fetch_assoc()) {
        $attInfo = $cRow;
    }

    $stmt = $conn->prepare("DELETE FROM attendance WHERE AttendanceId = ?");
    if ($stmt) {
        $stmt->bind_param("i", $attendanceId);
        if ($stmt->execute()) {
            if (file_exists(__DIR__ . '/../../../audit.php')) {
                require_once __DIR__ . '/../../../audit.php';
                $actorType = !empty($_SESSION['org_id']) ? 'organization' : (!empty($_SESSION['osa_id']) ? 'osa' : 'admin');
                $actorId   = (int)($_SESSION['org_id'] ?? $_SESSION['osa_id'] ?? $_SESSION['admin_id'] ?? 0);
                $attendeeName = trim(($attInfo['first_name'] ?? '') . ' ' . ($attInfo['last_name'] ?? '')) ?: 'Student';
                $evName = $attInfo['EventName'] ?? '';
                logAudit($conn, 'Delete Attendance Record', $actorType, $actorId ?: null, 'success', [
                    'AttendanceId' => $attendanceId,
                    'UserId'       => $attInfo['UserId'] ?? null,
                    'EventId'      => $attInfo['EventId'] ?? null,
                    'EventName'    => $evName,
                    'student_name' => $attendeeName
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Attendance record deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error preparing deletion']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
