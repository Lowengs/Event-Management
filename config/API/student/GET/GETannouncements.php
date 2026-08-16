<?php
/**
 * Student API: GET Announcements
 * Endpoint: /config/API/endpoints/index.php?action=get_student_announcements
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

$studentId = (int)($_SESSION['student_id'] ?? 0);
$studentOrgId = 0;

if ($studentId) {
    $uRow = $conn->query("SELECT OrgId FROM `user` WHERE UserId = $studentId LIMIT 1");
    if ($uRow && $r = $uRow->fetch_assoc()) $studentOrgId = (int)($r['OrgId'] ?? 0);
}

try {
    $announcements = [];
    
    // Direct robust query to announcement table joined with organization
    $query = "
        SELECT 
            a.AnnouncementId,
            a.OrgId,
            a.Title,
            a.Body,
            a.Category,
            a.Audience,
            a.Status,
            a.DatePosted,
            a.ExpirationDate,
            a.CreatedAt,
            COALESCE(o.OrgName, 'Office of Student Affairs (OSA)') AS OrgName,
            o.OrgPicture
        FROM announcement a
        LEFT JOIN organization o ON a.OrgId = o.OrgId
        WHERE LOWER(TRIM(COALESCE(a.Status, 'approved'))) = 'approved'
          AND (a.ExpirationDate IS NULL OR a.ExpirationDate >= CURDATE())
        ORDER BY COALESCE(a.DatePosted, a.CreatedAt) DESC, a.CreatedAt DESC
    ";

    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rawAudience = strtolower(trim($row['Audience'] ?? ''));
            $annOrgId = !empty($row['OrgId']) ? (int)$row['OrgId'] : 0;

            // Filter by Audience eligibility:
            // If audience is for members of a specific org, check if student belongs to that org
            if (in_array($rawAudience, ['by_org', 'all members', 'members', 'organization members']) && $annOrgId > 0) {
                if ($studentOrgId > 0 && $studentOrgId !== $annOrgId) {
                    continue; // Not for this student's organization
                }
            }

            // Map friendly audience label
            if (in_array($rawAudience, ['by_org', 'all members', 'members'])) {
                $row['AudienceLabel'] = ($row['OrgName'] !== 'Office of Student Affairs (OSA)' ? $row['OrgName'] . ' ' : '') . 'Members';
            } elseif (in_array($rawAudience, ['all_org', 'all organizations'])) {
                $row['AudienceLabel'] = 'All Organizations';
            } elseif (in_array($rawAudience, ['students', 'all', 'all students']) || empty($rawAudience)) {
                $row['AudienceLabel'] = 'All Students';
            } else {
                $row['AudienceLabel'] = ucwords($row['Audience']);
            }

            $announcements[] = $row;
        }
    }

    echo json_encode(['success' => true, 'data' => $announcements]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
if ($isDirectApiCall) exit;
?>
