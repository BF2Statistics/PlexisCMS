--
-- Database: `bf2stats`
-- Version: 1.0
--

-- --------------------------------------------------------
-- Delete Tables/Views/Triggers First
-- --------------------------------------------------------

DROP TABLE IF EXISTS `bf2web_sessions`;
DROP TABLE IF EXISTS `bf2web_site_navigation`;
DROP TABLE IF EXISTS `bf2web_group_permissions`;
DROP TABLE IF EXISTS `bf2web_permissions`;
DROP TABLE IF EXISTS `bf2web_accounts`;
DROP TABLE IF EXISTS `bf2web_account_groups`;
DROP TABLE IF EXISTS `bf2web_version`;

-- --------------------------------------------------------
-- Non-Player Tables First
-- --------------------------------------------------------

--
-- Table structure for table `_bf2web_version`
--

CREATE TABLE `bf2web_version` (
    `updateid` INT UNSIGNED,
    `version` VARCHAR(10) NOT NULL,
    `time` INT UNSIGNED DEFAULT 0,
    PRIMARY KEY(`updateid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TRIGGER `_version_insert_time` BEFORE INSERT ON `bf2web_version`
FOR EACH ROW SET new.time = UNIX_TIMESTAMP();

-- ----------------------------
-- Table structure for `bf2web_account_groups`
-- ----------------------------

CREATE TABLE `bf2web_account_groups` (
    `id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(48) NOT NULL,
    `banned` TINYINT(1) NOT NULL DEFAULT 0,
    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
    `is_owner` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for `bf2web_users`
-- ----------------------------

CREATE TABLE `bf2web_accounts` (
    `id` INT UNSIGNED NOT NULL,
    `username` VARCHAR(32) NOT NULL UNIQUE,
    `password` VARCHAR(88) NOT NULL,
    `country` VARCHAR(2) NOT NULL,
    `email` VARCHAR(64) NOT NULL,
    `rank_id` INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    `timezone` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`group_id`) REFERENCES `bf2web_account_groups` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for `bf2web_permissions`
-- ----------------------------

CREATE TABLE `bf2web_permissions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(24) NOT NULL DEFAULT '',
    `name` VARCHAR(64) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for `bf2web_group_permissions`
-- ----------------------------
CREATE TABLE `bf2web_group_permissions` (
    `group_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`group_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for `bf2web_site_navigation`
-- ----------------------------
CREATE TABLE `bf2web_site_navigation` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `parent_id` int(11) unsigned DEFAULT NULL,  -- Needs NULL for Root items
    `label` varchar(64) NOT NULL,
    `title` varchar(64) NOT NULL,
    `url` varchar(255) NOT NULL,    -- Relative to the site root
    `route_names` TEXT NOT NULL,    -- Stores JSON array of route names
    `target` varchar(20) DEFAULT '_self',
    `icon` varchar(32) DEFAULT '',
    `sort_order` int(11) DEFAULT 0,
    `separator_below` tinyint(1) DEFAULT 0 NOT NULL,
    `is_visible` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`parent_id`) REFERENCES `bf2web_site_navigation`(`id`) ON DELETE CASCADE
);

-- ----------------------------
-- Table structure for `bf2web_sessions`
-- ----------------------------
CREATE TABLE `bf2web_sessions` (
    `selector` CHAR(12) NOT NULL,
    `validator` CHAR(64) NOT NULL,       -- SHA-256 hash
    `account_id` INT UNSIGNED NOT NULL,
    `expires` INT UNSIGNED NOT NULL,        -- Unit timestamp.
    `refresh` INT UNSIGNED NOT NULL,        -- Unit timestamp.
    `last_seen` INT UNSIGNED NOT NULL DEFAULT 0,    -- Unit timestamp.
    `fingerprint` CHAR(64) NOT NULL,     -- SHA-256 of User Agent plus the security key.
    `user_agent` VARCHAR(512) NOT NULL,     -- The Raw Text (Used for UI Icons)
    `last_ip` VARCHAR(45) NOT NULL,         -- IPv6 is max 45 chars (Used for Geo/Bans)
    PRIMARY KEY (`selector`),
    INDEX `idx_account` (`account_id`),       -- Critical for "Logout All"
    INDEX `idx_expires` (`expires`)           -- Critical for Garbage Collection
) ENGINE=InnoDB DEFAULT CHARSET=utf8;