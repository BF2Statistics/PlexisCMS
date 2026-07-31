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
use System\Cache\DriverInfo;

/**
 * Redis-based session storage implementation.
 *
 * This class provides functionality to interact with a Redis server for managing session data.
 * It includes methods to initialize, load, save, and destroy session data, using the
 * `Redis` PHP extension. Sessions are stored as strings in the Redis server, and TTL (time-to-live)
 * is used to determine their expiration.
 */
class RedisSessionStorage implements SessionStorageInterface
{
    /**
     * Redis client instance, used for interacting with the Redis server.
     *
     * @var Redis
     */
    private Redis $redis;

    /**
     * Key prefix for session storage (e.g., "session:").
     *
     * Allows namespacing session keys to avoid collisions with other consumers.
     *
     * @var string
     */
    private string $keyPrefix = '';

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
     *     - `username` (string|null): Optional username for Redis ACL (Redis v6+).
     *     - `password` (string|null): Optional password for authentication.
     *     - `database` (int|null): Optional Redis database index to select.
     *
     * @throws Exception If the Redis extension is not loaded.
     * @throws Exception If the connection to the Redis server fails.
     */
    public function __construct(array $connectionData, string $keyPrefix = '')
    {
        if (!extension_loaded('redis')) {
            throw new Exception("The Redis extension is not loaded.");
        }

        try
        {
            $this->redis = new \Redis();

            // Attempt connection
            if (!empty($connectionData['persistent_id']))
            {
                $this->redis->pconnect(
                    $connectionData['host'],
                    $connectionData['port'],
                    $connectionData['timeout'] ?? 0,
                    $connectionData['persistent_id']
                );
            }
            else
            {
                $this->redis->connect(
                    $connectionData['host'],
                    $connectionData['port'],
                    $connectionData['timeout'] ?? 0
                );
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

            // Set serializer to none because the container will encode the data
            $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

            // Switch to database index
            if (!empty($connectionData['database']) && is_int($connectionData['database']) && $connectionData['database'] >= 0)
                $this->redis->select($connectionData['database']);
        }
        catch (\RedisException $e)
        {
            throw new Exception("Could not connect to Redis: " . $e->getMessage(), $e->getCode(), $e);
        }

        $this->keyPrefix = $keyPrefix;
    }

    /**
     * @inheritDoc
     */
    public function initialize(string $sessionId, int $ttl): void
    {
        // need to update the TTL for another amount of time
        $sessionId = $this->prefixKey($sessionId);
        if ($this->redis->exists($sessionId))
            $this->redis->expire($sessionId, $ttl);

        // No other initialization required for Redis
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
     * This method removes the session data from the Redis storage entirely.
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
        // Not needed in redis
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
     * Retrieves information about the Redis driver, including its name,
     * human-readable name, support status, and description.
     *
     * @return DriverInfo An object containing details about the Redis driver.
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
}