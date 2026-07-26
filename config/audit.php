<?php
/**
 * audit.php — Shared audit-log helper
 * ─────────────────────────────────────────────────────────────────
 * Include this file in any API or page that needs to log actions.
 *
 * Usage:
 *   require_once __DIR__ . '/../audit.php';   // adjust path as needed
 *
 *   logAudit($conn, 'Student Login',       'student',      $userId,  'success', ['email' => $email]);
 *   logAudit($conn, 'OSA Approved Event',  'osa',          $osaId,   'success', ['event_id' => 12]);
 *   logAudit($conn, 'Org Created Event',   'organization', $orgId,   'success', ['event_name' => 'Seminar']);
 *   logAudit($conn, 'Student Login',       'student',      null,     'failed',  ['email' => $email, 'reason' => 'Bad password']);
 *
 * Parameters:
 *   $conn       — active mysqli connection
 *   $action     — short human-readable action label
 *   $actorType  — 'student' | 'osa' | 'organization'
 *   $actorId    — PK from the actor's table (null if unknown/unauthenticated)
 *   $status     — 'success' | 'failed'
 *   $details    — associative array of extra context (auto JSON-encoded)
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
            // Only set if actor is a student (user.UserId)
            $userId = ($actorType === 'student') ? $actorId : null;

            // ── IP address ───────────────────────────────────────────
            $ip = '';
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            } else {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
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

            // i  s           i         s           s        s              s        s
            $stmt->bind_param(
                'isisssss',
                $userId, $actorType, $actorId, $actorName,
                $action, $detailsJson, $status, $ip
            );

            $stmt->execute();
            $stmt->close();

        } catch (Throwable $e) {
            // Audit failure must NEVER break the main request flow
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
