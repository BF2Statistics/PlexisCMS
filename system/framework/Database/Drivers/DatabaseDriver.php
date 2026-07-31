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
namespace System\Database\Drivers;

use InvalidArgumentException;
use System\Database\DbConnection;

/**
 * Abstract class representing a database driver. This class provides
 * a generic interface for interacting with a database. Specific
 * database drivers should extend this class and implement the abstract
 * methods based on their respective database system.
 *
 * @remarks We use a database driver because some functions of the ASP require fetching
 * information from the database that query's different or uses specific language in
 * the SQL queries that isn't compatible with other databases.
 *
 * @author Steven Wilson
 * @package System\Database\Drivers
 */
abstract class DatabaseDriver
{
    /**
     * @var DbConnection Represents a database connection instance
     */
    protected DbConnection $connection;

    /**
     * Constructor to initialize the database connection
     *
     * @param DbConnection $pdo The DbConnection instance
     * @return void
     */
    public function __construct(DbConnection $pdo)
    {
        $this->connection = $pdo;
    }

    /**
     * Retrieves the namespace associated with the current context.
     *
     * @return string
     */
    public abstract function getNamespace(): string;

    /**
     * Retrieves the character used as an identifier in SQL queries.
     *
     * @return string[]
     */
    public abstract function getIdentifierChars(): array;

    /**
     * Gets the name of the driver in friendly readable format
     *
     * @return string
     */
    public abstract function getDriverDisplayName(): string;

    /**
     * Quotes an SQL identifier according to the drivers specific rules for safe usage
     * in queries. This method helps ensure that identifiers are properly escaped
     * to avoid conflicts with reserved keywords or invalid characters.
     *
     * **Behavior:**
     * - Dot-separated identifiers (e.g., `schema.table.column`) are split and
     *   quoted individually.
     * - Special identifiers like `*` (wildcard) are returned unquoted.
     * - Identifiers that are already quoted are validated to ensure they are correctly formatted.
     *
     * **Examples:**
     * - `quoteIdentifier('users')` -> `"users"`
     * - `quoteIdentifier('schema.table')` -> `"schema"."table"`
     * - `quoteIdentifier('*')` -> `*`
     *
     * @param string $identifier The name of the SQL identifier (e.g., table or column name).
     * @return string The properly quoted identifier.
     *
     * @throws InvalidArgumentException If the identifier contains invalid characters
     *                                  or mismatched quotes.
     */
    public abstract function quoteIdentifier(string $identifier): string;

    /**
     * Prepares a value for safe use in an SQL query by converting it to its string representation,
     * quoting it if necessary, and handling specific types (e.g., booleans, NULL).
     *
     * This method ensures:
     * - Booleans are converted to their appropriate database value.
     * - `NULL` values are translated to the SQL `NULL` keyword.
     * - Numeric values (integers and floats) are returned as-is (no quotes).
     * - Strings are properly escaped and quoted for safe inclusion in SQL queries.
     *
     * @param mixed $value The value to prepare. Can be of any type (string, int, float, bool, null, etc.).
     *
     * @return string The prepared value for SQL. Always returns a string, with special handling
     *                depending on the input type.
     */
    public abstract function prepareValue(mixed $value): string;

    /**
     * Retrieves the database version information.
     *
     * @return string
     */
    public abstract function getVersion(): string;

    /**
     * Retrieves the size of the database.
     *
     * @return int Size of the database in bytes
     */
    public abstract function getDatabaseSize(): int;

    /**
     * Fetches the table status of the specified tables
     *
     * @param string[] $tables An array of tables to fetch the status for
     *
     * @return array formatted as below:
     * <pre>
     * tables[] = [
     *  'name' => name of the table,
     *  'size' => Total size in bytes,
     *  'filesize' => the filesize of the table on the hard disk,
     *  'rows' => total number of rows,
     *  'avg_row_filesize' => the filesize on average of each row on the disk drive,
     *  'avg_row_length' => the total size in bytes of each row on average,
     *  'engine' => The engine the table is using
     * ];
     *</pre>
     * @throws \Exception
     */
    public abstract function getTableStatus(array $tables): array;

    /**
     * Checks whether a specific table exists in the database
     *
     * @param string $table The name of the table to check for existence
     * @return bool
     */
    public abstract function tableExists(string $table): bool;

    /**
     * Resets the auto-increment value for the specified table to a given default value.
     *
     * @param string $table The name of the table whose auto-increment value needs to be reset.
     * @param int $default The default value to reset the auto-increment to. Defaults to 1 if not provided.
     */
    public abstract function resetAutoIncrement(string $table, int $default = 1);

    /**
     * Imports data from a CSV file into a specified database table.
     *
     * @param string $filename The name of the CSV file to be imported.
     * @param string $table The name of the database table where data will be inserted.
     * @param string $delimiter The character used to separate fields in the CSV file. Defaults to ','.
     * @param string $enclosure The character used to enclose fields in the CSV file. Defaults to '"'.
     */
    public abstract function importCSV(string $filename, string $table, string $delimiter = ',', string $enclosure = '"');

    /**
     * Exports data from a specified table into a CSV file.
     *
     * @param string $filename The name of the file where the CSV data will be saved.
     * @param string $table The name of the table to export data from.
     */
    public abstract function exportCSV(string $filename, string $table, string $delimiter = ',');

    /**
     * Detects if a value is a valid SQL placeholder.
     * Supports both unnamed (`?`) and named (`:param`) placeholders.
     *
     * @param string $value The value to check.
     * @return bool True if the value is a placeholder, false otherwise.
     */
    protected function isPlaceholder(string $value): bool
    {
        // Check for unnamed placeholders (?)
        if ($value === '?') {
            return true;
        }

        // Check for named placeholders (e.g., :param)
        if (preg_match('/^:[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
            return true;
        }

        return false;
    }
}