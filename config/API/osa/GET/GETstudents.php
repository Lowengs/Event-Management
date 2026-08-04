<?php
/**
 * OSA API: GET Students List
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$students = [];
try {
    if ($stmt = $conn->prepare("CALL sp_GetOSAStudents()")) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) $students[] = $r;
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {}

if (empty($students)) {
    $result = $conn->query("SELECT u.*, o.OrgName FROM `user` u LEFT JOIN organization o ON o.OrgId = u.OrgId ORDER BY u.created_at DESC");
    if ($result) while ($row = $result->fetch_assoc()) $students[] = $row;
}

$total = count($students);
$ilas = 0; $ics = 0; $inet = 0;

foreach ($students as $s) {
    $c = strtolower($s['course'] ?? '');
    if (in_array($c, ['bsait', 'bsais', 'aamt', 'aaet', 'bsamt', 'bsaet', 'bsaero'], true)) {
        $ilas++;
    } elseif (in_array($c, ['bsat', 'bsavtour', 'bsavcomm'], true)) {
        $ics++;
    } else {
        $inet++;
    }
}

$stats = [
    'total' => $total,
    'ilas'  => $ilas,
    'ics'   => $ics,
    'inet'  => $inet
];

echo json_encode([
    'success'  => true,
    'stats'    => $stats,
    'data'     => $students,
    'students' => $students
]);
if ($isDirectApiCall) exit;
?>
