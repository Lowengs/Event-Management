<?php
/**
 * rate_limit.php — Simple file-based rate limiter (no extensions required).
 * Usage: require_once '../rate_limit.php'; rateLimit('login', 10, 60);
 *
 * @param string $key         Unique label for the bucket (e.g. 'login', 'register')
 * @param int    $max         Max requests allowed within the window
 * @param int    $windowSecs  Rolling window in seconds (default 60)
 */
function rateLimit(string $key, int $max = 60, int $windowSecs = 60): void {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $id  = md5($ip . '_' . $key);
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'naap_rl' . DIRECTORY_SEPARATOR;

    if (!is_dir($dir)) @mkdir($dir, 0750, true);

    $file = $dir . $id . '.json';
    $now  = time();
    $data = ['c' => 0, 't' => $now];

    if (is_file($file)) {
        $raw = @json_decode(@file_get_contents($file), true);
        if ($raw && ($now - $raw['t']) < $windowSecs) {
            $data = $raw;
        }
    }

    $data['c']++;
    @file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['c'] > $max) {
        $retry = $windowSecs - ($now - $data['t']);
        header('Retry-After: ' . max(1, $retry));
        http_response_code(429);
        $isJson = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false)
               || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait and try again.']);
        } else {
            echo '<p style="font-family:sans-serif;text-align:center;margin-top:3rem;">Too many requests. Please wait a moment and try again.</p>';
        }
        exit;
    }
}
