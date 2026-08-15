<?php
/**
 * rate_limit.php — File-based rate limiter with progressive cooldown and audit log integration.
 * Cooldown: 3 minutes base (180s), adding +3 minutes for every subsequent violation (3m -> 6m -> 9m...).
 *
 * @param string $key         Unique label for the bucket (e.g. 'student_login', 'osa_login')
 * @param int    $max         Max requests allowed within the window
 * @param int    $windowSecs  Rolling window in seconds (default 60)
 */
function rateLimit(string $key, int $max = 60, int $windowSecs = 60): void {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $id  = md5($ip . '_' . $key);
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'naap_rl' . DIRECTORY_SEPARATOR;

    if (!is_dir($dir)) @mkdir($dir, 0750, true);

    $file = $dir . $id . '.json';
    $now  = time();
    $data = [
        'c'              => 0,
        't'              => $now,
        'violations'     => 0,
        'cooldown_until' => 0
    ];

    if (is_file($file)) {
        $raw = @json_decode(@file_get_contents($file), true);
        if (is_array($raw)) {
            $data = array_merge($data, $raw);
            // Reset window counter if window expired and not in cooldown
            if (($now - ($data['t'] ?? 0)) >= $windowSecs && $now >= ($data['cooldown_until'] ?? 0)) {
                $data['c'] = 0;
                $data['t'] = $now;
            }
        }
    }

    $isCurrentlyInCooldown = ($now < ($data['cooldown_until'] ?? 0));

    if (!$isCurrentlyInCooldown) {
        $data['c']++;
    }

    // Check if limit exceeded or currently blocked by cooldown
    if ($isCurrentlyInCooldown || $data['c'] > $max) {
        if (!$isCurrentlyInCooldown) {
            // New violation: progressive cooldown (+3 minutes every time)
            $data['violations'] = ($data['violations'] ?? 0) + 1;
            $cooldownSecs = $data['violations'] * 180; // 3 mins, 6 mins, 9 mins...
            $data['cooldown_until'] = $now + $cooldownSecs;
            @file_put_contents($file, json_encode($data), LOCK_EX);

            // Log rate limit violation to audit log
            try {
                require_once __DIR__ . '/db.php';
                global $conn;
                if (isset($conn) && $conn instanceof mysqli) {
                    $actorType = !empty($_SESSION['osa_id']) ? 'osa' : (
                        !empty($_SESSION['admin_id']) ? 'admin' : (
                            !empty($_SESSION['org_id']) ? 'organization' : (
                                (!empty($_SESSION['student_id']) || !empty($_SESSION['user_id'])) ? 'student' : 'guest'
                            )
                        )
                    );
                    $actorId = $_SESSION['osa_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['org_id'] ?? $_SESSION['student_id'] ?? $_SESSION['user_id'] ?? null;
                    $actorName = $_SESSION['osa_name'] ?? $_SESSION['admin_name'] ?? $_SESSION['org_name'] ?? $_SESSION['student_name'] ?? $_SESSION['name'] ?? 'Guest (' . $ip . ')';
                    $action = 'Too Many Requests';
                    $cooldownMins = (int)($cooldownSecs / 60);
                    $details = json_encode([
                        'message' => "Rate limit exceeded on '{$key}'. Progressive cooldown active for {$cooldownMins} minutes (Violation #{$data['violations']}).",
                        'key' => $key,
                        'violations' => $data['violations'],
                        'cooldown_minutes' => $cooldownMins,
                        'ip' => $ip,
                        'uri' => $_SERVER['REQUEST_URI'] ?? ''
                    ]);

                    $status = 'failed';
                    $stmt = $conn->prepare("INSERT INTO auditlog (UserId, ActorType, ActorId, ActorName, Action, Details, Status, IpAddress, Date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    if ($stmt) {
                        $stmt->bind_param("isisssss", $actorId, $actorType, $actorId, $actorName, $action, $details, $status, $ip);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            } catch (Throwable $e) {
                // Keep rate limit functional even if DB logging fails
            }
        } else {
            @file_put_contents($file, json_encode($data), LOCK_EX);
        }

        $remainingSecs = max(1, ($data['cooldown_until'] ?? 0) - $now);
        $remMins = floor($remainingSecs / 60);
        $remSecs = $remainingSecs % 60;
        $timeStr = $remMins > 0 ? "{$remMins}m {$remSecs}s" : "{$remSecs}s";
        $totalCooldownMins = max(3, ceil((($data['violations'] ?? 1) * 180) / 60));

        header('Retry-After: ' . $remainingSecs);
        http_response_code(429);

        $msg = "Too many requests. Please wait {$timeStr} and try again. (Cooldown: {$totalCooldownMins} mins)";

        $isJson = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false)
               || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $msg,
                'cooldown_seconds' => $remainingSecs,
                'cooldown_minutes' => $totalCooldownMins,
                'violations' => $data['violations'] ?? 1
            ]);
        } else {
            echo '<div style="font-family:Inter,sans-serif;text-align:center;padding:40px 20px;max-width:500px;margin:50px auto;background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.1);border:1px solid #fee2e2;">
                <div style="font-size:40px;margin-bottom:12px;">⏳</div>
                <h2 style="color:#b91c1c;margin:0 0 10px;">Too Many Requests</h2>
                <p style="color:#4b5563;font-size:15px;line-height:1.5;">' . htmlspecialchars($msg) . '</p>
            </div>';
        }
        exit;
    }
}
