<?php
require '../db.php';
$r = $conn->query('SHOW CREATE TABLE certificate_templates');
if ($r) {
    $row = $r->fetch_assoc();
    echo $row['Create Table'];
} else {
    echo "Table does not exist.";
}
