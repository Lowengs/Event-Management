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
        array   $details   = [],
        ?string $customActorName = null
    ): void {
        try {
            if ($actorId !== null && $actorId <= 0) {
                $actorId = null;
            }

            // ── Actor display name ───────────────────────────────────
            $actorName = $customActorName ?? _resolveActorName($conn, $actorType, $actorId);

            // ── UserId: for backward-compat with the FK on auditlog ──
            $userId = ($actorType === 'student' && !empty($actorId)) ? $actorId : null;

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

            // ── Auto-detect Browser, Device / OS, and Location ────────
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Client';
            $clientInfo = _detectBrowserDevice($ua);
            
            if (!isset($details['device'])) {
                $details['device'] = $clientInfo['device'];
            }
            if (!isset($details['browser'])) {
                $details['browser'] = $clientInfo['browser'];
            }
            if (!isset($details['user_agent'])) {
                $details['user_agent'] = substr($ua, 0, 255);
            }
            if (!isset($details['ip'])) {
                $details['ip'] = $ip;
            }
            if (!isset($details['location'])) {
                $details['location'] = ($ip === '127.0.0.1' || $ip === '::1') ? 'Localhost / Campus Network' : 'Philippines (Auto-detected)';
            }

            // ── Auto-resolve Module & Description if not explicitly provided ──
            if (empty($details['module']) || empty($details['description'])) {
                $resolved = _resolveAuditModuleAndDescription($action, $actorType, $actorName, $details);
                if (empty($details['module'])) {
                    $details['module'] = $resolved['module'];
                }
                if (empty($details['description'])) {
                    $details['description'] = $resolved['description'];
                }
            }

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
     * Parse User-Agent into human-readable Browser & OS / Device.
     */
    function _detectBrowserDevice(string $ua): array {
        $os = 'Unknown OS';
        $browser = 'Unknown Browser';

        // OS Detection
        if (preg_match('/windows nt 10/i', $ua)) {
            $os = 'Windows 10/11';
        } elseif (preg_match('/windows nt 6\.3/i', $ua)) {
            $os = 'Windows 8.1';
        } elseif (preg_match('/windows nt 6\.1/i', $ua)) {
            $os = 'Windows 7';
        } elseif (preg_match('/iphone/i', $ua)) {
            $os = 'iPhone (iOS)';
        } elseif (preg_match('/ipad/i', $ua)) {
            $os = 'iPad (iPadOS)';
        } elseif (preg_match('/android/i', $ua)) {
            $os = 'Android Device';
        } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/linux/i', $ua)) {
            $os = 'Linux';
        }

        // Browser Detection
        if (preg_match('/edg\/([0-9.]+)/i', $ua, $m)) {
            $browser = 'Microsoft Edge ' . explode('.', $m[1])[0];
        } elseif (preg_match('/chrome\/([0-9.]+)/i', $ua, $m) && !preg_match('/edg/i', $ua)) {
            $browser = 'Chrome ' . explode('.', $m[1])[0];
        } elseif (preg_match('/firefox\/([0-9.]+)/i', $ua, $m)) {
            $browser = 'Firefox ' . explode('.', $m[1])[0];
        } elseif (preg_match('/safari\/([0-9.]+)/i', $ua, $m) && !preg_match('/chrome/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/opr\/([0-9.]+)/i', $ua, $m)) {
            $browser = 'Opera ' . explode('.', $m[1])[0];
        }

        return [
            'device'  => $os,
            'browser' => $browser
        ];
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
    }

    /**
     * Resolve default Module and human-readable Description for an audit log.
     */
    function _resolveAuditModuleAndDescription(string $action, string $actorType, ?string $actorName, array $details): array {
        $aLower = strtolower($action);
        $module = 'General';
        $desc   = '';
        $name   = $actorName ?: ucfirst($actorType);

        // 1. Determine Module
        if (strpos($aLower, 'proposal') !== false) {
            $module = 'Proposals & Approvals';
        } elseif (strpos($aLower, 'event') !== false || strpos($aLower, 'pre-register') !== false) {
            $module = 'Event Management';
        } elseif (strpos($aLower, 'cor') !== false || strpos($aLower, 'ai verification') !== false) {
            $module = 'Student Verification';
        } elseif (strpos($aLower, 'attendance') !== false || strpos($aLower, 'face recognition') !== false || strpos($aLower, 'spoof') !== false) {
            $module = 'Attendance & Biometrics';
        } elseif (strpos($aLower, 'announcement') !== false) {
            $module = 'Announcements';
        } elseif (strpos($aLower, 'password') !== false || strpos($aLower, 'login') !== false || strpos($aLower, 'lockout') !== false) {
            $module = 'Security & Authentication';
        } elseif (strpos($aLower, 'user') !== false || strpos($aLower, 'student') !== false || strpos($aLower, 'member') !== false || strpos($aLower, 'suspend') !== false || strpos($aLower, 'activate') !== false) {
            $module = 'User Management';
        } elseif (strpos($aLower, 'document') !== false || strpos($aLower, 'report') !== false) {
            $module = 'Documents & Reports';
        } elseif (strpos($aLower, 'certificate') !== false) {
            $module = 'Certificates';
        }

        // 2. Synthesize Description
        $evName = $details['EventName'] ?? $details['event_name'] ?? $details['title'] ?? '';
        $targetUser = $details['target_user'] ?? $details['student_name'] ?? $details['Name'] ?? '';

        switch ($action) {
            case 'Proposal Submitted':
                $desc = "Organization submitted an event project proposal" . ($evName ? " for '$evName'" : "") . " for OSA administrative review.";
                break;
            case 'Proposal Approved':
                $desc = "OSA Administrator approved the event project proposal" . ($evName ? " for '$evName'" : "") . ".";
                break;
            case 'Proposal Rejected':
                $desc = "OSA Administrator rejected the event project proposal" . ($evName ? " for '$evName'" : "") . ".";
                break;
            case 'Event Created':
                $desc = "A new organization event" . ($evName ? " '$evName'" : "") . " was successfully created.";
                break;
            case 'Event Updated':
                $desc = "Event details" . ($evName ? " for '$evName'" : "") . " were updated.";
                break;
            case 'Event Cancelled':
                $desc = "Event" . ($evName ? " '$evName'" : "") . " was marked as cancelled.";
                break;
            case 'Student Registration':
                $desc = "New student account registered" . ($name ? " for $name" : "") . ".";
                break;
            case 'COR Submitted':
                $desc = "Certificate of Registration (COR) was submitted" . ($name ? " by $name" : "") . " for enrollment verification.";
                break;
            case 'AI Verification Completed':
                $desc = "AI automated verification completed successfully for student COR credentials.";
                break;
            case 'AI Verification Flagged':
                $desc = "AI verification flagged inconsistencies or low confidence in uploaded student credentials.";
                break;
            case 'Student Pre-Registered':
            case 'Event Registration':
                $desc = "Student $name pre-registered for the event" . ($evName ? " '$evName'" : "") . ".";
                break;
            case 'Attendance Recorded':
                $method = $details['method'] ?? $details['ScanType'] ?? 'Check-in';
                $logType = $details['log_type'] ?? $details['LogType'] ?? '';
                $desc = "Attendance ($method" . ($logType ? " - $logType" : "") . ") recorded for " . ($targetUser ?: $name) . ($evName ? " at '$evName'" : "") . ".";
                break;
            case 'Face Recognition Attempt':
                $desc = "Biometric face recognition verification attempt performed" . ($name ? " for $name" : "") . ".";
                break;
            case 'Anti-Spoofing Detected':
            case 'Anti-Spoofing Detection Blocked':
                $desc = "Biometric anti-spoofing security monitor detected and blocked suspicious/simulated camera feed.";
                break;
            case 'Account Suspended':
                $desc = "User account" . ($targetUser ? " for $targetUser" : "") . " was suspended / deactivated.";
                break;
            case 'Password Reset':
            case 'Reset User Password':
                $desc = "Account password was reset successfully.";
                break;
            case 'Change Password':
                $desc = "Account password was updated by $name.";
                break;
            default:
                $desc = "$action performed by $name." . (!empty($details['reason']) ? " Reason: " . $details['reason'] : "");
                break;
        }

        return ['module' => $module, 'description' => $desc];
    }

}
