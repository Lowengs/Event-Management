<?php
/**
 * migrate_monitoring.php
 * Adds tab_switches, engagement_score, and monitoring_flagged to event_pretest and event_posttest.
 */
require_once __DIR__ . '/../db.php';

header('Content-Type: text/plain');

$queries = [
    "ALTER TABLE `event_pretest` ADD COLUMN IF NOT EXISTS `tab_switches` INT DEFAULT 0 AFTER `Score`;",
    "ALTER TABLE `event_pretest` ADD COLUMN IF NOT EXISTS `engagement_score` INT DEFAULT 100 AFTER `tab_switches`;",
    "ALTER TABLE `event_pretest` ADD COLUMN IF NOT EXISTS `monitoring_flagged` TINYINT(1) DEFAULT 0 AFTER `engagement_score`;",

    "ALTER TABLE `event_posttest` ADD COLUMN IF NOT EXISTS `tab_switches` INT DEFAULT 0 AFTER `Score`;",
    "ALTER TABLE `event_posttest` ADD COLUMN IF NOT EXISTS `engagement_score` INT DEFAULT 100 AFTER `tab_switches`;",
    "ALTER TABLE `event_posttest` ADD COLUMN IF NOT EXISTS `monitoring_flagged` TINYINT(1) DEFAULT 0 AFTER `engagement_score`;"
];

echo "Starting migrations...\n";

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "SUCCESS: " . substr($q, 0, 80) . "...\n";
    } else {
        echo "ERROR: " . $conn->error . "\n";
    }
}

echo "Database migrations completed.\n";
