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
            if (empty($actorName)) {
                $actorName = ($actorType === 'osa') ? 'OSA Administrator' : (($actorType === 'admin') ? 'System Administrator' : ucfirst($actorType));
            }

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
            'student'      => "SELECT CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) AS n FROM `user` WHERE UserId = ? LIMIT 1",
            'osa'          => "SELECT Name AS n FROM `osa` WHERE OsaId = ? LIMIT 1",
            'organization' => "SELECT OrgName AS n FROM `organization` WHERE OrgId = ? LIMIT 1",
            'admin'        => "SELECT Name AS n FROM `admin` WHERE AdminId = ? LIMIT 1",
        ];

        if (!isset($queries[$type])) return null;

        $stmt = $conn->prepare($queries[$type]);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return !empty($row['n']) ? trim($row['n']) : null;
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
        } elseif (strpos($aLower, 'assessment') !== false || strpos($aLower, 'question') !== false || strpos($aLower, 'test') !== false) {
            $module = 'Assessments & Tests';
        } elseif (strpos($aLower, 'event') !== false || strpos($aLower, 'pre-register') !== false) {
            $module = 'Event Management';
        } elseif (strpos($aLower, 'cor') !== false || strpos($aLower, 'ai verification') !== false) {
            $module = 'Student Verification';
        } elseif (strpos($aLower, 'attendance') !== false || strpos($aLower, 'face recognition') !== false || strpos($aLower, 'spoof') !== false || strpos($aLower, 'presence') !== false || strpos($aLower, 'biometric') !== false) {
            $module = 'Attendance & Biometrics';
        } elseif (strpos($aLower, 'announcement') !== false) {
            $module = 'Announcements';
        } elseif (strpos($aLower, 'password') !== false || strpos($aLower, 'login') !== false || strpos($aLower, 'logout') !== false || strpos($aLower, 'session') !== false || strpos($aLower, 'lockout') !== false) {
            $module = 'Security & Authentication';
        } elseif (strpos($aLower, 'organization') !== false) {
            $module = 'Organization Management';
        } elseif (strpos($aLower, 'user') !== false || strpos($aLower, 'student') !== false || strpos($aLower, 'member') !== false || strpos($aLower, 'officer') !== false || strpos($aLower, 'suspend') !== false || strpos($aLower, 'activate') !== false || strpos($aLower, 'profile') !== false) {
            $module = 'User Management';
        } elseif (strpos($aLower, 'document') !== false || strpos($aLower, 'report') !== false) {
            $module = 'Documents & Reports';
        } elseif (strpos($aLower, 'certificate') !== false) {
            $module = 'Certificates';
        } elseif (strpos($aLower, 'message') !== false) {
            $module = 'Messages & Communication';
        } elseif (strpos($aLower, 'setting') !== false || strpos($aLower, 'email') !== false) {
            $module = 'System & Account Settings';
        }

        // 2. Synthesize Description
        $evName = $details['EventName'] ?? $details['event_name'] ?? $details['title'] ?? '';
        $targetUser = $details['target_user'] ?? $details['student_name'] ?? $details['Name'] ?? '';
        $portal = !empty($details['portal']) ? $details['portal'] : (($actorType === 'osa') ? 'OSA' : ucfirst($actorType));

        switch ($action) {
            case 'Login':
            case 'User Login':
                $desc = "$name signed in successfully to the $portal portal.";
                break;
            case 'Logout':
            case 'User Logout':
                $desc = "$name logged out of the $portal portal.";
                break;
            case 'Failed Login Attempt':
                $ident = $details['identifier'] ?? '';
                $desc = "Failed login attempt" . ($ident ? " for '$ident'" : "") . " on the $portal portal.";
                break;
            case 'Session Timeout':
                $desc = "Session for $name expired due to 40 minutes of inactivity.";
                break;
            case 'Login Lockout (3 Mins Cooldown)':
                $desc = "Account/IP temporarily locked for 3 minutes due to repeated failed login attempts.";
                break;
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
                $desc = "Event details" . ($evName ? " for '$evName'" : "") . " were updated by $name.";
                break;
            case 'Event Cancelled':
                $desc = "Event" . ($evName ? " '$evName'" : "") . " was marked as cancelled.";
                break;
            case 'Delete Event':
                $desc = "Event" . ($evName ? " '$evName'" : "") . " was deleted from the database by $name.";
                break;
            case 'Update Event Finance Setting':
                $desc = "Financial reporting requirement for event" . ($evName ? " '$evName'" : "") . " was updated.";
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
            case 'Cancel Registration':
                $desc = "Pre-registration for event" . ($evName ? " '$evName'" : "") . " was cancelled by $name.";
                break;
            case 'Attendance Recorded':
                $method = $details['method'] ?? $details['ScanType'] ?? 'Check-in';
                $logType = $details['log_type'] ?? $details['LogType'] ?? '';
                $desc = "Attendance ($method" . ($logType ? " - $logType" : "") . ") recorded for " . ($targetUser ?: $name) . ($evName ? " at '$evName'" : "") . ".";
                break;
            case 'Delete Attendance Record':
            case 'Delete Attendance':
                $desc = "Attendance record " . ($targetUser ? "for $targetUser " : "") . ($evName ? "at '$evName' " : "") . "was deleted by $name.";
                break;
            case 'Face Recognition Attempt':
                $desc = "Biometric face recognition verification attempt performed" . ($name ? " for $name" : "") . ".";
                break;
            case 'Biometric Verification Passed':
                $vType = !empty($details['check_type']) ? ucfirst($details['check_type']) : 'Biometric';
                $desc = "$vType verification check passed by student $name" . ($evName ? " for event '$evName'" : "") . ".";
                break;
            case 'Trigger Anti-Spoofing Check':
                $desc = "Anti-spoofing verification check was triggered for event" . ($evName ? " '$evName'" : "") . ".";
                break;
            case 'Trigger Presence Check':
                $desc = "Presence check was triggered for event" . ($evName ? " '$evName'" : "") . ".";
                break;
            case 'Stop Verification Checks':
                $desc = "Active biometric verification checks were stopped for event" . ($evName ? " '$evName'" : "") . ".";
                break;
            case 'Anti-Spoofing Detected':
            case 'Anti-Spoofing Detection Blocked':
                $desc = "Biometric anti-spoofing security monitor detected and blocked suspicious/simulated camera feed.";
                break;
            case 'Account Suspended':
                $desc = "User account" . ($targetUser ? " for $targetUser" : "") . " was suspended / deactivated.";
                break;
            case 'Activate User':
                $desc = "User account" . ($targetUser ? " for $targetUser" : "") . " was activated by $name.";
                break;
            case 'Delete Student User':
            case 'Delete Officer User':
            case 'Delete User':
                $desc = "User account " . ($targetUser ? "for $targetUser " : "") . "was deleted by $name.";
                break;
            case 'Password Reset':
            case 'Reset User Password':
                $desc = "Account password was reset successfully.";
                break;
            case 'Change Password':
                $desc = "Account password was updated by $name.";
                break;
            case 'Update Profile':
                $desc = "Profile details were updated by $name.";
                break;
            case 'Create Announcement':
                $title = $details['Title'] ?? $details['title'] ?? '';
                $desc = "New announcement" . ($title ? " '$title'" : "") . " was created by $name.";
                break;
            case 'Update Announcement':
                $title = $details['Title'] ?? $details['title'] ?? '';
                $desc = "Announcement" . ($title ? " '$title'" : "") . " was edited by $name.";
                break;
            case 'Approve Announcement':
                $title = $details['Title'] ?? $details['title'] ?? '';
                $desc = "Announcement" . ($title ? " '$title'" : "") . " was approved and published by $name.";
                break;
            case 'Reject Announcement':
                $title = $details['Title'] ?? $details['title'] ?? '';
                $desc = "Announcement" . ($title ? " '$title'" : "") . " was rejected by $name.";
                break;
            case 'Delete Announcement':
                $title = $details['Title'] ?? $details['title'] ?? '';
                $desc = "Announcement" . ($title ? " '$title'" : "") . " was deleted by $name.";
                break;
            case 'Create Organization':
                $orgN = $details['org_name'] ?? '';
                $desc = "New organization" . ($orgN ? " '$orgN'" : "") . " was registered by $name.";
                break;
            case 'Update Organization Status':
                $orgStatus = $details['Status'] ?? 'updated';
                $desc = "Organization status was changed to '$orgStatus' by $name.";
                break;
            case 'Delete Organization':
                $desc = "Organization and associated data were deleted by $name.";
                break;
            case 'Add Officer':
                $offName = $details['Name'] ?? '';
                $offRole = $details['Role'] ?? 'Officer';
                $desc = "New officer" . ($offName ? " $offName" : "") . " ($offRole) was added by $name.";
                break;
            case 'Update Member Status':
                $st = $details['Status'] ?? 'updated';
                $desc = "Member status was updated to '$st' by $name.";
                break;
            case 'Delete Member':
                $desc = "Member was removed from organization by $name.";
                break;
            case 'Create Certificate Template':
                $tplName = $details['TemplateName'] ?? '';
                $desc = "Certificate template" . ($tplName ? " '$tplName'" : "") . " was created by $name.";
                break;
            case 'Delete Certificate Template':
                $tplName = $details['TemplateName'] ?? '';
                $desc = "Certificate template" . ($tplName ? " '$tplName'" : "") . " was deleted by $name.";
                break;
            case 'Issue Certificates':
                $issued = $details['Issued'] ?? 0;
                $desc = "$issued certificates issued" . ($evName ? " for event '$evName'" : "") . " by $name.";
                break;
            case 'Upload Document':
                $docTitle = $details['title'] ?? $details['file_name'] ?? 'document';
                $desc = "Document '$docTitle' was uploaded by $name.";
                break;
            case 'Upload Event Report':
                $docTitle = $details['title'] ?? 'event report';
                $desc = "Report '$docTitle' was uploaded" . ($evName ? " for event '$evName'" : "") . " by $name.";
                break;
            case 'Send Message':
                $desc = "Message sent by $name.";
                break;
            case 'Create Assessment':
                $title = $details['Title'] ?? '';
                $desc = "New assessment" . ($title ? " '$title'" : "") . ($evName ? " for event '$evName'" : "") . " was created by $name.";
                break;
            case 'Update Assessment':
                $title = $details['Title'] ?? '';
                $desc = "Assessment" . ($title ? " '$title'" : "") . " was updated by $name.";
                break;
            case 'Update Assessment Status':
                $st = $details['Status'] ?? 'updated';
                $desc = "Assessment status updated to '$st' by $name.";
                break;
            case 'Add Question':
                $desc = "Question added to assessment by $name.";
                break;
            case 'Update Question':
                $desc = "Assessment question was edited by $name.";
                break;
            case 'Delete Question':
                $desc = "Question was removed from assessment by $name.";
                break;
            case 'Submit Assessment Test':
                $tType = $details['test_type'] ?? 'test';
                $score = $details['score'] ?? 0;
                $total = $details['total'] ?? 0;
                $desc = "Student $name submitted $tType" . ($evName ? " for event '$evName'" : "") . " (Score: $score/$total).";
                break;
            case 'Change Email':
                $nMail = $details['new_email'] ?? '';
                $desc = "Account email address updated to '$nMail' by $name.";
                break;
            case 'Update Settings':
            case 'Update System Setting':
                $desc = "System/account settings updated by $name.";
                break;
            default:
                $desc = "$action performed by $name." . (!empty($details['reason']) ? " Reason: " . $details['reason'] : "");
                break;
        }

        return ['module' => $module, 'description' => $desc];
    }

}
