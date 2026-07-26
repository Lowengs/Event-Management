<?php
/** get_certificate_templates.php — GET list of org's templates */
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

$orgId = (int)$_SESSION['org_id'];
$r = $conn->query("
    SELECT t.TemplateId, t.TemplateName, t.TemplateImage, t.FieldConfig, t.CreatedAt, t.EventId, e.EventName
    FROM certificate_templates t
    LEFT JOIN event e ON e.EventId = t.EventId
    WHERE t.OrgId=$orgId AND t.IsDeleted=0 
    ORDER BY t.CreatedAt DESC
");
$templates = [];
if ($r) while ($row = $r->fetch_assoc()) {
    $row['FieldConfig'] = json_decode($row['FieldConfig'], true);
    $templates[] = $row;
}
echo json_encode(['success'=>true,'templates'=>$templates]);
