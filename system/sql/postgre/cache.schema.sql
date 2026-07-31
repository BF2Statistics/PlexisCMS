-- Cache table
CREATE TABLE IF NOT EXISTS {TABLE_NAME} (
                                            "key" VARCHAR(255) NOT NULL PRIMARY KEY,
    "value" TEXT NOT NULL,
    "soft_ttl" INTEGER NOT NULL,
    "hard_ttl" INTEGER NOT NULL
    );

CREATE INDEX IF NOT EXISTS idx_soft_ttl ON {TABLE_NAME} ("soft_ttl");
CREATE INDEX IF NOT EXISTS idx_hard_ttl ON {TABLE_NAME} ("hard_ttl");

-- Locks table
CREATE TABLE IF NOT EXISTS {LOCKS_TABLE_NAME} (
                                                  "key" VARCHAR(255) NOT NULL PRIMARY KEY,
    "token" VARCHAR(255) NOT NULL,
    "expiry" INTEGER NOT NULL
    );

CREATE INDEX IF NOT EXISTS idx_expiry ON {LOCKS_TABLE_NAME} ("expiry");