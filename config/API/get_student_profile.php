<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$student_id = (int)$_SESSION['student_id'];
$data = ['success' => true, 'profile' => [], 'registrations' => [], 'certificates' => [], 'stats' => []];

if ($conn) {
    $student = $conn->query("
        SELECT u.UserId, u.first_name, u.last_name, u.middle_name,
               u.Email, u.course, u.year_level, u.section, u.student_id,
               u.phone, u.Address, u.profile_photo, u.Position,
               o.OrgName, o.OrgPicture, o.OrgId AS student_orgid
        FROM user u LEFT JOIN organization o ON o.OrgId = u.OrgId
        WHERE u.UserId = $student_id LIMIT 1
    ")->fetch_assoc();

    if ($student) {
        // Fix photo path
        if (!empty($student['profile_photo']) && strpos($student['profile_photo'], 'assets') === 0) {
            $student['profile_photo_url'] = '../../' . $student['profile_photo'];
        } else {
            $student['profile_photo_url'] = $student['profile_photo'] ?? '';
        }
        $data['profile'] = $student;
    }

    $data['stats']['registrations'] = (int)$conn->query("SELECT COUNT(*) FROM eventregistration WHERE UserId = $student_id")->fetch_row()[0];
    $data['stats']['attendance']    = (int)$conn->query("SELECT COUNT(*) FROM attendance WHERE UserId = $student_id")->fetch_row()[0];

    $rq = $conn->query("
        SELECT er.RegistrationId, er.DateIssued,
               e.EventName, e.EventDateTime, e.EventLocation, e.EventStatus, o.OrgName
        FROM eventregistration er
        JOIN event e ON e.EventId = er.EventId
        LEFT JOIN organization o ON o.OrgId = e.OrgId
        WHERE er.UserId = $student_id ORDER BY e.EventDateTime DESC LIMIT 20
    ");
    if ($rq) while ($row = $rq->fetch_assoc()) $data['registrations'][] = $row;

    $cq = $conn->query("
        SELECT
            c.CertId          AS CertificateId,
            c.CertCode,
            c.CertificateURL,
            c.GeneratedImage,
            c.IssuedAt        AS DateIssued,
            c.OrgId,
            e.EventName, e.EventDateTime, e.EventLocation,
            o.OrgName
        FROM certificates c
        JOIN event e ON e.EventId = c.EventId
        LEFT JOIN organization o ON o.OrgId = COALESCE(c.OrgId, e.OrgId)
        WHERE c.UserId = $student_id
        ORDER BY c.IssuedAt DESC
    ");
    if ($cq) while ($row = $cq->fetch_assoc()) $data['certificates'][] = $row;
    $data['stats']['certificates'] = count($data['certificates']);
}

echo json_encode($data);
