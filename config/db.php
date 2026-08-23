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
 * Automatically synchronizes event statuses (Scheduled -> Ongoing -> Completed)
 * based on current Asia/Manila date & time.
 */
function autoSyncEventStatuses($conn): void {
    if (!$conn) return;
    try {
        // 1. Any future event (start time is in the future) that is not Cancelled/Delayed must be 'Scheduled'
        $conn->query("
            UPDATE event 
            SET EventStatus = 'Scheduled' 
            WHERE LOWER(TRIM(COALESCE(EventStatus, 'scheduled'))) IN ('ongoing', 'completed')
              AND EventDateTime > NOW()
        ");

        // 2. Active events (start time reached, and event duration has not ended yet) -> 'Ongoing'
        $conn->query("
            UPDATE event 
            SET EventStatus = 'Ongoing' 
            WHERE LOWER(TRIM(COALESCE(EventStatus, 'scheduled'))) IN ('scheduled', 'upcoming')
              AND EventDateTime <= NOW() 
              AND (
                  (EndDateTime IS NOT NULL AND EndDateTime > '2000-01-01' AND EndDateTime >= NOW())
                  OR ((EndDateTime IS NULL OR EndDateTime <= '2000-01-01') AND EventDateTime >= NOW() - INTERVAL 3 HOUR)
              )
        ");

        // 3. Completed events (start time reached AND end time has passed) -> 'Completed'
        $conn->query("
            UPDATE event 
            SET EventStatus = 'Completed' 
            WHERE LOWER(TRIM(COALESCE(EventStatus, 'ongoing'))) IN ('ongoing', 'scheduled', 'upcoming')
              AND EventDateTime <= NOW()
              AND (
                  (EndDateTime IS NOT NULL AND EndDateTime > '2000-01-01' AND EndDateTime < NOW())
                  OR ((EndDateTime IS NULL OR EndDateTime <= '2000-01-01') AND EventDateTime < NOW() - INTERVAL 3 HOUR)
              )
        ");
    } catch (\Throwable $e) {}
}

// Run auto-sync
autoSyncEventStatuses($conn);

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
  <link rel="icon" href="../../assets/img/philsca.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', 'Inter', system-ui, sans-serif;
      background: #020617;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      color: #f8fafc;
    }
    .card {
      background: #0b1536;
      border: 1px solid #1e3a8a;
      border-radius: 20px;
      padding: 44px 36px;
      max-width: 480px;
      width: 100%;
      text-align: center;
      box-shadow: none !important;
      animation: fadeUp .25s ease;
    }
    @keyframes fadeUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    .icon {
      width: 72px; height: 72px;
      background: #dc2626;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
      font-size: 32px;
      font-weight: 700;
      color: #ffffff;
      box-shadow: none !important;
    }
    h1 { font-size: 22px; font-weight: 700; color: #f8fafc; margin-bottom: 8px; }
    p  { font-size: 14px; color: #94a3b8; line-height: 1.6; margin-bottom: 8px; }
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
      margin-bottom: 20px;
    }
    .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }
    .btn {
      padding: 11px 24px;
      border-radius: 10px;
      border: none;
      font-size: 13px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: all .2s;
      box-shadow: none !important;
    }
    .btn-primary { 
      background: #1e40af; 
      color: #ffffff; 
      border: 1px solid #1e40af;
    }
    .btn-primary:hover { 
      background: #2563eb; 
      border-color: #2563eb;
      transform: translateY(-1px); 
    }
    .btn-ghost { 
      background: transparent; 
      color: #cbd5e1; 
      border: 1px solid #334155; 
    }
    .btn-ghost:hover { 
      background: rgba(255,255,255,0.06); 
      color: #ffffff; 
      border-color: #64748b; 
    }
    .divider { height: 1px; background: #1e293b; margin: 22px 0; }
    .code { 
      font-family: monospace; 
      font-size: 12px; 
      color: #94a3b8; 
      background: #020617; 
      border: 1px solid #1e293b; 
      border-radius: 8px; 
      padding: 10px 14px; 
      text-align: left; 
      word-break: break-all; 
      margin-top: 14px; 
      box-shadow: none !important;
    }
    .retry-msg { font-size: 12.5px; color: #64748b; margin-top: 16px; line-height: 1.5; }
    .retry-msg a { color: #38bdf8; text-decoration: none; font-weight: 500; }
    .retry-msg a:hover { text-decoration: underline; }
    #countdown { color: #38bdf8; font-weight: 700; }
  </style>
</head>
<body>
<div class="card">
  <div class="icon">!</div>
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

  <p class="retry-msg">If this problem persists, please contact your system administrator at <br><a href="mailto:naaporganization@gmail.com">naaporganization@gmail.com</a></p>
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