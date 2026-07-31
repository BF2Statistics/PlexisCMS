# Session System

## Overview

This framework provides a session management system with multiple interchangeable
storage backends. Sessions use a "sliding expiration" model — the session extends
on every request and only expires after a configured period of inactivity.

## Architecture

```
Request
  → SessionMiddleware (reads/sets session cookie)
    → SessionHandler (manages session lifecycle, encryption)
      → SessionStorageInterface (pluggable storage backend)
```

### Key Classes

| Class | Location | Purpose |
|-------|----------|---------|
| `SessionMiddleware` | `Http/Session/Middleware/` | Reads session cookie, initializes session, re-sets cookie on each request |
| `SessionHandler` | `Http/Session/` | Core session logic: start, save, destroy, regenerate ID, encrypt/decrypt data |
| `SessionStorageInterface` | `Http/Session/Storage/` | Interface all storage engines implement |
| `Cookie` | `Http/` | Cookie creation and header rendering |

### Storage Engines

| Engine | Class | Best For |
|--------|-------|----------|
| **PHP Native** | `PhpSessionStorage` | Simple setups, uses PHP's built-in `$_SESSION` |
| **File** | `FileSessionStorage` | Single-server apps without Redis/Memcached |
| **Redis** | `RedisSessionStorage` | Multi-server / load-balanced environments |
| **Redis Cluster** | `RedisClusterSessionStorage` | High-availability Redis clusters |
| **Memcached** | `MemcachedSessionStorage` | Multi-server, high-speed caching (no persistence) |

All engines implement `SessionStorageInterface` and are fully interchangeable.
Switching engines does not affect application behavior.

## Configuration

Edit `system/config/session.php`:

```php
return [
    'session_driver'  => 'File',       // 'PHP', 'File', 'Redis', 'RedisCluster', 'Memcached'
    'session_ttl'     => 1440,          // Session lifetime in seconds (24 minutes)
    'key_prefix'      => 'sess_',       // Key prefix for storage engines
    'driver_config'   => [
        'File' => [
            'class'     => FileSessionStorage::class,
            'save_path' => '/path/to/sessions',
        ],
        'Redis' => [
            'class' => RedisSessionStorage::class,
            'host'  => '127.0.0.1',
            'port'  => 6379,
        ],
        // ... other driver configs
    ],
];
```

## How It Works

### Request Lifecycle

1. **`SessionMiddleware::process()`** intercepts the request
2. Reads the `session` cookie (or generates a new session ID)
3. Re-sets the cookie with `time() + ttl` (sliding expiration)
4. Calls `SessionHandler::start($sessionId)`
5. `SessionHandler` calls `StorageDriver::initialize()` then `load()` to read & decrypt data
6. Session is attached to the `Request` object — available via `$request->session()`
7. After the response is generated, `SessionHandler::save()` encrypts & writes data back

### Sliding Expiration

- **Server-side:** Every `save()` call resets the storage TTL
- **Client-side:** Every request re-sets the cookie expiration to `now + ttl`
- **Result:** Session stays alive while user is active, expires after `session_ttl` seconds of inactivity

### Session ID Regeneration

Call `$session->regenerateId()` after authentication state changes (login/logout)
to prevent session fixation attacks. The old session is destroyed and a new ID is issued.

## Usage in Application Code

```php
// In a controller or middleware (after SessionMiddleware has run):

// Read a value
$username = $request->session()->get('username');

// Write a value
$request->session()->set('username', 'john');

// Check existence
if ($request->session()->has('username')) { ... }

// Remove a value
$request->session()->remove('username');

// Regenerate session ID (do this after login)
$request->session()->regenerateId();

// Destroy entire session (do this on logout)
$request->session()->destroy();
```

## Engine Differences

All engines produce identical results from the application's perspective.
Operational differences:

| Aspect | PHP Native | File | Redis | Memcached |
|--------|-----------|------|-------|-----------|
| Multi-server support | ❌ | ❌ | ✅ | ✅ |
| Expiration precision | Probabilistic (GC) | File mtime check | Precise TTL | Precise TTL |
| Concurrency locking | ✅ (blocking) | ❌ | ❌ | ❌ |
| Data persistence | Filesystem | Filesystem | Configurable | ❌ (memory only) |

## Adding a Custom Storage Engine

Implement `SessionStorageInterface`:

```php
class MyCustomStorage implements SessionStorageInterface
{
    public function initialize(string $sessionId, int $ttl): void { /* refresh TTL */ }
    public function load(string $sessionId): ?string { /* return data or null */ }
    public function save(string $sessionId, string $data, int $ttl): void { /* write data */ }
    public function destroy(string $sessionId): void { /* delete session */ }
    public function purgeStaleSessions(int $timeToLive): void { /* cleanup expired */ }
    public static function GetDriverInfo(): DriverInfo { /* return driver metadata */ }
}
```

Add it to `session.php` config and set `session_driver` to your new driver name.