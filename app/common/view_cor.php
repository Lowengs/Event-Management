<?php
/**
 * NAAP System - Centralized COR Document Viewer & Streamer
 * Safely streams Certificate of Registration documents (PDF/images)
 * with correct MIME headers, URL decoding, cross-directory resolution,
 * and friendly preview fallback.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Access control ──────────────────────────────────────────────────
// This viewer used to stream ANY file under the project root to anyone,
// including config files with credentials. Now: staff only, or a student
// viewing their own COR, and only document/image file types.
$isStaff   = !empty($_SESSION['admin_id']) || !empty($_SESSION['admin_logged_in'])
          || !empty($_SESSION['osa_id'])   || !empty($_SESSION['osa_logged_in'])
          || !empty($_SESSION['org_id']);
$studentId = (int)($_SESSION['student_id'] ?? 0);
if (!$isStaff && $studentId <= 0) {
    http_response_code(403);
    exit('Login required to view COR documents.');
}

$rawFile = $_GET['file'] ?? $_GET['path'] ?? '';
$download = !empty($_GET['download']);

// Clean and sanitize file path
$rawFile = trim($rawFile);
$decoded = urldecode($rawFile);

$allowedExt = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
foreach ([$rawFile, $decoded] as $p) {
    if ($p === '') continue;
    // Reject traversal outright (single-pass stripping of '../' is bypassable with '....//')
    if (strpos($p, '..') !== false || strpos($p, "\0") !== false
        || !in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), $allowedExt, true)) {
        http_response_code(400);
        exit('Invalid COR document path.');
    }
}

if (!$isStaff) {
    // Students may only open the COR saved on their own account
    require_once __DIR__ . '/../../config/db.php';
    $own = '';
    if ($st = $conn->prepare("SELECT cor_document FROM `user` WHERE UserId = ? LIMIT 1")) {
        $st->bind_param('i', $studentId);
        $st->execute();
        $own = (string)($st->get_result()->fetch_assoc()['cor_document'] ?? '');
        $st->close();
    }
    if ($own === '' || basename($own) !== basename($decoded)) {
        http_response_code(403);
        exit('You can only view your own COR document.');
    }
}

$baseDir = dirname(__DIR__, 2); // Project root
$foundPath = null;

if (!empty($rawFile)) {
    // 1. Check relative path from root
    $cleanRel = ltrim(str_replace(['../', '..\\'], '', $rawFile), '/\\');
    $cleanDecoded = ltrim(str_replace(['../', '..\\'], '', $decoded), '/\\');
    
    $candidatePaths = [
        $baseDir . '/' . $cleanRel,
        $baseDir . '/' . $cleanDecoded,
        $baseDir . '/assets/uploads/cors/' . basename($cleanRel),
        $baseDir . '/assets/uploads/cors/' . basename($cleanDecoded),
        $baseDir . '/assets/uploads/cor_documents/' . basename($cleanRel),
        $baseDir . '/assets/uploads/cor_documents/' . basename($cleanDecoded),
        $baseDir . '/assets/uploads/' . basename($cleanRel),
        $baseDir . '/assets/uploads/' . basename($cleanDecoded),
    ];

    foreach ($candidatePaths as $candidate) {
        if (!empty($candidate) && file_exists($candidate) && is_file($candidate)) {
            $foundPath = $candidate;
            break;
        }
    }
}

// If file found on disk, stream it with proper headers
if ($foundPath && is_file($foundPath)) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $foundPath) ?: 'application/pdf';
    finfo_close($finfo);

    $ext = strtolower(pathinfo($foundPath, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        $mime = 'application/pdf';
    } elseif (in_array($ext, ['jpg', 'jpeg'], true)) {
        $mime = 'image/jpeg';
    } elseif ($ext === 'png') {
        $mime = 'image/png';
    }

    $fileName = basename($foundPath);

    // Audit every COR access: it is a sensitive personal document
    require_once __DIR__ . '/../../config/db.php';
    if (function_exists('logAudit')) {
        $vRole = !empty($_SESSION['admin_id']) ? 'admin' : (!empty($_SESSION['osa_id']) ? 'osa' : (!empty($_SESSION['org_id']) ? 'organization' : 'student'));
        $vId   = (int)($_SESSION['admin_id'] ?? $_SESSION['osa_id'] ?? $_SESSION['org_id'] ?? $studentId);
        logAudit($conn, $download ? 'Download COR Document' : 'View COR Document', $vRole, $vId ?: null, 'success', [
            'file' => $fileName,
        ]);
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($foundPath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, no-store');

    if ($download) {
        header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    } else {
        header('Content-Disposition: inline; filename="' . addslashes($fileName) . '"');
    }

    readfile($foundPath);
    exit;
}

// Fallback HTML preview when file is not physically found
$displayName = htmlspecialchars(basename($decoded ?: ($rawFile ?: 'COR Document')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COR Document Preview</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        body {
            background-color: #f8fafc;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px 16px;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 28px 24px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #fef3c7;
            color: #d97706;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 26px;
        }
        h2 {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }
        p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 14px;
        }
        .file-badge {
            display: inline-block;
            background: #f1f5f9;
            color: #334155;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            word-break: break-all;
            margin-bottom: 16px;
        }
        .note {
            font-size: 11.5px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
        </div>
        <h2>COR Document File Not Located</h2>
        <p>The Certificate of Registration file could not be retrieved from system storage or is pending re-upload.</p>
        <div class="file-badge"><?= $displayName ?></div>
        <p class="note">If this student recently registered, please ask them to re-upload their COR via their student profile.</p>
    </div>
</body>
</html>
