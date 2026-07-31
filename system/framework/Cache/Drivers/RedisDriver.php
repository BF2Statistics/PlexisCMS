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
use Redis;
use System\Cache\CacheInterface;
use System\Cache\DriverInfo;

/**
 * Class RedisDriver
 *
 * Implements the CacheInterface using a Redis backend. This class provides
 * methods for interacting with a Redis server to store, retrieve, and manage
 * cached data. It includes methods for single and multiple key operations,
 * expiration control, and support for stale reads.
 *
 * PSR-16 compliant
 */
class RedisDriver implements CacheInterface
{
    /**
     * Redis client instance.
     *
     * This is the primary interface for interacting with the Redis server.
     *
     * @var Redis
     */
    private Redis $redis;

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
     * Constructor to initialize a connection to the Redis server.
     *
     * The provided connection data is used to establish a connection.
     * Optionally, authentication credentials and database index can be specified.
     *
     * @param array $connectionData An associative array containing the connection details:
     *     - `host` (string): The hostname or IP address of the Redis server.
     *     - `port` (int): The port number on which the Redis server is running.
     *     - `persistent_id` (string|null): Optional persistent connection identifier.
     *     - `timeout` (float|null): Optional connection timeout value in seconds.
     *     - `retry_interval` (int|null) Optional time to wait between connection retries
     *     - `username` (string|null): Optional username for Redis ACL (Redis v6+).
     *     - `password` (string|null): Optional password for authentication.
     *     - `database` (int|null): Optional Redis database index to select.
     *     - `read_timeout` (float|null) Optional response timeout from the Redis server
     *     - `serializer` (int): Optional Redis serializer method. Defaults to IgBinary if installed, or PHP_SERIALIZE
     *
     * @throws Exception If the Redis extension is not loaded.
     * @throws InvalidArgumentException If required connection data is missing.
     * @throws Exception If the connection to the Redis server fails.
     */
    public function __construct(array $connectionData)
    {
        // Ensure Redis extension is loaded
        if (!extension_loaded('redis')) {
            throw new Exception("The Redis extension is not loaded.");
        }

        // Ensure we have at least the required data
        if (empty($connectionData['host']) || empty($connectionData['port'])) {
            throw new InvalidArgumentException("Redis connection data must contain 'host' and 'port'.");
        }

        // Ensure we have a proper time out
        $timeout = $connectionData['timeout'] ?? 0.0; // Default timeout is 0 seconds (no limit)

        try
        {
            $this->redis = new Redis();

            // Attempt connection
            if (!empty($connectionData['persistent_id'])) {
                $this->redis->pconnect($connectionData['host'], $connectionData['port'], $timeout, $connectionData['persistent_id']);
            }
            else {
                $this->redis->connect($connectionData['host'], $connectionData['port'], $timeout);
            }

            // Authenticate with the server if credentials are provided
            if (!empty($connectionData['password']))
            {
                if (!empty($connectionData['username']))
                {
                    // Use username and password for Redis 6+ ACL authentication
                    $this->redis->auth([$connectionData['username'], $connectionData['password']]);
                }
                else
                {
                    // Use password-only authentication
                    $this->redis->auth($connectionData['password']);
                }
            }

            // Set serializer to none. We do our own serialization here
            // Set the serializer (default to PHP serializer)
			$defined = property_exists('Redis', 'SERIALIZER_IGBINARY');
            $canUseIgBinary = extension_loaded('igbinary') && $defined;
            $serializer = $connectionData['serializer'] ?? ($canUseIgBinary ? Redis::SERIALIZER_IGBINARY : Redis::SERIALIZER_PHP);

            // Ensure we can use igbinary if selected
            if (!$canUseIgBinary && $defined && $serializer == Redis::SERIALIZER_IGBINARY)
            {
                \System::Log()->logWarning("Redis driver is configured to use IGBINARY serializer, but IGBINARY extension is not loaded. Falling back to PHP serializer.");
                $serializer = Redis::SERIALIZER_PHP;
            }

            // Set serializer
            $this->redis->setOption(Redis::OPT_SERIALIZER, $serializer);

            // Switch to database index
            if (!empty($connectionData['database']) && is_int($connectionData['database']) && $connectionData['database'] >= 0)
                $this->redis->select($connectionData['database']);
        }
        catch (\RedisException $e)
        {
            throw new Exception("Could not connect to Redis: " . $e->getMessage(), $e->getCode(), $e);
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
        $data = $this->redis->get($key);
        if ($data === false)
        {
            $isStale = false;
            return $default;
        }

        // Check if the data is stale
        $now = time();
        $isStale = $data['softTTL'] < $now;
        return $data['value'];
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
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;

        // Serialize the cache metadata and value
        $data = [
            'value' => $value,
            'softTTL' => $now + $staleAfter
        ];

        // Store the serialized data in Redis, with an expiration time
        return $this->redis->set($key, $data, ['ex' => $expiresAfter]) !== false;
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
        return (bool) $this->redis->exists($key);
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
        return $this->redis->del($key) === 1;
    }

    /**
     * Clears all items from the cache.
     *
     * @return bool True on success, false on failure.
     */
    public function clear(): bool
    {
        $this->redis->flushDB();
        return true;
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
        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);
        $rawValues = $this->redis->mget($keyArray);

        $result = [];
        foreach ($keyArray as $index => $key)
        {
            $data = $rawValues[$index];
            if ($data === false) {
                $result[$key] = $default;
                continue;
            }

            $result[$key] = $data['value'];
        }

        return $result;
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
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;
        $pipe = $this->redis->multi(Redis::PIPELINE);

        foreach ($values as $key => $value) {
            $data = [
                'value' => $value,
                'softTTL' => $now + $staleAfter
            ];
            $pipe->set($key, $data, ['ex' => $expiresAfter]);
        }

        $results = $pipe->exec();
        return !in_array(false, $results, true);
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
        return $this->redis->del($keyArray) > 0;
    }

    /**
     * Refreshes the cache item associated with the given key, updating its time-to-live (TTL) values.
     *
     * @param string $key The unique cache key identifying the item to refresh.
     * @param int $expiresAfter The hard time-to-live in seconds; determines the period after which the item expires completely (optional).
     * @param ?int $staleAfter The soft time-to-live in seconds; determines the period after which the item is considered stale but can still be used (optional).
     *
     * @return bool True on a successful refresh, or false if the refresh fails or the item does not exist.
     */
    public function refresh(string $key, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        // Check if the key exists
        if (!$this->has($key)) {
            return false;
        }

        // Get the current data to preserve the value
        $data = $this->redis->get($key);
        if ($data === false || !is_array($data) || !array_key_exists('value', $data)) {
            return false;
        }

        // Update both soft and hard TTL
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;
        $data['softTTL'] = $now + $staleAfter;

        // Update with new TTL
        return $this->redis->set($key, $data, ['ex' => $expiresAfter]) !== false;
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
        // Fetch the current cache entry
        $data = $this->redis->get($key);
        if ($data === false)
        {
            return false;
        }

        // Ensure the value is numeric
        if (!is_numeric($data['value'])) {
            return false;
        }

        // Increment the value
        $data['value'] += $step;

        // Re-serialize and update in Redis (preserving softTTL and other metadata)
        $serializedData = serialize($data);
        $this->redis->set($key, $serializedData);

        return $data['value'];
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
        // Fetch the current cache entry
        $data = $this->redis->get($key);
        if ($data === false) {
            return false;
        }

        // Ensure the value is numeric
        if (!is_numeric($data['value'])) {
            return false;
        }

        // Decrement the value
        $data['value'] -= $step;

        // Re-serialize and update in Redis (preserving softTTL and other metadata)
        $serializedData = serialize($data);
        $this->redis->set($key, $serializedData);

        return $data['value'];
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
        $lockKey = "{$key}:lock";
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;

        // Step 1: Attempt to retrieve the cached value
        $data = $this->redis->get($key);
        if ($data !== false)
        {
            if ($data['softTTL'] > $now)
            {
                // Data is still fresh
                return $data['value'];
            }

            // Data is stale
            if (!$this->acquireLock($lockKey))
            {
                // Failed to acquire lock, serve stale data
                return $data['value'];
            }

            try
            {
                // Refresh the stale data
                $generatedValue = $callback();
                $this->set($key, $generatedValue, $expiresAfter, $staleAfter);
                return $generatedValue;
            }
            finally
            {
                $this->releaseLock($lockKey);
            }
        }

        // Step 2: If no valid/stale data, acquire lock to regenerate
        if ($this->acquireLock($lockKey))
        {
            // Acquire the lock successfully
            try
            {
                $generatedValue = $callback();
                $this->set($key, $generatedValue, $expiresAfter, $staleAfter);
                return $generatedValue;
            }
            finally
            {
                $this->releaseLock($lockKey);
            }
        }

        // Step 3: If unable to acquire lock, wait for regeneration and retrieve the value again
        $i = 0;
        while ($this->redis->exists($lockKey) && $i < 50)
        {
            usleep(50000); // Sleep for 50ms before retrying (reduce busy-waiting)
            $i++;
        }

        // Log error if unable to acquire lock after 50 attempts
        if ($i == 50)
        {
            \System::Log()->logError("Unable to acquire cache data for key '$key' after 50 attempts.");
        }

        // Retry to fetch the value after lock is released
        $data = $this->redis->get($key);
        if ($data !== false && is_array($data) && array_key_exists('value', $data)) {
            return $data['value'];
        }

        // If all else fails, regenerate as a fallback, though this is unlikely
        $generatedValue = $callback();
        $this->set($key, $generatedValue, $expiresAfter, $staleAfter);
        return $generatedValue;
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
        return new DriverInfo(
            name: 'Redis',
            readableName: 'Redis',
            isSupported: extension_loaded('redis'),
            description: 'Redis is an in-memory key-value store with advanced features like persistence and pub-sub messaging.'
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
        $token = bin2hex(random_bytes(16)); // Generate a unique token
        $lockAcquired = $this->redis->set($lockKey, $token, ['NX', 'EX' => $this->lockTimeout]);

        if ($lockAcquired) {
            $this->lockTokens[$lockKey] = $token; // Keep track of the token for this lock
        }

        return (bool) $lockAcquired;
    }

    /**
     * Releases a previously acquired lock identified by its unique key.
     * Uses an atomic operation to ensure the lock is released only if it
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

        // We use LUA because `get()` and `del()` are NOT atomic operations,
        // but REDIS does process LUA scripts atomically
        $script = '
        if redis.call("get", KEYS[1]) == ARGV[1] then
            return redis.call("del", KEYS[1])
        else
            return 0
        end';

        $result = $this->redis->eval($script, [$lockKey, $token], 1);

        if ($result) {
            unset($this->lockTokens[$lockKey]); // Clear local token tracking
            return true;
        }

        return false; // Lock was not owned by this process
    }
}