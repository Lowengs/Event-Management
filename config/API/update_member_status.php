<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$orgId = (int)$_SESSION['org_id'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!isset($data['user_id']) || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$userId = (int)$data['user_id'];
$action = strtolower($data['action']);

// Verify the user belongs to this org
$verify = $conn->prepare("SELECT status FROM user WHERE UserId = ? AND OrgId = ?");
$verify->bind_param("ii", $userId, $orgId);
$verify->execute();
$res = $verify->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found in your organization']);
    exit;
}

if ($action === 'approve') {
    $newStatus = 'active';
    // optionally, update verification_status to something else if needed
} elseif ($action === 'decline') {
    $newStatus = 'rejected';
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$update = $conn->prepare("UPDATE user SET status = ? WHERE UserId = ? AND OrgId = ?");
$update->bind_param("sii", $newStatus, $userId, $orgId);

if ($update->execute()) {
    // Optionally fetch the student's email to notify them
    $emailQ = $conn->query("SELECT Email, first_name FROM user WHERE UserId = $userId");
    if ($row = $emailQ->fetch_assoc()) {
        $to = $row['Email'];
        $fname = $row['first_name'];
        $headers = "From: noreply@naap.edu.ph\r\n" . "Reply-To: noreply@naap.edu.ph\r\n" . "X-Mailer: PHP/" . phpversion();
        
        if ($newStatus === 'active') {
            $subject = "NAAP Student Portal - Organization Member Approved";
            $body = "Hello {$fname},\n\nYour membership application has been APPROVED by the organization.\nYour account is now ACTIVE and ready to use.\n\nBest Regards,\nNAAP Office of Student Affairs";
        } else {
            $subject = "NAAP Student Portal - Organization Member Declined";
            $body = "Hello {$fname},\n\nWe regret to inform you that your membership application has been DECLINED by the organization.\n\nBest Regards,\nNAAP Office of Student Affairs";
        }
        @mail($to, $subject, $body, $headers);
    }

    echo json_encode(['success' => true, 'message' => 'Member status updated successfully', 'newStatus' => $newStatus]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
