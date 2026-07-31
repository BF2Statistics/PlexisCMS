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
use Memcached;
use System\Cache\CacheInterface;
use System\Cache\DriverInfo;


/**
 * Class MemcachedDriver
 *
 * Implements the CacheInterface using a Memcached backend. This class provides
 * methods for interacting with a Memcached server to store, retrieve, and manage
 * cached data. It includes support for expiration control and softTTL for stale reads.
 */
class MemcachedDriver implements CacheInterface
{
    /**
     * Memcached client instance.
     *
     * This is the primary interface for interacting with the Memcached server.
     *
     * @var Memcached
     */
    private Memcached $memcached;

    /**
     * Lock timeout in seconds.
     *
     * Determines how long a lock should persist before timing out.
     *
     * @var int
     */
    private int $lockTimeout = 10;

    /**
     * Store lock tokens locally during the process
     *
     * @var array
     */
    private array $lockTokens = [];

    /**
     * Default TTL used when Memcached remaining TTL cannot be determined (e.g., during
     * increment/decrement JSON fallback updates). Configurable via $connectionData['defaultTtl'].
     *
     * @var int
     */
    private int $defaultTtl = 3600;

    /**
     * Initializes the Memcached client and connects it to the specified servers.
     *
     * @param array $connectionData An associative array containing connection details,
     *                              including a 'servers' key with an array of server configurations.
     *                              Each server is expected to have 'host' and 'port' keys.
     *
     * @throws Exception If the Memcached extension is not loaded.
     */
    public function __construct(array $connectionData)
    {
        if (!extension_loaded('memcached')) {
            throw new Exception("The Memcached extension is not loaded.");
        }

        // Connect and add all servers
        $this->memcached = new \Memcached();
        foreach ($connectionData['servers'] as $server)
        {
            $this->memcached->addServer($server['host'], $server['port']);
        }

        // Optional default TTL for JSON fallback updates
        if (!empty($connectionData['defaultTtl']) && is_int($connectionData['defaultTtl']) && $connectionData['defaultTtl'] > 0) {
            $this->defaultTtl = $connectionData['defaultTtl'];
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
        $result = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return $default;
        }

        // Decode cached object
        $cachedObject = json_decode($result, true);
        if (!is_array($cachedObject) || !array_key_exists('value', $cachedObject)) {
            return $default;
        }

        $now = time();
        // Enforce hard expiration if expiresAt is present
        if (isset($cachedObject['expiresAt']) && $now > (int)$cachedObject['expiresAt']) {
            // Best effort cleanup
            $this->memcached->delete($key);
            return $default;
        }

        $isStale = isset($cachedObject['staleAfter']) ? ($now > (int)$cachedObject['staleAfter']) : false;
        return $cachedObject['value'];
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
        $expiresAfterTime = $now + $expiresAfter;
        $data = [
            'value'      => $value,
            'staleAfter' => $staleAfter ? ($now + $staleAfter) : $expiresAfterTime,
            'expiresAt'  => $expiresAfterTime,
        ];
        return $this->memcached->set($key, json_encode($data), $expiresAfterTime);
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
        $result = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return false;
        }

        $data = json_decode($result, true);
        if (!is_array($data) || !array_key_exists('value', $data)) {
            return false;
        }

        // Enforce hard expiry if available
        if (isset($data['expiresAt']) && time() > (int)$data['expiresAt']) {
            $this->memcached->delete($key);
            return false;
        }

        return true; // Stale is acceptable here; caller may refine later
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
        return $this->memcached->delete($key);
    }

    /**
     * Clears all items from the cache.
     *
     * @return bool True on success, false on failure.
     */
    public function clear(): bool
    {
        return $this->memcached->flush();
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
        $keyArray = (array)$keys;
        $rawResults = $this->memcached->getMulti($keyArray);

        $now = time();
        $final = [];
        foreach ($keyArray as $k) {
            if (!isset($rawResults[$k])) {
                $final[$k] = $default;
                continue;
            }

            $decoded = json_decode($rawResults[$k], true);
            if (!is_array($decoded) || !array_key_exists('value', $decoded)) {
                $final[$k] = $default;
                continue;
            }

            if (isset($decoded['expiresAt']) && $now > (int)$decoded['expiresAt']) {
                // Cleanup expired entries
                $this->memcached->delete($k);
                $final[$k] = $default;
                continue;
            }

            $final[$k] = $decoded['value'];
        }

        return $final;
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
        $data = [];
        $now = time();
        $expirationTime = $now + $expiresAfter;

        foreach ($values as $key => $value)
        {
            $data[$key] = json_encode([
                'value'      => $value,
                'staleAfter' => $staleAfter ? ($now + $staleAfter) : $expirationTime,
                'expiresAt'  => $expirationTime,
            ]);
        }

        return $this->memcached->setMulti($data, $expirationTime);
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
        $success = true;
        foreach ($keys as $key)
        {
            if (!$this->memcached->delete($key))
            {
                $success = false;
            }
        }
        return $success;
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
        // Fetch existing value
        $raw = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('value', $data)) {
            return false;
        }

        $now = time();
        $hardTtlAt = $now + $expiresAfter;
        $data['staleAfter'] = $staleAfter !== null ? ($now + $staleAfter) : $hardTtlAt;
        $data['expiresAt'] = $hardTtlAt;

        return $this->memcached->set($key, json_encode($data), $hardTtlAt);
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
        // Try atomic increment first (works only for numeric raw values)
        $newVal = $this->memcached->increment($key, $step);
        if ($newVal !== false) {
            return (int)$newVal;
        }

        // Fallback for structured payload {value, staleAfter}
        $raw = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['value']) || !is_numeric($data['value'])) {
            return false;
        }

        $data['value'] += $step;

        // Memcached does not expose remaining TTL; use configured default TTL
        $now = time();
        $hardTtlAt = $now + $this->defaultTtl;
        $data['expiresAt'] = $hardTtlAt;
        if (!isset($data['staleAfter']) || $data['staleAfter'] > $hardTtlAt) {
            $data['staleAfter'] = $hardTtlAt;
        }

        $this->memcached->set($key, json_encode($data), $hardTtlAt);
        return (int)$data['value'];
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
        // Try atomic decrement first
        $newVal = $this->memcached->decrement($key, $step);
        if ($newVal !== false) {
            return (int)$newVal;
        }

        // Fallback for structured payload {value, staleAfter}
        $raw = $this->memcached->get($key);
        if ($this->memcached->getResultCode() === Memcached::RES_NOTFOUND) {
            return false;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['value']) || !is_numeric($data['value'])) {
            return false;
        }

        $data['value'] -= $step;

        $now = time();
        $hardTtlAt = $now + $this->defaultTtl;
        $data['expiresAt'] = $hardTtlAt;
        if (!isset($data['staleAfter']) || $data['staleAfter'] > $hardTtlAt) {
            $data['staleAfter'] = $hardTtlAt;
        }

        $this->memcached->set($key, json_encode($data), $hardTtlAt);
        return (int)$data['value'];
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
        $lockKey = "$key:lock";
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;

        // Try to get existing data
        $raw = $this->memcached->get($key);
        if ($this->memcached->getResultCode() !== Memcached::RES_NOTFOUND)
        {
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['value']))
            {
                // If we have a hard expiry and it's past, treat as missing
                if (isset($data['expiresAt']) && $now > (int)$data['expiresAt']) {
                    // Best-effort cleanup
                    $this->memcached->delete($key);
                }
                else if (isset($data['staleAfter']) && $data['staleAfter'] > $now) {
                    // Fresh
                    return $data['value'];
                }

                // Stale: try to acquire lock and refresh, otherwise serve stale
                if (!$this->acquireLock($lockKey)) {
                    return $data['value'];
                }

                try {
                    $generated = $callback();
                    $this->set($key, $generated, $expiresAfter, $staleAfter);
                    return $generated;
                }
                finally {
                    $this->releaseLock($lockKey);
                }
            }
        }

        // No data: try to acquire lock
        if ($this->acquireLock($lockKey))
        {
            try {
                $generated = $callback();
                $this->set($key, $generated, $expiresAfter, $staleAfter);
                return $generated;
            }
            finally {
                $this->releaseLock($lockKey);
            }
        }

        // Wait for lock to be released, then try again
        $start = microtime(true);
        while ($this->memcached->get($lockKey) !== false) {
            usleep(50000); // 50ms
            // Avoid infinite wait in unlikely cases: obey lockTimeout
            if ((microtime(true) - $start) >= ($this->lockTimeout + 1)) {
                break;
            }
        }

        $raw = $this->memcached->get($key);
        if ($this->memcached->getResultCode() !== Memcached::RES_NOTFOUND) {
            $data = json_decode($raw, true);
            if (is_array($data) && array_key_exists('value', $data)) {
                // Only return if not hard-expired
                if (!isset($data['expiresAt']) || time() <= (int)$data['expiresAt']) {
                    return $data['value'];
                }
            }
        }

        // Fallback regenerate
        $generated = $callback();
        $this->set($key, $generated, $expiresAfter, $staleAfter);
        return $generated;
    }

    /**
     * Attempts to acquire a lock for a given key. The lock is created with a unique token
     * and stored in the caching system. This method ensures that the lock is not overwritten
     * by other processes, providing atomicity.
     *
     * @param string $lockKey The unique identifier for the lock to be acquired.
     *
     * @return bool True if the lock was successfully acquired, false if a lock already exists for the given key.
     *
     * @throws Exception If the random token generation fails.
     */
    private function acquireLock(string $lockKey): bool
    {
        $token = bin2hex(random_bytes(16)); // Create a unique token

        // The `add` operation ensures that locks will not be overwritten. Its Atomic
        $success = $this->memcached->add($lockKey, $token, $this->lockTimeout);
        if ($success) {
            $this->lockTokens[$lockKey] = $token; // Keep track of the token locally
        }

        return $success; // True if lock was acquired, false if it already exists
    }

    /**
     * Releases a distributed lock associated with the given key. The lock is removed
     * only if it matches the token stored locally, ensuring that the release operation
     * is valid and safe.
     *
     * @param string $lockKey The unique key identifying the lock to be released.
     *
     * @return bool True if the lock was successfully released, false otherwise.
     */
    private function releaseLock(string $lockKey): bool
    {
        $token = $this->lockTokens[$lockKey] ?? null; // Retrieve the token associated with the lock
        if ($token === null) {
            return false; // No lock token available for this key
        }

        // Retrieve the current value of the lock (the owner token)
        $currentValue = $this->memcached->get($lockKey);

        // Compare the tokens: Only delete the lock if it matches our token
        if ($currentValue === $token)
        {
            $this->memcached->delete($lockKey); // Delete the lock
            unset($this->lockTokens[$lockKey]); // Remove the token from local tracking
            return true;
        }

        return false; // Lock is owned by someone else or doesn't exist anymore
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
            name: 'Memcached',
            readableName: 'Memcached',
            isSupported: extension_loaded('memcached'),
            description: 'Memcached is a high-performance, distributed memory object caching system.'
        );
    }
}