<?php
/**
 * rate_limit.php — Rate limiting and login security with 3-minute cooldown and audit log integration.
 * Cooldown: 3 minutes base (180s), adding +3 minutes for progressive violations (3m -> 6m -> 9m...).
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/audit.php';

function _getRateLimitClientIp(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    if ($ip === '::1' || empty($ip) || $ip === 'localhost') {
        $ip = '127.0.0.1';
    }
    return substr($ip, 0, 45);
}

function _getRateLimitStorageDir(): string {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'naap_rl' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

function _getRateLimitFile(string $key): string {
    $ip = _getRateLimitClientIp();
    $id = md5($ip . '_' . $key);
    return _getRateLimitStorageDir() . $id . '.json';
}

function _loadRateLimitData(string $file): array {
    $now = time();
    $data = [
        'failed_attempts' => 0,
        'violations'      => 0,
        'cooldown_until'  => 0,
        'last_attempt'    => $now
    ];

    if (is_file($file)) {
        $raw = @json_decode(@file_get_contents($file), true);
        if (is_array($raw)) {
            $data = array_merge($data, $raw);
        }
    }
    return $data;
}

/**
 * Check if the current client is under a 3-minute login cooldown lockout.
 * If locked, immediately outputs a JSON/HTTP 429 response and terminates.
 */
function checkLoginCooldown(string $key, ?mysqli $conn = null): void {
    $file = _getRateLimitFile($key);
    $data = _loadRateLimitData($file);
    $now = time();

    if ($now < ($data['cooldown_until'] ?? 0)) {
        $remainingSecs = max(1, ($data['cooldown_until'] ?? 0) - $now);
        $remMins = floor($remainingSecs / 60);
        $remSecs = $remainingSecs % 60;
        $timeStr = $remMins > 0 ? "{$remMins}m {$remSecs}s" : "{$remSecs}s";
        $totalCooldownMins = max(3, ceil((($data['violations'] ?? 1) * 180) / 60));

        header('Retry-After: ' . $remainingSecs);
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success'          => false,
            'locked'           => true,
            'message'          => "Account/IP temporarily locked due to too many failed attempts. Please wait {$timeStr} before trying again. (3-min cooldown active)",
            'cooldown_seconds' => $remainingSecs,
            'cooldown_minutes' => $totalCooldownMins,
            'violations'       => $data['violations'] ?? 1
        ]);
        exit;
    }
}

/**
 * Record a failed login attempt. If failed attempts reach threshold (default 5),
 * enforces a 3-minute progressive cooldown (180s, 360s, 540s...) and logs to auditlog.
 */
function recordLoginFailure(string $key, string $actorType, string $identifier, ?mysqli $conn = null, int $maxAttempts = 5, int $cooldownBaseSecs = 180): void {
    $file = _getRateLimitFile($key);
    $data = _loadRateLimitData($file);
    $now = time();

    $data['failed_attempts'] = ($data['failed_attempts'] ?? 0) + 1;
    $data['last_attempt'] = $now;

    global $conn;
    $db = $conn;

    if ($data['failed_attempts'] >= $maxAttempts) {
        $data['violations'] = ($data['violations'] ?? 0) + 1;
        $cooldownSecs = max(180, $data['violations'] * $cooldownBaseSecs);
        $data['cooldown_until'] = $now + $cooldownSecs;
        $data['failed_attempts'] = 0; // Reset counter for the lockout window
        @file_put_contents($file, json_encode($data), LOCK_EX);

        $cooldownMins = (int)($cooldownSecs / 60);
        $ip = _getRateLimitClientIp();

        if ($db && $db instanceof mysqli) {
            logAudit(
                $db,
                'Login Lockout (3 Mins Cooldown)',
                $actorType,
                null,
                'failed',
                [
                    'identifier'       => $identifier,
                    'key'              => $key,
                    'reason'           => "Exceeded {$maxAttempts} failed login attempts. Locked for {$cooldownMins} minutes.",
                    'cooldown_minutes' => $cooldownMins,
                    'violation_count'  => $data['violations'],
                    'ip'               => $ip
                ]
            );
        }

        header('Retry-After: ' . $cooldownSecs);
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success'          => false,
            'locked'           => true,
            'message'          => "Too many failed login attempts. Your account/IP has been temporarily locked for {$cooldownMins} minutes.",
            'cooldown_seconds' => $cooldownSecs,
            'cooldown_minutes' => $cooldownMins,
            'violations'       => $data['violations']
        ]);
        exit;
    } else {
        @file_put_contents($file, json_encode($data), LOCK_EX);
        $remaining = $maxAttempts - $data['failed_attempts'];

        if ($db && $db instanceof mysqli) {
            logAudit(
                $db,
                'Failed Login Attempt',
                $actorType,
                null,
                'failed',
                [
                    'identifier'         => $identifier,
                    'key'                => $key,
                    'attempt'            => $data['failed_attempts'],
                    'remaining_attempts' => $remaining
                ]
            );
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'            => false,
            'message'            => "Invalid credentials. ({$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before 3-minute cooldown)",
            'failed_attempts'    => $data['failed_attempts'],
            'remaining_attempts' => $remaining
        ]);
        exit;
    }
}

/**
 * Record a successful login: resets failed attempt counters and logs to auditlog.
 */
function recordLoginSuccess(string $key, string $actorType, ?int $actorId, ?mysqli $conn = null, array $extraDetails = []): void {
    $file = _getRateLimitFile($key);
    if (is_file($file)) {
        @unlink($file); // Reset rate limit file on successful auth
    }

    global $conn;
    $db = $conn;
    if ($db && $db instanceof mysqli) {
        logAudit($db, 'Login', $actorType, $actorId, 'success', $extraDetails);
    }
}

/**
 * Standard generic rate limit helper
 */
function rateLimit(string $key, int $max = 60, int $windowSecs = 60): void {
    checkLoginCooldown($key);
}

