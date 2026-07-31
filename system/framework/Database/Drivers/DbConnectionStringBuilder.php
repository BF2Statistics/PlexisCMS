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
namespace System\Database\Drivers;

use PDO;
use System\IO\File;
use System\IO\Path;

/**
 * Provides a base class for strongly typed connection string builders.
 *
 * @package System\Database
 */
abstract class DbConnectionStringBuilder
{
    /**
     * @var string Gets or sets the server IP address used to connect with.
     */
    public string $host = '127.0.0.1';

    /**
     * @var int Gets or sets the port number that is used when the socket protocol is being used.
     */
    public int $port = 3306;

    /**
     * @var string Gets or sets the user id that should be used to connect with.
     */
    public string $user = '';

    /**
     * @var string Gets or sets the password that should be used to connect with.
     */
    public string $password = '';

    /**
     * @var string Gets or sets the name of the database the connection should initially connect to.
     */
    public string $database = '';

    /**
     * @var array Gets or sets A key => value array of driver-specific connection options.
     */
    protected array $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];

    /**
     * @var array
     */
    protected array $metadata;

    /**
     * @param array /**
     *
     */

    /**
     * Constructor for initializing the object with metadata.
     *
     * @param array $metadata An array containing metadata information.
     */
    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Gets the connection string associated with this DbConnectionStringBuilder.
     *
     * @return string
     */
    public abstract function getConnectionString(): string;

    /**
     * Gets the database creation string associated with this DbConnectionStringBuilder.
     *
     * @param string $databaseName The name of the database to create
     *
     * @return string
     */
    public abstract function getDatabaseCreateString(string $databaseName): string;

    /**
     * Gets the use database string associated with this DbConnectionStringBuilder.
     *
     * @param string $databaseName The name of the database to select and use
     *
     * @return string
     */
    public abstract function getUseDatabaseString(string $databaseName): string;

    /**
     * @return string Returns the name of the Driver class for this database type
     */
    public abstract function getDriverName(): string;

    /**
     * Gets an array of PDO connect attributes set by this DbConnectionStringBuilder.
     *
     * @return array
     */
    public function getConnectAttributes(): array
    {
        return $this->pdoOptions;
    }

    /**
     * Sets a specific PDO option with the given value.
     *
     * @param mixed $pdoOption The PDO option to be set.
     * @param mixed $value The value to assign to the specified PDO option.
     *
     * @return void
     */
    public function setAttribute(mixed $pdoOption, mixed $value): void
    {
        $this->pdoOptions[$pdoOption] = $value;
    }

    /**
     * Retrieves the value of the specified PDO attribute option.
     *
     * @param mixed $pdoOption The PDO option key to retrieve the value for
     *
     * @return mixed|null Returns the value of the PDO option if set, or null if it is not set
     */
    public function getAttribute(mixed $pdoOption): mixed
    {
        return (isset($this->pdoOptions[$pdoOption])) ? $this->pdoOptions[$pdoOption] : null;
    }

    /**
     * Configures whether the database connection should emulate prepared statements.
     *
     * @param bool $do Determines if prepared statements should be emulated. Use true to enable emulation, or false to disable it.
     *
     * @return void
     */
    //public abstract function emulatePrepares(bool $do): void;

    /**
     * Creates a DbConnectionStringBuilder instance using the specified database type
     *
     * @param string $dbType The name string of the database type (ex: mysql)
     *
     * @return DbConnectionStringBuilder
     *
     * @throws \Exception
     */
    public static function Create(string $dbType): DbConnectionStringBuilder
    {
        // Check for metadata
        $filePath = Path::Combine(APP_DIR, 'sql', $dbType, "metadata.json");
        if (!file_exists($filePath))
            throw new \Exception("Database type not supported");

        // load metadata
        $metadata = json_decode(File::ReadAllText($filePath), JSON_OBJECT_AS_ARRAY);
        if (empty($metadata))
        {
            throw new \Exception("Incorrect metadata file format");
        }

        // extract the helper class name
        $className = '\\System\\Database\\Drivers\\' . $metadata['subspace'] . '\\' . 'ConnectionStringBuilder';
        if (!class_exists($className))
            throw new \Exception("{$className} is not installed correctly.");

        return new $className($metadata);
    }
}