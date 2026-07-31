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
use System\Collections\Dictionary;
use System\Database\Drivers\DbConnectionStringBuilder;
use System\IO\Directory;
use System\IO\File;
use System\IO\Path;

/**
 * Provides functionality for managing database connections, retrieving metadata,
 * and working with database drivers or connection string builders.
 */
class DbFactory
{
    /**
     * @var DbConnection[] An array of all stored connections by name
     */
    protected static array $connections = array();

    /**
     * Creates a new database connection or returns an existing one based on the parameters provided.
     *
     * @param string $name The name of the connection.
     * @param DbConnectionStringBuilder $builder The builder object containing the connection string configuration.
     * @param bool $new Whether to force the creation of a new connection even if one already exists.
     * 
     * @return DbConnection Returns the created or existing database connection instance.
     */
    public static function CreateConnection(string $name, DbConnectionStringBuilder $builder, bool $new = false): DbConnection
    {
        // If the connection already exists, and $new is false, return existing
        if (isset(self::$connections[$name]) && !$new)
            return self::$connections[$name];

        // Connect using the PDO Constructor
        self::$connections[$name] = new DbConnection($builder);
        return self::$connections[$name];
    }

    /**
     * Retrieves an existing database connection by its name.
     *
     * @param string $name The name of the connection to retrieve.
     *
     * @return DbConnection|false Returns the existing database connection if it is found, or false if the connection does not exist.
     */
    public static function GetConnection(string $name): DbConnection|false
    {
        if (isset(self::$connections[$name]))
            return self::$connections[$name];

        return false;
    }

    /**
     * Assigns a database connection instance to a specified connection name.
     *
     * @param string $name The name to associate with the database connection.
     * @param DbConnection $connection The database connection instance to be set.
     *
     * @return void
     */
    public static function SetConnectionByName(string $name, DbConnection $connection): void
    {
        self::$connections[$name] = $connection;
    }

    /**
     * Closes an existing database connection.
     *
     * @param string $name Name or ID of the connection to be closed
     * @return void
     */
    public static function CloseConnection(string $name): void
    {
        if (isset(self::$connections[$name]))
            unset(self::$connections[$name]);
    }

    /**
     * Closes all active database connections by clearing the connections array.
     *
     * @return void
     */
    public static function CloseAllConnections(): void
    {
        self::$connections = array();
    }

    /**
     * Gets the total number of active database connections.
     *
     * @return int Returns the count of currently established database connections.
     */
    public static function GetConnectionCount(): int
    {
        return count(self::$connections);
    }

    /**
     * Retrieves the names of all active database connections.
     *
     * @return string[] An array of connection names currently available.
     */
    public static function GetConnectionNames(): array
    {
        return array_keys(self::$connections);
    }

    /**
     * Retrieves a list of supported database drivers by scanning for metadata files in the designated directory.
     *
     * @return array Returns an associative array where the keys are driver names (derived from folder names)
     *               and the values are dictionary objects containing the driver metadata.
     *
     * @throws \System\IO\DirectoryNotFoundException
     * @throws \System\IO\FileNotFoundException
     * @throws \System\IO\IOException
     * @throws \System\ObjectDisposedException
     * @throws \System\Security\SecurityException
     */
    public static function GetSupportedDrivers(): array
    {
        // return data
        $drivers = [];

        // Get driver folders
        $folderPath = Path::Combine(APP_DIR, "sql");
        $folders = Directory::GetDirectories($folderPath);

        foreach ($folders as $folderPath)
        {
            $filePath = Path::Combine($folderPath, "metadata.json");
            if (file_exists($filePath))
            {
                // read and parse the json content
                $rawData = File::ReadAllText($filePath);
                $json = json_decode($rawData, JSON_OBJECT_AS_ARRAY);
                if (empty($json)) continue;

                // Convert array to a dictionary
                $data = new Dictionary(true, $json, false);

                // store the data
                $folder = Path::GetFileName($folderPath);
                $drivers[$folder] = $data;
            }
        }

        return $drivers;
    }

    /**
     * Retrieves a connection string builder instance based on the specified database type.
     *
     * @param string $dbType The type of the database for which the connection string builder is required.
     *
     * @return DbConnectionStringBuilder Returns an instance of DbConnectionStringBuilder configured for the specified database type.
     * @throws \Exception
     */
    public static function GetConnectionStringBuilder(string $dbType): DbConnectionStringBuilder
    {
        return DbConnectionStringBuilder::Create($dbType);
    }
}