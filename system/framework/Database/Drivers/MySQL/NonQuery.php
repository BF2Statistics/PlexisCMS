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
namespace System\Database\Drivers\MySQL;
class NonQuery extends \System\Database\NonQuery
{
    /**
     * @inheritDoc
     */
    protected function executeUpsert(): int
    {
        // Build the column names
        $columns = implode(", ", array_map([$this, 'quoteIdentifier'], array_keys($this->columns)));

        // Build the values (VALUES part)
        $values = $this->buildValuesList();

        // Build the ON DUPLICATE KEY UPDATE part
        $updates = implode(", ", array_map(function ($column, $key) {
            // Extract the value (we only need $key for the column name)
            $value = $column[1] ?? null;

            // Quote the updated value
            $quotedValue = is_null($value) ? "NULL" : $this->prepareValue($value);

            // Return the assignment logic
            return "{$this->quoteIdentifier($key)} = {$quotedValue}";
        }, $this->columns, array_keys($this->columns)));

        // Compile the full SQL query
        $sql = "INSERT INTO {$this->quoteIdentifier($this->table)} ({$columns}) VALUES ({$values})
            ON DUPLICATE KEY UPDATE {$updates}";

        // Execute the query and return the number of affected rows
        return $this->connection->exec($sql);
    }
}