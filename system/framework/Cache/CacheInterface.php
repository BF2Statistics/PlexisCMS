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
namespace System\Cache;

use Exception;

/**
 * Interface CacheInterface
 *
 * Provides a standardized PSR-16 compliant interface for cache handling, including methods
 * to interact with cached data either for single or multiple keys.
 *
 * Supports serving stale data (softTTL) before data expires completely (hardTTL)
 * Supports stampede protection with @see getOrRegenerateWithLock
 *
 * @package System\CacheNew
 */
interface CacheInterface
{
    /**
     * Retrieves an item from the cache by its unique key.
     *
     * @param string $key The unique cache key identifying the item.
     * @param mixed $default The default value to return if the key is not found in the cache.
     * @param bool|null $isStale Indicates whether the data assigned to the associated $key is stale, but still retrievable.
     *
     * @return mixed The cached value, or the default value if the key does not exist.
     */
    public function get(string $key, mixed $default = null, ?bool &$isStale = false): mixed;

    /**
     * Stores an item in the cache, identified by a key.
     *
     * @param string    $key The unique cache key for the stored item.
     * @param mixed     $value The value to be cached.
     * @param int       $expiresAfter The time (in seconds) before expiration. Default is 3600 seconds.
     * @param int|null  $staleAfter The time (in seconds) after which the data is stale but still retrievable.
     *                              If null, data never becomes stale and only expires at `$expiresAfter`.
     *
     * @return bool True on success, false on failure.
     */
    public function set(string $key, mixed $value, int $expiresAfter = 3600, ?int $staleAfter = null): bool;

    /**
     * Checks if an item exists in the cache by its unique key.
     *
     * @param string $key The unique cache key to check for existence.
     *
     * @return bool True if the cache item exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Deletes an item from the cache by its unique key.
     *
     * @param string $key The unique cache key identifying the item to delete.
     *
     * @return bool True if the item was successfully deleted, false otherwise.
     */
    public function delete(string $key): bool;

    /**
     * Clears all items from the cache.
     *
     * @return bool True on success, false on failure.
     */
    public function clear(): bool;

    /**
     * Retrieves multiple items from the cache by their unique keys.
     *
     * @param iterable $keys An iterable list of unique cache keys to retrieve.
     * @param mixed $default The default value to return for keys that do not exist.
     *
     * @return iterable An iterable list of values retrieved from the cache. For missing keys, the default value is returned.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable;

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
    public function setMultiple(iterable $values, int $expiresAfter = 3600, ?int $staleAfter = null): bool;

    /**
     * Deletes multiple items from the cache by their unique keys.
     *
     * @param iterable $keys An iterable list of unique cache keys to delete.
     *
     * @return bool True if the items were successfully deleted, false otherwise.
     */
    public function deleteMultiple(iterable $keys): bool;

    /**
     * Refreshes the cache item associated with the given key, updating its time-to-live (TTL) values.
     *
     * @param string $key The unique cache key identifying the item to refresh.
     * @param int $expiresAfter The hard time-to-live in seconds; determines the period after which the item expires completely (optional).
     * @param ?int $staleAfter The soft time-to-live in seconds; determines the period after which the item is considered stale but can still be used (optional).
     *
     * @return bool True on successful refresh, or false if the refresh fails or the item does not exist.
     */
    public function refresh(string $key, int $expiresAfter = 3600, ?int $staleAfter = null): bool;

    /**
     * Atomically increments a numeric value stored in the cache by the specified step.
     *
     * @param string $key The unique cache key identifying the item to increment.
     * @param int $step The amount by which to increment the value (default is 1).
     *
     * @return int|false The incremented value, or false if the operation fails or the $key doesn't exist
     */
    public function increment(string $key, int $step = 1): int|false;

    /**
     * Atomically decreases the numeric value of an item in the cache by the specified step
     *
     * @param string $key The unique cache key identifying the item.
     * @param int $step The value by which the item's value should be decreased (optional, defaults to 1).
     *
     * @return int|false The decremented value, or false if the operation fails or the $key doesn't exist
     */
    public function decrement(string $key, int $step = 1): int|false;

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
     * @param string   $key          The unique key identifying the cache item.
     * @param callable $callback     A callback function to generate the data if regeneration is required.
     *                                The callback should return the new value that needs to be cached.
     * @param int      $expiresAfter The duration, in seconds, for which the item is considered valid
     *                                before expiring completely (hard TTL). Defaults to 3600 (1 hour).
     * @param int|null $staleAfter   The duration, in seconds, after which the item is considered stale.
     *                                Stale data can still be served while being refreshed if this value is set.
     *                                If null, stale data handling is disabled, and only the hard TTL is considered.
     *
     * @return mixed The cached value if it exists and is valid, or the newly generated value upon regeneration.
     *
     * @throws Exception If an error occurs with the locking or cache mechanism.
     */
    public function getOrRegenerateWithLock(string $key, callable $callback, int $expiresAfter = 3600, ?int $staleAfter = null): mixed;

    /**
     * Retrieves information about the driver used for caching.
     *
     * This method provides details about the caching driver, including its name
     * and whether it is supported in the current environment.
     *
     * @return DriverInfo An instance containing the driver's name and support status.
     */
    public static function GetDriverInfo(): DriverInfo;
}