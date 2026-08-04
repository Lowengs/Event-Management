<?php
/**
 * audit.php — Shared audit-log helper
 */

if (!function_exists('logAudit')) {

    function logAudit(
        mysqli  $conn,
        string  $action,
        string  $actorType = 'student',
        ?int    $actorId   = null,
        string  $status    = 'success',
        array   $details   = []
    ): void {
        try {
            // ── Actor display name ───────────────────────────────────
            $actorName = _resolveActorName($conn, $actorType, $actorId);

            // ── UserId: for backward-compat with the FK on auditlog ──
            $userId = ($actorType === 'student') ? $actorId : null;

            // ── IP address ───────────────────────────────────────────
            $ip = '';
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
            $ip = substr($ip, 0, 45);

            // ── Serialize details ────────────────────────────────────
            $detailsJson = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;

            // ── Insert ───────────────────────────────────────────────
            $stmt = $conn->prepare(
                "INSERT INTO `auditlog`
                    (UserId, ActorType, ActorId, ActorName, Action, Details, Status, IpAddress, Date)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            if (!$stmt) {
                error_log('[Audit] Prepare failed: ' . $conn->error);
                return;
            }

            $stmt->bind_param(
                'isisssss',
                $userId, $actorType, $actorId, $actorName,
                $action, $detailsJson, $status, $ip
            );

            $stmt->execute();
            $stmt->close();

        } catch (Throwable $e) {
            error_log('[Audit] Exception: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the actor's display name from their respective table.
     */
    function _resolveActorName(mysqli $conn, string $type, ?int $id): ?string {
        if ($id === null) return null;

        $queries = [
            'student'      => "SELECT CONCAT(first_name, ' ', last_name) AS n FROM `user`         WHERE UserId = ? LIMIT 1",
            'osa'          => "SELECT Name                                 AS n FROM `osa`          WHERE OsaId  = ? LIMIT 1",
            'organization' => "SELECT OrgName                              AS n FROM `organization` WHERE OrgId  = ? LIMIT 1",
            'admin'        => "SELECT Name                                 AS n FROM `admin`        WHERE AdminId = ? LIMIT 1",
        ];

        if (!isset($queries[$type])) return null;

        $stmt = $conn->prepare($queries[$type]);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['n'] ?? null;
    }

}
?>
