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

use InvalidArgumentException;
use System\Convert;
use System\Database\DbConnection;
use System\Database\Drivers\DatabaseDriver;

class Driver extends DatabaseDriver
{
    /**
     * @var string Gets or sets the identifier escape character
     */
    protected array $identifierEscapeChars = ['"', '"'];

    /**
     * Regular expression pattern used for validating indentifiers that adhere to a specific naming convention
     * of the database driver.
     */
    protected string $validationRules = '/^([a-zA-Z_][a-zA-Z0-9_]*)(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/';

    public function __construct(DbConnection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * Quotes an SQL identifier using PostgreSQL's quoting strategy.
     *
     * Supports dot-separated identifiers (e.g., schema.table.column) by quoting each part
     * individually. Special identifiers like `*` are returned as-is without quoting.
     *
     * @param string $identifier The identifier name (e.g., column, table, or schema.table).
     * @return string The quoted identifier.
     *
     * @throws InvalidArgumentException If the identifier contains invalid characters.
     */
    public function quoteIdentifier(string $identifier): string
    {
        // Check if the identifier is the special wildcard `*`
        if ($identifier === '*') {
            return $identifier; // Return * as-is
        }

        // Check if the identifier is already quoted with double quotes
        if ($this->isAlreadyQuoted($identifier))
        {
            if (!$this->hasMatchedQuotes($identifier)) {
                throw new InvalidArgumentException("Mismatched or invalid quotes in SQL identifier: {$identifier}");
            }
            return $identifier; // Return as-is if properly quoted
        }

        // Support dot-separated identifiers (e.g., schema.table.column)
        $segments = explode('.', $identifier);
        $quotedSegments = [];

        foreach ($segments as $segment)
        {
            // Validate each unquoted segment
            if (!preg_match($this->validationRules, $segment)) {
                throw new InvalidArgumentException("Invalid SQL identifier segment for PostgreSQL: {$segment}");
            }

            // Quote the valid segment (using the updated quoteSegment method)
            $quotedSegments[] = $this->quoteSegment($segment);
        }

        // Combine quoted segments and return
        return implode('.', $quotedSegments);
    }

    /**
     * @inheritDoc
     */
    public function prepareValue($value): string
    {
        // Handle NULL values
        if ($value === null) {
            return 'NULL';
        }

        // Handle placeholders: return them as-is
        $isString = is_string($value);
        if ($isString && $this->isPlaceholder($value)) {
            return $value; // Do not quote placeholders
        }

        // Postgres boolean representation
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        // Handle integers and floats (return them as-is)
        if (is_numeric($value)) {
            return (string) $value;
        }

        // Handle strings (use PDO::quote for escaping)
        if ($isString) {
            return $this->connection->quote($value);
        }

        // For any other types (e.g., objects, arrays), throw an exception
        throw new InvalidArgumentException('Unsupported type for SQL value quoting.');
    }


    /**
     * @inheritDoc
     */
    public function getIdentifierChars(): array
    {
        return $this->identifierEscapeChars;
    }

    /**
     * @inheritDoc
     */
    public function getDriverDisplayName(): string
    {
        return 'PostgreSQL';
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): string
    {
        return $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    /**
     * @inheritDoc
     */
    public function getDatabaseSize(): int
    {
        $result = $this->connection->query("SELECT pg_database_size(current_database()) AS size")->fetch();
        return $result['size'];
    }

    /**
     * @inheritDoc
     */
    public function getTableStatus(array $tables): array
    {
        $return = [];
        foreach ($tables as $table) {
            $stmt = $this->connection->prepare("
            SELECT 
                relname AS name,
                pg_total_relation_size(relname::regclass) AS size,
                pg_table_size(relname::regclass) AS table_size,
                pg_indexes_size(relname::regclass) AS index_size,
                reltuples::bigint AS rows
            FROM 
                pg_stat_user_tables
            WHERE 
                relname = :tableName
        ");

            $stmt->execute([':tableName' => $table]);
            $status = $stmt->fetch();

            if ($status) {
                // Calculate average row size (avoid division by zero)
                $rows = (int)$status['rows'];
                $avgRowSize = (int)$status['table_size'] / max($rows, 1);

                $return[] = [
                    'name' => $status['name'],
                    'size' => $status['size'],
                    'filesize' => Convert::BytesToUnits((int)$status['size']),
                    'rows' => number_format($rows),
                    'avg_row_filesize' => Convert::BytesToUnits($avgRowSize),
                    'avg_row_length' => $avgRowSize,
                    'engine' => 'PostgreSQL'
                ];
            }
        }

        return $return;
    }


    /**
     * @inheritDoc
     */
    public function tableExists(string $table): bool
    {
        $stmt = $this->connection->prepare("SELECT to_regclass(:table)");
        $stmt->execute([':table' => $table]);
        $result = $stmt->fetch(\PDO::FETCH_NUM);
        return $result[0] !== null;
    }

    /**
     * @inheritDoc
     */
    public function resetAutoIncrement(string $table, int $default = 1): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $default = (int)$default; // Ensure integer
        $this->connection->exec("ALTER SEQUENCE {$quotedTable}_id_seq RESTART WITH {$default}");
    }

    /**
     * @param string $filename
     * @param string $table
     * @param string $delimiter
     * @param string $enclosure
     * @inheritDoc
     */
    public function importCSV(string $filename, string $table, string $delimiter = ',', string $enclosure = '"'): void
    {
        $table = $this->quoteIdentifier($table);

        // Validate delimiter is a single safe character
        if (strlen($delimiter) !== 1) {
            throw new \InvalidArgumentException("Delimiter must be a single character.");
        }
        if (!preg_match('/^[,\t|;:]$/', $delimiter)) {
            throw new \InvalidArgumentException("Delimiter contains an unsafe character: {$delimiter}");
        }

        $quotedFilename = $this->connection->quote($filename, \PDO::PARAM_STR);

        $sql = sprintf(
            "COPY %s FROM %s WITH (FORMAT CSV, HEADER, DELIMITER %s)",
            $table,
            $quotedFilename,
            $this->connection->quote($delimiter, \PDO::PARAM_STR)
        );
        $this->connection->exec($sql);
    }

    /**
     * @inheritDoc
     */
    public function exportCSV(string $filename, string $table, string $delimiter = ','): void
    {
        $table = $this->quoteIdentifier($table);

        // Validate delimiter is a single safe character
        if (strlen($delimiter) !== 1) {
            throw new \InvalidArgumentException("Delimiter must be a single character.");
        }
        if (!preg_match('/^[,\t|;:]$/', $delimiter)) {
            throw new \InvalidArgumentException("Delimiter contains an unsafe character: {$delimiter}");
        }

        $quotedFilename = $this->connection->quote($filename, \PDO::PARAM_STR);

        $sql = sprintf(
            "COPY %s TO %s WITH (FORMAT CSV, HEADER, DELIMITER %s)",
            $table,
            $quotedFilename,
            $this->connection->quote($delimiter, \PDO::PARAM_STR)
        );
        $this->connection->exec($sql);
    }

    protected function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Retrieves the namespace of this Driver
     *
     * @return string The namespace of the current class.
     */
    public function getNamespace(): string
    {
        return __NAMESPACE__;
    }

    /**
     * Quotes a single segment of an identifier using PostgreSQL's double quotes.
     * Escapes internal double quotes by doubling them. Ignores the special `*` identifier.
     *
     * @param string $segment The identifier segment to quote.
     * @return string The quoted segment or `*` as-is.
     */
    private function quoteSegment(string $segment): string
    {
        // If the segment is `*`, return it as-is
        if ($segment === '*') {
            return $segment;
        }

        // Escape internal double quotes by replacing " with ""
        $escapedSegment = str_replace('"', '""', $segment);

        // Wrap the escaped segment with double quotes
        return "\"{$escapedSegment}\"";
    }

    /**
     * Checks if an identifier is already quoted with double quotes ("identifier").
     *
     * @param string $identifier The identifier to check.
     * @return bool True if already quoted, false otherwise.
     */
    private function isAlreadyQuoted(string $identifier): bool
    {
        return str_starts_with($identifier, '"') && str_ends_with($identifier, '"');
    }

    /**
     * Validates that quotes in an identifier are properly matched.
     *
     * @param string $identifier The identifier to validate.
     * @return bool True if quotes are matched, false otherwise.
     */
    private function hasMatchedQuotes(string $identifier): bool
    {
        // Check that the identifier starts and ends with double quotes
        return $this->isAlreadyQuoted($identifier);
    }
}