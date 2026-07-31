<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace Modules\Install\Models;

use Exception;
use System\Database\DbConnection;
use System\Database\Drivers\DbConnectionStringBuilder;
use System\Database\SqlException;

/**
 * Validates and  ensures the proper configuration and availability of required tables.
 */
class ConnectionTester
{
    /**
     * @var DbConnection
     */
    private DbConnection $connection;

    /**
     * Performs a series of checks to validate and establish a connection to the database,
     * ensuring the database exists and optionally creating it if it does not exist.
     *
     * @param array $data An associative array containing database connection details, such as:
     * <pre>
     *      'driver': The database driver (e.g., MySQL).
     *      'host': The hostname or IP address of the database server.
     *      'port': The port number for the database server.
     *      'username': The username for the database connection.
     *      'password': The password for the database connection.
     *      'database': The name of the database to connect to or create.
     * </pre>
     * @param bool $createDatabaseIfNotExists Specifies if the database should be created if it does not already exist.
     * @param string|null $tablePrefix An optional prefix for database tables to verify specific records.
     *                                 If null, it defaults to an empty string.
     *
     * @return bool Returns true if the checks pass and the database exists or was successfully created.
     *              Returns false if tables do not exist or checks fail without creating the database.
     *
     * @throws \Exception If the connection to the database server or the database selection fails.
     */
    public function performChecks(array $data, bool $createDatabaseIfNotExists, ?string $tablePrefix = null): bool
    {
        $builder = DbConnectionStringBuilder::Create($data['driver']);
        if ($tablePrefix === null)
            $tablePrefix = '';

        // Try to connect to the database host with new settings
        try
        {
            // Create connection using the MySQL connection builder
            $builder->host = $data['host'];
            $builder->port = $data['port'];
            $builder->user = $data['username'];
            $builder->password = $data['password'];
            $this->connection = new DbConnection($builder);
        }
        catch (Exception $e)
        {
            $message = "Failed to establish connection to database ('{$builder->host}:{$builder->port}'): " . $e->getMessage();
            throw new \Exception($message, $e->getCode(), $e);
        }

        /*
         * If we are here then the connection is Good. So lets check if the database exists!
         * Now we will attempt to either select the database, or create it if not already existing.
         */
        try
        {
            $this->connection->selectDatabase($data['database'], $createDatabaseIfNotExists);
        }
        catch (Exception $e)
        {
            $message = 'Failed to select or create database (' . $data['database'] . '): ' . $e->getMessage();
            throw new \Exception($message, $e->getCode(), $e);
        }

        // Fetch tables version. This will tell us if the tables already exist
        try
        {
            $query = $this->connection->from($tablePrefix . '_version')->select('version')->limit(1);
            $versions = $query->execute()->fetchAll();
            if (empty($versions) && !$createDatabaseIfNotExists)
            {
                return false;
            }
        }
        catch (Exception $e)
        {
            return false;
        }

        return true;
    }

    /**
     * Checks whether the target table contains any records.
     *
     * @return bool Returns true if the target table is empty, indicating no records exist.
     *              Returns false if the table contains one or more records.
     */
    public function checkEmptyTables(?string $tablePrefix = null): bool
    {
        // Ensure we have a string
        if ($tablePrefix === null)
            $tablePrefix = '';

        try {
            $query = $this->connection->from($tablePrefix . '_version')->select('version')->limit(1);
            $versions = $query->execute()->fetchAll();
            return empty($versions);
        }
        catch (SqlException $e) {
            return true;
        }
    }
}