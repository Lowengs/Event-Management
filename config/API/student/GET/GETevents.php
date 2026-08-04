<?php
/**
 * Student API: GET Events
 * Endpoint: /config/API/endpoints/index.php?action=get_student_events
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$userId = !empty($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0;
$events = [];

try {
    $stmt = $conn->prepare("CALL sp_GetStudentEvents()");
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $events[] = $row;
        }
    }
    $stmt->close();
    while ($conn->more_results() && $conn->next_result()) { ; }
} catch (Throwable $e) {
    // proceed
}

if (empty($events)) {
    $registrationField = $userId > 0
        ? "EXISTS(SELECT 1 FROM eventregistration er WHERE er.EventId = e.EventId AND er.UserId = $userId)"
        : '0';
    $result = $conn->query("
        SELECT e.*, o.OrgName,
               (SELECT COUNT(*) FROM attendance a WHERE a.EventId = e.EventId) AS attended_count,
               (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS reg_count,
               $registrationField AS is_registered
          FROM event e
          LEFT JOIN organization o ON o.OrgId = e.OrgId
         WHERE (e.EventStatus IS NULL OR LOWER(e.EventStatus) NOT IN ('archived', 'cancelled'))
         ORDER BY CASE LOWER(COALESCE(e.EventStatus, '')) WHEN 'ongoing' THEN 1 WHEN 'scheduled' THEN 2 WHEN 'upcoming' THEN 3 WHEN 'active' THEN 4 ELSE 9 END, e.EventDateTime DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
}

if (!empty($events)) {
    $eventIds = array_values(array_unique(array_filter(array_map(fn($e) => (int)($e['EventId'] ?? 0), $events))));
    if ($eventIds) {
        $idList = implode(',', $eventIds);
        $counts = [];
        $countRes = $conn->query("SELECT EventId, COUNT(*) AS total FROM eventregistration WHERE EventId IN ($idList) GROUP BY EventId");
        while ($countRes && ($row = $countRes->fetch_assoc())) $counts[(int)$row['EventId']] = (int)$row['total'];
        
        $registered = [];
        $attendedMap = [];
        if ($userId) {
            $registeredRes = $conn->query("SELECT EventId FROM eventregistration WHERE UserId = $userId AND EventId IN ($idList)");
            while ($registeredRes && ($row = $registeredRes->fetch_assoc())) $registered[(int)$row['EventId']] = true;
            
            $attRes = $conn->query("SELECT DISTINCT EventId FROM attendance WHERE UserId = $userId AND EventId IN ($idList)");
            while ($attRes && ($row = $attRes->fetch_assoc())) $attendedMap[(int)$row['EventId']] = true;
        }
        foreach ($events as &$event) {
            $id = (int)$event['EventId'];
            $event['reg_count'] = $counts[$id] ?? 0;
            $event['is_registered'] = isset($registered[$id]) ? 1 : 0;
            $event['has_attended'] = isset($attendedMap[$id]) ? 1 : 0;
        }
        unset($event);
    }
    
    // Sort events: Ongoing, Scheduled/Upcoming, Active on top; Completed at bottom
    usort($events, function($a, $b) {
        $orderMap = ['ongoing' => 1, 'scheduled' => 2, 'upcoming' => 3, 'active' => 4, 'completed' => 9];
        $stA = $orderMap[strtolower(trim($a['EventStatus'] ?? ''))] ?? 5;
        $stB = $orderMap[strtolower(trim($b['EventStatus'] ?? ''))] ?? 5;
        if ($stA !== $stB) return $stA <=> $stB;
        return strtotime($b['EventDateTime'] ?? '') <=> strtotime($a['EventDateTime'] ?? '');
    });
}

echo json_encode([
    'success' => true,
    'message' => 'Events retrieved successfully',
    'events'  => $events,
    'data'    => $events
]);
if ($isDirectApiCall) exit;
?>
