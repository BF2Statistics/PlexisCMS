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
use System\Configuration\ConfigBase;
use System\Configuration\ConfigManager;
use System\Database\DbConnection;
use System\Database\Drivers\DbConnectionStringBuilder;
use System\Database\SqlFileParser;
use System\Diagnostics\LogWriter;
use System\IO\FileNotFoundException;
use System\IO\Path;

/**
 * This class provides methods to set up the database schema and initialize default data.
 * It handles establishing database connections, executing schema and data SQL files,
 * and managing transactions to ensure the database is set up properly.
 */
class SchemaSetup
{
    /**
     * @var ?DbConnection
     */
    private ?DbConnection $connection = null;

    private string $dbType;

    private ConfigBase $config;

    /**
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function __construct()
    {
        $dbFilePath = Path::Combine(SYSTEM_DIR, "config", "database.php");
        $this->config = ConfigManager::Load($dbFilePath);

        // Grab the database type
        $configItems = $this->config->get('web');
        $this->dbType = $configItems['driver'];

        // Create connection if null
        if ($this->connection === null)
            $this->establishDatabaseConnection($configItems);
    }

    /**
     * Installs the database schema by executing the statements defined in the schema file.
     *
     * This method connects to the database (if not already connected), reads the schema SQL file,
     * and executes its queries to set up the database tables. If any errors occur during execution,
     * it rolls back the transaction and logs the error details.
     *
     * @return void
     *
     * @throws Exception If an error occurs while installing the database schema.
     */
    public function installSchema(): void
    {
        // Fetch tables version
        try
        {
            // Start a new transaction
            $this->connection->beginTransaction();

            // Create parser
            $filePath = Path::Combine(APP_DIR, 'sql', $this->dbType, 'schema.sql');
            $parser = new SqlFileParser($filePath, $this->connection->getDriver());
            $queries = $parser->getStatements();

            // Read file contents
            foreach ($queries as $query) {
                $this->connection->exec($query);
            }

            // Commit changes
            $this->connection->commit();
        }
        catch (Exception $e)
        {
            $this->connection->rollBack();

            $filePath = Path::Combine(SYSTEM_DIR, 'logs', 'php_errors.log');
            $logWriter = new LogWriter($filePath);
            $logWriter->logDebug('Failed to create database tables: ' . $e);

            // Send Error Results
            throw new Exception('Failed to install database tables! ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Installs the default data into the database by executing the statements defined in the data SQL file.
     *
     * This method establishes a connection to the database (if not already connected) and processes
     * the SQL statements from the data file to populate the database with initial or default data.
     * If an error occurs during execution, the transaction is rolled back and the details
     * of the failure are logged.
     *
     * @return void
     *
     * @throws Exception If an error occurs while installing the default data.
     */
    public function installDefaultData(): void
    {
        // Create parser
        $filePath = Path::Combine(APP_DIR, 'sql', $this->dbType, 'data.sql');
        $parser = new SqlFileParser($filePath, $this->connection->getDriver());
        $queries = $parser->getStatements();
        $current = '';

        try
        {
            // Start transaction
            $this->connection->beginTransaction();

            // Read file contents
            foreach ($queries as $query)
            {
                $current = $query;
                $this->connection->exec($query);
            }

            // Commit changes
            $this->connection->commit();
        }
        catch (Exception $e)
        {
            $this->connection->rollBack();

            $filePath = Path::Combine(SYSTEM_DIR, 'logs', 'php_errors.log');
            $logWriter = new LogWriter($filePath);
            $logWriter->logDebug('Query Failed: ' . $current);

            // Send Error Results
            throw new Exception('Failed to install database default data! ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Establishes a database connection using the provided configuration settings.
     *
     * @param array $config An associative array containing database configuration settings. Keys may include:
     * <pre>
     *      - 'driver' (string): The database driver to use.
     *      - 'host' (string): The database server hostname or IP.
     *      - 'port' (int): The database server port.
     *      - 'username' (string): The username for database authentication.
     *      - 'password' (string): The password for database authentication.
     *      - 'database' (string): The name of the database to connect to.
     * </pre>
     *
     * @return void
     *
     * @throws \Exception If the connection to the database cannot be established.
     */
    protected function establishDatabaseConnection(array $config): void
    {
        // Grab the database type
        $builder = DbConnectionStringBuilder::Create($config['driver']);

        // Try to connect to the database with new settings
        try
        {
            // Create connection using the MySQL connection builder
            $builder->host = $config['host'];
            $builder->port = $config['port'];
            $builder->user = $config['username'];
            $builder->password = $config['password'];
            $builder->database = $config['database'];
            $this->connection = new DbConnection($builder);
        }
        catch (Exception $e)
        {
            $message = "Failed to establish connection to ('{$builder->host}:{$builder->port}'): " . $e->getMessage();
            throw new Exception($message, $e->getCode(), $e);
        }
    }
}