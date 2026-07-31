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
namespace System\Database\Drivers\PostgreSQL;

use Pdo\Pgsql;
use System\Database\Drivers\DbConnectionStringBuilder;

/**
 * Generates connection strings used to connect to PostgreSQL databases.
 *
 * @package System\Database
 */
class ConnectionStringBuilder extends DbConnectionStringBuilder
{
    /**
     * @var string Gets or sets the character set to use.
     */
    public string $charset = "UTF8";

    /**
     * @var string Contains the driver name for the PostgreSQL driver class
     */
    protected string $driverName = 'PostgreSQL';

    /**
     * @param array $metadata
     */
    public function __construct(array $metadata)
    {
        parent::__construct($metadata);

        // Set any required PostgreSQL-specific attributes (if necessary)
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
        $connectionString = "pgsql:host={$this->host};port={$this->port};";

        // Add database
        if (!empty($this->database)) {
            $connectionString .= "dbname={$this->database};";
        }

        // Add charset (PostgreSQL supports parameterization via `options`)
        if (!empty($this->charset)) {
            $connectionString .= "options='--client_encoding={$this->charset}'";
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
        $returnString = "CREATE DATABASE \"{$databaseName}\"";

        // Add charset (PostgreSQL requires "LC_CTYPE" and "LC_COLLATE" for encoding setup)
        if (!empty($this->charset)) {
            $returnString .= " ENCODING '{$this->charset}'";
        }

        return $returnString;
    }

    /**
     * @inheritDoc
     */
    public function getUseDatabaseString(string $databaseName): string
    {
        // Remove the quotes
        $databaseName = str_replace(['"', "'", '`'], "", $databaseName);
        return "SET search_path TO \"{$databaseName}\"";
    }

    /**
     * @inheritDoc
     *
     */
    public function emulatePrepares(bool $do): void
    {
        // see: https://www.php.net/manual/en/class.pdo-pgsql.php#pdo-pgsql.constants.attr-disable-prepares
        if (defined('\PDO::PGSQL_ATTR_DISABLE_PREPARES')) {
            $this->setAttribute(\PDO::PGSQL_ATTR_DISABLE_PREPARES, $do);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getConnectionString();
    }
}
