<?php
/**
 * Common API: GET Index Data
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = !defined('INDEX_DATA_INCLUDE') && (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$stats  = ['total_orgs' => 0, 'total_students' => 0, 'total_events' => 0, 'total_certs' => 0];
$orgs   = [];
$events = [];

if ($conn) {
    $getColCount = function($q) {
        if ($q && ($row = $q->fetch_row())) {
            return (int)($row[0] ?? 0);
        }
        return 0;
    };

    $stats['total_orgs']     = $getColCount($conn->query("SELECT COUNT(*) FROM organization WHERE LOWER(COALESCE(Status, 'active')) = 'active'"));
    $stats['total_students'] = $getColCount($conn->query("SELECT COUNT(*) FROM `user` WHERE LOWER(role) = 'student' OR role IS NULL OR role = ''"));
    if ($stats['total_students'] === 0) {
        $stats['total_students'] = $getColCount($conn->query("SELECT COUNT(*) FROM `user`"));
    }
    
    $stats['total_events']   = $getColCount($conn->query("SELECT COUNT(*) FROM event WHERE LOWER(EventStatus) = 'completed'"));
    if ($stats['total_events'] === 0) {
        $stats['total_events'] = $getColCount($conn->query("SELECT COUNT(*) FROM event"));
    }
    
    $stats['total_certs']    = $getColCount($conn->query("SELECT COUNT(*) FROM certificates"));
    if ($stats['total_certs'] === 0) {
        $stats['total_certs'] = $getColCount($conn->query("SELECT COUNT(*) FROM certificate"));
    }

    // Organizations List (Only Active Organizations)
    $q = $conn->query("
        SELECT o.*,
            (SELECT COUNT(*) FROM `user` u WHERE u.OrgId = o.OrgId) AS member_count,
            (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId) AS event_count
        FROM organization o
        WHERE LOWER(COALESCE(o.Status, 'active')) = 'active'
        ORDER BY o.OrgName ASC
    ");
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            if (strtolower(trim((string)($row['Status'] ?? 'active'))) === 'active') {
                $orgs[] = $row;
            }
        }
    }

    // Scheduled / Upcoming / Approved Events for index (from Active Orgs only)
    $eq = $conn->query("
        SELECT e.*, o.OrgName
        FROM event e
        LEFT JOIN organization o ON o.OrgId = e.OrgId
        WHERE (e.EventStatus IS NULL OR e.EventStatus = '' OR LOWER(e.EventStatus) != 'archived')
          AND (o.Status IS NULL OR LOWER(o.Status) = 'active')
        ORDER BY CASE WHEN LOWER(e.EventStatus) = 'ongoing' THEN 1 WHEN e.EventDateTime >= NOW() THEN 2 ELSE 3 END, e.EventDateTime DESC
        LIMIT 12
    ");
    if ($eq) {
        while ($erow = $eq->fetch_assoc()) {
            $events[] = $erow;
        }
    }
}

if ($isDirectApiCall) {
    echo json_encode([
        'success'       => true,
        'stats'         => $stats,
        'organizations' => $orgs,
        'events'        => $events
    ]);
}
?>
