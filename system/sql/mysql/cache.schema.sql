-- Cache table
CREATE TABLE IF NOT EXISTS {TABLE_NAME} (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `value` LONGTEXT NOT NULL,
    `soft_ttl` INT UNSIGNED NOT NULL,
    `hard_ttl` INT UNSIGNED NOT NULL,
    INDEX idx_soft_ttl (`soft_ttl`),
    INDEX idx_hard_ttl (`hard_ttl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Locks table
CREATE TABLE IF NOT EXISTS {LOCKS_TABLE_NAME} (
    `key` VARCHAR(255) NOT NULL PRIMARY KEY,
    `token` VARCHAR(255) NOT NULL,
    `expiry` INT UNSIGNED NOT NULL,
    INDEX idx_expiry (`expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;