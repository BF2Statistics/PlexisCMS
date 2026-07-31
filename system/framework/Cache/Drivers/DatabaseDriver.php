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
namespace System\Cache\Drivers;

use Exception;
use InvalidArgumentException;
use Random\RandomException;
use System\Cache\CacheInterface;
use System\Cache\DriverInfo;
use System\Database\DbConnection;
use System\Database\DbFactory;
use System\Database\NonQuery;
use System\IO\File;
use System\IO\Path;

/**
 * Class DatabaseDriver
 *
 * Implements the CacheInterface using a database backend. This class provides
 * methods for interacting with a database to store, retrieve, and manage
 * cached data. It includes methods for single and multiple key operations,
 * expiration control, and support for stale reads.
 *
 * PSR-16 compliant
 */
class DatabaseDriver implements CacheInterface
{
    /**
     * Database connection instance.
     *
     * @var DbConnection
     */
    private DbConnection $connection;

    /**
     * The name of the cache table.
     *
     * @var string
     */
    private string $tableName;

    /**
     * Lock timeout in seconds (default: 10 seconds).
     * Determines how long the lock should be held before timing out.
     *
     * @var int
     */
    private int $lockTimeout = 10;

    /**
     * Store lock tokens locally during the process.
     * These tokens maintain a mapping of locks held by the current process.
     *
     * @var array
     */
    private array $lockTokens = [];

    /**
     * Constructor to initialize a connection to the database.
     *
     * This constructor accepts either:
     * 1. A DbConnection instance directly
     * 2. A connection name to retrieve from DbFactory
     * 3. An array of connection data to create a new connection
     *
     * @param DbConnection|string|array $connection Either:
     *     - A DbConnection instance
     *     - A string with the name of an existing connection in DbFactory
     *     - An array with connection details:
     *         - `db_type` (string): The database type (mysql, mssql, postgresql)
     *         - `host` (string): The database server hostname or IP
     *         - `port` (int): The database server port
     *         - `username` (string): The database username
     *         - `password` (string): The database password
     *         - `database` (string): The database name
     * @param string $tableName Optional name for the cache table (defaults to 'cache')
     *
     * @throws InvalidArgumentException If required connection data is missing or invalid.
     * @throws Exception If the connection to the database fails.
     */
    public function __construct(mixed $connection, string $tableName = 'cache')
    {
        $this->tableName = $tableName;

        try
        {
            // Handle different types of connection parameters
            if ($connection instanceof DbConnection)
            {
                // Use the provided connection directly
                $this->connection = $connection;
            } 
            else if (is_string($connection))
            {
                // Get an existing connection from DbFactory
                $dbConnection = DbFactory::GetConnection($connection);
                if ($dbConnection === false) {
                    throw new InvalidArgumentException("No database connection found with name: {$connection}");
                }

                $this->connection = $dbConnection;
            } 
            else if (is_array($connection))
            {
                // Create a new connection from the provided data
                if (empty($connection['db_type'])) {
                    throw new InvalidArgumentException("Connection data must contain 'db_type'.");
                }

                // Create a connection string builder for the specified database type
                $builder = DbFactory::GetConnectionStringBuilder($connection['db_type']);

                // Set connection properties
                $builder->host = $connection['host'] ?? '127.0.0.1';
                $builder->port = $connection['port'] ?? 3306;
                $builder->user = $connection['username'] ?? '';
                $builder->password = $connection['password'] ?? '';
                $builder->database = $connection['database'] ?? '';

                // Create the connection
                $connectionName = 'cache_' . uniqid();
                $this->connection = DbFactory::CreateConnection($connectionName, $builder);
            } 
            else
            {
                throw new InvalidArgumentException("Invalid connection parameter. Must be a DbConnection instance, connection name, or connection data array.");
            }

            // Ensure the cache table exists
            $this->ensureCacheTableExists();
        }
        catch (Exception $e)
        {
            throw new Exception("Could not initialize database cache: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Ensures that the cache table exists in the database.
     *
     * This method checks if the required tables exist and creates them if they don't.
     * It loads the SQL schema from external files in system/sql/<driver>/ directory.
     *
     * @throws Exception If the table creation fails or SQL file is missing.
     */
    private function ensureCacheTableExists(): void
    {
        try
        {
            // Get the database driver
            $driver = $this->connection->getDriver();
            $driverName = $this->connection->driverName;

            // Check if the tables exist
            if ($driver->tableExists($this->tableName) && $driver->tableExists("{$this->tableName}_locks")) {
                return; // Both tables already exist
            }

            // Check if the schema file exists
            $schemaFile = Path::Combine(ROOT, 'system', 'sql', $driverName, 'cache.schema.sql');
            if (!file_exists($schemaFile)) {
                throw new Exception("Cache schema file not found: {$schemaFile}");
            }

            // Read the SQL file
            $sql = File::ReadAllText($schemaFile);

            // Replace placeholders with actual table names
            $quotedTableName = $driver->quoteIdentifier($this->tableName);
            $quotedLocksTableName = $driver->quoteIdentifier("{$this->tableName}_locks");

            $sql = str_replace('{TABLE_NAME}', $quotedTableName, $sql);
            $sql = str_replace('{LOCKS_TABLE_NAME}', $quotedLocksTableName, $sql);

            // Execute the SQL
            // For MSSQL, we need to execute the entire block as one statement
            // For others, we can split by semicolons
            if ($driverName === 'mssql')
            {
                // Execute as a single batch
                $this->connection->exec($sql);
            }
            else
            {
                // Split by semicolons and execute each statement
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
                );

                foreach ($statements as $statement)
                {
                    if (!empty($statement)) {
                        $this->connection->exec($statement);
                    }
                }
            }
        }
        catch (Exception $e)
        {
            throw new Exception("Failed to create cache tables: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Retrieves an item from the cache by its unique key.
     *
     * @param string $key The unique cache key identifying the item.
     * @param mixed $default The default value to return if the key is not found in the cache.
     * @param bool|null $isStale Indicates whether the data assigned to the associated $key is stale.
     *
     * @return mixed The cached value, or the default value if the key does not exist.
     */
    public function get(string $key, mixed $default = null, ?bool &$isStale = false): mixed
    {
        try
        {
            $this->validateKey($key);
            $now = time();

            // Delete expired items
            $this->deleteExpiredItems();

            // Use the query builder to retrieve the item
            $query = $this->connection->from($this->tableName)
                ->select(['value', 'soft_ttl', 'hard_ttl'])
                ->where('key')->equals($key)
                ->and('hard_ttl')->greaterThan($now)
                ->apply();

            $reader = $query->executeReader();
            if (!$reader->read()) {
                $isStale = false;
                return $default;
            }

            // Check if the data is stale
            $isStale = $reader->getValue('soft_ttl') < $now;

            // Unserialize the value safely
            $raw = $reader->getValue('value');
            $value = @unserialize($raw);
            if ($value === false && $raw !== serialize(false))
            {
                // Treat corrupted data as a cache miss
                $isStale = false;
                return $default;
            }
            return $value;
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache get error: " . $e->getMessage());
            $isStale = false;
            return $default;
        }
    }

    /**
     * Stores an item in the cache, identified by a key.
     *
     * @param string    $key The unique cache key for the stored item.
     * @param mixed     $value The value to be cached.
     * @param int       $expiresAfter The time (in seconds) before expiration. Default is 3600 seconds.
     * @param int|null  $staleAfter The time (in seconds) after which the data is stale but retrievable.
     *                              If null, data never becomes stale and only expires at `$expiresAfter`.
     *
     * @return bool True on success, false on failure.
     */
    public function set(string $key, mixed $value, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        try
        {
            $this->validateKey($key);
            [$expiresAfter, $staleAfterNorm] = $this->validateTtls($expiresAfter, $staleAfter);
            $now = time();
            $softTTL = $now + $staleAfterNorm;
            $hardTTL = $now + $expiresAfter;

            // Serialize the value
            $serializedValue = serialize($value);

            // Use the query builder to insert or update the cache item
            $query = $this->connection->upsert($this->tableName);
            $query->setValues([
                'key' => $key,
                'value' => $serializedValue,
                'soft_ttl' => $softTTL,
                'hard_ttl' => $hardTTL
            ]);

            // Execute the query
            return $query->execute() > 0;
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if an item exists in the cache by its unique key.
     *
     * @param string $key The unique cache key to check for existence.
     *
     * @return bool True if the cache item exists, false otherwise.
     */
    public function has(string $key): bool
    {
        try
        {
            $this->validateKey($key);
            $now = time();

            // Delete expired items
            $this->deleteExpiredItems();

            // Use the query builder to check if the key exists
            $query = $this->connection->from($this->tableName)
                ->select('1')
                ->where('key')->equals($key)
                ->and('hard_ttl')->greaterThan($now)->apply()
                ->limit(1);

            $reader = $query->executeReader();
            return $reader->read();
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache has error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes an item from the cache by its unique key.
     *
     * @param string $key The unique cache key identifying the item to delete.
     *
     * @return bool True if the item was successfully deleted, false otherwise.
     */
    public function delete(string $key): bool
    {
        try
        {
            $this->validateKey($key);
            // Use the query builder to delete the item
            $query = $this->connection->delete($this->tableName)
                ->where('key')->equals($key)
                ->apply();

            /**
             * Execute the query and check if any rows were affected
             * @var NonQuery $query
             */
            return $query->execute() > 0;
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clears all items from the cache.
     *
     * @return bool True on success, false on failure.
     */
    public function clear(): bool
    {
        try
        {
            // Use the query builder to delete all items
            $query = $this->connection->delete($this->tableName);

            // Execute the query (no conditions means delete all)
            $query->execute();

            return true;
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves multiple items from the cache by their unique keys.
     *
     * @param iterable $keys An iterable list of unique cache keys to retrieve.
     * @param mixed $default The default value to return for keys that do not exist.
     *
     * @return iterable An iterable list of values retrieved from the cache. For missing keys, the default value is returned.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);

        if (empty($keyArray)) {
            return $result;
        }

        try
        {
            // Validate keys
            $validated = [];
            foreach ($keyArray as $k)
            {
                if (!is_string($k)) {
                    throw new InvalidArgumentException('All cache keys must be strings.');
                }

                $this->validateKey($k);
                $validated[] = $k;
            }
            $now = time();

            // Delete expired items
            $this->deleteExpiredItems();

            // Use the query builder to retrieve multiple items
            $query = $this->connection->from($this->tableName)
                ->select(['key', 'value', 'soft_ttl'])
                ->where('key')->in($validated)
                ->and('hard_ttl')->greaterThan($now)->apply();

            $reader = $query->executeReader();
            $fetchedKeys = [];

            // Process fetched items
            while ($reader->read())
            {
                $key = $reader->getValue('key');
                $fetchedKeys[] = $key;
                $raw = $reader->getValue('value');
                $val = @unserialize($raw);
                if ($val === false && $raw !== serialize(false))
                {
                    // Corrupted entry, treat as missing
                    $result[$key] = $default;
                }
                else {
                    $result[$key] = $val;
                }
            }

            // Add default values for missing keys
            foreach ($validated as $key)
            {
                if (!in_array($key, $fetchedKeys)) {
                    $result[$key] = $default;
                }
            }

            return $result;
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache getMultiple error: " . $e->getMessage());

            // Return default values for all keys
            foreach ($keyArray as $key) {
                $result[$key] = $default;
            }

            return $result;
        }
    }

    /**
     * Stores multiple items in the cache.
     *
     * @param iterable $values An iterable list of key-value pairs (e.g. `['key' => value, ...]`) to store in the cache.
     * @param int $expiresAfter The time (in seconds) before expiration. Default is 3600 seconds.
     * @param int|null $staleAfter The time (in seconds) after which the data is stale but still retrievable.
     *                               If null, data never becomes stale and only expires at `$expiresAfter`.
     *
     * @return bool True on success, false on failure.
     */
    public function setMultiple(iterable $values, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        if (!is_array($values) && !($values instanceof \Traversable)) {
            return false;
        }

        try
        {
            [$expiresAfter, $staleAfterNorm] = $this->validateTtls($expiresAfter, $staleAfter);
            $now = time();
            $softTTL = $now + $staleAfterNorm;
            $hardTTL = $now + $expiresAfter;

            // Begin transaction
            $this->connection->beginTransaction();

            try
            {
                foreach ($values as $key => $value)
                {
                    if (!is_string($key)) {
                        throw new InvalidArgumentException('All cache keys must be strings.');
                    }

                    $this->validateKey($key);
                    // Serialize the value
                    $serializedValue = serialize($value);

                    // Use the query builder to insert or update the cache item
                    $query = $this->connection->upsert($this->tableName);
                    $query->setValues([
                        'key' => $key,
                        'value' => $serializedValue,
                        'soft_ttl' => $softTTL,
                        'hard_ttl' => $hardTTL
                    ]);

                    // Execute the query
                    $query->execute();
                }

                // Commit transaction
                $this->connection->commit();
                return true;
            }
            catch (Exception $e)
            {
                // Rollback transaction on error
                $this->connection->rollback();
                throw $e;
            }
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache setMultiple error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes multiple items from the cache by their unique keys.
     *
     * @param iterable $keys An iterable list of unique cache keys to delete.
     *
     * @return bool True if the items were successfully deleted, false otherwise.
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);

        if (empty($keyArray)) {
            return true;
        }

        try
        {
            // Validate keys
            $validated = [];
            foreach ($keyArray as $k)
            {
                if (!is_string($k)) {
                    throw new InvalidArgumentException('All cache keys must be strings.');
                }

                $this->validateKey($k);
                $validated[] = $k;
            }
            // Use the query builder to delete multiple items
            $query = $this->connection->delete($this->tableName)
                ->where('key')->in($validated);

            /**
             * Execute the query and check if any rows were affected
             * @var NonQuery $query
             */
            $query->execute();

            return true;
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache deleteMultiple error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Refreshes the cache item associated with the given key, updating its time-to-live (TTL) values.
     *
     * @param string $key The unique cache key identifying the item to refresh.
     * @param int $expiresAfter The hard time-to-live in seconds; determines the period after which the item expires completely (optional).
     * @param ?int $staleAfter The soft time-to-live in seconds; determines the period after which the item is considered stale but can still be used (optional).
     *
     * @return bool True on successful refresh, or false if the refresh fails or the item does not exist.
     */
    public function refresh(string $key, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        try
        {
            $this->validateKey($key);
            [$expiresAfter, $staleAfterNorm] = $this->validateTtls($expiresAfter, $staleAfter);
            $now = time();
            $softTTL = $now + $staleAfterNorm;
            $hardTTL = $now + $expiresAfter;

            // Use the query builder to update TTL values
            $query = $this->connection->update($this->tableName);
            $query->setValues([
                'soft_ttl' => $softTTL,
                'hard_ttl' => $hardTTL
            ]);
            $query->where('key')->equals($key);
            $query->where('hard_ttl')->greaterThan($now);

            // Execute the query and check if any rows were affected
            return $query->execute() > 0;
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache refresh error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atomically increments a numeric value stored in the cache by the specified step.
     *
     * @param string $key The unique cache key identifying the item to increment.
     * @param int $step The amount by which to increment the value (default is 1).
     *
     * @return int|false The incremented value, or false if the operation fails or the $key doesn't exist
     */
    public function increment(string $key, int $step = 1): int|false
    {
        try
        {
            $this->validateKey($key);
            $now = time();

            // Begin transaction
            $this->connection->beginTransaction();

            try
            {
                // Get the current value using the query builder
                $query = $this->connection->from($this->tableName)
                    ->select(['value', 'soft_ttl', 'hard_ttl'])
                    ->where('key')->equals($key)
                    ->and('hard_ttl')->greaterThan($now)->apply();

                // Add FOR UPDATE to lock the row
                $reader = $query->execute()->fetchAll();

                if (empty($reader)) {
                    $this->connection->rollback();
                    return false;
                }

                // Unserialize the value safely
                $raw = $reader[0]['value'];
                $value = @unserialize($raw);
                if ($value === false && $raw !== serialize(false)) {
                    $this->connection->rollback();
                    return false;
                }

                // Ensure the value is numeric
                if (!is_numeric($value)) {
                    $this->connection->rollback();
                    return false;
                }

                // Increment the value
                $value += $step;

                // Update the value using the query builder
                $updateQuery = $this->connection->update($this->tableName);
                $updateQuery->setValues([
                    'value' => serialize($value)
                ]);
                $updateQuery->where('key')->equals($key);
                $updateQuery->execute();

                // Commit transaction
                $this->connection->commit();

                return $value;
            }
            catch (Exception $e)
            {
                // Rollback transaction on error
                $this->connection->rollback();
                throw $e;
            }
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache increment error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atomically decreases the numeric value of an item in the cache by the specified step
     *
     * @param string $key The unique cache key identifying the item.
     * @param int $step The value by which the item's value should be decreased (optional, defaults to 1).
     *
     * @return int|false The decremented value, or false if the operation fails or the $key doesn't exist
     */
    public function decrement(string $key, int $step = 1): int|false
    {
        try
        {
            $this->validateKey($key);
            $now = time();

            // Begin transaction
            $this->connection->beginTransaction();

            try
            {
                // Get the current value using the query builder
                $query = $this->connection->from($this->tableName)
                    ->select(['value', 'soft_ttl', 'hard_ttl'])
                    ->where('key')->equals($key)
                    ->and('hard_ttl')->greaterThan($now);

                // Add FOR UPDATE to lock the row
                $reader = $query->apply()->execute()->fetchAll();
                if (empty($reader))
                {
                    $this->connection->rollback();
                    return false;
                }

                // Unserialize the value safely
                $raw = $reader[0]['value'];
                $value = @unserialize($raw);
                if ($value === false && $raw !== serialize(false)) {
                    $this->connection->rollback();
                    return false;
                }

                // Ensure the value is numeric
                if (!is_numeric($value)) {
                    $this->connection->rollback();
                    return false;
                }

                // Decrement the value
                $value -= $step;

                // Update the value using the query builder
                $updateQuery = $this->connection->update($this->tableName);
                $updateQuery->setValues([
                    'value' => serialize($value)
                ]);
                $updateQuery->where('key')->equals($key);
                $updateQuery->execute();

                // Commit transaction
                $this->connection->commit();

                return $value;
            }
            catch (Exception $e)
            {
                // Rollback transaction on error
                $this->connection->rollback();
                throw $e;
            }
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache decrement error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves an item from the cache by its unique key or regenerates it if necessary,
     * while preventing cache stampede by utilizing an internal locking mechanism.
     *
     * This method serves to minimize the risk of redundant computation during high-concurrency
     * situations where multiple requests attempt to regenerate the same cache item concurrently.
     *
     * - If the cache item is fresh and valid, it will simply return the cached value.
     * - If the cache item is stale or missing, the first call to this method acquires a lock
     *   and regenerates the item using the provided callback. Subsequent calls will:
     *     - Wait for the lock to be released and then return the updated value, or
     *     - Serve stale data while waiting, depending on the specific implementation.
     *
     * @param string $key The unique key identifying the cache item.
     * @param callable $callback A callback function to generate the data if regeneration is required.
     *                                The callback should return the new value that needs to be cached.
     * @param int $expiresAfter The duration, in seconds, for which the item is considered valid
     *                                before expiring completely (hard TTL). Defaults to 3600 (1 hour).
     * @param int|null $staleAfter The duration, in seconds, after which the item is considered stale.
     *                                Stale data can still be served while being refreshed if this value is set.
     *                                If null, stale data handling is disabled, and only the hard TTL is considered.
     *
     * @return mixed The cached value if it exists and is valid, or the newly generated value upon regeneration.
     *
     * @throws Exception If an error occurs with the locking or cache mechanism.
     */
    public function getOrRegenerateWithLock(string $key, callable $callback, int $expiresAfter = 3600, ?int $staleAfter = null): mixed
    {
        $this->validateKey($key);
        $lockKey = "{$key}:lock";
        $now = time();
        [$expiresAfter, $staleAfterNorm] = $this->validateTtls($expiresAfter, $staleAfter);

        try
        {
            // Step 1: Attempt to retrieve the cached value using the query builder
            $query = $this->connection->from($this->tableName)
                ->select(['value', 'soft_ttl', 'hard_ttl'])
                ->where('key')->equals($key)
                ->and('hard_ttl')->greaterThan($now)->apply();

            $reader = $query->executeReader();
            if ($reader->read())
            {
                $raw = $reader->getValue('value');
                $value = @unserialize($raw);
                if ($value === false && $raw !== serialize(false)) {
                    // treat as missing
                    $value = null;
                }
                $softTTL = $reader->getValue('soft_ttl');

                if ($softTTL > $now) {
                    // Data is still fresh
                    return $value;
                }

                // Data is stale
                if (!$this->acquireLock($lockKey)) {
                    // Failed to acquire lock, serve stale data
                    return $value;
                }

                try
                {
                    // Refresh the stale data
                    $generatedValue = $callback();
                    $this->set($key, $generatedValue, $expiresAfter, $staleAfterNorm);
                    return $generatedValue;
                }
                finally
                {
                    $this->releaseLock($lockKey);
                }
            }

            // Step 2: If no valid/stale data, acquire lock to regenerate
            if ($this->acquireLock($lockKey)) {
                // Acquire the lock successfully
                try
                {
                    $generatedValue = $callback();
                    $this->set($key, $generatedValue, $expiresAfter, $staleAfterNorm);
                    return $generatedValue;
                }
                finally
                {
                    $this->releaseLock($lockKey);
                }
            }

            // Step 3: If unable to acquire lock, wait for regeneration and retrieve the value again
            $maxWaitTime = time() + $this->lockTimeout;
            while (time() < $maxWaitTime)
            {
                // Check if lock still exists
                if (!$this->lockExists($lockKey))
                {
                    // Lock released, try to get the fresh value using the query builder
                    $freshQuery = $this->connection->from($this->tableName)
                        ->select('value')
                        ->where('key')->equals($key)
                        ->and('hard_ttl')->greaterThan(time())->apply();

                    $freshReader = $freshQuery->executeReader();
                    if ($freshReader->read())
                    {
                        $rawFresh = $freshReader->getValue('value');
                        $freshVal = @unserialize($rawFresh);
                        if (! ($freshVal === false && $rawFresh !== serialize(false)) )
                        {
                            return $freshVal;
                        }
                    }

                    break;
                }

                // Sleep for a short time before retrying
                usleep(50000); // 50ms
            }

            // Log if we timed out waiting for lock
            if (time() >= $maxWaitTime) {
                \System::Log()->logError("Cache stampede protection timeout for key '{$key}' after {$this->lockTimeout} seconds.");
            }

            // If all else fails, regenerate as a fallback
            $generatedValue = $callback();
            $this->set($key, $generatedValue, $expiresAfter, $staleAfterNorm);
            return $generatedValue;

        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache getOrRegenerateWithLock error: " . $e->getMessage());

            // As a last resort, just generate the value without caching
            return $callback();
        }
    }

    /**
     * Retrieves information about the driver used for caching.
     *
     * This method provides details about the caching driver, including its name
     * and whether it is supported in the current environment.
     *
     * @return DriverInfo An instance containing the driver's name and support status.
     */
    public static function GetDriverInfo(): DriverInfo
    {
        // Check for PDO and at least one database driver
        $hasPdo = extension_loaded('pdo');
        $hasMysql = extension_loaded('pdo_mysql');
        $hasMssql = extension_loaded('pdo_sqlsrv');
        $hasPostgres = extension_loaded('pdo_pgsql');

        // The driver is supported if PDO and at least one database driver is available
        $isSupported = $hasPdo && ($hasMysql || $hasMssql || $hasPostgres);

        // Build a description of supported database types
        $supportedDbs = [];
        if ($hasMysql) $supportedDbs[] = 'MySQL';
        if ($hasMssql) $supportedDbs[] = 'MSSQL';
        if ($hasPostgres) $supportedDbs[] = 'PostgreSQL';

        $dbList = empty($supportedDbs) ? 'No database drivers detected' : 'Supported databases: ' . implode(', ', $supportedDbs);

        return new DriverInfo(
            name: 'Database',
            readableName: 'Database',
            isSupported: $isSupported,
            description: "Database cache driver stores cache items in a database table, providing persistent storage with transaction support. {$dbList}."
        );
    }

    /**
     * Acquires a lock for a given key for regenerating the cache.
     *
     * @param string $lockKey The unique lock key.
     *
     * @return bool True if the lock was acquired successfully, false otherwise.
     * @throws RandomException
     */
    protected function acquireLock(string $lockKey): bool
    {
        try
        {
            $token = bin2hex(random_bytes(16)); // Generate a unique token
            $expiry = time() + $this->lockTimeout;
            $now = time();

            // Begin transaction to ensure atomicity
            $this->connection->beginTransaction();

            try
            {
                // First check if the lock exists and is not expired
                $existingLock = $this->connection->from("{$this->tableName}_locks")
                    ->select(['token', 'expiry'])
                    ->where('key')->equals($lockKey)->apply()
                    ->execute()->fetchAll();

                if (empty($existingLock) || $existingLock[0]['expiry'] < $now) {
                    // Lock doesn't exist or is expired, insert or update it
                    if (empty($existingLock)) {
                        // Insert new lock
                        $insertQuery = $this->connection->insert("{$this->tableName}_locks");
                        $insertQuery->setValues([
                            'key' => $lockKey,
                            'token' => $token,
                            'expiry' => $expiry
                        ]);
                        $insertQuery->execute();
                    } else {
                        // Update expired lock
                        $updateQuery = $this->connection->update("{$this->tableName}_locks");
                        $updateQuery->setValues([
                            'token' => $token,
                            'expiry' => $expiry
                        ]);
                        $updateQuery->where('key')->equals($lockKey);
                        $updateQuery->execute();
                    }
                }

                // Check if we acquired the lock
                $checkLock = $this->connection->from("{$this->tableName}_locks")
                    ->select(['token'])
                    ->where('key')->equals($lockKey)
                    ->and('token')->equals($token)->apply()
                    ->execute()->fetchAll();

                $lockAcquired = !empty($checkLock);

                if ($lockAcquired) {
                    $this->lockTokens[$lockKey] = $token; // Keep track of the token for this lock
                }

                // Commit the transaction
                $this->connection->commit();
                return $lockAcquired;
            }
            catch (Exception $e)
            {
                // Rollback transaction on error
                $this->connection->rollback();
                throw $e;
            }
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache acquireLock error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a lock exists and is still valid.
     *
     * @param string $lockKey The unique lock key.
     *
     * @return bool True if the lock exists and is valid, false otherwise.
     */
    protected function lockExists(string $lockKey): bool
    {
        try
        {
            $now = time();

            // Use the query builder to check if the lock exists
            $query = $this->connection->from("{$this->tableName}_locks")
                ->select('1')
                ->where('key')->equals($lockKey)
                ->and('expiry')->greaterThan($now)->apply()
                ->limit(1);

            $reader = $query->executeReader();
            return $reader->read();
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache lockExists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Releases a previously acquired lock identified by its unique key.
     * Uses a transaction to ensure the lock is released only if it
     * is owned by the current process.
     *
     * @param string $lockKey The unique identifier for the lock to be released.
     *
     * @return bool True if the lock was successfully released, or false if
     * the process did not own the lock or if the release operation failed.
     */
    protected function releaseLock(string $lockKey): bool
    {
        $token = $this->lockTokens[$lockKey] ?? null;

        if ($token === null) {
            return false;
        }

        try
        {
            // Begin transaction
            $this->connection->beginTransaction();

            try
            {
                // Check if we own the lock using the query builder
                $checkLock = $this->connection->from("{$this->tableName}_locks")
                    ->select('1')
                    ->where('key')->equals($lockKey)
                    ->and('token')->equals($token)->apply()
                    ->execute()->fetchAll();

                if (empty($checkLock)) {
                    // We don't own the lock
                    $this->connection->rollback();
                    return false;
                }

                // Delete the lock using the query builder
                $deleteQuery = $this->connection->delete("{$this->tableName}_locks")
                    ->where('key')->equals($lockKey)
                    ->and('token')->equals($token)->apply();
                $deleteQuery->execute();

                // Commit transaction
                $this->connection->commit();

                // Clear local token tracking
                unset($this->lockTokens[$lockKey]);

                return true;
            }
            catch (Exception $e)
            {
                // Rollback transaction on error
                $this->connection->rollback();
                throw $e;
            }
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache releaseLock error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes expired items from the cache.
     *
     * This method is called internally to clean up expired items.
     */
    private function deleteExpiredItems(): void
    {
        try
        {
            $now = time();

            // Use the query builder to delete expired items
            $itemsQuery = $this->connection->delete($this->tableName)
                ->where('hard_ttl')->lessThanOrEqual($now)->apply();
            $itemsQuery->execute();

            // Use the query builder to delete expired locks
            $locksQuery = $this->connection->delete("{$this->tableName}_locks")
                ->where('expiry')->lessThanOrEqual($now)->apply();
            $locksQuery->execute();
        }
        catch (Exception $e)
        {
            // Log the error
            \System::Log()->logWarning("Cache deleteExpiredItems error: " . $e->getMessage());
        }
    }

    /**
     * Validates a cache key to conform to standards.
     *
     * @param string $key
     */
    private function validateKey(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgumentException('Cache key must be a non-empty string.');
        }

        // Allow [A-Za-z0-9_.-] only
        if (!preg_match('/^[A-Za-z0-9_.-]+$/', $key)) {
            throw new InvalidArgumentException("Cache key contains invalid characters. Allowed: A-Z, a-z, 0-9, '_', '-', '.'.");
        }
    }

    /**
     * Validates and normalizes TTLs.
     * Returns array [expiresAfter, staleAfterNormalized]
     */
    private function validateTtls(int $expiresAfter, ?int $staleAfter): array
    {
        if ($expiresAfter <= 0) {
            throw new InvalidArgumentException('expiresAfter must be a positive integer.');
        }
        if ($staleAfter !== null) {
            if ($staleAfter <= 0) {
                throw new InvalidArgumentException('staleAfter must be a positive integer when provided.');
            }
            if ($staleAfter > $expiresAfter) {
                throw new InvalidArgumentException('staleAfter cannot exceed expiresAfter.');
            }
        }

        return [$expiresAfter, $staleAfter ?? $expiresAfter];
    }
}
