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
namespace System\Database\Drivers\MSSQL;

use System\Database\DbConnection;

class NonQuery extends \System\Database\NonQuery
{
    /**
     * @inheritDoc
     */
    protected function executeUpsert(): int
    {
        $matchConditions = [];
        foreach ($this->where as $col => $values) {
            [$operator, $value] = $values;

            // Sanitize and build match conditions
            $matchConditions[] = "target.{$this->quoteIdentifier($col)} {$operator} source.{$this->quoteIdentifier($col)}";
        }

        $updates = [];
        foreach ($this->columns as $col => $value) {
            // Sanitize and build update value assignments
            $updates[] = "target.{$this->quoteIdentifier($col)} = source.{$this->quoteIdentifier($col)}";
        }

        // Quote column names and build values list
        $columns = implode(", ", array_map([$this, 'quoteIdentifier'], array_keys($this->columns)));
        $values = $this->buildValuesList();

        // Build the MERGE query
        $sql = "MERGE INTO {$this->quoteIdentifier($this->table)} AS target
            USING (SELECT {$values}) AS source ({$columns})
            ON " . implode(" AND ", $matchConditions) . "
            WHEN MATCHED THEN
                UPDATE SET " . implode(", ", $updates) . "
            WHEN NOT MATCHED THEN
                INSERT ({$columns}) VALUES ({$values});";

        return $this->connection->exec($sql);
    }
}