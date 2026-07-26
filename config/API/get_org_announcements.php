<?php
/** get_org_announcements.php */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
$orgId = (int)$_SESSION['org_id'];

$globalWhere = "(OrgId IS NULL AND Audience IN ('all_org', 'students', 'all'))";
$orgWhere = "(OrgId = $orgId OR (Audience = 'by_org' AND OrgId = $orgId))";
$visibilityWhere = "($orgWhere OR $globalWhere)";

$total    = (int)$conn->query("SELECT COUNT(*) FROM announcement WHERE $visibilityWhere")->fetch_row()[0];
$approved = (int)$conn->query("SELECT COUNT(*) FROM announcement WHERE $visibilityWhere AND Status='approved'")->fetch_row()[0];
$pending  = (int)$conn->query("SELECT COUNT(*) FROM announcement WHERE $visibilityWhere AND Status='pending'")->fetch_row()[0];
$draft    = (int)$conn->query("SELECT COUNT(*) FROM announcement WHERE $visibilityWhere AND Status='draft'")->fetch_row()[0];

$items = [];
$r = $conn->query("SELECT * FROM announcement WHERE $visibilityWhere ORDER BY CreatedAt DESC");
if ($r) {
	while ($row = $r->fetch_assoc()) {
		$audienceCode = strtolower(trim($row['Audience'] ?? ''));
		if ($audienceCode === 'by_org') {
			$row['AudienceLabel'] = 'By Organization';
		} elseif ($audienceCode === 'all_org') {
			$row['AudienceLabel'] = 'All Organizations';
		} elseif ($audienceCode === 'students') {
			$row['AudienceLabel'] = 'Students';
		} elseif ($audienceCode === 'all') {
			$row['AudienceLabel'] = 'All';
		} else {
			$row['AudienceLabel'] = $row['Audience'] ?? 'All Members';
		}

		$items[] = $row;
	}
}

echo json_encode(['success'=>true,'stats'=>compact('total','approved','pending','draft'),'announcements'=>$items]);
