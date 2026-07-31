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
namespace System\Http\Session;

use Random\RandomException;
use System\Http\Session\Containers\SessionDataInterface;
use System\Http\Session\Storage\SessionStorageInterface;
use System\Security\UserInterface;

/**
 * Handles session data storage and lifecycle management using a caching mechanism.
 * A session is a short-term storage mechanism for storing data during a user's session, such as a csrf token,
 * not a long-term authenticated user data store.
 *
 * This class provides an implementation of the \SessionHandlerInterface to
 * manage PHP session data. It utilizes a cache driver for storage, offering a
 * configurable time-to-live (TTL) for session data expiration.
 */
class SessionHandler implements SessionInterface
{
    /**
     * Indicates whether the session has been started.
     */
    protected(set) bool $started = false;

    /**
     * Indicates whether the session ID was regenerated.
     */
    protected(set) bool $isIdRegenerated = false;

    /**
     * The session ID.
     */
    protected string $sessionId;

    /**
     * @var SessionStorageInterface
     */
    protected(set) SessionStorageInterface $storageDriver;

    /**
     * @var SessionDataInterface
     */
    protected(set) SessionDataInterface $data;

    /**
     * @var int
     */
    protected int $ttl;

    /**
     * @var ?UserInterface
     */
    protected(set) ?UserInterface $user = null;

    /**
     * Initializes a new instance of the class with the specified session storage, data container, and time-to-live (TTL).
     *
     * @param SessionStorageInterface $storage The session storage implementation to be used.
     * @param SessionDataInterface $container The container for storing session data.
     * @param int $ttl The time-to-live in seconds for session data. Defaults to 1440 seconds.
     */
    public function __construct(SessionStorageInterface $storage, SessionDataInterface $container, int $ttl = 1440)
    {
        $this->storageDriver = $storage;
        $this->ttl = $ttl;
        $this->data = $container;
    }

    /**
     * @inheritDoc
     *
     * @throws RandomException
     */
    public function start(string $sessionId): void
    {
        // Ensure a session isn't started more than once
        if ($this->started)
            throw new \RuntimeException('Session has already been started.');

        // Load storage
        $this->sessionId = $sessionId;

        // Run garbage collection roughly 2% of the time
        if (mt_rand(1, 100) <= 2) {
            $this->storageDriver->purgeStaleSessions($this->ttl);
        }

        // Always call Initialize before loading
        $this->storageDriver->initialize($sessionId, $this->ttl);

        // Load data
        $data = $this->storageDriver->load($sessionId);
        if ($data !== null)
        {
            $this->data->initialize($data);
        }

        // Ensure we have a CSRF token
        if (!$this->data->has('csrf_token'))
        {
            $this->data->set('csrf_token', $this->createCsrfToken());
        }

        // Set flag
        $this->started = true;

        // Age flash data — move last request's "new" to "old", clear stale
        $this->ageFlashData();

        // Register a shutdown function to save session data when the script ends
        register_shutdown_function(function () {
            $this->save();
        });
    }

    /**
     * @inheritDoc
     */
    public function destroy(): void
    {
        if (!$this->started) return;

        // Use the cache driver to delete the session by ID
        $this->storageDriver->destroy($this->sessionId);

        // Clear data
        $this->data->clear();
        $this->user = null;
        $this->started = false;
    }

    /**
     * @inheritDoc
     */
    public function save(): void
    {
        // If the session isn’t started, nothing to do
        if (!$this->started) return;

        // Decide once if we must persist: regeneration forces a save,
        // otherwise only save when data changed
        $mustSave = $this->isIdRegenerated() || $this->data->hasChanges();
        if (!$mustSave) return;

        // Single write
        $this->storageDriver->save($this->sessionId, $this->data->encode(), $this->ttl);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        return $this->data->get($key, $default);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value): void
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $this->data->set($key, $value);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        return $this->data->has($key);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): void
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $this->data->remove($key);
    }

    /**
     * @inheritDoc
     */
    public function attachUser(UserInterface $user): void
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $this->user = $user;
    }

    /**
     * @inheritDoc
     */
    public function detachUser(): void
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $this->user = null;
    }

    /**
     * @inheritDoc
     */
    public function getUser(): ?UserInterface
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        return $this->user;
    }

    /**
     * @inheritDoc
     * @throws RandomException
     */
    public function rotateCsrfToken(): void
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $this->set('csrf_token', $this->createCsrfToken());
    }

    /**
     * @inheritDoc
     * @throws RandomException
     */
    public function regenerateId(): void
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $oldId = $this->sessionId;
        $this->isIdRegenerated = true;
        $this->sessionId = $this->createSessionId();

        // Use the cache driver to delete the old session by ID
        $this->storageDriver->destroy($oldId);

        // Initialize the storage driver with the new session ID and TTL
        $this->storageDriver->initialize($this->sessionId, $this->ttl);
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return $this->data->getAll();
    }

    /**
     * @inheritDoc
     */
    public function getExcept(string ...$keys): array
    {
        $data = $this->getAll();
        foreach ($keys as $key)
        {
            unset($data[$key]);
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function getOnly(string ...$keys): array
    {
        $data = $this->getAll();
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * Sets a flash value that will only be available during the next request.
     *
     * @param string $key The flash data key.
     * @param mixed $value The flash data value.
     *
     * @return void
     */
    public function flash(string $key, mixed $value): void
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $flash = $this->data->get('_flash_new', []);
        $flash[$key] = $value;
        $this->data->set('_flash_new', $flash);
    }

    /**
     * Retrieves a flash value. Returns the default if not found.
     * Flash values are only available during the request immediately
     * following the one in which they were set.
     *
     * @param string $key The flash data key.
     * @param mixed $default Default value if key doesn't exist.
     *
     * @return mixed
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $flash = $this->data->get('_flash_old', []);
        return array_key_exists($key, $flash) ? $flash[$key] : $default;
    }

    /**
     * Checks whether a flash value exists for the given key.
     *
     * @param string $key The flash data key.
     *
     * @return bool
     */
    public function hasFlash(string $key): bool
    {
        if (!$this->started)
            throw new \RuntimeException('Session has not been started.');

        $flash = $this->data->get('_flash_old', []);
        return array_key_exists($key, $flash);
    }

    /**
     * Ages flash data by moving "new" flash data to "old" and clearing
     * the previous "old" data. This should be called at the beginning
     * of each request (inside start()).
     *
     * @return void
     */
    protected function ageFlashData(): void
    {
        // Previous request's "new" flash becomes this request's "old" (readable) flash
        $newFlash = $this->data->get('_flash_new', []);

        if (!empty($newFlash))
            $this->data->set('_flash_old', $newFlash);
        else
            $this->data->remove('_flash_old');

        // Clear "new" so flash data doesn't persist beyond one additional request
        $this->data->remove('_flash_new');
    }

    /**
     * @inheritDoc
     */
    public function isIdRegenerated(): bool
    {
        return $this->isIdRegenerated;
    }

    /**
     * @inheritDoc
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->sessionId;
    }

    /**
     * @inheritDoc
     * @throws RandomException If an error occurs during random byte generation.
     */
    public function createSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generates a new CSRF token to protect against Cross-Site Request Forgery attacks.
     *
     * Creates a cryptographically secure random string that can be used as a CSRF token.
     *
     * @return string The generated CSRF token.
     *
     * @throws RandomException
     */
    protected function createCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}