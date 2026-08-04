<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
header('Content-Type: application/json');
if (empty($_SESSION['student_id'])) { echo json_encode(['success'=>false,'message'=>'Login required']); exit; }

$studentId = (int)$_SESSION['student_id'];
$requestedPage = (int)($_GET['page'] ?? 1);
$perPage = min(20, max(1, (int)($_GET['per_page'] ?? 3)));
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$where = "er.UserId = $studentId";
if ($search !== '') { $like = $conn->real_escape_string('%'.$search.'%'); $where .= " AND (e.EventName LIKE '$like' OR o.OrgName LIKE '$like')"; }
if ($status !== '') { $safeStatus = $conn->real_escape_string(strtolower($status)); $where .= " AND LOWER(COALESCE(e.EventStatus, '')) = '$safeStatus'"; }

$countResult = $conn->query("SELECT COUNT(*) total FROM eventregistration er JOIN event e ON e.EventId=er.EventId LEFT JOIN organization o ON o.OrgId=e.OrgId WHERE $where");
$total = (int)($countResult->fetch_assoc()['total'] ?? 0);

$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, min($requestedPage, $pages));
$offset = max(0, ($page - 1) * $perPage);

$sql = "SELECT er.RegistrationId, e.EventId, e.EventName, e.EventDateTime, e.EventLocation, e.EventStatus, o.OrgName,
        EXISTS(SELECT 1 FROM attendance a WHERE a.EventId=e.EventId AND a.UserId=er.UserId) AS has_checkin,
        (EXISTS(SELECT 1 FROM event_pretest pt WHERE pt.EventId=e.EventId AND pt.UserId=er.UserId) OR EXISTS(SELECT 1 FROM preposttest ppt WHERE ppt.EventId=e.EventId AND ppt.StudentId=er.UserId AND LOWER(ppt.TestType)='pre') OR EXISTS(SELECT 1 FROM assessment_responses ar JOIN assessments a ON a.assessment_id=ar.assessment_id WHERE a.event_id=e.EventId AND (LOWER(COALESCE(a.type, a.test_type, '')) LIKE '%pre%') AND ar.user_id=er.UserId)) AS pre_taken,
        (EXISTS(SELECT 1 FROM event_posttest pt WHERE pt.EventId=e.EventId AND pt.UserId=er.UserId) OR EXISTS(SELECT 1 FROM preposttest ppt WHERE ppt.EventId=e.EventId AND ppt.StudentId=er.UserId AND LOWER(ppt.TestType)='post') OR EXISTS(SELECT 1 FROM assessment_responses ar JOIN assessments a ON a.assessment_id=ar.assessment_id WHERE a.event_id=e.EventId AND (LOWER(COALESCE(a.type, a.test_type, '')) LIKE '%post%') AND ar.user_id=er.UserId)) AS post_taken,
        (EXISTS(SELECT 1 FROM event_pretest WHERE EventId=e.EventId) OR EXISTS(SELECT 1 FROM preposttest WHERE EventId=e.EventId AND LOWER(TestType)='pre') OR EXISTS(SELECT 1 FROM assessments s WHERE s.event_id=e.EventId AND (LOWER(COALESCE(s.type, s.test_type, '')) LIKE '%pre%'))) AS pre_created,
        (EXISTS(SELECT 1 FROM event_posttest WHERE EventId=e.EventId) OR EXISTS(SELECT 1 FROM preposttest WHERE EventId=e.EventId AND LOWER(TestType)='post') OR EXISTS(SELECT 1 FROM assessments s WHERE s.event_id=e.EventId AND (LOWER(COALESCE(s.type, s.test_type, '')) LIKE '%post%'))) AS post_created
        FROM eventregistration er JOIN event e ON e.EventId=er.EventId LEFT JOIN organization o ON o.OrgId=e.OrgId
        WHERE $where
        ORDER BY CASE LOWER(COALESCE(e.EventStatus,'')) WHEN 'ongoing' THEN 1 WHEN 'scheduled' THEN 2 WHEN 'upcoming' THEN 3 WHEN 'completed' THEN 9 ELSE 5 END, e.EventDateTime ASC
        LIMIT $perPage OFFSET $offset";
$rows=[]; $res=$conn->query($sql); while ($res && ($row=$res->fetch_assoc())) $rows[]=$row;
echo json_encode(['success'=>true,'registrations'=>$rows,'page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>$pages]);
?>
