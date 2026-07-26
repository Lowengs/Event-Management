<?php
session_start();
require_once '../db.php';
require_once '../audit.php';

if (empty($_SESSION['org_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$orgId = (int)$_SESSION['org_id'];

// Get Org Name for filename
$orgRes = $conn->query("SELECT OrgName FROM organization WHERE OrgId = $orgId LIMIT 1");
$orgRow = $orgRes->fetch_assoc();
$orgName = $orgRow ? preg_replace('/[^a-zA-Z0-9_]/', '_', $orgRow['OrgName']) : 'Organization';
$filename = $orgName . '_Members_' . date('Y-m-d') . '.csv';

// Headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, array('Student ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Course', 'Year Level', 'Section', 'Status', 'Date Joined'));

// Fetch the members
$sql = "SELECT student_id, first_name, last_name, Email, phone, course, year_level, section, status, created_at 
        FROM user 
        WHERE OrgId = $orgId AND Role = 'student' 
        ORDER BY last_name ASC, first_name ASC";
        
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}

// Log the action
logAudit($conn, 'Exported Members List', 'organization', $orgId, 'success', ['filename' => $filename]);

fclose($output);
exit;
