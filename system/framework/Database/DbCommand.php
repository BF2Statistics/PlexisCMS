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

/**
 * Represents a database command that allows executing queries and managing query parameters.
 *
 * This class provides methods to prepare SQL statements, execute them, bind parameters,
 * and retrieve results or error information. It works with a database connection and leverages
 * PDO for handling database operations.
 *
 * @package System\Database
 */
class DbCommand
{
    /**
     * @var DbConnection The database connection instance used by this command.
     */
    protected(set) DbConnection $connection;

    /**
     * @var string The SQL query string from the prepared statement.
     */
    public string $queryString
    {
        get { return $this->statement->queryString; }
    }

    /**
     * @var SqlStatement|false The prepared SQL statement or false if preparation failed.
     */
    protected SqlStatement|false $statement;

    /**
     * @var bool Indicates whether the SQL statement has been successfully executed.
     */
    public bool $isExecuted
    {
        get { return $this->statement?->isExecuted ?? false; }
    }

    /**
     * Constructs a new `DbCommand` instance.
     *
     * Prepares an SQL statement for execution and initializes the associated
     * database connection.
     *
     * @param string $query The SQL query to prepare.
     * @param DbConnection $connection The database connection object.
     *
     * @throws SqlException If the query preparation fails.
     */
    public function __construct(string $query, DbConnection $connection)
    {
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection = $connection;

        /*
         * In some drivers rowCount() only works when using the prepare() with PDO::CURSOR_SCROLL
         */
        $this->statement = $this->connection->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
    }

    /**
     * Executes the prepared SQL statement.
     *
     * Optionally, an array of parameters can be passed to bind values dynamically to the
     * SQL query before execution.
     *
     * @param array|null $params An optional array of parameters to bind to the SQL query.
     * If no parameters are provided, the statement is executed with previously bound values.
     *
     * @return bool Returns `true` if the statement executes successfully, `false` otherwise.
     *
     * @throws SqlException If the statement execution fails.
     */
    public function execute(?array $params = null): bool
    {
        return $this->statement->execute($params);
    }

    /**
     * Executes the statement and returns a data reader for the result set.
     *
     * Allows iteration over the result set using the returned `DbDataReader` instance.
     *
     * @param array|null $params An optional array of parameters to bind prior to execution.
     *
     * @return DbDataReader|false A `DbDataReader` instance to iterate over the result set,
     * or `false` if the execution fails.
     *
     * @throws SqlException If the query fails during execution.
     */
    public function executeReader(?array $params = null): DbDataReader|false
    {
        $result = $this->statement->execute($params);
        if (!$result) {
            return false;
        }

        return new DbDataReader($this->statement);
    }

    /**
     * Binds a parameter to the specified SQL query variable name or position.
     *
     * This method allows setting up parameterized SQL queries dynamically to
     * protect against SQL injection and to replace placeholders with values.
     *
     * @param string|int $key The parameter identifier. For a named parameter, use a string like `:paramName`.
     *                        For a positional parameter, provide an integer.
     * @param mixed $value The value to bind to the parameter.
     * @param int $type [optional] The data type of the parameter. Defaults to `PDO::PARAM_STR`.
     *
     * @return bool Returns `true` on success, `false` otherwise.
     */
    public function bindParam(string|int $key, mixed &$value, int $type = PDO::PARAM_STR): bool
    {
        return $this->statement->bindParam($key, $value, $type);
    }

    /**
     * Binds a value to a parameter in the prepared statement.
     *
     * @param string|int $key The parameter identifier (e.g., `:paramName`).
     * @param mixed $value The value to bind.
     * @param int $type [optional] The data type of the parameter. Defaults to `PDO::PARAM_STR`.
     *
     * @return bool Returns true on success, or false on failure.
     */
    public function bindValue(string|int $key, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return $this->statement->bindValue($key, $value, $type);
    }

    /**
     * Starts a database transaction.
     *
     * @return bool Returns true if the transaction is started successfully.
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commits the current transaction.
     *
     * @return bool Returns true on success, or false on failure.
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Rolls back the current transaction.
     *
     * @return bool Returns true on success, or false on failure.
     */
    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Retrieves the error information from the last operation on the database.
     *
     * Returns an array containing the SQLSTATE code, driver error code, and driver error message.
     *
     * @return array An array with error information from the last database operation:
     * - `0`: SQLSTATE error code.
     * - `1`: Driver-specific error code.
     * - `2`: Driver-specific error message.
     */
    public function getErrorInfo(): array
    {
        return $this->statement->errorInfo();
    }

    /**
     * Retrieves the current PDO statement instance.
     *
     * @return SqlStatement|null The PDO statement object if available, or null if no statement is set.
     */
    public function getStatement(): SqlStatement|null
    {
        return $this->statement;
    }
}