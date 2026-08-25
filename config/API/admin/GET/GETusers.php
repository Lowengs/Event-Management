<?php
/**
 * Admin API: GET System Users
 * Endpoint: /config/API/endpoints/index.php?action=get_admin_users
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['osa_id'])) {
    echo json_encode(['success' => false, 'message' => 'Admin login required']);
if ($isDirectApiCall) exit;
    return;
}

$activeTab    = $_GET['tab'] ?? 'students';
$search       = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$verifFilter  = trim($_GET['verif_status'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = max(1, (int)($_GET['per_page'] ?? 15));
$offset       = ($page - 1) * $perPage;
$searchParam  = "%$search%";

$users = [];
$total = 0;

try {
    switch ($activeTab) {
        case 'osa':
            $where = ["1=1"];
            $params = [];
            $types = "";
            if ($search !== '') {
                $where[] = "(Name LIKE ? OR Email LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= "ss";
            }
            if ($statusFilter !== '' && $statusFilter !== 'all') {
                if (strtolower($statusFilter) !== 'active') {
                    $where[] = "1=0";
                }
            }
            $whereSql = implode(" AND ", $where);
            $stmtC = $conn->prepare("SELECT COUNT(*) FROM `osa` WHERE $whereSql");
            if (!empty($params)) $stmtC->bind_param($types, ...$params);
            
            $stmtD = $conn->prepare("SELECT OsaId AS id, OsaId, Name AS name, Email AS email, 'OSA Staff' AS role, 'active' AS status, '' AS extra FROM `osa` WHERE $whereSql ORDER BY Name ASC LIMIT ? OFFSET ?");
            $typesD = $types . "ii";
            $paramsD = array_merge($params, [$perPage, $offset]);
            $stmtD->bind_param($typesD, ...$paramsD);
            break;

        case 'organizations':
            $where = ["1=1"];
            $params = [];
            $types = "";
            if ($search !== '') {
                $where[] = "(OrgName LIKE ? OR username LIKE ? OR email LIKE ? OR Adviser LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= "ssss";
            }
            if ($statusFilter !== '' && $statusFilter !== 'all') {
                $where[] = "LOWER(Status) = ?";
                $params[] = strtolower($statusFilter);
                $types .= "s";
            }
            $whereSql = implode(" AND ", $where);
            $stmtC = $conn->prepare("SELECT COUNT(*) FROM `organization` WHERE $whereSql");
            if (!empty($params)) $stmtC->bind_param($types, ...$params);

            $stmtD = $conn->prepare("SELECT o.*, o.OrgId AS id, o.OrgName AS name, o.OrgName, o.username, o.email, 'Organization' AS role, COALESCE(o.Status, 'Active') AS status, o.Description, o.Adviser, o.DateRegistered, o.OrgPicture, o.username AS extra,
                (SELECT COUNT(*) FROM `event` e WHERE e.OrgId = o.OrgId) AS total_events,
                (SELECT COUNT(*) FROM `event` e WHERE e.OrgId = o.OrgId AND (LOWER(e.EventStatus) = 'completed' OR LOWER(e.EventStatus) = 'approved' OR LOWER(e.EventStatus) = 'ongoing')) AS approved_events,
                (SELECT COUNT(*) FROM `event` e WHERE e.OrgId = o.OrgId AND (LOWER(e.EventStatus) = 'pending' OR LOWER(e.EventStatus) = 'for_approval' OR LOWER(e.EventStatus) LIKE '%pending%')) AS pending_proposals
                FROM `organization` o WHERE $whereSql ORDER BY o.OrgName ASC LIMIT ? OFFSET ?");
            $typesD = $types . "ii";
            $paramsD = array_merge($params, [$perPage, $offset]);
            $stmtD->bind_param($typesD, ...$paramsD);
            break;

        default:
            $activeTab = 'students';
            $where = ["1=1"];
            $params = [];
            $types = "";
            if ($search !== '') {
                $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.Email LIKE ? OR u.student_id LIKE ? OR u.username LIKE ?)";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= "sssss";
            }
            if ($statusFilter !== '' && $statusFilter !== 'all') {
                $where[] = "LOWER(u.status) = ?";
                $params[] = strtolower($statusFilter);
                $types .= "s";
            }
            if ($verifFilter !== '' && $verifFilter !== 'all') {
                $vf = strtolower($verifFilter);
                if ($vf === 'verified' || $vf === 'approved') {
                    $where[] = "(LOWER(u.verification_status) = 'approved' OR LOWER(u.verification_status) = 'ai_verified')";
                } elseif ($vf === 'pending') {
                    $where[] = "(u.verification_status IS NULL OR LOWER(u.verification_status) = 'pending' OR LOWER(u.verification_status) = 'needs_org_review' OR u.verification_status = '')";
                } elseif ($vf === 'rejected') {
                    $where[] = "LOWER(u.verification_status) = 'rejected'";
                }
            }
            $whereSql = implode(" AND ", $where);
            $stmtC = $conn->prepare("SELECT COUNT(*) FROM `user` u LEFT JOIN organization o ON o.OrgId = u.OrgId WHERE $whereSql");
            if (!empty($params)) $stmtC->bind_param($types, ...$params);

            $stmtD = $conn->prepare("SELECT u.*, u.UserId AS id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), ''), u.username, 'Student') AS name, u.Email AS email, 'Student' AS role, COALESCE(u.status, 'active') AS status, o.OrgName, u.Email AS extra FROM `user` u LEFT JOIN organization o ON o.OrgId = u.OrgId WHERE $whereSql ORDER BY u.UserId DESC LIMIT ? OFFSET ?");
            $typesD = $types . "ii";
            $paramsD = array_merge($params, [$perPage, $offset]);
            $stmtD->bind_param($typesD, ...$paramsD);
            break;
    }

    $stmtC->execute();
    $total = (int)($stmtC->get_result()->fetch_row()[0] ?? 0);
    $stmtC->close();

    $stmtD->execute();
    $res = $stmtD->get_result();
    if ($res) while ($row = $res->fetch_assoc()) $users[] = $row;
    $stmtD->close();

    echo json_encode([
        'success'    => true,
        'active_tab' => $activeTab,
        'users'      => $users,
        'total'      => $total,
        'page'       => $page,
        'per_page'   => $perPage
    ]);
    if ($isDirectApiCall) exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    if ($isDirectApiCall) exit;
}
?>

