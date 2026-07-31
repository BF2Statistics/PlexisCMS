<?php
/**
 * Plexis Core
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace System\Database\Drivers\PostgreSQL;

use System\Database\DbConnection;

class NonQuery extends \System\Database\NonQuery
{
    /**
     * @inheritDoc
     */
    protected function executeUpsert(): int
    {
        $columns = implode(", ", array_map([$this, 'quoteIdentifier'], array_keys($this->columns)));
        $placeholders = $this->buildValuesList();

        $conflictColumns = implode(", ", array_map([$this, 'quoteIdentifier'], array_keys($this->where)));
        $updates = [];
        foreach ($this->columns as $col => $value) {
            $updates[] = "{$this->quoteIdentifier($col)} = EXCLUDED.{$this->quoteIdentifier($col)}";
        }

        $sql = "INSERT INTO {$this->quoteIdentifier($this->table)} ({$columns}) VALUES ({$placeholders}) 
                ON CONFLICT ({$conflictColumns}) DO UPDATE SET " . implode(", ", $updates);
        return $this->connection->exec($sql);
    }
}