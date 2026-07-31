<?php
/**
 * Plexis Core.
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace System\Http\Session\Storage;

use Exception;
use Memcached;
use System\Cache\DriverInfo;

/**
 * Memcached-backed implementation of {@see SessionStorageInterface}.
 *
 * Stores session payloads in Memcached using the provided
 * session ID as the cache key (optionally prefixed).
 *
 * Notes:
 * - Memcached handles expiration internally via TTL, so explicit garbage collection
 *   is not required.
 * - {@see \Memcached::get()} returns `false` on failure *and* when the stored value
 *   is literally boolean `false`. This storage assumes session payloads are strings,
 *   so treating `false` as "not found" is acceptable.
 */
class MemcachedSessionStorage implements SessionStorageInterface
{
    /**
     * Memcached client instance used to communicate with the configured Memcached servers.
     *
     * @var Memcached
     */
    private Memcached $memcached;

    /**
     * Key prefix for session storage (e.g., "session:").
     *
     * Allows namespacing session keys to avoid collisions with other Memcached consumers.
     *
     * @var string
     */
    private string $keyPrefix = '';

    /**
     * Creates a new Memcached session storage instance and connects to the configured servers.
     *
     * Expected $connectionData format:
     * - `servers` (array<int, array{host: string, port: int}>): List of Memcached servers.
     *
     * @param array $connectionData Connection configuration (see format above).
     * @param string $keyPrefix Optional key prefix prepended to all session keys.
     *
     * @throws Exception If the Memcached PHP extension is not loaded.
     */
    public function __construct(array $connectionData, string $keyPrefix = '')
    {
        // Check extension (line 73-75 in MemcachedDriver)
        if (!extension_loaded('memcached')) {
            throw new Exception("The Memcached extension is not loaded.");
        }

        // Connect to servers
        $this->memcached = new \Memcached();
        foreach ($connectionData['servers'] as $server) {
            $this->memcached->addServer($server['host'], $server['port']);
        }

        $this->keyPrefix = $keyPrefix;
    }

    /**
     * @inheritDoc
     */
    public function initialize(string $sessionId, int $ttl): void
    {
        $sessionId = $this->prefixKey($sessionId);

        // Update the TTL
        $this->memcached->touch($sessionId, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function load(string $sessionId): ?string
    {
        $sessionId = $this->prefixKey($sessionId);
        $data = $this->memcached->get($sessionId);
        return ($data === false) ? null : $data;
    }

    /**
     * @inheritDoc
     */
    public function save(string $sessionId, string $data, int $ttl): void
    {
        $sessionId = $this->prefixKey($sessionId);
        $this->memcached->set($sessionId, $data, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function destroy(string $sessionId): void
    {
        $sessionId = $this->prefixKey($sessionId);
        $this->memcached->delete($sessionId);
    }

    /**
     * @inheritDoc
     */
    public function purgeStaleSessions(int $timeToLive): void
    {
        // Not needed - Memcached handles expiration automatically
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
     * @inheritDoc
     */
    public static function GetDriverInfo(): DriverInfo
    {
        return new DriverInfo(
            name: 'Memcached',
            readableName: 'Memcached',
            isSupported: extension_loaded('memcached'),
            description: 'Memcached is a high-performance, distributed memory caching system ideal for session storage.'
        );
    }
}