<?php
/**
 * OSA API: GET Reports Engine
 * Supports 7 Report Categories: Event, Student, Organization, Announcement, Attendance, Financial, Audit
 * Supports Date Range (from_date, to_date), Organization filtering, Search, Status, and Export metrics.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) header('Content-Type: application/json');

$reportType = strtolower(trim($_GET['category'] ?? $_GET['report_type'] ?? $_GET['type'] ?? 'event'));
$orgFilter  = (int)($_GET['org'] ?? 0);
$search     = trim($_GET['search'] ?? '');
$status     = trim($_GET['status'] ?? '');
$fromDate   = trim($_GET['from_date'] ?? '');
$toDate     = trim($_GET['to_date'] ?? '');

// Global Summary Metrics (Top 6 cards)
$summary = [
    'total_events'        => 0,
    'total_organizations' => 0,
    'total_students'      => 0,
    'total_participants'  => 0,
    'completed_events'    => 0,
    'cancelled_events'    => 0
];

// 1. Total Events, Completed, Cancelled
$qEv = $conn->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(EventStatus) = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN LOWER(EventStatus) IN ('cancelled', 'canceled') THEN 1 ELSE 0 END) AS cancelled
    FROM event
");
if ($qEv && $r = $qEv->fetch_assoc()) {
    $summary['total_events']     = (int)($r['total'] ?? 0);
    $summary['completed_events'] = (int)($r['completed'] ?? 0);
    $summary['cancelled_events'] = (int)($r['cancelled'] ?? 0);
}

// 2. Total Organizations
$qOrg = $conn->query("SELECT COUNT(*) AS total FROM organization");
if ($qOrg && $r = $qOrg->fetch_assoc()) {
    $summary['total_organizations'] = (int)($r['total'] ?? 0);
}

// 3. Total Students
$qStu = $conn->query("SELECT COUNT(*) AS total FROM `user` WHERE Role = 'student' OR Role IS NULL");
if ($qStu && $r = $qStu->fetch_assoc()) {
    $summary['total_students'] = (int)($r['total'] ?? 0);
}

// 4. Total Participants (Distinct attendances across events)
$qAtt = $conn->query("SELECT COUNT(DISTINCT UserId, EventId) AS total FROM attendance");
if ($qAtt && $r = $qAtt->fetch_assoc()) {
    $summary['total_participants'] = (int)($r['total'] ?? 0);
}

// Organizations list for dropdown
$orgs = [];
$or = $conn->query('SELECT OrgId, OrgName FROM organization ORDER BY OrgName');
if ($or) while ($row = $or->fetch_assoc()) $orgs[] = $row;

// Event Stats breakdown (for legacy compatibility)
$stats = ['scheduled' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0, 'delayed' => 0];
$sr = $conn->query("SELECT LOWER(EventStatus) status, COUNT(*) total FROM event GROUP BY LOWER(EventStatus)");
if ($sr) while ($row = $sr->fetch_assoc()) {
    $key = $row['status'];
    if (isset($stats[$key])) $stats[$key] = (int)$row['total'];
    if ($key === 'cancelled') $stats['cancelled'] = (int)$row['total'];
}

// Data container for active report category
$reportData = [];

// Helper date range condition
$dateCond = function($field) use ($fromDate, $toDate, $conn) {
    $sql = '';
    if (!empty($fromDate)) {
        $fromEsc = $conn->real_escape_string($fromDate);
        $sql .= " AND DATE($field) >= '$fromEsc'";
    }
    if (!empty($toDate)) {
        $toEsc = $conn->real_escape_string($toDate);
        $sql .= " AND DATE($field) <= '$toEsc'";
    }
    return $sql;
};

// ── 1. EVENT REPORTS ───────────────────────────────────────────────
$eventsByOrg = [];
$officersByOrg = [];
$docsByEvent = [];

if ($reportType === 'event' || $reportType === 'events') {
    $where = ['1=1'];
    if ($orgFilter > 0) $where[] = 'e.OrgId = ' . $orgFilter;
    if ($status !== '') $where[] = "e.EventStatus = '" . $conn->real_escape_string($status) . "'";
    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(e.EventName LIKE '%$s%' OR o.OrgName LIKE '%$s%' OR e.EventLocation LIKE '%$s%')";
    }
    $whereSql = implode(' AND ', $where) . $dateCond('e.EventDateTime');

    $hasColumn = ($c = $conn->query("SHOW COLUMNS FROM event LIKE 'NoFinancialReport'")) && $c->num_rows > 0;
    $noFinance = $hasColumn ? 'e.NoFinancialReport' : '0';

    $q = $conn->query("
        SELECT e.*, o.OrgName, $noFinance AS no_financial_report,
            (SELECT COUNT(*) FROM eventregistration r WHERE r.EventId = e.EventId) registered,
            (SELECT COUNT(DISTINCT a.UserId) FROM attendance a WHERE a.EventId = e.EventId) attended
        FROM event e 
        LEFT JOIN organization o ON o.OrgId = e.OrgId 
        WHERE $whereSql 
        ORDER BY e.EventDateTime DESC
    ");
    if ($q) while ($row = $q->fetch_assoc()) {
        $reportData[] = $row;
        $eventsByOrg[$row['OrgName'] ?? 'Unassigned'][] = $row;
    }

    $officersRes = $conn->query("SELECT u.OrgId, o.OrgName, CONCAT(u.first_name, ' ', u.last_name, ' (', COALESCE(u.officer_role, u.Position, 'Officer'), ')') AS officer_label FROM user u JOIN organization o ON o.OrgId = u.OrgId WHERE u.is_officer = 1");
    if ($officersRes) while ($row = $officersRes->fetch_assoc()) {
        $officersByOrg[$row['OrgName']][] = $row['officer_label'];
    }

    $docs = $conn->query("SELECT d.*, e.EventName FROM org_documents d LEFT JOIN event e ON e.EventId = d.EventId WHERE d.EventId IS NOT NULL AND d.EventId > 0 ORDER BY d.UploadedAt DESC");
    if ($docs) while ($doc = $docs->fetch_assoc()) {
        $eventId = (int)($doc['EventId'] ?? 0);
        if ($eventId <= 0) continue;
        $cleanType = strtolower($doc['DocType'] ?? '');
        $cleanTitle = strtolower($doc['Title'] ?? '');
        if ((strpos($cleanType, 'post') !== false || strpos($cleanTitle, 'post') !== false) && !isset($docsByEvent[$eventId]['postactivityreport'])) {
            $docsByEvent[$eventId]['postactivityreport'] = $doc;
        }
        if ((strpos($cleanType, 'finan') !== false || strpos($cleanTitle, 'finan') !== false) && !isset($docsByEvent[$eventId]['financialreport'])) {
            $docsByEvent[$eventId]['financialreport'] = $doc;
        }
    }
}

// ── 2. STUDENT REPORTS ─────────────────────────────────────────────
elseif ($reportType === 'student' || $reportType === 'students') {
    $where = ["(u.Role = 'student' OR u.Role IS NULL)"];
    if ($orgFilter > 0) $where[] = 'u.OrgId = ' . $orgFilter;
    if ($status !== '') {
        $stEsc = $conn->real_escape_string($status);
        if ($stEsc === 'active') $where[] = "LOWER(u.status) = 'active' AND LOWER(COALESCE(u.verification_status, '')) IN ('ai_verified', 'approved', 'verified')";
        elseif ($stEsc === 'pending') $where[] = "(LOWER(u.status) = 'pending' OR LOWER(COALESCE(u.verification_status, '')) IN ('pending', 'needs_org_review'))";
        else $where[] = "LOWER(u.status) = '$stEsc'";
    }
    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR u.student_id LIKE '%$s%' OR u.Email LIKE '%$s%' OR u.course LIKE '%$s%')";
    }
    $whereSql = implode(' AND ', $where) . $dateCond('u.created_at');

    $q = $conn->query("
        SELECT u.UserId, u.student_id, u.first_name, u.middle_name, u.last_name, u.Email, u.phone, 
               u.course, u.year_level, u.section, u.status, u.verification_status, u.ai_verification_score, 
               u.created_at, o.OrgName
        FROM `user` u
        LEFT JOIN organization o ON o.OrgId = u.OrgId
        WHERE $whereSql
        ORDER BY u.created_at DESC
    ");
    if ($q) while ($row = $q->fetch_assoc()) $reportData[] = $row;
}

// ── 3. ORGANIZATION REPORTS ────────────────────────────────────────
elseif ($reportType === 'organization' || $reportType === 'organizations') {
    $where = ['1=1'];
    if ($orgFilter > 0) $where[] = 'o.OrgId = ' . $orgFilter;
    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(o.OrgName LIKE '%$s%' OR o.OrgType LIKE '%$s%')";
    }
    $whereSql = implode(' AND ', $where);

    $q = $conn->query("
        SELECT o.*,
            (SELECT COUNT(*) FROM `user` u WHERE u.OrgId = o.OrgId AND (u.Role = 'student' OR u.Role IS NULL)) AS total_members,
            (SELECT COUNT(*) FROM `user` u WHERE u.OrgId = o.OrgId AND u.is_officer = 1) AS total_officers,
            (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId) AS total_events,
            (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId AND LOWER(e.EventStatus) = 'completed') AS completed_events
        FROM organization o
        WHERE $whereSql
        ORDER BY o.OrgName ASC
    ");
    if ($q) while ($row = $q->fetch_assoc()) $reportData[] = $row;
}

// ── 4. ANNOUNCEMENT REPORTS ────────────────────────────────────────
elseif ($reportType === 'announcement' || $reportType === 'announcements') {
    $where = ['1=1'];
    if ($orgFilter > 0) $where[] = 'a.OrgId = ' . $orgFilter;
    if ($status !== '') $where[] = "LOWER(a.Status) = '" . strtolower($conn->real_escape_string($status)) . "'";
    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(a.Title LIKE '%$s%' OR a.Content LIKE '%$s%' OR o.OrgName LIKE '%$s%')";
    }
    $dateCol = 'a.DatePosted';
    $hasDateCol = ($c = $conn->query("SHOW COLUMNS FROM announcement LIKE 'DatePosted'")) && $c->num_rows > 0;
    if (!$hasDateCol) $dateCol = 'a.created_at';
    $whereSql = implode(' AND ', $where) . $dateCond($dateCol);

    $q = $conn->query("
        SELECT a.*, o.OrgName
        FROM announcement a
        LEFT JOIN organization o ON o.OrgId = a.OrgId
        WHERE $whereSql
        ORDER BY $dateCol DESC
    ");
    if ($q) while ($row = $q->fetch_assoc()) $reportData[] = $row;
}

// ── 5. ATTENDANCE REPORTS ──────────────────────────────────────────
elseif ($reportType === 'attendance') {
    $where = ['1=1'];
    if ($orgFilter > 0) $where[] = 'e.OrgId = ' . $orgFilter;
    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(e.EventName LIKE '%$s%' OR u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR u.student_id LIKE '%$s%')";
    }
    $whereSql = implode(' AND ', $where) . $dateCond('a.CheckInTime');

    $q = $conn->query("
        SELECT a.*, e.EventName, e.EventDateTime, o.OrgName,
               u.first_name, u.last_name, u.student_id, u.course, u.year_level, u.section
        FROM attendance a
        JOIN event e ON e.EventId = a.EventId
        LEFT JOIN organization o ON o.OrgId = e.OrgId
        JOIN `user` u ON u.UserId = a.UserId
        WHERE $whereSql
        ORDER BY a.CheckInTime DESC
        LIMIT 500
    ");
    if ($q) while ($row = $q->fetch_assoc()) $reportData[] = $row;
}

// ── 6. FINANCIAL REPORTS ───────────────────────────────────────────
elseif ($reportType === 'financial') {
    $where = ['1=1'];
    if ($orgFilter > 0) $where[] = 'e.OrgId = ' . $orgFilter;
    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(e.EventName LIKE '%$s%' OR o.OrgName LIKE '%$s%')";
    }
    $whereSql = implode(' AND ', $where) . $dateCond('e.EventDateTime');

    $q = $conn->query("
        SELECT e.EventId, e.EventName, e.EventDateTime, e.EventStatus, o.OrgName,
               (SELECT d.FilePath FROM org_documents d WHERE d.EventId = e.EventId AND (LOWER(d.DocType) LIKE '%financial%' OR LOWER(d.Title) LIKE '%financial%') LIMIT 1) AS FinancialDocPath,
               (SELECT d.Title FROM org_documents d WHERE d.EventId = e.EventId AND (LOWER(d.DocType) LIKE '%financial%' OR LOWER(d.Title) LIKE '%financial%') LIMIT 1) AS FinancialDocTitle,
               (SELECT d.UploadedAt FROM org_documents d WHERE d.EventId = e.EventId AND (LOWER(d.DocType) LIKE '%financial%' OR LOWER(d.Title) LIKE '%financial%') LIMIT 1) AS FinancialUploadedAt
        FROM event e
        LEFT JOIN organization o ON o.OrgId = e.OrgId
        WHERE $whereSql
        ORDER BY e.EventDateTime DESC
    ");
    if ($q) while ($row = $q->fetch_assoc()) $reportData[] = $row;
}

// ── 7. AUDIT REPORTS ───────────────────────────────────────────────
elseif ($reportType === 'audit') {
    $where = ['1=1'];
    if ($search !== '') {
        $s = $conn->real_escape_string($search);
        $where[] = "(al.Action LIKE '%$s%' OR al.ActorName LIKE '%$s%' OR al.IpAddress LIKE '%$s%' OR al.Details LIKE '%$s%')";
    }
    $whereSql = implode(' AND ', $where) . $dateCond('al.Date');

    $q = $conn->query("
        SELECT al.*
        FROM auditlog al
        WHERE $whereSql
        ORDER BY al.Date DESC
        LIMIT 500
    ");
    if ($q) while ($row = $q->fetch_assoc()) {
        $detailsArr = json_decode($row['Details'] ?? '', true) ?: [];
        $row['module'] = $detailsArr['module'] ?? ($row['ActorType'] === 'student' ? 'Student' : ($row['ActorType'] === 'organization' ? 'Organization' : 'System Admin'));
        $row['description'] = $detailsArr['description'] ?? $row['Action'];
        $reportData[] = $row;
    }
}

// Output standardized JSON
echo json_encode([
    'success'            => true,
    'report_type'        => $reportType,
    'from_date'          => $fromDate,
    'to_date'            => $toDate,
    'summary'            => $summary,
    'stats'              => $stats,
    'orgs'               => $orgs,
    'data'               => $reportData,
    // Legacy fields for backward compatibility
    'events_by_org'      => $eventsByOrg,
    'officers_by_org'    => $officersByOrg,
    'all_docs_by_event'  => $docsByEvent
]);
if ($isDirectApiCall) exit;
