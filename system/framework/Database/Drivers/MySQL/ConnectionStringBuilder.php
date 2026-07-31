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

use System\Database\Drivers\DbConnectionStringBuilder;

/**
 * Generates connection strings used to connect to MySQL databases.
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
     * @var string Contains the driver name for the MySQL driver class
     */
    protected string $driverName = 'MySQL';

    /**
     * @param array $metadata
     */
    public function __construct(array $metadata)
    {
        parent::__construct($metadata);

        // Set default connection attributes

        /*
         * When updating a Mysql table with identical values nothing's really affected so rowCount will return 0.
         * Setting PDO::MYSQL_ATTR_FOUND_ROWS to true, rowCount() will tell you how many rows your update-query
         * actually found/matched.
         */
        $this->setAttribute(\PDO::MYSQL_ATTR_FOUND_ROWS, true);
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
        $connectionString = "mysql:host={$this->host};port={$this->port};";

        // Add database?
        if (!empty($this->database))
        {
           $connectionString .= "dbname={$this->database};";

           // Add charset?
            if (!empty($this->charset))
            {
                $connectionString .= "charset={$this->charset};";
            }
        }

        return $connectionString;
    }

    /**
     * @inheritDoc
     */
    public function getDatabaseCreateString(string $databaseName): string
    {
        // Remove the quotes
        $databaseName = str_replace(array('"', '\'', '`'), "", $databaseName);
        $returnString = "CREATE DATABASE IF NOT EXISTS `{$databaseName}`";

        // Add charset?
        if (!empty($this->charset))
            $returnString .= " CHARACTER SET {$this->charset}";

        return $returnString;
    }

    /**
     * @inheritDoc
     */
    public function getUseDatabaseString(string $databaseName): string
    {
        // Remove the quotes
        $databaseName = str_replace(array('"', '\'', '`'), "", $databaseName);
        return "USE `{$databaseName}`";
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getConnectionString();
    }

    /**
     * @inheritDoc
     *
     */
    public function emulatePrepares(bool $do): void
    {
        $this->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $do);
    }
}