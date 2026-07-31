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

use PDO;
use System\Database\Drivers\DatabaseDriver;
use System\Database\Drivers\DbConnectionStringBuilder;

/**
 * Represents an enhanced database connection built on the PDO class,
 * offering additional abstractions and features for interacting with
 * databases in a safe and efficient way.
 *
 * **Features Include:**
 * - Transaction handling (begin, commit, rollback).
 * - Query execution and table management tools.
 * - Integration with database drivers for specific SQL dialects or optimizations.
 * - Advanced options for query building and non-query operations (e.g., INSERT, UPDATE).
 *
 * @package System\Database
 * @extends PDO
 */
class DbConnection extends PDO
{
    /**
     * @var string Stores the last query that was executed on the database.
     * This can be useful for debugging or tracking executed queries.
     */
    public string $lastQuery;

    /**
     * @var DbConnectionStringBuilder An instance of the connection string builder
     * that holds configuration details for connecting to the database.
     */
    protected DbConnectionStringBuilder $builder;

    /**
     * @var DatabaseDriver An instance of the database driver being used to
     * manage interactions with the database.
     */
    protected DatabaseDriver $driver;

    /**
     * @var string The name of the database driver currently in use (e.g., MySQL, SQLite).
     */
    protected(set) string $driverName;

    /**
     * @var bool Indicates if there is an active transaction for the current connection.
     */
    protected(set) bool $hasActiveTransaction = false;

    /**
     * Constructor for the DbConnection class.
     *
     * Initializes the database connection using a connection string builder.
     * It sets up the necessary attributes for the connection and initializes
     * the driver for handling database-specific operations.
     *
     * @param DbConnectionStringBuilder $builder The connection string builder that
     * provides necessary information for establishing the database connection.
     */
    public function __construct(DbConnectionStringBuilder $builder)
    {
        // Connect using the PDO Constructor
        parent::__construct(
            $builder->getConnectionString(),
            $builder->user,
            $builder->password,
            $builder->getConnectAttributes()
        );

        // Use the SqlStatement class
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [SqlStatement::class]);

        // Store the connection string builder
        $this->builder = $builder;
        $this->driverName = $builder->getDriverName();

        // Load driver
        $driverName = '\System\Database\Drivers\\'. $builder->getDriverName() . '\Driver';
        $this->driver = new $driverName($this);
    }

    /**
     * Starts a database transaction.
     *
     * Transactions allow creating a group of queries that either all execute successfully,
     * or roll back if one fails.
     *
     * @return bool Returns true if the transaction is started successfully.
     *
     * @throws \PDOException If the driver does not support transactions.
     */
    public function beginTransaction(): bool
    {
        if ($this->hasActiveTransaction) {
            return true;
        }

        $this->hasActiveTransaction = parent::beginTransaction();
        return $this->hasActiveTransaction;
    }

    /**
     * Commits the current transaction.
     *
     * @return bool Returns true if the transaction is committed successfully,
     * or false otherwise.
     */
    public function commit(): bool
    {
        if (!$this->hasActiveTransaction) {
            return false;
        }

        $result = parent::commit();
        $this->hasActiveTransaction = false; // Reset
        return $result;
    }

    /**
     * Rolls back the current transaction.
     *
     * @return bool Returns true if the transaction is rolled back successfully,
     * or false otherwise.
     */
    public function rollBack(): bool
    {
        if (!$this->hasActiveTransaction) {
            return false;
        }

        $result = parent::rollBack();
        $this->hasActiveTransaction = false; // Reset
        return $result;
    }

    /**
     * Executes a callback within a database transaction.
     * Automatically commits on success or rolls back on exception.
     *
     * @param callable $callback The callback to execute within the transaction.
     *                           Receives this DbConnection as its argument.
     * @return mixed The return value of the callback.
     *
     * @throws \Throwable Re-throws any exception after rolling back.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try
        {
            $result = $callback($this);
            $this->commit();
            return $result;
        }
        catch (\Throwable $e)
        {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Executes an SQL statement and returns the number of affected rows.
     *
     * @param string $statement The SQL statement to execute.
     *
     * @return false|int The number of affected rows, or false on failure.
     *
     * @throws SqlException If an SQL error occurs.
     */
    public function exec(string $statement): false|int
    {
        try {
            return parent::exec($statement);
        }
        catch (\PDOException $e) {
            throw new SqlException($e->errorInfo, $statement, $e);
        }
    }

    /**
     * Prepares an SQL statement for execution.
     *
     * @param string $query The SQL query string to prepare.
     * @param array $options Optional attributes for the prepared statement.
     *
     * @return SqlStatement|false A prepared statement, or false on failure.
     *
     * @throws SqlException If an SQL error occurs.
     */

    public function prepare(string $query, array $options = []): SqlStatement|false
    {
        try {
            return parent::prepare($query, $options);
        }
        catch (\PDOException $e) {
            throw new SqlException($e->errorInfo, $query, $e);
        }
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string|null $name Name of the sequence object (required for PostgreSQL).
     *
     * @return string|false The last insert ID, or false on failure.
     */
    public function getLastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertId($name);
    }

    /**
     * Sets the table for the query.
     *
     * @param string $table The name of the table to query.
     *
     * @return QueryBuilder Returns an instance of the QueryBuilder.
     */
    public function from(string $table): QueryBuilder
    {
        $className = $this->driver->getNamespace() . '\QueryBuilder';
        return new $className($this)->from($table);
    }

    /**
     * Deletes rows from the specified table.
     *
     * @param string $table The name of the table from which rows will be deleted.
     *
     * @return NonQuery The non-query operation object for the delete action.
     */
    public function delete(string $table): NonQuery
    {
        return $this->createNonQuery($table, NonQueryMode::Delete);
    }

    /**
     * Inserts data into a specified table.
     *
     * @param string $table The name of the table where the data will be inserted.
     *
     * @return NonQuery Returns a NonQuery instance representing the insertion operation.
     */
    public function insert(string $table): NonQuery
    {
        return $this->createNonQuery($table, NonQueryMode::Insert);
    }

    /**
     * Performs an update operation on the specified table.
     *
     * @param string $table The name of the table to update.
     *
     * @return NonQuery Returns an instance of NonQuery configured for the update operation.
     */
    public function update(string $table): NonQuery
    {
        return $this->createNonQuery($table, NonQueryMode::Update);
    }

    /**
     * Performs an upsert operation on the specified table.
     *
     * @param string $table The name of the table on which the upsert operation is performed.
     *
     * @return NonQuery An instance of NonQuery representing the upsert operation.
     */
    public function upsert(string $table): NonQuery
    {
        return $this->createNonQuery($table, NonQueryMode::Upsert);
    }

    /**
     * Creates a new NonQuery instance for the specified table.
     *
     * @param string $table The name of the table for which the NonQuery instance is to be created.
     *
     * @return NonQuery Returns an instance of the NonQuery class.
     */
    public function createNonQuery(string $table, NonQueryMode $mode): NonQuery
    {
        $className = $this->driver->getNamespace() . '\NonQuery';
        return new $className($this, $table, $mode);
    }

    /**
     * Quotes an SQL identifier using the current driver's quoting strategy
     *
     * @param string $identifier The identifier name
     *
     * @return string The identifier wrapped in driver specific quotes
     */
    public function quoteIdentifier(string $identifier): string
    {
        return $this->driver->quoteIdentifier($identifier);
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
    public function prepareValue(mixed $value): string
    {
        return $this->driver->prepareValue($value);
    }

    /**
     * Selects the given database name for use
     *
     * @param string $databaseName The name of the database being selected.
     * @param bool $createDatabase If true, the database will be created if it does not exist already.
     *
     * @return void
     */
    public function selectDatabase(string $databaseName, bool $createDatabase): void
    {
        // Create database if not existing already?
        if ($createDatabase)
        {
            $str = $this->builder->getDatabaseCreateString($databaseName);
            $this->lastQuery = $str;
            $this->exec($str);
        }

        // Finally select the database
        $str = $this->builder->getUseDatabaseString($databaseName);
        $this->lastQuery = $str;
        $this->exec($str);
    }

    /**
     * Retrieves the database driver instance.
     *
     * @return DatabaseDriver The driver instance associated with the database connection.
     */
    public function getDriver(): DatabaseDriver
    {
        return $this->driver;
    }
}