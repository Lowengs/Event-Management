-- ====================================================================
-- setup_admin_tables.sql
-- Run once to ensure admin table and auditlog columns are ready.
-- ====================================================================

-- ── Admin table ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admin` (
    `AdminId`      INT AUTO_INCREMENT PRIMARY KEY,
    `Name`         VARCHAR(255)  NOT NULL,
    `Email`        VARCHAR(255)  NOT NULL UNIQUE,
    `PasswordHash` VARCHAR(255)  NOT NULL,
    `Role`         VARCHAR(50)   NOT NULL DEFAULT 'SuperAdmin',
    `Status`       VARCHAR(20)   NOT NULL DEFAULT 'active',
    `CreatedAt`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `UpdatedAt`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Seed default admin if table is empty ─────────────────────────────
-- Password: Admin@123  (bcrypt hash)
INSERT INTO `admin` (`Name`, `Email`, `PasswordHash`, `Role`, `Status`)
SELECT 'System Administrator', 'admin@naap.edu.ph',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
       'SuperAdmin', 'active'
WHERE NOT EXISTS (SELECT 1 FROM `admin` LIMIT 1);

-- ── Ensure auditlog supports actor types ─────────────────────────────
-- Add ActorType column if missing
-- (safe to run repeatedly — MySQL will just skip if already exists)
ALTER TABLE `auditlog` ADD COLUMN IF NOT EXISTS `ActorType`  VARCHAR(50)  NULL AFTER `UserId`;
ALTER TABLE `auditlog` ADD COLUMN IF NOT EXISTS `ActorId`    INT          NULL AFTER `ActorType`;
ALTER TABLE `auditlog` ADD COLUMN IF NOT EXISTS `ActorName`  VARCHAR(255) NULL AFTER `ActorId`;
ALTER TABLE `auditlog` ADD COLUMN IF NOT EXISTS `Details`    TEXT         NULL AFTER `Action`;
ALTER TABLE `auditlog` ADD COLUMN IF NOT EXISTS `Status`     VARCHAR(20)  NULL AFTER `Details`;
ALTER TABLE `auditlog` ADD COLUMN IF NOT EXISTS `IpAddress`  VARCHAR(45)  NULL AFTER `Status`;
