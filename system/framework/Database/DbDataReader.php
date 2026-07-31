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
namespace System\Database;

use Countable;
use DateTime;
use Exception;
use Generator;
use Iterator;
use PDO;
use RuntimeException;
use System\FormatException;
use System\IndexOutOfRangeException;

/**
 * Class DbDataReader
 *
 * The `DbDataReader` class provides a mechanism for reading data from SQL result sets.
 * It allows for row-by-row traversal using `read()` and access to individual column
 * values through type-specific methods (`getString`, `getInt`, `getBool`, etc.).
 *
 *  ## Features:
 *  - **Row Traversal**: Allows row-by-row traversal using the `read()` and `readRows()` methods.
 *  - **Chunked Fetching**: Fetches rows in memory-efficient chunks with `readChunks()`.
 *  - **Iterator Support**: Implements the `Iterator` interface for seamless integration with `foreach`.
 *  - **Countable Interface**: Implements `Countable` so you can determine the total number of rows in the result set.
 *  - **`DbRow` Integration**: Each row is encapsulated in a `DbRow` object, providing safer, more descriptive access to column data.
 *  - **Memory Optimization**: Supports lazy fetching for large result sets to minimize memory consumption.
 *
 *  ## Recommended Usage:
 *  - Use `read()` or `readRows()` for row-by-row processing.
 *  - Use `readChunks()` for batch processing with lower memory usage.
 *  - Avoid using `readAll()` with large result sets as it loads all rows into memory.
 *  - Check the `hasRows` property to determine if the result set contains any rows.
 *
 * @package        System\Database
 * @author         Steven Wilson
 * @copyright      Copyright 2025, Steven Wilson, All rights reserved.
 * @license        GNU GPL v3
 * @requires       PHP 8.4.2 or newer
 * @property-read SqlStatement $statement The SQL statement associated with the reader.
 * @property-read DbRow|null   $row       The current row being read, or null if no row is available.
 */
class DbDataReader implements Iterator, Countable
{
    /**
     * @var SqlStatement The SQL statement used to generate the result set.
     */
    protected SqlStatement $statement;

    /**
     * @var DbRow|null The currently active row in the result set.
     *                 This will be null when the reader has not been advanced or
     *                 if there are no more rows to read.
     */
    private ?DbRow $row = null;

    /**
     * @var int
     */
    private int $position = 0;

    /**
     * @var bool Determines whether iteration is currently active (used in foreach).
     */
    private bool $isIterating = false;

    /**
     * Indicates whether the SQL result set contains any rows. This property provides a read-only
     * boolean value derived from the associated SQL statement.
     *
     * @property-read bool $hasRows True if the result set contains rows, false otherwise.
     */
    public bool $hasRows
    {
        get { return $this->statement->hasRows; }
    }

    /**
     * Constructor.
     *
     * Initializes the `DbDataReader` with a given `SqlStatement`.
     *
     * @param SqlStatement $statement The executed SQL statement generating the result set.
     */
    public function __construct(SqlStatement $statement)
    {
        if (!$statement->isExecuted) {
            throw new RuntimeException("The SQL statement must be executed before passing to DbDataReader.");
        }

        $this->statement = $statement;
        $this->statement->setFetchMode(PDO::FETCH_BOTH);
    }

    /**
     * Advances the reader to the next row in the result set.
     *
     * This method prepares the next row for reading. If there are no more rows
     * available, it returns `false`. Otherwise, it returns `true`, and the current
     * row becomes accessible.
     *
     * @return bool `true` if another row is available; `false` if the end of the result set is reached.
     */
    public function read(): bool
    {
        return $this->fetchNextRow();
    }

    /**
     * Fetches the next row from the executed statement.
     *
     * This method retrieves the next row from the result set. If there are no more rows
     * available, it returns `false`. Otherwise, it returns `true`, and the current
     * row becomes accessible.
     *
     * @return bool `true` if another row is available; `false` if the end of the result set is reached.
     */
    private function fetchNextRow(): bool
    {
        $items = $this->statement->fetch();
        if ($items === false)
            return false;

        $this->row = new DbRow($items);
        return true;
    }

    /**
     * Fetches all rows from the executed statement into an array.
     *
     * This method retrieves the entire result set at once, returning all rows in an array.
     * This is particularly useful when working with smaller datasets or when traversal
     * of all rows is desired without manual iteration.
     *
     * It is recommended `NOT` to use this method when working with large datasets, and instead using
     * `readChunks`, `read` or `readRows`
     *
     * @return array An array of all rows in the result set.
     */
    public function readAll(): array
    {
        // Ensure we aren't calling this during an active iteration
        if ($this->isIterating) {
            throw new RuntimeException("Cannot call 'readAll' while actively iterating the DbDataReader.");
        }

        $rows = $this->statement->fetchAll(PDO::FETCH_BOTH);
        return array_map(fn($row) => new DbRow($row), $rows);
    }

    /**
     * Fetches rows one by one as `DbRow` objects.
     *
     * @return Generator Yields each row as an instance of `DbRow`.
     */
    public function readRows(): Generator
    {
        // Ensure we aren't calling this during an active iteration
        if ($this->isIterating) {
            throw new RuntimeException("Cannot call 'readRows' while actively iterating the DbDataReader.");
        }

        while ($this->read())
        {
            yield $this->row;
        }
    }

    /**
     * Fetches rows in chunks to optimize memory usage for large result sets.
     *
     * Each chunk is an array of `DbRow` objects.
     * Use this method for batch processing instead of loading the entire result set into memory.
     *
     * @param int $chunkSize The size of each chunk to fetch.
     *
     * @return Generator Yields an array of `DbRow` objects in chunks.
     */

    public function readChunks(int $chunkSize = 100): Generator
    {
        // Ensure we aren't calling this during an active iteration
        if ($this->isIterating) {
            throw new RuntimeException("Cannot call 'readChunks' while actively iterating the DbDataReader.");
        }

        $chunk = [];
        while ($items = $this->statement->fetch(PDO::FETCH_BOTH))
        {
            $chunk[] = new DbRow($items);

            if (count($chunk) >= $chunkSize)
            {
                yield $chunk;
                $chunk = [];
            }
        }

        // Yield any remaining rows
        if (!empty($chunk))
        {
            yield $chunk;
        }
    }

    /**
     * Retrieves the value of the specified column from the current row.
     *
     * This method fetches the value from the current row using the column's name
     * (associative access) or its numeric index (positional access).
     *
     * If no row is currently active, or if the column does not exist, an exception will be thrown.
     *
     * @param string|int $col The column name or index to fetch the value from.
     * @return mixed The value of the specified column, or `false` if there is no row available.
     *
     * @throws IndexOutOfRangeException If an invalid column is specified.
     * @throws RuntimeException If there is no row to retrieve a column value from.
     */
    public function getValue(string|int $col): mixed
    {
        // Make sure we don't have a false return
        if (empty($this->row))
            throw new RuntimeException("No row available to retrieve the column value.");

        return $this->row->get($col);
    }

    /**
     * Retrieves the value of the specified column from the current row or returns a default value if the
     * column value is not available.
     *
     * @param string|int $col The column name or index to fetch the value from.
     * @param mixed $default The default value to return if the column value cannot be retrieved.
     *
     * @return mixed The value from the specified column, or the default value if the column value is not available.
     *
     * @throws RuntimeException If no row is available to retrieve the column value.
     */
    public function getValueOrDefault(string|int $col, mixed $default): mixed
    {
        // Make sure we don't have a false return
        if (empty($this->row))
            throw new RuntimeException("No row available to retrieve the column value.");

        try {
            return $this->row->get($col) ?? $default;
        }
        catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Retrieves and returns the current row data as an accessible object.
     *
     * The returned object implements `ArrayAccess` and allows for easy access
     * to column values.
     *
     * @return DbRow|null The current row as an `ArrayAccess` object, or null if no row is active.
     */
    public function getValues(): ?DbRow
    {
        return $this->row;
    }

    /**
     * Retrieves the value of the specified column and converts it to a string.
     *
     * This method is useful for ensuring type-safe string retrieval, even for
     * non-string input values.
     *
     * @param string|int $col The column name or index to fetch from.
     *
     * @return string The column value as a string.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     */
    public function getString(string|int $col): string
    {
        return (string) $this->getValue($col);
    }

    /**
     * Retrieves the value of the specified column and returns it as a boolean.
     *
     * @param string|int $col The name or index of the column to retrieve the value from.
     *
     * @return bool The boolean representation of the column's value.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     */
    public function getBool(string|int $col): bool
    {
        return (bool) $this->getValue($col);
    }

    /**
     * Retrieves a nullable boolean value from the specified column.
     *
     * @param string|int $col The column name or index to fetch the value from.
     *
     * @return bool|null The value as a boolean if not null, otherwise null.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     */
    public function getBoolNullable(string|int $col): ?bool
    {
        $value = $this->getValue($col);
        return $value === null ? null : (bool) $value;
    }

    /**
     * Retrieves and returns the value of the specified column as an integer.
     *
     * @param string|int $col The column name or index from which to retrieve the value.
     *
     * @return int The value of the specified column cast to an integer.
     *
     * @throws IndexOutOfRangeException
     */
    public function getInt(string|int $col): int
    {
        return (int) $this->getValue($col);
    }

    /**
     * Retrieves and returns the value of the specified column as a float.
     *
     * @param string|int $col The column identifier, either as a string or integer.
     *
     * @return float The value of the specified column cast to a float.
     *
     * @throws IndexOutOfRangeException
     */
    public function getFloat(string|int $col): float
    {
        return (float) $this->getValue($col);
    }

    /**
     * Retrieves the value of the specified column and returns it as a DateTime object.
     *
     * @param string|int $col The column name or index.
     *
     * @return DateTime The DateTime instance.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     * @throws FormatException If the value cannot be converted to a DateTime object.
     */
    public function getDateTime(string|int $col): DateTime
    {
        $value = $this->getValue($col);

        // Explicitly handle null values
        if ($value === null) {
            throw new FormatException("Column '{$col}' contains null, DateTime or UNIX timestamp expected.");
        }

        // If the value is numeric, treat it as an epoch and create a DateTime from it
        if (is_numeric($value)) {
            return (new DateTime())->setTimestamp((int)$value);
        }

        // Otherwise, assume it's a date string and try to parse it
        try {
            return new DateTime($value);
        } catch (Exception $e) {
            // Rethrow as an InvalidTimestampException
            throw new FormatException("Column '{$col}' has invalid date/time format: {$value}", 0, $e);
        }
    }

    /**
     * Retrieves the value of the specified column and returns it as a Unix timestamp.
     *
     * @param string|int $col The column name or index from which the value is retrieved.
     *
     * @return int The Unix timestamp.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     * @throws FormatException if the value cannot be converted to a timestamp.
     */
    public function getTimestamp(string|int $col): int
    {
        $value = $this->getValue($col);

        // Handle null values explicitly
        if ($value === null) {
            throw new FormatException("Column '{$col}' value is null, timestamp expected.");
        }

        // If the value is numeric, treat it as an epoch
        if (is_numeric($value)) {
            return (int) $value;
        }

        // Otherwise, parse a string timestamp
        try {
            $dateTime = new DateTime($value);
            return $dateTime->getTimestamp();
        } catch (Exception $e) {
            // Rethrow as an InvalidTimestampException
            throw new FormatException("Column '{$col}' has invalid timestamp format: {$value}", 0, $e);
        }
    }

    /**
     * Retrieves a value corresponding to the specified column, decodes it as JSON, and returns it as an array.
     *
     * @param string|int $col The column identifier to retrieve the value from.
     *
     * @return array The decoded JSON data as an associative array. Defaults to an empty array if decoding fails.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     * @throws FormatException If the column's value is not a string or if the JSON data is invalid.
     */
    public function getArray(string|int $col): array
    {
        $value = $this->getJson($col);
        if (!is_array($value)) {
            throw new FormatException("Column '{$col}' does not contain a valid array.");
        }

        return $value;
    }

    /**
     * Retrieves and decodes a JSON string from the specified column.
     *
     * @param string|int $col The column name or index containing the JSON string.
     * @param bool $assoc Whether to return the decoded data as an associative array. Defaults to true.
     *
     * @return array|object The decoded JSON data as an associative array or an object, depending on the value of $assoc.
     *
     * @throws FormatException If the column's value is not a string or if the JSON data is invalid.
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     */
    public function getJson(string|int $col, bool $assoc = true): array|object
    {
        $value = $this->getValue($col);
        if (!is_string($value)) {
            throw new FormatException("Column '{$col}' does not contain a JSON string.");
        }

        $decoded = json_decode($value, $assoc);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new FormatException("Invalid JSON data in column '{$col}': " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Retrieves and decodes a JSON object from the specified column value.
     *
     * @param string|int $col The column name or index from which the value is retrieved.
     *
     * @return object The decoded JSON object, or an empty stdClass object if decoding fails.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     */
    public function getObject(string|int $col): object
    {
        $value = $this->getValue($col);
        return json_decode($value) ?? new \stdClass();
    }

    /**
     * Retrieves the decimal representation of the specified column value.
     *
     * @param string|int $col The column identifier from which the value is to be retrieved.
     *
     * @return string The formatted decimal value with 10 decimal places.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     */
    public function getDecimal(string|int $col): string
    {
        return number_format((float) $this->getValue($col), 10, '.', '');
    }

    /**
     * Retrieves the binary string representation of the value from the specified column.
     *
     * @param string|int $col The column identifier, which can be a column name as a string or its numerical index.
     *
     * @return string The value from the specified column cast to a binary string.
     *
     * @throws IndexOutOfRangeException If the given key or index does not exist in the result set.
     */
    public function getBinary(string|int $col): string
    {
        return (string) $this->getValue($col);
    }

    /**
     * Returns the number of columns in the result set.
     */
    public function getColumnCount(): int
    {
        if (!empty($this->row))
            return count($this->row);

        return $this->statement->columnCount();
    }

    /**
     * Returns the number of rows in the result set.
     */
    public function getRowCount(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * Closes the cursor, enabling the statement to be executed again if needed.
     *
     * @return void
     */
    public function close(): void
    {
        $this->statement->closeCursor();
    }

    /**
     * Returns the current row in the result set.
     *
     * @return DbRow|null The current row, or null if none available.
     */
    public function current(): ?DbRow
    {
        return $this->row;
    }

    /**
     * Advances to the next row in the result set.
     *
     * @return void
     */
    public function next(): void
    {
        if ($this->fetchNextRow())
            $this->position++;
        else
            $this->row = null;
    }

    /**
     * Returns the zero-based key/index of the current row.
     *
     * @return int The current row index.
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Checks if the current row is valid (i.e., exists).
     *
     * @return bool Returns `true` if the row is valid, otherwise `false`.
     */
    public function valid(): bool
    {
        $valid = $this->row !== null;
        if (!$valid)
        {
            // Unlock manual methods after iteration ends
            $this->isIterating = false;
        }
        return $valid;
    }

    /**
     * Rewinds the iterator to the first row in the result set.
     *
     * This method is called automatically by PHP when a foreach loop begins. It clears
     * the current row and resets the position tracker.
     *
     * @return void
     */
    public function rewind(): void
    {
        // Prevent rewinding after the iterator has started
        if ($this->position > 0) {
            throw new RuntimeException("The result set cannot be rewound. Data can only be traversed in a forward direction.");
        }

        $this->isIterating = true; // Lock manual methods

        // Preload the first row (if not already done)
        if ($this->row === null)
            $this->fetchNextRow();
    }

    /**
     * Counts the total number of rows in the result set.
     *
     * @return int The total number of rows.
     */
    public function count(): int
    {
        return $this->statement->rowCount();
    }
}