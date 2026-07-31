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

use InvalidArgumentException;
use System\Collections\Dictionary;
use System\Convert;
use System\Database\DbConnection;
use System\Database\Drivers\DatabaseDriver;
use System\IO\FileStream;

class Driver extends DatabaseDriver
{
    /**
     * @var string Gets or sets the identifier escape character
     */
    protected array $identifierEscapeChars = ['`', '`'];

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
     * Quotes an SQL identifier using MySQL's quoting strategy.
     *
     * Supports dot-separated identifiers (e.g., schema.table.column) by quoting each part
     * individually. Special identifiers like `*` are returned as-is without quoting.
     *
     * @param string $identifier The identifier name (e.g., column, table, or schema.table).w
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

        // Check if the identifier is already properly quoted with backticks
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
                throw new InvalidArgumentException("Invalid SQL identifier segment for MySQL: {$segment}");
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
    public function prepareValue(mixed $value): string
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

        // MySQL boolean representation
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
        return 'MySQL';
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): string
    {
        //return $this->connection->query('SELECT version()')->fetchColumn(0);
        return $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    /**
     * @inheritDoc
     */
    public function getDatabaseSize(): int
    {
        // Get database size
        $size = 0;
        $q = $this->connection->query("SHOW TABLE STATUS");
        while ($row = $q->fetch())
        {
            // Views will return null here
            if (!isset($row["Data_length"]) || $row["Data_length"] == null)
                continue;

            $size += $row["Data_length"] + $row["Index_length"];
        }
        return $size;
    }

    /**
     * @inheritDoc
     */
    public function getTableStatus(array $tables): array
    {
        $return = [];

        // Get table sizes
        $q = $this->connection->query("SHOW TABLE STATUS");
        while ($row = $q->fetch())
        {
            // Convert to dictionary for exceptions!
            $row = new Dictionary(false, $row);

            // Skip tables we don't care about
            if (!in_array($row['Name'], $tables))
                continue;

            // Get an accurate row count with InnoDB, since it returns an estimate in STATUS
            if (strtolower($row['Engine']) == 'innodb')
            {
                $quotedName = $this->quoteIdentifier($row['Name']);
                $rowCount = (int)$this->connection->query("SELECT COUNT(*) FROM {$quotedName}")->fetchColumn(0);
            }
            else
            {
                $rowCount = $row['Rows'];
            }

            // Determine size, and output data
            $size = ((float)$row["Data_length"] + (float)$row["Index_length"]);
            $return[] = [
                'name' => $row['Name'],
                'size' => $size,
                'filesize' => Convert::BytesToUnits($size),
                'rows' => number_format($rowCount),
                'avg_row_filesize' => Convert::BytesToUnits($row['Avg_row_length']),
                'avg_row_length' => $row['Avg_row_length'],
                'engine' => $row['Engine']
            ];
        }

        return $return;
    }

    /**
     * @inheritDoc
     */
    public function tableExists(string $table): bool
    {
        $table = $this->connection->quote($table);
        $result = $this->connection->query("SHOW TABLES LIKE {$table}");
        return (!empty($result->fetchAll()));
    }

    /**
     * @inheritDoc
     */
    public function resetAutoIncrement(string $table, int $default = 1): void
    {
        $table = $this->quoteIdentifier($table);
        $this->connection->exec("ALTER TABLE {$table} AUTO_INCREMENT = {$default}");
    }

    /**
     * @inheritDoc
     */
    public function importCSV(string $filename, string $table, string $delimiter = ',', string $enclosure = '"'): void
    {
        // Sanitize inputs with proper quoting
        $table = $this->quoteIdentifier($table);
        $filename = $this->connection->quote($filename, \PDO::PARAM_STR);

        // Sanitize delimiter and enclosure — they should be single characters only
        if (strlen($delimiter) !== 1) {
            throw new \InvalidArgumentException("Delimiter must be a single character.");
        }
        if (strlen($enclosure) !== 1) {
            throw new \InvalidArgumentException("Enclosure must be a single character.");
        }

        $quotedDelimiter = $this->connection->quote($delimiter, \PDO::PARAM_STR);
        $quotedEnclosure = $this->connection->quote($enclosure, \PDO::PARAM_STR);

        $query = "LOAD DATA LOCAL INFILE {$filename} INTO TABLE {$table}"
            . " FIELDS TERMINATED BY {$quotedDelimiter}"
            . " OPTIONALLY ENCLOSED BY {$quotedEnclosure}"
            . " LINES TERMINATED BY '\\n';";

        // Execute the query
        $this->connection->exec($query);
    }

    /**
     * @inheritDoc
     * @throws \System\IO\IOException
     * @throws \System\ObjectDisposedException
     * @throws \Exception
     */
    public function exportCSV(string $filename, string $table, string $delimiter = ','): void
    {
        // Open the file
        $file = new FileStream($filename, FileStream::WRITE);
        $file->truncate();
        $pageSize = 1000;

        // Quote the table name safely
        $quotedTable = $this->quoteIdentifier($table);

        try
        {
            /** @noinspection SqlResolve */
            $count = (int)$this->connection->query("SELECT COUNT(*) FROM {$quotedTable}")->fetchColumn(0);
            $currentIndex = 0;
            while ($count > 0)
            {
                // Table Exists, lets back it up
                /** @noinspection SqlResolve */
                $query = "SELECT * FROM {$quotedTable} LIMIT {$pageSize} OFFSET {$currentIndex};";
                $result = $this->connection->query($query);
                while ($row = $result->fetch())
                {
                    $file->writeCSVLine($row);
                }

                $currentIndex += $pageSize;
                $count -= $pageSize;
            }

            $file->close();
        }
        catch (\Exception $ex)
        {
            $file->close();
            throw $ex;
        }
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
     * Checks if an identifier is already quoted with either backticks (`identifier`)
     * or double quotes ("identifier").
     *
     * @param string $identifier The identifier to check.
     * @return bool True if already quoted, false otherwise.
     */
    private function isAlreadyQuoted(string $identifier): bool
    {
        return (str_starts_with($identifier, '`') && str_ends_with($identifier, '`')) ||
            (str_starts_with($identifier, '"') && str_ends_with($identifier, '"'));
    }

    /**
     * Confirms that quotes in an identifier are properly matched at the start and end.
     *
     * @param string $identifier The identifier to validate.
     * @return bool True if quotes are matched, false otherwise.
     */
    private function hasMatchedQuotes(string $identifier): bool
    {
        // Check for proper backtick (`...`) or double quote ("...") matching
        return (str_starts_with($identifier, '`') && str_ends_with($identifier, '`')) ||
            (str_starts_with($identifier, '"') && str_ends_with($identifier, '"'));
    }

    /**
     * Quotes a single segment of an identifier using MySQL's backtick quoting strategy.
     * Escapes internal backticks by doubling them. Ignores the special `*` identifier.
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

        // Escape internal backticks by replacing ` with ``
        $escapedSegment = str_replace('`', '``', $segment);

        // Wrap the escaped segment with backticks
        return "`{$escapedSegment}`";
    }
}