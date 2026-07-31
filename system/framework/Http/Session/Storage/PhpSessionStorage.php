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
use System\Cache\DriverInfo;

/**
 * PHP native session storage implementation.
 *
 * This class provides a bridge between the framework's session storage interface
 * and PHP's built-in session handling mechanism. It delegates all session operations
 * to PHP's native session functions ($_SESSION superglobal).
 *
 * Benefits:
 * - No external dependencies (Redis, Memcached, etc.)
 * - Works on any PHP hosting environment
 * - Leverages PHP's built-in session handling and garbage collection
 * - Can use any session.save_handler configured in php.ini (files, memcached, redis, etc.)
 *
 * Notes:
 * - Session configuration is controlled via php.ini settings (session.save_path, etc.)
 * - The session is started automatically when needed
 * - Key prefix is not supported (PHP manages session keys internally)
 * - TTL is controlled via session.gc_maxlifetime in php.ini
 */
class PhpSessionStorage implements SessionStorageInterface
{
    /**
     * Internal key used to store session data in $_SESSION superglobal.
     * This prevents conflicts with other code that might use $_SESSION.
     *
     * @var string
     */
    private const SESSION_DATA_KEY = '__plexis_session_data__';

    /**
     * Tracks whether the PHP session has been started.
     *
     * @var bool
     */
    private bool $sessionStarted = false;

    /**
     * The current session ID being managed.
     *
     * @var string|null
     */
    private ?string $currentSessionId = null;

    /**
     * Constructor to initialize PHP session storage.
     *
     * @param array $connectionData Configuration options (currently unused, but kept for interface consistency)
     * @param string $keyPrefix Key prefix (not supported by PHP sessions, parameter ignored)
     *
     * @throws Exception If session configuration is invalid
     */
    public function __construct(array $connectionData = [], string $keyPrefix = '')
    {
        // Validate that sessions are enabled
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new Exception("PHP sessions are disabled in php.ini. Cannot use PhpSessionStorage.");
        }
    }

    /**
     * @inheritDoc
     */
    public function initialize(string $sessionId, int $ttl): void
    {
        // Start or resume the session with the given ID and TTL
        $this->ensureSessionStarted($sessionId, $ttl);
    }


    /**
     * @inheritDoc
     */
    public function load(string $sessionId): ?string
    {
        $this->ensureSessionStarted($sessionId);

        // Check if session data exists
        if (!isset($_SESSION[self::SESSION_DATA_KEY])) {
            return null;
        }

        // Return the stored JSON string
        $data = $_SESSION[self::SESSION_DATA_KEY];
        return is_string($data) ? $data : null;
    }

    /**
     * @inheritDoc
     */
    public function save(string $sessionId, string $data, int $ttl): void
    {
        $this->ensureSessionStarted($sessionId, $ttl);

        // Store the JSON-encoded session data
        $_SESSION[self::SESSION_DATA_KEY] = $data;

        // Write and close the session to release the lock
        session_write_close();

        // Important! Don't reset sessionStarted — let ensureSessionStarted check session_status() instead
    }

    /**
     * @inheritDoc
     */
    public function destroy(string $sessionId): void
    {
        $this->ensureSessionStarted($sessionId);

        $_SESSION = [];

        if (ini_get('session.use_cookies'))
        {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->sessionStarted = false;
        $this->currentSessionId = null;
    }

    /**
     * @inheritDoc
     */
    public function purgeStaleSessions(int $timeToLive): void
    {
        // Not needed - PHP's garbage collection handles this automatically
        // based on session.gc_maxlifetime, session.gc_probability, and session.gc_divisor
        // in php.ini
    }

    /**
     * Ensures that a PHP session is started with the given session ID.
     *
     * @param string $sessionId The session ID to use
     * @param int $ttl The session lifetime in seconds (0 = until browser closes)
     *
     * @return void
     * @throws Exception If session cannot be started
     */
    private function ensureSessionStarted(string $sessionId, int $ttl = 0): void
    {
        // If session is already active with the correct ID, nothing to do
        if ($this->sessionStarted && $this->currentSessionId === $sessionId) {
            return;
        }

        // If a different session is active, close it first
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            $this->sessionStarted = false;
        }

        // Set cookie params BEFORE starting the session
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $ttl,
            'path' => $cookieParams['path'],
            'domain' => $cookieParams['domain'],
            'secure' => $cookieParams['secure'],
            'httponly' => $cookieParams['httponly'],
            'samesite' => $cookieParams['samesite'] ?? 'Lax'
        ]);

        // Set the session ID before starting
        session_id($sessionId);

        // Start the session
        if (!session_start()) {
            throw new Exception("Failed to start PHP session with ID: {$sessionId}");
        }

        $this->sessionStarted = true;
        $this->currentSessionId = $sessionId;
    }

    /**
     * @inheritDoc
     */
    public static function GetDriverInfo(): DriverInfo
    {
        $isSupported = session_status() !== PHP_SESSION_DISABLED;

        return new DriverInfo(
            name: 'PHP',
            readableName: 'PHP Native Sessions',
            isSupported: $isSupported,
            description: 'Uses PHP\'s built-in session handling mechanism ($_SESSION). Works on any hosting environment and can use any session.save_handler configured in php.ini (files, memcached, redis, etc.).'
        );
    }
}