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
use System\Cache\CacheInterface;
use System\Cache\DriverInfo;
use System\IO\Directory;
use System\IO\File;
use System\IO\Path;

/**
 * Class FileDriver
 *
 * A filesystem-based cache driver with support for:
 * - Hard TTL (expiresAt): data is never returned after this time
 * - Soft TTL (staleAfter): data may be served as stale until expiresAt
 * - Stampede protection using lock files
 * - Increment/Decrement for numeric values
 *
 * Stored payload format (JSON): { value, staleAfter, expiresAt }
 */
class FileDriver implements CacheInterface
{
    private string $basePath;

    /**
     * Timeout for lock files in seconds
     */
    private int $lockTimeout = 10;

    /**
     * Default TTL used when we need to set a new hard expiry without a prior value
     */
    private int $defaultTtl = 3600;

    public function __construct(array $connectionData)
    {
        // Default relative cache directory
        $configuredPath = $connectionData['path'] ?? 'system\\cache';

        // Normalize and resolve to absolute path if needed
        $normalized = Path::Normalize($configuredPath);
        if (!$this->isAbsolutePath($normalized)) {
            $normalized = Path::Combine(ROOT, $normalized);
        }

        $this->basePath = rtrim($normalized, "\\/");

        if (!Directory::Exists($this->basePath)) {
            Directory::CreateDirectory($this->basePath, 0777);
        }

        if (!Directory::IsWritable($this->basePath)) {
            throw new Exception("Cache directory is not writable: {$this->basePath}");
        }

        if (!empty($connectionData['defaultTtl']) && is_int($connectionData['defaultTtl']) && $connectionData['defaultTtl'] > 0) {
            $this->defaultTtl = $connectionData['defaultTtl'];
        }
    }

    public function get(string $key, mixed $default = null, ?bool &$isStale = false): mixed
    {
        $isStale = false;
        $file = $this->pathForKey($key);
        $data = $this->readPayload($file);
        if ($data === null) {
            return $default;
        }

        $now = time();
        if (isset($data['expiresAt']) && $now > (int)$data['expiresAt']) {
            // Hard-expired: delete and miss
            File::Delete($file);
            return $default;
        }

        $isStale = isset($data['staleAfter']) && $now > (int)$data['staleAfter'];
        return $data['value'];
    }

    public function set(string $key, mixed $value, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        $now = time();
        $expiresAt = $now + $expiresAfter;
        $payload = [
            'value' => $value,
            'staleAfter' => $staleAfter !== null ? ($now + $staleAfter) : $expiresAt,
            'expiresAt' => $expiresAt,
        ];
        return $this->writePayload($this->pathForKey($key), $payload);
    }

    public function has(string $key): bool
    {
        $file = $this->pathForKey($key);
        $data = $this->readPayload($file);
        if ($data === null) {
            return false;
        }

        if (isset($data['expiresAt']) && time() > (int)$data['expiresAt']) {
            File::Delete($file);
            return false;
        }
        return true;
    }

    public function delete(string $key): bool
    {
        $file = $this->pathForKey($key);
        return File::Delete($file) || !File::Exists($file);
    }

    public function clear(): bool
    {
        $success = true;
        // Remove cache JSON files
        $cacheFiles = Directory::GetFiles($this->basePath, '^.*\.cache$');
        foreach ($cacheFiles as $file) {
            if (!File::Delete($file)) {
                $success = false;
            }
        }
        // Also remove any lingering lock files
        $lockFiles = Directory::GetFiles($this->basePath, '^.*\.lock$');
        foreach ($lockFiles as $lock) {
            File::Delete($lock);
        }
        return $success;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        $now = time();
        foreach ((array)$keys as $key) {
            $file = $this->pathForKey($key);
            $data = $this->readPayload($file);
            if ($data === null) {
                $result[$key] = $default;
                continue;
            }
            if (isset($data['expiresAt']) && $now > (int)$data['expiresAt']) {
                File::Delete($file);
                $result[$key] = $default;
                continue;
            }
            $result[$key] = $data['value'];
        }
        return $result;
    }

    public function setMultiple(iterable $values, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        $now = time();
        $expiresAt = $now + $expiresAfter;
        $allOk = true;
        foreach ($values as $key => $value) {
            $payload = [
                'value' => $value,
                'staleAfter' => $staleAfter !== null ? ($now + $staleAfter) : $expiresAt,
                'expiresAt' => $expiresAt,
            ];
            $ok = $this->writePayload($this->pathForKey($key), $payload);
            if (!$ok) $allOk = false;
        }
        return $allOk;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $allOk = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) $allOk = false;
        }
        return $allOk;
    }

    public function refresh(string $key, int $expiresAfter = 3600, ?int $staleAfter = null): bool
    {
        $file = $this->pathForKey($key);
        $data = $this->readPayload($file);
        if ($data === null) return false;

        $now = time();
        $expiresAt = $now + $expiresAfter;
        $data['expiresAt'] = $expiresAt;
        $data['staleAfter'] = $staleAfter !== null ? ($now + $staleAfter) : $expiresAt;
        return $this->writePayload($file, $data);
    }

    public function increment(string $key, int $step = 1): int|false
    {
        $file = $this->pathForKey($key);
        $data = $this->readPayload($file);
        if ($data === null) return false;
        $now = time();
        if (isset($data['expiresAt']) && $now > (int)$data['expiresAt']) {
            File::Delete($file);
            return false;
        }
        if (!isset($data['value']) || !is_numeric($data['value'])) return false;
        $data['value'] += $step;
        // Preserve existing expiry metadata as-is
        $this->writePayload($file, $data);
        return (int)$data['value'];
    }

    public function decrement(string $key, int $step = 1): int|false
    {
        $file = $this->pathForKey($key);
        $data = $this->readPayload($file);
        if ($data === null) return false;
        $now = time();
        if (isset($data['expiresAt']) && $now > (int)$data['expiresAt']) {
            File::Delete($file);
            return false;
        }
        if (!isset($data['value']) || !is_numeric($data['value'])) return false;
        $data['value'] -= $step;
        $this->writePayload($file, $data);
        return (int)$data['value'];
    }

    public function getOrRegenerateWithLock(string $key, callable $callback, int $expiresAfter = 3600, ?int $staleAfter = null): mixed
    {
        $now = time();
        $staleAfter = $staleAfter ?? $expiresAfter;
        $file = $this->pathForKey($key);
        $lockHandle = null;
        $lockFile = $this->lockPathForKey($key);

        // Try to read existing
        $data = $this->readPayload($file);
        if ($data !== null) {
            if (isset($data['expiresAt']) && $now > (int)$data['expiresAt']) {
                File::Delete($file);
            } elseif (!isset($data['staleAfter']) || $data['staleAfter'] > $now) {
                // Fresh
                return $data['value'];
            } else {
                // Stale: try to acquire lock
                if ($this->tryAcquireLock($lockFile, $lockHandle)) {
                    try {
                        $generated = $callback();
                        $this->set($key, $generated, $expiresAfter, $staleAfter);
                        return $generated;
                    } finally {
                        $this->releaseLock($lockHandle, $lockFile);
                    }
                }
                // Could not acquire lock: serve stale
                return $data['value'];
            }
        }

        // Missing: acquire lock to regenerate
        if ($this->tryAcquireLock($lockFile, $lockHandle)) {
            try {
                $generated = $callback();
                $this->set($key, $generated, $expiresAfter, $staleAfter);
                return $generated;
            } finally {
                $this->releaseLock($lockHandle, $lockFile);
            }
        }

        // Wait for lock release up to timeout
        $start = microtime(true);
        while (File::Exists($lockFile)) {
            usleep(50000);
            if ((microtime(true) - $start) >= ($this->lockTimeout + 1)) {
                break;
            }
        }

        // Try to read again
        $data = $this->readPayload($file);
        if ($data !== null && (!isset($data['expiresAt']) || time() <= (int)$data['expiresAt'])) {
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
            name: 'File',
            readableName: 'Filesystem',
            isSupported: is_writable(sys_get_temp_dir()) || is_writable('.'),
            description: 'Stores cache entries as JSON files with soft (stale) and hard expiration, plus lock files for stampede protection.'
        );
    }

    // ===== Helpers =====
    private function pathForKey(string $key): string
    {
        // Use md5-encoded key with .cache extension (old FileCache style)
        $encoded = md5($key);
        return $this->basePath . DIRECTORY_SEPARATOR . $encoded . '.cache';
    }

    private function lockPathForKey(string $key): string
    {
        $encoded = md5($key);
        return $this->basePath . DIRECTORY_SEPARATOR . $encoded . '.lock';
    }

    private function readPayload(string $file): ?array
    {
        if (!File::Exists($file)) return null;
        try {
            $contents = File::ReadAllText($file);
        } catch (\Throwable $e) {
            return null;
        }
        if ($contents === '') return null;
        $data = json_decode($contents, true);
        return is_array($data) && array_key_exists('value', $data) ? $data : null;
    }

    private function writePayload(string $file, array $payload): bool
    {
        $json = json_encode($payload);
        try {
            // Write directly to the cache file. We coordinate concurrency using a separate .lock file.
            $stream = File::OpenWrite($file);
            try {
                $stream->truncate(0);
                $stream->write($json);
            } finally {
                $stream->close();
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tryAcquireLock(string $lockFile, &$handle): bool
    {
        try {
            // Open or create lock file
            $stream = File::Open($lockFile);
        } catch (\Throwable $e) {
            return false;
        }

        // Try non-blocking exclusive lock
        if (!$stream->lock(true, true)) {
            $stream->close();
            return false;
        }

        // Write a timestamp for visibility
        try {
            $stream->truncate(0);
            $stream->write((string)time());
        } catch (\Throwable $e) {
            // ignore write issues; lock is what matters
        }

        $handle = $stream;
        return true;
    }

    private function releaseLock($handle, string $lockFile): void
    {
        if ($handle instanceof \System\IO\FileStream) {
            try { $handle->unlock(); } catch (\Throwable $e) {}
            try { $handle->close(); } catch (\Throwable $e) {}
        }
        File::Delete($lockFile);
    }

    private function isAbsolutePath(string $path): bool
    {
        // Windows drive letter or UNC or root-based path
        return (bool)preg_match('#^[A-Za-z]:[\\\/]#', $path) || str_starts_with($path, '\\') || str_starts_with($path, '/');
    }
}
