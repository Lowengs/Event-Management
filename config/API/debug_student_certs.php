<?php
require '../db.php';
$r = $conn->query("SELECT * FROM certificates");
$certs = [];
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $certs[] = $row;
    }
}
echo "Certificates in DB: \n";
print_r($certs);

$r2 = $conn->query("SELECT * FROM attendance");
$att = [];
if ($r2) {
    while ($row = $r2->fetch_assoc()) {
        $att[] = $row;
    }
}
echo "\nAttendance in DB: \n";
print_r($att);
