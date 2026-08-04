<?php
/**
 * Setup Missing Database Tables
 */
require_once __DIR__ . '/db.php';

$sqls = [
    "CREATE TABLE IF NOT EXISTS `certificatetemplate` (
        `TemplateId` INT(11) NOT NULL AUTO_INCREMENT,
        `OrgId` INT(11) NOT NULL,
        `EventId` INT(11) DEFAULT NULL,
        `TemplateName` VARCHAR(255) NOT NULL,
        `TemplateImage` VARCHAR(255) NOT NULL,
        `NameX` FLOAT DEFAULT 50,
        `NameY` FLOAT DEFAULT 50,
        `FontSize` INT(11) DEFAULT 60,
        `FontColor` VARCHAR(50) DEFAULT '#000000',
        `CreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`TemplateId`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

    "CREATE TABLE IF NOT EXISTS `preposttest` (
        `TestId` INT(11) NOT NULL AUTO_INCREMENT,
        `EventId` INT(11) NOT NULL,
        `StudentId` INT(11) NOT NULL,
        `TestType` ENUM('pre','post') NOT NULL,
        `Score` INT(11) DEFAULT 0,
        `CompletedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`TestId`),
        UNIQUE KEY `unique_test` (`EventId`, `StudentId`, `TestType`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

foreach ($sqls as $sql) {
    if (!$conn->query($sql)) {
        echo "Error creating table: " . $conn->error . "\n";
    } else {
        echo "Table verified successfully.\n";
    }
}
?>
