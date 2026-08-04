<?php
require_once __DIR__ . '/db.php';

echo "=== USERS COUNT & LIST ===\n";
$uRes = $conn->query("SELECT UserId, first_name, last_name, Email, course, OrgId, student_id FROM `user` ORDER BY UserId ASC");
while ($u = $uRes->fetch_assoc()) {
    echo "ID: {$u['UserId']} | Name: {$u['first_name']} {$u['last_name']} | Email: {$u['Email']} | Course: {$u['course']} | OrgId: {$u['OrgId']} | StudentID: {$u['student_id']}\n";
}

echo "\n=== EVENT REGISTRATION ROWS ===\n";
$erRes = $conn->query("SELECT * FROM eventregistration");
if ($erRes) {
    while ($er = $erRes->fetch_assoc()) {
        print_r($er);
    }
} else {
    echo "Error querying eventregistration: " . $conn->error . "\n";
}

echo "\n=== EVENTS LIST ===\n";
$evRes = $conn->query("SELECT EventId, EventName, OrgId, EventStatus, EventDateTime FROM event");
if ($evRes) {
    while ($ev = $evRes->fetch_assoc()) {
        echo "EventId: {$ev['EventId']} | Name: {$ev['EventName']} | OrgId: {$ev['OrgId']} | Status: {$ev['EventStatus']} | Date: {$ev['EventDateTime']}\n";
    }
}
