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
use Exception;

/**
 * Represents an exception thrown during the execution of an SQL query.
 *
 * This exception is intended to provide meaningful details about
 * the error that occurred, including the SQL query executed and
 * error information returned by the database driver.
 */
class SqlException extends Exception
{
    protected string $query;
    protected array $errorInfo;

    public function __construct(array $errorInfo, string $query, ?\Exception $previous = null)
    {
        parent::__construct($errorInfo[2], 0, $previous);
        $this->query = $query;
        $this->errorInfo = $errorInfo;
    }

    public function getPdoCode()
    {
        return $this->errorInfo[0];
    }

    public function getDriverCode()
    {
        return $this->errorInfo[1];
    }

    public function getQuery()
    {
        return $this->query;
    }
}