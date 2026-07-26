<?php
require '../db.php';
$r = $conn->query('SHOW CREATE TABLE event');
$row = $r->fetch_assoc();
echo $row['Create Table'];
echo "\n\n";
$r = $conn->query('SHOW CREATE TABLE event_questions');
if ($r) {
    $row = $r->fetch_assoc();
    echo $row['Create Table'];
} else {
    echo "No event_questions table.";
}
echo "\n\n";
