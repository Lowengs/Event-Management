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
    $result = $conn->query("
        SELECT u.*, o.OrgName 
        FROM `user` u 
        LEFT JOIN organization o ON o.OrgId = u.OrgId 
        WHERE u.Role = 'student' OR u.Role IS NULL 
        ORDER BY u.UserId DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) $students[] = $row;
    }
} catch (Throwable $e) {}

if (empty($students)) {
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
}

$total = count($students);
$pending_ai    = 0;
$verified      = 0;
$failed        = 0;
$manual_review = 0;

$ilas = 0; $ics = 0; $inet = 0;

foreach ($students as $s) {
    $vs = strtolower(trim($s['verification_status'] ?? ''));
    $st = strtolower(trim($s['status'] ?? ''));
    $detailsStr = strtolower(trim($s['ai_verification_details'] ?? ''));

    $isRejected = in_array($vs, ['rejected', 'failed'], true);
    $isNeedsReview = in_array($vs, ['needs_org_review', 'manual_review', 'flagged'], true);
    $isApproved = in_array($vs, ['ai_verified', 'approved', 'verified'], true) || ($st === 'active' && !$isRejected && !$isNeedsReview);

    if ($isNeedsReview) {
        $manual_review++;
    } elseif ($isApproved) {
        $verified++;
    } elseif ($isRejected) {
        $failed++;
    } else {
        // Pending or unverified
        $pending_ai++;
    }

    // Accurate Institute distribution
    $c = strtolower(trim($s['course'] ?? ''));
    if (in_array($c, ['bsait', 'bsais'], true)) {
        $ics++; // Institute of Computing Studies
    } elseif (in_array($c, ['bsat', 'bsavtour', 'bsavcomm', 'bsavsec', 'bsavssm', 'bsavlog'], true)) {
        $ilas++; // Institute of Liberal Arts and Sciences
    } else {
        $inet++; // Institute of Engineering and Technology
    }
}

$stats = [
    'total'         => $total,
    'pending_ai'    => $pending_ai,
    'verified'      => $verified,
    'failed'        => $failed,
    'manual_review' => $manual_review,
    'ilas'          => $ilas,
    'ics'           => $ics,
    'inet'          => $inet
];

echo json_encode([
    'success'  => true,
    'stats'    => $stats,
    'data'     => $students,
    'students' => $students
]);
if ($isDirectApiCall) exit;
