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

use System\Database\Drivers\DbConnectionStringBuilder;

/**
 * Generates connection strings used to connect to Microsoft SQL Server databases.
 *
 * @package System\Database
 */
class ConnectionStringBuilder extends DbConnectionStringBuilder
{
    /**
     * @var string The default driver name for the SQL Server driver class
     */
    protected string $driverName = 'MSSQL';

    /**
     * @param array $metadata
     */
    public function __construct(array $metadata)
    {
        parent::__construct($metadata);

        // Set SQL Server-specific attributes if required
    }

    /**
     * @inheritDoc
     */
    public function getDriverName(): string
    {
        return $this->driverName;
    }

    /**
     * @inheritdoc
     */
    public function getConnectionString(): string
    {
        $connectionString = "sqlsrv:Server={$this->host}";

        // Add port if exists
        if (!empty($this->port)) {
            $connectionString .= ",{$this->port};";
        } else {
            $connectionString .= ";";
        }

        // Add database
        if (!empty($this->database)) {
            $connectionString .= "Database={$this->database};";
        }

        return $connectionString;
    }

    /**
     * @inheritDoc
     */
    public function getDatabaseCreateString(string $databaseName): string
    {
        // Remove the quotes
        $databaseName = str_replace(['"', "'", '`'], "", $databaseName);
        return "CREATE DATABASE [{$databaseName}]";
    }

    /**
     * @inheritDoc
     */
    public function getUseDatabaseString(string $databaseName): string
    {
        // Remove the quotes
        $databaseName = str_replace(['"', "'", '`'], "", $databaseName);
        return "USE [{$databaseName}]";
    }

    /**
     * @inheritDoc
     *
     * With emulate prepares set to true, the security of parameterized queries isn't in effect.
     * Therefore, your application should ensure that the data that is bound to the parameters doesn't
     * contain malicious Transact-SQL code.
     */
    public function emulatePrepares(bool $do): void
    {
        // see: https://learn.microsoft.com/en-us/sql/connect/php/pdo-prepare?view=sql-server-ver16
        // MSSQL does indeed support prepare emulation
        $this->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $do);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getConnectionString();
    }
}
