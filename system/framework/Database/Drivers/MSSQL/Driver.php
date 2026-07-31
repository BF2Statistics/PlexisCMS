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

use InvalidArgumentException;
use System\Convert;
use System\Database\DbConnection;
use System\Database\Drivers\DatabaseDriver;

class Driver extends DatabaseDriver
{
    /**
     * @var string SQL Server identifier escape characters
     */
    protected array $identifierEscapeChars = ['[', ']'];

    /**
     * Regular expression pattern used for validating indentifiers that adhere to a specific naming convention
     * of the database driver.
     */
    protected string $validationRules = '/^([a-zA-Z_][a-zA-Z0-9_]*)(\.[a-zA-Z_][a-zA-Z0-9_]*)*$/';

    protected readonly string $namespace;

    public function __construct(DbConnection $connection)
    {
        parent::__construct($connection);
        $this->namespace = 'System\Database\Drivers\MSSQL';
    }

    /**
     * Quotes an SQL identifier using MSSQL's quoting strategy.
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
        if ($identifier === '*') {
            return $identifier;
        }

        // Check if already quoted with square brackets
        if ($this->isAlreadyQuoted($identifier))
        {
            if (!$this->hasMatchedQuotes($identifier)) {
                throw new InvalidArgumentException("Mismatched or invalid quotes in SQL identifier: {$identifier}");
            }
            return $identifier;
        }

        $segments = explode('.', $identifier);
        $quotedSegments = [];

        foreach ($segments as $segment)
        {
            if (!preg_match($this->validationRules, $segment)) {
                throw new InvalidArgumentException("Invalid SQL identifier segment for MSSQL: {$segment}");
            }
            $quotedSegments[] = $this->quoteSegment($segment);
        }

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

        // MSSQL boolean representation
        if (is_bool($value)) {
            return $value ? '1' : '0';
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
        return 'Microsoft SQL Server';
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
        $query = "SELECT SUM(size) * 8 * 1024 AS size FROM sys.master_files WHERE database_id = DB_ID()";
        $result = $this->connection->query($query)->fetch();
        return $result['size'];
    }

    /**
     * @inheritDoc
     */
    public function getTableStatus(array $tables): array
    {
        $return = [];
        foreach ($tables as $table)
        {
            $stmt = $this->connection->prepare("
            SELECT 
                t.NAME AS name,
                p.rows AS rows,
                SUM(a.total_pages) * 8 * 1024 AS size,
                CAST(SUM(a.used_pages) AS FLOAT) * 8 AS used_size_kb,
                CAST(SUM(a.data_pages) AS FLOAT) * 8 AS data_size_kb,
                CAST(SUM(a.index_pages) AS FLOAT) * 8 AS index_size_kb,
                i.type_desc AS engine
            FROM 
                sys.tables t
            INNER JOIN 
                sys.indexes i ON t.OBJECT_ID = i.object_id
            INNER JOIN 
                sys.partitions p ON i.object_id = p.OBJECT_ID AND i.index_id = p.index_id
            INNER JOIN 
                sys.allocation_units a ON p.partition_id = a.container_id
            WHERE 
                t.NAME = :tableName
            GROUP BY 
                t.NAME, p.rows, i.type_desc
        ");

            $stmt->execute([':tableName' => $table]);
            $status = $stmt->fetch();

            if ($status) {
                $return[] = [
                    'name' => $status['name'],
                    'size' => $status['size'],
                    'filesize' => Convert::BytesToUnits((int)$status['size']),
                    'rows' => number_format((int)$status['rows']),
                    'avg_row_filesize' => Convert::BytesToUnits((int)$status['data_size_kb'] * 1024 / max((int)$status['rows'], 1)),
                    'avg_row_length' => (int)$status['data_size_kb'] * 1024 / max((int)$status['rows'], 1),
                    'engine' => $status['engine'],
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
        $stmt = $this->connection->prepare("SELECT OBJECT_ID(:table)");
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
        $this->connection->exec("DBCC CHECKIDENT ({$quotedTable}, RESEED, {$default})");
    }

    /**
     * @inheritDoc
     */
    public function importCSV(string $filename, string $table, string $delimiter = ',', string $enclosure = '"'): void
    {
        $table = $this->quoteIdentifier($table);

        // Validate delimiter is a single safe character
        if (strlen($delimiter) !== 1) {
            throw new \InvalidArgumentException("Delimiter must be a single character.");
        }

        // Sanitize delimiter — only allow safe characters
        if (!preg_match('/^[,\t|;:]$/', $delimiter)) {
            throw new \InvalidArgumentException("Delimiter contains an unsafe character: {$delimiter}");
        }

        $quotedFilename = $this->quote($filename);

        $sql = sprintf(
            "BULK INSERT %s FROM %s WITH (
            FORMAT = 'CSV',
            FIELDTERMINATOR = '%s',
            ROWTERMINATOR = '\\n',
            FIRSTROW = 2
        )",
            $table,
            $quotedFilename,
            $delimiter
        );

        $this->connection->exec($sql);
    }

    /**
     * @inheritDoc
     */
    public function exportCSV(string $filename, string $table, string $delimiter = ','): void
    {
        // Validate delimiter
        if (strlen($delimiter) !== 1) {
            throw new \InvalidArgumentException("Delimiter must be a single character.");
        }
        if (!preg_match('/^[,\t|;:]$/', $delimiter)) {
            throw new \InvalidArgumentException("Delimiter contains an unsafe character: {$delimiter}");
        }

        // Validate filename — reject characters that could break out of the command
        if (preg_match('/[\'";|&`$]/', $filename)) {
            throw new \InvalidArgumentException("Filename contains unsafe characters for shell execution.");
        }

        // Get the server name using a query
        $serverName = $this->connection->query("SELECT @@SERVERNAME AS ServerName")->fetchColumn();

        // Format the bcp command
        $quotedTable = $this->quoteIdentifier($table);
        $escapedFilename = str_replace('"', '""', $filename);

        $sql = sprintf(
            "EXEC xp_cmdshell 'bcp \"%s.dbo.%s\" queryout \"%s\" -c -t\"%s\" -T'",
            str_replace("'", "''", $serverName),
            str_replace("'", "''", $quotedTable),
            str_replace("'", "''", $escapedFilename),
            str_replace("'", "''", $delimiter)
        );

        // Execute export
        $this->connection->exec($sql);
    }

    /**
     *
     */
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
     * Quotes a single segment of an identifier using MSSQL's square brackets.
     * Escapes internal square brackets by doubling them. Ignores the special `*` identifier.
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

        // Escape internal square brackets by doubling them
        $escapedSegment = str_replace(']', ']]', $segment);

        // Wrap the escaped segment with square brackets
        return "[{$escapedSegment}]";
    }

    /**
     * Checks if an identifier is already quoted with square brackets [identifier].
     *
     * @param string $identifier The identifier to check.
     * @return bool True if already quoted, false otherwise.
     */
    private function isAlreadyQuoted(string $identifier): bool
    {
        return str_starts_with($identifier, '[') && str_ends_with($identifier, ']');
    }

    /**
     * Validates that quotes in an identifier are properly matched for MSSQL.
     *
     * @param string $identifier The identifier to validate.
     * @return bool True if quotes are matched, false otherwise.
     */
    private function hasMatchedQuotes(string $identifier): bool
    {
        // Check that the identifier starts and ends with square brackets
        return $this->isAlreadyQuoted($identifier);
    }
}
