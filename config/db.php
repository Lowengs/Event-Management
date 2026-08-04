<?php
/**
 * db.php — Central database connection with graceful error handling.
 * All pages in the system include this file. If the DB is unreachable,
 * a user-friendly error page is shown instead of a raw PHP fatal error.
 */

date_default_timezone_set('Asia/Manila');

$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "naap_org_system";

// Suppress the default warning; we handle errors ourselves.
mysqli_report(MYSQLI_REPORT_OFF);

$conn = @mysqli_connect($host, $user, $pass, $dbname);

if (!$conn || mysqli_connect_errno()) {
    $errMsg = mysqli_connect_error() ?: 'Unknown connection error';
    _db_error_page($errMsg);
    exit;
}

// Set charset for proper UTF-8 support
mysqli_set_charset($conn, 'utf8mb4');

/**
 * Renders a graceful "database unavailable" error page and exits.
 */
function _db_error_page(string $detail = ''): void {
    // Detect development environment (localhost / 127.0.0.1 / CLI)
    $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
    $isDev = php_sapi_name() === 'cli'
          || isset($_GET['debug'])
          || in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)
          || strpos($serverName, 'localhost') === 0;

    // Only treat as API/JSON if the request URL itself hits the API endpoint,
    // NOT if a page (e.g. messages.php) set $_GET['action'] for internal use.
    $uri    = $_SERVER['REQUEST_URI'] ?? '';
    $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $isDirectApiHit = strpos($uri, '/API/') !== false
                   || strpos($uri, '/api/') !== false
                   || (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT)
                   || strpos(basename($scriptFile), 'index.php') !== false && strpos($scriptFile, 'endpoints') !== false;
    $isJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

    if ($isDirectApiHit || $isJson) {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode([
            'success'  => false,
            'message'  => 'Database is currently unavailable. Please try again later.',
            'db_error' => $isDev ? $detail : null,
        ]);
        return;
    }

    // HTML error page for browser requests
    http_response_code(503);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Service Unavailable — NAAP System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      color: #e2e8f0;
    }
    .card {
      background: rgba(255,255,255,0.06);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 24px;
      padding: 48px 40px;
      max-width: 480px;
      width: 100%;
      text-align: center;
      box-shadow: 0 25px 50px rgba(0,0,0,0.4);
      animation: fadeUp .4s ease;
    }
    @keyframes fadeUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
    .icon {
      width: 80px; height: 80px;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 24px;
      font-size: 36px;
      box-shadow: 0 8px 24px rgba(239,68,68,0.35);
    }
    h1 { font-size: 22px; font-weight: 800; color: #f1f5f9; margin-bottom: 10px; }
    p  { font-size: 14px; color: #94a3b8; line-height: 1.65; margin-bottom: 8px; }
    .badge {
      display: inline-block;
      background: rgba(239,68,68,0.15);
      border: 1px solid rgba(239,68,68,0.3);
      color: #fca5a5;
      padding: 4px 12px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .5px;
      margin-bottom: 24px;
    }
    .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 28px; }
    .btn {
      padding: 11px 22px;
      border-radius: 12px;
      border: none;
      font-size: 13px;
      font-weight: 700;
      font-family: inherit;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all .2s;
    }
    .btn-primary { background: linear-gradient(135deg,#6366f1,#8b5cf6); color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,.35); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,.45); }
    .btn-ghost  { background: rgba(255,255,255,0.08); color: #cbd5e1; border: 1px solid rgba(255,255,255,0.12); }
    .btn-ghost:hover { background: rgba(255,255,255,0.14); }
    .divider { height: 1px; background: rgba(255,255,255,0.08); margin: 24px 0; }
    .code { font-family: monospace; font-size: 12px; color: #475569; background: rgba(0,0,0,.3); border-radius: 8px; padding: 10px 14px; text-align: left; word-break: break-all; margin-top: 16px; }
    .retry-msg { font-size: 12px; color: #64748b; margin-top: 14px; }
    #countdown { color: #6366f1; font-weight: 700; }
  </style>
</head>
<body>
<div class="card">
  <div class="icon" style="font-size:36px;line-height:1;">!</div>
  <div class="badge">503 Service Unavailable</div>
  <h1>Database Unavailable</h1>
  <p>The system cannot connect to the database right now. This is usually a temporary issue.</p>
  <p>Automatically retrying in <span id="countdown">10</span> seconds…</p>

  <?php if (!empty($detail) && $isDev): ?>
  <div class="code"><?= htmlspecialchars($detail) ?></div>
  <?php endif; ?>

  <div class="divider"></div>

  <div class="actions">
    <button class="btn btn-primary" onclick="location.reload()">Retry Now</button>
    <a class="btn btn-ghost" href="javascript:history.back()">← Go Back</a>
  </div>

  <p class="retry-msg">If this problem persists, please contact your system administrator.</p>
</div>

<script>
let s = 10;
const el = document.getElementById('countdown');
const t = setInterval(() => {
  s--;
  if (el) el.textContent = s;
  if (s <= 0) { clearInterval(t); location.reload(); }
}, 1000);
</script>
</body>
</html>
<?php
}