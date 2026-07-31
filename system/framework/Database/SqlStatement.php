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

/**
 * Extends the native `PDOStatement` class to introduce additional features for handling
 * database queries, such as execution tracking, custom exception handling, and a convenient
 * property for checking if query results contain rows.
 *
 * **Features:**
 * - Overrides `PDOStatement::execute()` to track execution status and manage exceptions.
 * - Exposes a read-only `$hasRows` property to quickly check if a query returned any rows.
 * - Simplifies error handling by throwing a custom `SqlException` with detailed context.
 *
 * @package System\Database
 */
class SqlStatement extends \PDOStatement
{
    /**
     * Read-only property to determine if the query returned any rows.
     *
     * Internally, this property checks if the statement has been executed and verifies
     * that the row count is greater than zero.
     *
     * **Behavior:**
     * - Returns `true` only if the statement has been successfully executed (`$isExecuted`)
     *   and `rowCount()` is greater than 0.
     * - Returns `false` if the statement has not been executed or there are no rows.
     *
     * @return bool `true` if rows are present, otherwise `false`.
     */
    public bool $hasRows
    {
        get { return $this->isExecuted && $this->rowCount() > 0; }
    }

    /**
     * Tracks whether the statement has been executed.
     *
     * This property is updated automatically when the `execute()` method is called,
     * giving developers a simple way to check if the query has been run.
     *
     * @var bool `true` if the statement has been executed successfully, otherwise `false`.
     */
    protected(set) bool $isExecuted = false;

    /**
     * Executes the prepared SQL statement with optional parameters.
     *
     * This method overrides `PDOStatement::execute()` to enhance execution tracking and error handling
     * by introducing custom functionality:
     * - Updates the `$isExecuted` property to `true` upon successful execution.
     * - Catches `PDOException` and throws a custom `SqlException` with additional error context
     *   (e.g., error information and query string).
     *
     * **Behavior:**
     * - If successful, the method returns `true` and marks `$isExecuted` as `true`.
     * - If a `PDOException` occurs, the method throws an `SqlException`, providing the
     *   following details:
     *     - Error information (`errorInfo`).
     *     - The executed query string (`queryString`).
     *     - The original exception (`PDOException`).
     *
     * **Examples:**
     * ```php
     * $statement = $db->prepare("SELECT * FROM users WHERE id = :id");
     * $statement->execute([':id' => 1]); // Returns true if successful
     * if ($statement->hasRows) {
     *     // Process results
     * }
     * ```
     *
     * @param array|null $params Optional parameters to bind to the prepared statement.
     *
     * @return bool `true` on successful execution, otherwise `false`.
     *
     * @throws SqlException If a database error occurs during execution.
     */
    public function execute(?array $params = []): bool
    {
        try {
            $result = parent::execute($params);
            $this->isExecuted = true;
            return $result;
        }
        catch (\PDOException $e) {
            throw new SqlException($e->errorInfo, $this->queryString, $e);
        }
    }
}