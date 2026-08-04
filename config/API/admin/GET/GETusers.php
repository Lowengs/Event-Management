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

$activeTab   = $_GET['tab'] ?? 'students';
$search      = trim($_GET['q'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = max(1, (int)($_GET['per_page'] ?? 15));
$offset      = ($page - 1) * $perPage;
$searchParam = "%$search%";

$users = [];
$total = 0;

try {
    switch ($activeTab) {
        case 'osa':
            if ($search) {
                $stmtC = $conn->prepare("SELECT COUNT(*) FROM `osa` WHERE Name LIKE ? OR Email LIKE ?");
                $stmtC->bind_param("ss", $searchParam, $searchParam);
                $stmtD = $conn->prepare("SELECT OsaId AS id, Name AS name, Email AS email, 'OSA Staff' AS role, 'active' AS status, '' AS extra FROM `osa` WHERE Name LIKE ? OR Email LIKE ? ORDER BY Name ASC LIMIT ? OFFSET ?");
                $stmtD->bind_param("ssii", $searchParam, $searchParam, $perPage, $offset);
            } else {
                $stmtC = $conn->prepare("SELECT COUNT(*) FROM `osa`");
                $stmtD = $conn->prepare("SELECT OsaId AS id, Name AS name, Email AS email, 'OSA Staff' AS role, 'active' AS status, '' AS extra FROM `osa` ORDER BY Name ASC LIMIT ? OFFSET ?");
                $stmtD->bind_param("ii", $perPage, $offset);
            }
            break;

        case 'organizations':
            if ($search) {
                $stmtC = $conn->prepare("SELECT COUNT(*) FROM `organization` WHERE OrgName LIKE ? OR Username LIKE ?");
                $stmtC->bind_param("ss", $searchParam, $searchParam);
                $stmtD = $conn->prepare("SELECT OrgId AS id, OrgName AS name, Username AS email, 'Organization' AS role, Status AS status, Username AS extra FROM `organization` WHERE OrgName LIKE ? OR Username LIKE ? ORDER BY OrgName ASC LIMIT ? OFFSET ?");
                $stmtD->bind_param("ssii", $searchParam, $searchParam, $perPage, $offset);
            } else {
                $stmtC = $conn->prepare("SELECT COUNT(*) FROM `organization`");
                $stmtD = $conn->prepare("SELECT OrgId AS id, OrgName AS name, Username AS email, 'Organization' AS role, Status AS status, Username AS extra FROM `organization` ORDER BY OrgName ASC LIMIT ? OFFSET ?");
                $stmtD->bind_param("ii", $perPage, $offset);
            }
            break;

        case 'admins':
            if ($search) {
                $stmtC = $conn->prepare("SELECT COUNT(*) FROM `admin` WHERE Name LIKE ? OR Email LIKE ?");
                $stmtC->bind_param("ss", $searchParam, $searchParam);
                $stmtD = $conn->prepare("SELECT AdminId AS id, Name AS name, Email AS email, Role AS role, Status AS status, '' AS extra FROM `admin` WHERE Name LIKE ? OR Email LIKE ? ORDER BY Name ASC LIMIT ? OFFSET ?");
                $stmtD->bind_param("ssii", $searchParam, $searchParam, $perPage, $offset);
            } else {
                $stmtC = $conn->prepare("SELECT COUNT(*) FROM `admin`");
                $stmtD = $conn->prepare("SELECT AdminId AS id, Name AS name, Email AS email, Role AS role, Status AS status, '' AS extra FROM `admin` ORDER BY Name ASC LIMIT ? OFFSET ?");
                $stmtD->bind_param("ii", $perPage, $offset);
            }
            break;

        default:
            $activeTab = 'students';
            if ($search) {
                $stmtC = $conn->prepare("SELECT COUNT(*) FROM `user` WHERE (first_name LIKE ? OR last_name LIKE ? OR Email LIKE ? OR student_id LIKE ?)");
                $stmtC->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchParam);
                $stmtD = $conn->prepare("SELECT UserId AS id, COALESCE(NULLIF(TRIM(CONCAT(first_name,' ',last_name)), ''), 'Student') AS name, Email AS email, 'Student' AS role, COALESCE(status, 'active') AS status, course AS course, student_id AS student_id, year_level AS year_level, section AS section FROM `user` WHERE (first_name LIKE ? OR last_name LIKE ? OR Email LIKE ? OR student_id LIKE ?) ORDER BY UserId DESC LIMIT ? OFFSET ?");
                $stmtD->bind_param("ssssii", $searchParam, $searchParam, $searchParam, $searchParam, $perPage, $offset);
            } else {
                $stmtC = $conn->prepare("SELECT COUNT(*) FROM `user`");
                $stmtD = $conn->prepare("SELECT UserId AS id, COALESCE(NULLIF(TRIM(CONCAT(first_name,' ',last_name)), ''), 'Student') AS name, Email AS email, 'Student' AS role, COALESCE(status, 'active') AS status, course AS course, student_id AS student_id, year_level AS year_level, section AS section FROM `user` ORDER BY UserId DESC LIMIT ? OFFSET ?");
                $stmtD->bind_param("ii", $perPage, $offset);
            }
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

