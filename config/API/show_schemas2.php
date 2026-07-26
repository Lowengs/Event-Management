<?php
require '../db.php';
$r = $conn->query('SHOW CREATE TABLE assessments');
if ($r) {
    $row = $r->fetch_assoc();
    echo $row['Create Table'];
}
echo "\n\n";
$r = $conn->query('SHOW CREATE TABLE assessment_questions');
if ($r) {
    $row = $r->fetch_assoc();
    echo $row['Create Table'];
}
echo "\n\n";
