<?php
/**
 * Fix all database stored procedures and create missing API files.
 */
require_once __DIR__ . '/db.php';

$fixes = [];

// ═══════════════════════════════════════════════════════════════
// 1. Fix sp_GetStudentInfo — wrong table name 'user' vs correct table,
//    wrong column 'StudentId' vs 'student_id',
//    and should also search by UserId
// ═══════════════════════════════════════════════════════════════
$sql = "DROP PROCEDURE IF EXISTS sp_GetStudentInfo";
$conn->query($sql);

$sql = "
CREATE PROCEDURE sp_GetStudentInfo(IN p_StudentId VARCHAR(100), IN p_EventId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id, 
           u.course, u.year_level, u.section, u.profile_photo, u.phone,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = p_EventId AND er.UserId = u.UserId), 0) AS is_registered,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId AND a.LogType = 'Log In'), 0) AS has_logged_in,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId AND a.LogType = 'Log Out'), 0) AS has_logged_out,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId), 0) AS has_attended
    FROM `user` u 
    WHERE u.student_id = p_StudentId 
       OR u.UserId = CAST(p_StudentId AS UNSIGNED)
       OR u.Email = p_StudentId
    LIMIT 1;
END
";
if ($conn->query($sql)) {
    $fixes[] = "✅ sp_GetStudentInfo fixed (searches by student_id, UserId, or Email)";
} else {
    $fixes[] = "❌ sp_GetStudentInfo: " . $conn->error;
}

// ═══════════════════════════════════════════════════════════════
// 2. Fix sp_GetOrgMembers — wrong column 'StudentId'
// ═══════════════════════════════════════════════════════════════
$sql = "DROP PROCEDURE IF EXISTS sp_GetOrgMembers";
$conn->query($sql);

$sql = "
CREATE PROCEDURE sp_GetOrgMembers(IN p_OrgId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.student_id, u.Email, 
           u.course, u.year_level, u.section, u.phone, u.profile_photo, 
           u.Position, u.officer_role, u.is_officer, u.status,
           u.verification_status, u.created_at
    FROM `user` u 
    WHERE u.OrgId = p_OrgId 
    ORDER BY u.first_name ASC;
END
";
if ($conn->query($sql)) {
    $fixes[] = "✅ sp_GetOrgMembers fixed (correct column names)";
} else {
    $fixes[] = "❌ sp_GetOrgMembers: " . $conn->error;
}

// ═══════════════════════════════════════════════════════════════
// 3. Fix sp_RecordAttendance — wrong column names
//    Actual columns: ScanType (not AttendanceMethod), 
//    Timestamp (not AttendanceTime), LogType
// ═══════════════════════════════════════════════════════════════
$sql = "DROP PROCEDURE IF EXISTS sp_RecordAttendance";
$conn->query($sql);

$sql = "
CREATE PROCEDURE sp_RecordAttendance(
    IN p_EventId INT,
    IN p_UserId INT,
    IN p_Method VARCHAR(50),
    IN p_Status VARCHAR(50),
    IN p_LogType VARCHAR(20)
)
BEGIN
    INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType)
    VALUES (p_EventId, p_UserId, p_Method, p_Status, NOW(), p_LogType);
END
";
if ($conn->query($sql)) {
    $fixes[] = "✅ sp_RecordAttendance fixed (correct column names: ScanType, Timestamp, LogType)";
} else {
    $fixes[] = "❌ sp_RecordAttendance: " . $conn->error;
}

// ═══════════════════════════════════════════════════════════════
// 4. Fix sp_GetOrgOfficers
// ═══════════════════════════════════════════════════════════════
$sql = "DROP PROCEDURE IF EXISTS sp_GetOrgOfficers";
$conn->query($sql);

$sql = "
CREATE PROCEDURE sp_GetOrgOfficers(IN p_OrgId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id,
           u.course, u.year_level, u.section, u.phone,
           u.Position AS officer_role, u.profile_photo, u.is_officer
    FROM `user` u 
    WHERE u.OrgId = p_OrgId AND (u.is_officer = 1 OR u.Position IS NOT NULL AND u.Position != '')
    ORDER BY u.first_name ASC;
END
";
if ($conn->query($sql)) {
    $fixes[] = "✅ sp_GetOrgOfficers fixed";
} else {
    $fixes[] = "❌ sp_GetOrgOfficers: " . $conn->error;
}

// ═══════════════════════════════════════════════════════════════
// 5. Fix sp_GetOrgAnnouncements
// ═══════════════════════════════════════════════════════════════
$sql = "DROP PROCEDURE IF EXISTS sp_GetOrgAnnouncements";
$conn->query($sql);

$sql = "
CREATE PROCEDURE sp_GetOrgAnnouncements(IN p_OrgId INT)
BEGIN
    SELECT a.*, o.OrgName
    FROM announcement a
    LEFT JOIN organization o ON o.OrgId = a.OrgId
    WHERE a.OrgId = p_OrgId
    ORDER BY a.DatePosted DESC;
END
";
if ($conn->query($sql)) {
    $fixes[] = "✅ sp_GetOrgAnnouncements fixed";
} else {
    $fixes[] = "❌ sp_GetOrgAnnouncements: " . $conn->error;
}

echo "=== Database Fixes ===\n";
foreach ($fixes as $f) echo "$f\n";
echo "\nDone.\n";
