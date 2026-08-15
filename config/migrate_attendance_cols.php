<?php
require_once __DIR__ . '/../config/db.php';
$conn->query("ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `CheckInTime` DATETIME NULL AFTER `Timestamp`");
$conn->query("ALTER TABLE `attendance` ADD COLUMN IF NOT EXISTS `CheckOutTime` DATETIME NULL AFTER `CheckInTime`");
echo "ATTENDANCE COLUMNS ADDED SUCCESSFULLY\n";
