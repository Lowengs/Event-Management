<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['org_id'])) { 
    header('Location: ../osa/login.php'); 
    exit; 
}

$orgId = (int)$_SESSION['org_id'];
$orgData = $conn->query("SELECT OrgName FROM organization WHERE OrgId = $orgId")->fetch_assoc();
$orgName = $orgData['OrgName'] ?? 'Organization';

// Handle direct form post toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $eventId = (int)($_POST['event_id'] ?? 0);
    $newStatus = trim($_POST['status'] ?? '');
    $allowed = ['Scheduled', 'Ongoing', 'Delayed', 'Cancelled', 'Completed'];
    if ($eventId && in_array($newStatus, $allowed)) {
        $stmt = $conn->prepare("UPDATE event SET EventStatus = ? WHERE EventId = ? AND OrgId = ?");
        $stmt->bind_param("sii", $newStatus, $eventId, $orgId);
        $stmt->execute();
    }
    header("Location: test_events.php");
    exit;
}

$events = [];
$res = $conn->query("SELECT * FROM event WHERE OrgId = $orgId ORDER BY EventDateTime DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) $events[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Status Test Tool - <?= htmlspecialchars($orgName) ?></title>
    <link rel="stylesheet" href="../../assets/css/organization/nav.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="../../assets/img/philsca.png">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 30px 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 24px; border: 1px solid #e2e8f0; }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px; }
        .header h1 { font-size: 1.25rem; margin: 0; display: flex; align-items: center; gap: 8px; color: #1e3a8a; }
        .btn-back { padding: 8px 14px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #334155; font-size: 0.85rem; font-weight: 600; }
        .btn-back:hover { background: #e2e8f0; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; font-size: 0.85rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
        .badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .badge.scheduled { background: #e0f2fe; color: #0369a1; }
        .badge.ongoing { background: #fef3c7; color: #d97706; }
        .badge.delayed { background: #ffedd5; color: #c2410c; }
        .badge.cancelled { background: #fee2e2; color: #b91c1c; }
        .badge.completed { background: #dcfce7; color: #15803d; }
        .btn-group { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
        .toggle-btn { padding: 5px 10px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.15s; }
        .toggle-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .toggle-btn.active { background: #3b82f6; color: #fff; border-color: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Event Status Test Tool (<?= htmlspecialchars($orgName) ?>)</h1>
            <a href="events_org.php" class="btn-back">← Back to Events</a>
        </div>
        <p style="font-size: 0.9rem; color: #64748b; margin-top: 0; margin-bottom: 20px;">
            Freely toggle event statuses for testing and verification purposes. Changes take effect instantly.
        </p>

        <table>
            <thead>
                <tr>
                    <th>Event Title</th>
                    <th>Date &amp; Time</th>
                    <th>Current Status</th>
                    <th style="text-align:right;">Freely Toggle Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;">No events found for this organization.</td></tr>
                <?php else: ?>
                    <?php foreach ($events as $ev): 
                        $st = $ev['EventStatus'] ?: 'Scheduled';
                        $dt = !empty($ev['EventDateTime']) ? date('M j, Y g:i A', strtotime($ev['EventDateTime'])) : 'TBA';
                        $activeBg = [
                            'Scheduled' => '#2563eb',
                            'Ongoing'   => '#d97706',
                            'Delayed'   => '#ea580c',
                            'Cancelled' => '#dc2626',
                            'Completed' => '#16a34a'
                        ];
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($ev['EventName']) ?></strong></td>
                        <td style="font-size:0.85rem;color:#475569;"><?= $dt ?></td>
                        <td><span class="badge <?= strtolower($st) ?>"><?= htmlspecialchars($st) ?></span></td>
                        <td>
                            <form method="POST" class="btn-group">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="event_id" value="<?= $ev['EventId'] ?>">
                                <?php foreach (['Scheduled', 'Ongoing', 'Delayed', 'Cancelled', 'Completed'] as $opt): 
                                    $isCurrent = strtolower($st) === strtolower($opt);
                                    $btnStyle = $isCurrent ? "background:{$activeBg[$opt]};color:#fff;border-color:{$activeBg[$opt]};font-weight:700;" : "";
                                ?>
                                    <button type="submit" name="status" value="<?= $opt ?>" class="toggle-btn <?= $isCurrent ? 'active' : '' ?>" style="<?= $btnStyle ?>">
                                        <?= $isCurrent ? '✓ ' . $opt : $opt ?>
                                    </button>
                                <?php endforeach; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
