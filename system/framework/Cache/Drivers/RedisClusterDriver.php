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
use Redis;
use RedisCluster;
use System\Cache\CacheInterface;
use System\Cache\DriverInfo;

/**
 * Class RedisclusterDriver
 *
 * Implements CacheInterface against a Redis Cluster using the phpredis extension.
 * Follows the same semantics as RedisDriver:
 * - Payload structure: [ 'value' => mixed, 'softTTL' => int ]
 * - Hard expiration handled by Redis via EX on SET
 * - Stale reads are allowed until hard expiration; `isStale` is set when now > softTTL
 * - Stampede protection via per-key lock using NX + EX and token-based Lua release
 */
class RedisclusterDriver implements CacheInterface
{
    /** @var RedisCluster */
    private RedisCluster $redis;

    /** Lock timeout in seconds (default 10s) */
    private int $lockTimeout = 10;

    /** Track lock tokens for safe release */
    private array $lockTokens = [];

    /**
     * @param array $connectionData Expected keys:
     *   - nodes (array<string>): array of 'host:port' seed strings (required)
     *   - password (string|null): cluster password (optional)
     *   - timeout (float|null): connection timeout seconds (optional, default 0.0)
     *   - read_timeout (float|null): read timeout seconds (optional)
     *   - persistent (bool|null): use persistent connections (optional, default false)
     *   - serializer (int|null): serializer option like Redis::SERIALIZER_PHP or IGBINARY
     *
     * @throws Exception
     */
    public function __construct(array $connectionData)
    {
        if (!extension_loaded('redis') || !class_exists(RedisCluster::class)) {
            throw new Exception('The Redis (phpredis) extension with RedisCluster support is not loaded.');
        }

        $nodes = $connectionData['nodes'] ?? null;
        if (!is_array($nodes) || empty($nodes)) {
            throw new InvalidArgumentException("RedisCluster connection data must contain non-empty 'nodes'.");
        }

        $timeout = (float)($connectionData['timeout'] ?? 0.0);
        $readTimeout = (float)($connectionData['read_timeout'] ?? 0.0);
        $persistent = (bool)($connectionData['persistent'] ?? false);

        try {
            // Instantiate cluster client. Name=null means non-persistent unless $persistent=true.
            // phpredis signature: __construct(?string $name, array $seeds, float $timeout=0, float $read_timeout=0, bool $persistent=false)
            $this->redis = new RedisCluster(null, $nodes, $timeout, $readTimeout, $persistent);

            // Serializer handling mirrors RedisDriver
            $defined = property_exists(Redis::class, 'SERIALIZER_IGBINARY');
            $canUseIgBinary = extension_loaded('igbinary') && $defined;
            $serializer = $connectionData['serializer'] ?? ($canUseIgBinary ? Redis::SERIALIZER_IGBINARY : Redis::SERIALIZER_PHP);
            if (!$canUseIgBinary && $defined && $serializer === Redis::SERIALIZER_IGBINARY) {
                \System::Log()->logWarning("RedisCluster driver configured to use IGBINARY serializer, but extension not loaded. Falling back to PHP serializer.");
                $serializer = Redis::SERIALIZER_PHP;
            }
            $this->redis->setOption(Redis::OPT_SERIALIZER, $serializer);

            // Authenticate if password provided (supported in modern phpredis)
            if (!empty($connectionData['password']) && method_exists($this->redis, 'auth')) {
                $this->redis->auth($connectionData['password']);
            }
        }
        catch (\RedisClusterException $e) {
            throw new Exception('Could not connect to Redis Cluster: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function get(string $key, mixed $default = null, ?bool &$isStale = false): mixed
    {
        $data = $this->redis->get($key);
        if ($data === false) {
            $isStale = false;
            return $default;
        }

        $now = time();
        $isStale = isset($data['softTTL']) ? ($data['softTTL'] < $now) : false;
        return $data['value'] ?? $default;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keyArray = is_array($keys) ? $keys : iterator_to_array($keys);

        // Note: RedisCluster doesn't support mget across different hash slots
        // So we need to handle this differently or use individual gets
        $result = [];
        $now = time();

        foreach ($keyArray as $key) {
            $data = $this->redis->get($key);
            if ($data === false) {
                $result[$key] = $default;
                continue;
            }

            // Check if data is properly structured
            if (!is_array($data) || !array_key_exists('value', $data)) {
                $result[$key] = $default;
                continue;
            }

            $result[$key] = $data['value'];
        }

        return $result;
    }

    public function set(string $key, mixed $value, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;
        $payload = [
            'value'   => $value,
            'softTTL' => $now + $staleAfter,
        ];
        return $this->redis->set($key, $payload, ['ex' => $expiresAfter]) !== false;
    }

    public function has(string $key): bool
    {
        return (bool)$this->redis->exists($key);
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) === 1;
    }

    public function clear(): bool
    {
        // phpredis broadcasts FLUSHDB across nodes for RedisCluster
        $this->redis->flushDB();
        return true;
    }

    public function setMultiple(iterable $values, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;
        $ok = true;
        foreach ($values as $key => $value) {
            $payload = [
                'value'   => $value,
                'softTTL' => $now + $staleAfter,
            ];
            $res = $this->redis->set($key, $payload, ['ex' => $expiresAfter]);
            if ($res === false) $ok = false;
        }
        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            if ($this->redis->del($key) <= 0) {
                $ok = false;
            }
        }
        return $ok;
    }

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

    public function increment(string $key, int $step = 1): int|false
    {
        $data = $this->redis->get($key);
        if ($data === false) {
            return false;
        }

        if (!is_array($data) || !isset($data['value']) || !is_numeric($data['value'])) {
            return false;
        }

        // Get current TTL to preserve it
        $ttl = $this->redis->ttl($key);
        if ($ttl <= 0) {
            return false; // Key expired or has no expiry
        }

        $data['value'] += $step;

        // Re-serialize and update with preserved TTL
        $this->redis->set($key, $data, ['ex' => $ttl]);

        return (int)$data['value'];
    }

    public function decrement(string $key, int $step = 1): int|false
    {
        $data = $this->redis->get($key);
        if ($data === false) {
            return false;
        }

        if (!is_array($data) || !isset($data['value']) || !is_numeric($data['value'])) {
            return false;
        }

        // Get current TTL to preserve it
        $ttl = $this->redis->ttl($key);
        if ($ttl <= 0) {
            return false; // Key expired or has no expiry
        }

        $data['value'] -= $step;

        // Re-serialize and update with preserved TTL
        $this->redis->set($key, $data, ['ex' => $ttl]);

        return (int)$data['value'];
    }

    public function getOrRegenerateWithLock(string $key, callable $callback, int $expiresAfter = 3600, ?int $staleAfter = null): mixed
    {
        $lockKey = "$key:lock";
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;

        // Try to get existing data
        $data = $this->redis->get($key);
        if ($data !== false) {
            if (($data['softTTL'] ?? $now) > $now) {
                // fresh
                return $data['value'];
            }

            // stale: attempt to acquire lock, else serve stale
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

        // Missing: acquire lock to regenerate
        if ($this->acquireLock($lockKey)) {
            try {
                $generated = $callback();
                $this->set($key, $generated, $expiresAfter, $staleAfter);
                return $generated;
            }
            finally {
                $this->releaseLock($lockKey);
            }
        }

        // Wait for lock release (bounded)
        $start = microtime(true);
        while ($this->redis->exists($lockKey)) {
            usleep(50_000);
            if ((microtime(true) - $start) >= ($this->lockTimeout + 1)) {
                break;
            }
        }

        // Retry fetch
        $data = $this->redis->get($key);
        if ($data !== false && is_array($data) && array_key_exists('value', $data)) {
            return $data['value'];
        }

        // Fallback regenerate
        $generated = $callback();
        $this->set($key, $generated, $expiresAfter, $staleAfter);
        return $generated;
    }

    public static function GetDriverInfo(): DriverInfo
    {
        return new DriverInfo(
            name: 'RedisCluster',
            readableName: 'Redis Cluster',
            isSupported: extension_loaded('redis') && class_exists(RedisCluster::class),
            description: 'Redis Cluster driver using phpredis with soft TTL (stale) and hard expiry via EX.'
        );
    }

    // === Lock helpers ===
    protected function acquireLock(string $lockKey): bool
    {
        $token = bin2hex(random_bytes(16));
        $acquired = $this->redis->set($lockKey, $token, ['NX', 'EX' => $this->lockTimeout]);
        if ($acquired) {
            $this->lockTokens[$lockKey] = $token;
        }
        return (bool)$acquired;
    }

    protected function releaseLock(string $lockKey): bool
    {
        $token = $this->lockTokens[$lockKey] ?? null;
        if ($token === null) {
            return false;
        }

        $script = <<<'LUA'
        if redis.call('get', KEYS[1]) == ARGV[1] then
            return redis.call('del', KEYS[1])
        else
            return 0
        end
        LUA;

        // phpredis cluster eval signature: eval(string $script, array $args, int $numKeys)
        $result = $this->redis->eval($script, [$lockKey, $token], 1);
        if ($result) {
            unset($this->lockTokens[$lockKey]);
            return true;
        }
        return false;
    }
}
