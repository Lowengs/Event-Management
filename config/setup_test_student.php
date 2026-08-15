<?php
/**
 * setup_test_student.php
 * Run ONCE at: http://localhost/project/config/setup_test_student.php
 * Creates/resets the test student account with a fresh password hash.
 */
require_once __DIR__ . '/db.php';

$email    = 'maria@test.naap.edu.ph';
$password = 'Test@1234';
$hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verify hash works
$ok = password_verify($password, $hash) ? '[OK] PASSES' : '[FAIL] FAILS';

// Check if user already exists
$existing = $conn->query("SELECT UserId FROM `user` WHERE Email = '$email' LIMIT 1")->fetch_assoc();

if ($existing) {
    // UPDATE only the hash and status — keep the record, avoid FK violation
    $stmt  = $conn->prepare("UPDATE `user` SET PasswordHash=?, status='active', Role='student' WHERE Email=?");
    $stmt->bind_param('ss', $hash, $email);
    $result = $stmt->execute();
    $newId  = $existing['UserId'];
    $action = 'UPDATED';
} else {
    // Safe to INSERT — no audit log rows tied to this user yet
    $stmt = $conn->prepare("INSERT INTO `user`
        (`Name`,`first_name`,`middle_name`,`last_name`,`student_id`,`course`,
         `year_level`,`section`,`username`,`phone`,`profile_photo`,
         `Position`,`status`,`Address`,`Email`,`PasswordHash`,`Role`,`OrgId`)
        VALUES ('Maria Santos','Maria','Lopez','Santos','2026-0002','BSAIT',
                '1st Year','B','maria_test','09987654321',NULL,
                'Member','active','Quezon City',?,?,'student',1)");
    $stmt->bind_param('ss', $email, $hash);
    $result = $stmt->execute();
    $newId  = $conn->insert_id;
    $action = 'INSERTED';
}

echo '<!DOCTYPE html><html><head>
<title>Test Student Setup</title>
<style>body{font-family:monospace;background:#111;color:#0f0;padding:2rem;} .ok{color:#0f0} .err{color:#f44} table{border-collapse:collapse;margin-top:1rem} td,th{border:1px solid #333;padding:.5rem 1rem;} h2{color:#fff}</style>
</head><body>';

echo "<h2>Test Student Setup</h2>";

if ($result) {
    echo "<p class='ok'>[OK] {$action} successfully. UserId = $newId</p>";
} else {
    echo "<p class='err'>[FAIL] Failed: " . htmlspecialchars($conn->error) . "</p>";
}

echo "<table>
<tr><th>Field</th><th>Value</th></tr>
<tr><td>Email</td><td>$email</td></tr>
<tr><td>Password</td><td>$password</td></tr>
<tr><td>Hash (first 30 chars)</td><td>" . substr($hash, 0, 30) . "...</td></tr>
<tr><td>Hash length</td><td>" . strlen($hash) . "</td></tr>
<tr><td>password_verify() check</td><td>$ok</td></tr>
</table>";


// Verify it's in the DB
$check = $conn->query("SELECT UserId, Email, LEFT(PasswordHash,30) as HP, status, Role FROM `user` WHERE Email = '$email'")->fetch_assoc();
if ($check) {
    echo "<p class='ok'>[OK] DB record confirmed: UserId={$check['UserId']}, Role={$check['Role']}, Status={$check['status']}</p>";
    echo "<p>Stored hash prefix: " . htmlspecialchars($check['HP']) . "...</p>";
} else {
    echo "<p class='err'>[FAIL] Could not find record after insert</p>";
}

echo "<hr><p style='color:#fff'>Now try logging in at: <a href='http://localhost/project/app/student/login.php' style='color:#4fd1c5'>Student Login Page</a></p>";
echo "<p style='color:#aaa'>Email: <strong style='color:#fff'>$email</strong> &nbsp; Password: <strong style='color:#fff'>$password</strong></p>";
echo "</body></html>";
