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

use \InvalidArgumentException;

class QueryBuilder extends \System\Database\QueryBuilder
{
    // Builds the query based on the current state of the query builder
    protected function buildQuery(): void
    {
        if (empty($this->table)) {
            throw new InvalidArgumentException('Table name must be specified using the `from` method.');
        }

        // SELECT clause
        $fields = implode(', ', $this->fields);
        $this->command = "SELECT {$fields}";

        // FROM clause
        $this->command .= " FROM {$this->table}";

        // JOIN clause
        if (!empty($this->joins)) {
            $this->command .= ' ' . implode(' ', $this->joins);
        }

        // WHERE clause
        if (!empty($this->conditions)) {
            $conditions = implode(
                ' ',
                array_map(fn ($item) => "{$item['type']} {$item['condition']}", $this->conditions)
            );
            $conditions = ltrim($conditions, 'AND ');
            $conditions = ltrim($conditions, 'OR ');
            $this->command .= " WHERE {$conditions}";
        }

        // GROUP BY clause
        if (!empty($this->groupings)) {
            $this->command .= ' GROUP BY ' . implode(', ', $this->groupings);
        }

        // ORDER BY clause
        if (!empty($this->orderings)) {
            $this->command .= ' ORDER BY ' . implode(', ', $this->orderings);
        }

        // LIMIT clause
        if ($this->limit !== null)
        {
            if (empty($this->orderings))
            {
                // MS SQL requires an ORDER BY clause for OFFSET
                throw new InvalidArgumentException('ORDER BY is required when using OFFSET in SQL Server.');
            }

            $this->command .= " OFFSET {$this->offset} ROWS FETCH NEXT {$this->limit} ROWS ONLY";
        }
    }
}