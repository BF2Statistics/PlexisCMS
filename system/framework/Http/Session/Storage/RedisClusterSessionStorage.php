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
namespace System\Http\Session\Storage;

use Exception;
use Redis;
use RedisCluster;
use System\Cache\DriverInfo;

/**
 * RedisCluster-based session storage implementation.
 *
 * This class provides functionality to interact with a Redis Cluster for managing session data.
 * It includes methods to initialize, load, save, and destroy session data, using the
 * `RedisCluster` PHP extension. Sessions are stored as strings in the Redis Cluster, and TTL
 * (time-to-live) is used to determine their expiration.
 *
 * RedisCluster provides high availability and horizontal scaling for session storage.
 */
class RedisClusterSessionStorage implements SessionStorageInterface
{
    /**
     * RedisCluster client instance, used for interacting with the Redis Cluster.
     *
     * @var RedisCluster
     */
    private RedisCluster $redis;

    /**
     * Key prefix for session storage (e.g., 'session:')
     * @var string
     */
    private string $keyPrefix = '';

    /**
     * Constructor to initialize a connection to the Redis Cluster.
     *
     * The provided connection data is used to establish a connection to the cluster.
     * Optionally, authentication credentials can be specified.
     *
     * @param array $connectionData An associative array containing the connection details:
     *     - `nodes` (array): Array of seed node strings in 'host:port' format (required).
     *     - `timeout` (float|null): Optional connection timeout value in seconds.
     *     - `read_timeout` (float|null): Optional read timeout value in seconds.
     *     - `persistent` (bool|null): Optional flag for persistent connections.
     *     - `password` (string|null): Optional password for authentication.
     *     - `serializer` (int|null): Optional serializer (Redis::SERIALIZER_PHP, etc.)
     *
     * @throws Exception If the Redis extension with RedisCluster support is not loaded.
     * @throws Exception If the connection to the Redis Cluster fails.
     */
    public function __construct(array $connectionData, string $keyPrefix = '')
    {
        if (!extension_loaded('redis') || !class_exists(RedisCluster::class)) {
            throw new Exception("The Redis extension with RedisCluster support is not loaded.");
        }

        $nodes = $connectionData['nodes'] ?? null;
        if (!is_array($nodes) || empty($nodes)) {
            throw new Exception("RedisCluster connection data must contain non-empty 'nodes' array.");
        }

        try
        {
            $timeout = (float)($connectionData['timeout'] ?? 0.0);
            $readTimeout = (float)($connectionData['read_timeout'] ?? 0.0);
            $persistent = (bool)($connectionData['persistent'] ?? false);

            // Instantiate RedisCluster client
            // Signature: __construct(?string $name, array $seeds, float $timeout, float $read_timeout, bool $persistent)
            $this->redis = new RedisCluster(null, $nodes, $timeout, $readTimeout, $persistent);

            // Set serializer to none because the session container will encode the data
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);

            // Authenticate if password provided
            if (!empty($connectionData['password']) && method_exists($this->redis, 'auth'))
            {
                $this->redis->auth($connectionData['password']);
            }
        }
        catch (\RedisClusterException $e)
        {
            throw new Exception("Could not connect to Redis Cluster: " . $e->getMessage(), $e->getCode(), $e);
        }

        $this->keyPrefix = $keyPrefix;
    }

    /**
     * @inheritDoc
     */
    public function initialize(string $sessionId, int $ttl): void
    {
        // Update the TTL for another amount of time if session exists
        $sessionId = $this->prefixKey($sessionId);
        if ($this->redis->exists($sessionId))
            $this->redis->expire($sessionId, $ttl);

        // No other initialization required for RedisCluster
    }

    /**
     * Retrieves the session data associated with a given session ID.
     *
     * The session data is stored as a JSON-encoded string, or `null` is returned
     * if no data exists for the provided session ID.
     *
     * @param string $sessionId The session ID to retrieve data for.
     *
     * @return string|null The stored session data as a JSON string, or `null` if the session does not exist.
     */
    public function load(string $sessionId): ?string
    {
        $sessionId = $this->prefixKey($sessionId);
        $data = $this->redis->get($sessionId);
        return $data === false ? null : $data;
    }

    /**
     * Saves session data for a given session ID.
     *
     * The session data is stored as a JSON-encoded string along with a TTL (time-to-live).
     *
     * @param string $sessionId The session ID for which the data is being saved.
     * @param string $data The session data as a JSON-encoded string.
     * @param int $ttl The time-to-live for the session data in seconds.
     *
     * @return void
     */
    public function save(string $sessionId, string $data, int $ttl): void
    {
        $sessionId = $this->prefixKey($sessionId);
        $this->redis->set($sessionId, $data, $ttl);
    }

    /**
     * Deletes the session data associated with a given session ID.
     *
     * This method removes the session data from the Redis Cluster storage entirely.
     *
     * @param string $sessionId The session ID to delete.
     *
     * @return void
     */
    public function destroy(string $sessionId): void
    {
        $sessionId = $this->prefixKey($sessionId);
        $this->redis->del($sessionId);
    }

    /**
     * @inheritDoc
     */
    public function purgeStaleSessions(int $timeToLive): void
    {
        // Not needed in RedisCluster - TTL handles expiration automatically
    }

    /**
     * Prepends the key prefix to the session ID
     *
     * @param string $sessionId The raw session ID
     * @return string The prefixed session key
     */
    private function prefixKey(string $sessionId): string
    {
        return $this->keyPrefix . $sessionId;
    }

    /**
     * Retrieves information about the RedisCluster driver, including its name,
     * human-readable name, support status, and description.
     *
     * @return DriverInfo An object containing details about the RedisCluster driver.
     */
    public static function GetDriverInfo(): DriverInfo
    {
        $isSupported = extension_loaded('redis') && class_exists(RedisCluster::class);

        return new DriverInfo(
            name: 'RedisCluster',
            readableName: 'Redis Cluster',
            isSupported: $isSupported,
            description: 'Redis Cluster is a distributed implementation of Redis with automatic sharding, high availability, and horizontal scaling capabilities.'
        );
    }
}