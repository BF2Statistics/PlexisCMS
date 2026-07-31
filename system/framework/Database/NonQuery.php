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

use RuntimeException;

/**
 * Represents an abstract base class for non-query SQL operations, such as
 * UPDATE, INSERT, or INSERT ON DUPLICATE KEY UPDATE.
 * Provides methods to build and execute SQL statements on a specified
 * database table by dynamically handling columns, values, and conditions.
 */
abstract class NonQuery
{
    /**
     * @var string The table we are modifying
     */
    public string $table = '';

    /**
     * @var array An array of [Key => [operator => value]]
     */
    protected array $columns = array();

    /**
     * @var array An array of [Key => [operator => value]]
     */
    protected array $where = array();

    /**
     * @var string Specified the AND / OR where statement separator
     */
    public string $whereSeparator = "AND";

    /**
     * @var DbConnection
     */
    protected DbConnection $connection;

    /**
     * @var NonQueryMode
     */
    protected NonQueryMode $queryMode;

    /**
     * @var array Conditions array used by ConditionGroup
     */
    public array $conditions = [];

    /**
     * @var array Bindings array used by ConditionGroup
     */
    public array $bindings = [];

    /**
     * Initializes a new instance of the class with the specified database connection, table, and non-query mode.
     *
     * @param DbConnection $connection The database connection to be used.
     * @param string $table The name of the table associated with this instance.
     * @param NonQueryMode $nonQueryMode The operational mode for non-query operations.
     *
     * @return void
     */
    public function __construct(DbConnection $connection, string $table, NonQueryMode $nonQueryMode)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->queryMode = $nonQueryMode;
    }

    /**
     * Quotes a column name for safe use in queries.
     */
    public function quoteColumn(string $column): string
    {
        return ($column === '*') ? '*' : $this->connection->quoteIdentifier($column);
    }

    /**
     * Sets a column and value
     *
     * @param string $column The column name
     * @param string $operator The comparison operator to use. Supported operators include:
     *                          '=' (equal), '<' (less than), '>' (greater than),
     *                          '<=' (less than or equals), '>=' (greater than or equals),
     *                          '+' or '+=' (increment existing value by value),
     *                          '-' or '-=' (decrement existing value by value),
     *                          '<~' (keep the lesser of the current value and given value),
     *                          '~>' (keep the greater of the current value and given value).
     * @param mixed $value The new value
     */
    public function set(string $column, string $operator, mixed $value): self
    {
        // Correct bool values
        if (is_bool($value))
            $value = ($value) ? 1 : 0;

        $this->columns[$column] = array($operator, $value);
        return $this;
    }

    /**
     * Sets multiple columns and their respective values for various SQL non-query operations.
     *
     * This method processes an array of column-value pairs to prepare them for use
     * in SQL operations such as INSERT, UPDATE, UPSERT, or DELETE. It dynamically determines
     * whether each value is:
     * - A simple value (for INSERT).
     * - An array containing an operator and a value (for UPDATE, UPSERT, or DELETE).
     *
     * - For **INSERT operations**, the `$pairs` array should be a key-value collection,
     *   where the key is the column name and the value is the value to insert.
     *
     * - For **UPDATE, UPSERT, or DELETE operations**, if a value in the `$pairs` array is:
     *   - An array, it should be in the format `['operator', $value]`, where `operator` is
     *     the SQL operation (e.g., `=`, `+=`, `-=`), and `$value` is the data to use.
     *   - Not an array, the operator defaults to `=` and the value is treated as the value to set.
     *
     * ### Usage Examples
     *
     * **INSERT:**
     * ```
     * $query->setValues([
     *     'name' => 'John Doe',
     *     'age' => 30,
     * ]);
     * ```
     *
     * **UPDATE/UPSERT/DELETE with operators:**
     * ```
     * $query->setValues([
     *     'age' => ['+=', 1],                // Increment age by 1
     *     'last_login' => ['=', '2025-01-01'], // Set last_login to a specific date
     * ]);
     * ```
     *
     * **UPDATE/UPSERT/DELETE with default operator (=):**
     * ```
     * $query->setValues([
     *     'age' => 30, // Defaults to ['=', 30]
     * ]);
     * ```
     *
     * @param array $pairs An associative array where:
     *   - For INSERT, it is a key-value collection: `'column_name' => 'value'`.
     *   - For UPDATE, UPSERT, or DELETE:
     *     - If the value is an array, it should be formatted as `['operator', 'value']`.
     *     - If the value is not an array, it defaults to `['=', $value]`.
     *
     * @return self Returns the current instance for method chaining.
     */

    public function setValues(array $pairs): self
    {
        foreach ($pairs as $column => $value)
        {
            if (is_array($value))
            {
                $this->set($column, $value[0], $value[1]);
            }
            else
            {
                $this->set($column, '=', $value);
            }
        }

        return $this;
    }

    /**
     * Creates a new ConditionGroup to handle query conditions and adds the specified column as a condition.
     *
     * @param mixed $column The column or condition to be added to the new ConditionGroup.
     *
     * @return ConditionGroup The ConditionGroup instance with the specified condition applied.
     */
    public function where(string $column): ConditionGroup
    {
        $group = new ConditionGroup($this);
        return $group->and($column);
    }

    /**
     * Executes the appropriate database operation based on the query mode.
     *
     * @return int The number of rows affected by the executed query.
     *
     * @throws \InvalidArgumentException If the query mode is not supported.
     * @throws RuntimeException If any of the query-specific methods encounter an error during execution.
     * @throws SqlException
     */
    public function execute(): int
    {
        // Perform action based on the query type
        return match ($this->queryMode) {
            NonQueryMode::Insert => $this->executeInsert(),
            NonQueryMode::Update => $this->executeUpdate(),
            NonQueryMode::Upsert => $this->executeUpsert(),
            NonQueryMode::Delete => $this->executeDelete(),
        };
    }


    /**
     * Executes an UPDATE SQL query on the database with the specified column updates and conditions.
     * The method prepares the UPDATE statements by dynamically generating SQL for each column,
     * applies conditional operations based on provided operators, and ensures that a WHERE clause is present.
     *
     * @return int The number of rows affected by the executed UPDATE query.
     *
     * @throws RuntimeException If the WHERE clause is absent in the query.
     *
     * @throws SqlException
     */
    protected function executeUpdate(): int
    {
        // Build the UPDATE statements for columns
        $updates = [];
        foreach ($this->columns as $col => $values)
        {
            // Values should contain the operator and the operand value
            [$operator, $value] = $values;

            // Quote the identifier for the column name
            $col = $this->connection->quoteIdentifier($col);

            // Quote the value if it's not numeric
            $value = is_numeric($value) ? $value : $this->prepareValue($value);

            // Handle operations based on the operator
            $updates[] = match (strtolower($operator)) {
                "+", "+=" => "{$col} = {$col} + {$value}", // Increment
                "-", "-=" => "{$col} = {$col} - {$value}", // Decrement
                "~>"      => "{$col} = CASE WHEN {$value} > {$col} THEN {$value} ELSE {$col} END", // Keep greater value
                "<~"      => "{$col} = CASE WHEN {$value} < {$col} THEN {$value} ELSE {$col} END", // Keep lesser value
                default   => "{$col} = {$value}", // Default assignment or unsupported operator
            };
        }

        // Join the update statements with commas
        $updatesSQL = implode(", ", $updates);

        // Build the WHERE clause
        $whereClause = $this->buildWhereClause();
        if (empty($whereClause)) {
            throw new RuntimeException("WHERE clause is required for an UPDATE query.");
        }

        // Construct the full SQL UPDATE statement
        $sql = "UPDATE {$this->connection->quoteIdentifier($this->table)} SET {$updatesSQL} WHERE {$whereClause}";

        // Execute the query
        return $this->connection->exec($sql);
    }


    /**
     * Executes an SQL query to insert data into the specified table.
     *
     * @return int The number of rows affected by the execution of the SQL statement.
     *
     * @throws SqlException
     */
    protected function executeInsert(): int
    {
        // Extract column names and properly quote them as identifiers
        $columns = implode(", ", array_map([$this, 'quoteIdentifier'], array_keys($this->columns)));

        // Extract and quote the values
        $values = $this->buildValuesList();

        // Build the SQL query
        $sql = "INSERT INTO {$this->connection->quoteIdentifier($this->table)} ({$columns}) VALUES ({$values})";

        // Execute the query and return the number of affected rows
        return $this->connection->exec($sql);
    }

    /**
     * Executes an SQL query to insert data into the specified table or update existing rows if a duplicate key is found.
     *
     * @return int The number of rows affected by the execution of the SQL statement.
     *
     * @throws SqlException
     */
    abstract protected function executeUpsert(): int;

    /**
     * Executes a DELETE statement to remove rows from the database.
     *
     * @return int The number of rows affected by the DELETE query.
     * @throws RuntimeException If no WHERE clause is specified to safeguard against accidental deletion.
     * @throws SqlException
     */
    protected function executeDelete(): int
    {
        // Ensure the WHERE clause is present to prevent accidental deletion of all rows
        if (empty($this->where) && empty($this->conditions)) {
            throw new RuntimeException("Cannot execute DELETE query without a WHERE clause to prevent accidental deletion of all rows.");
        }

        // Build the WHERE clause
        $filter = $this->buildWhereClause();

        // Build the DELETE SQL query
        $sql = "DELETE FROM {$this->connection->quoteIdentifier($this->table)} WHERE {$filter}";

        // Execute and return the number of affected rows
        return $this->connection->exec($sql);
    }


    /**
     * Constructs and returns a SQL WHERE clause string based on the current conditions.
     *
     * @return string The compiled WHERE clause as a string.
     */
    protected function buildWhereClause(): string
    {
        // First check the new conditions array (from ConditionGroup)
        if (!empty($this->conditions))
        {
            $sqlParts = array_map(
                fn($condition) => "{$condition['type']} {$condition['condition']}",
                $this->conditions
            );
            $whereClause = implode(' ', $sqlParts);
            return preg_replace('/^(AND|OR) /', '', trim($whereClause));
        }

        // Fall back to legacy where array
        $clauses = [];
        foreach ($this->where as $col => $values) {
            [$operator, $value] = $values;
            $clauses[] = "{$this->quoteIdentifier($col)} {$operator} " . $this->prepareValue($value);
        }
        return implode(" {$this->whereSeparator} ", $clauses);
    }

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
    protected function prepareValue(mixed $value): string
    {
        return $this->connection->prepareValue($value);
    }

    /**
     * Quotes a given identifier to ensure it is safely used in database queries.
     *
     * @param string $identifier The identifier to be quoted.
     *
     * @return string The quoted identifier.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return $this->connection->quoteIdentifier($identifier);
    }

    /**
     * Builds a comma-separated list of values for the columns used in a SQL query. These values are quoted for
     * SQL safety
     *
     * @return string A comma-separated list of quoted values or NULL placeholders derived from the columns array.
     */
    protected function buildValuesList(): string
    {
        return implode(", ", array_map(function ($column) {
            return $this->prepareValue($column[1]);
        }, $this->columns));
    }
}