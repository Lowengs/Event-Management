<?php
/**
 * Organization API: Export Members to CSV
 * Endpoint: /config/API/endpoints/index.php?action=export_org_members
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../audit.php';

if (empty($_SESSION['org_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$orgName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_SESSION['org_name'] ?? 'Organization');
if (empty($orgName)) $orgName = 'Organization_' . $orgId;

$members = [];

// Fetch members using direct SQL or stored procedure
try {
    if ($stmt = $conn->prepare("CALL sp_GetOrgMembers(?)")) {
        $stmt->bind_param("i", $orgId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
        }
        $stmt->close();
        while ($conn->more_results() && $conn->next_result()) { ; }
    }
} catch (Throwable $e) {
    // proceed to fallback
}

if (empty($members)) {
    $q = $conn->query("
        SELECT DISTINCT 
            u.UserId, 
            u.student_id,
            u.first_name, 
            u.middle_name,
            u.last_name, 
            u.Email, 
            u.course, 
            u.year_level, 
            u.section, 
            u.phone, 
            u.status,
            u.verification_status,
            u.created_at
        FROM `user` u
        WHERE u.OrgId = $orgId
        ORDER BY u.first_name ASC
    ");
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            $members[] = $r;
        }
    }
}

$filename = "{$orgName}_Members_" . date('Y-m-d') . ".csv";

// Set CSV download headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel compatibility
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Header Row
fputcsv($out, [
    '#',
    'Student ID',
    'First Name',
    'Middle Name',
    'Last Name',
    'Email Address',
    'Course / Program',
    'Year Level',
    'Section',
    'Phone Number',
    'Account Status',
    'Verification Status',
    'Registration Date'
]);

$i = 1;
foreach ($members as $m) {
    $sid   = !empty($m['student_id']) ? $m['student_id'] : ($m['StudentIdNumber'] ?? 'N/A');
    $fn    = $m['first_name'] ?? ($m['FirstName'] ?? '');
    $mn    = $m['middle_name'] ?? ($m['MiddleName'] ?? '');
    $ln    = $m['last_name'] ?? ($m['LastName'] ?? '');
    $email = $m['Email'] ?? ($m['email'] ?? '');
    $crs   = $m['course'] ?? ($m['Course'] ?? 'N/A');
    $yr    = $m['year_level'] ?? ($m['YearLevel'] ?? 'N/A');
    $sec   = $m['section'] ?? ($m['Section'] ?? 'N/A');
    $ph    = $m['phone'] ?? ($m['Phone'] ?? '');
    $stat  = ucfirst(strtolower($m['status'] ?? ($m['Status'] ?? 'Active')));
    $vStat = ucfirst(str_replace('_', ' ', strtolower($m['verification_status'] ?? ($m['VerificationStatus'] ?? 'AI Verified'))));
    $date  = !empty($m['created_at']) ? date('Y-m-d H:i', strtotime($m['created_at'])) : (!empty($m['CreatedAt']) ? date('Y-m-d H:i', strtotime($m['CreatedAt'])) : '');

    fputcsv($out, [
        $i++,
        $sid,
        $fn,
        $mn,
        $ln,
        $email,
        $crs,
        $yr,
        $sec,
        $ph,
        $stat,
        $vStat,
        $date
    ]);
}

fclose($out);

// Record in Audit Trail
try {
    logAudit($conn, 'Exported Members List', 'organization', $orgId, 'success', [
        'filename'      => $filename,
        'total_records' => count($members)
    ]);
} catch (Throwable $e) {
    // ignore
}
exit;
?>
