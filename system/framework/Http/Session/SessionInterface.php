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

use System\Security\UserInterface;

/**
 * Interface for managing session operations.
 */
interface SessionInterface
{
    /**
     * Starts a session with the given session ID.
     *
     * @param string $sessionId The session identifier
     *
     * @return void
     */
    public function start(string $sessionId): void;

    /**
     * Saves the current state or data of the session to storage only if changes are made
     *
     * @return void
     */
    public function save(): void;

    /**
     * Destroys a session associated with the current ID. Do NOT call this method when
     * regenerating a session ID, as all the data is also cleared.
     */
    public function destroy(): void;

    /**
     * Gets a value from the session storage.
     *
     * @param string $key The session key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Retrieves all items from the session storage
     *
     * @return array An array containing all items.
     */
    public function getAll(): array;

    /**
     * Retrieves all stored values except for the specified keys.
     *
     * @param string ...$keys The keys to exclude from the result
     *
     * @return array The filtered array excluding the specified keys
     */
    public function getExcept(string ...$keys): array;

    /**
     * Retrieves an array containing only the specified keys from the data source.
     *
     * @param string ...$keys The keys to retrieve.
     *
     * @return array An associative array containing the specified keys and their corresponding values.
     */
    public function getOnly(string ...$keys): array;

    /**
     * Sets a value into session storage.
     *
     * @param string $key The session key
     * @param mixed $value The value to store
     */
    public function set(string $key, mixed $value): void;

    /**
     * Checks whether a specific session key exists.
     *
     * @param string $key The session key
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Removes a key from the session.
     *
     * @param string $key The session key
     */
    public function delete(string $key): void;

    /**
     * Sets a flash value that will only be available during the next request.
     *
     * @param string $key The flash data key.
     * @param mixed $value The flash data value.
     *
     * @return void
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Retrieves a flash value set during the previous request.
     * Returns the default if the key doesn't exist.
     *
     * @param string $key The flash data key.
     * @param mixed $default Default value if key doesn't exist.
     *
     * @return mixed
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Checks whether a flash value exists for the given key.
     *
     * @param string $key The flash data key.
     *
     * @return bool
     */
    public function hasFlash(string $key): bool;

    /**
     * Attaches a user to the current instance.
     *
     * @param UserInterface $user The user to attach
     *
     * @return void
     */
    public function attachUser(UserInterface $user): void;

    /**
     * Detaches the currently authenticated user from the session or application context.
     *
     * @return void
     */
    public function detachUser(): void;

    /**
     * Retrieves the user.
     *
     * @return ?UserInterface The user instance
     */
    public function getUser(): ?UserInterface;

    /**
     * Creates and returns a new unique session ID.
     *
     * @return string The newly generated session ID.
     */
    public function createSessionId(): string;

    /**
     * Regenerates and returns a new unique session ID.
     */
    public function regenerateId(): void;

    /**
     * Checks whether the session ID has been regenerated.
     */
    public function isIdRegenerated(): bool;

    /**
     * Checks whether the session has been started.
     */
    public function isStarted(): bool;

    /**
     * Gets the session ID.
     */
    public function getId(): string;

    /**
     * Generates and assigns a new CSRF token to the session for security purposes.
     *
     * Replaces the existing CSRF token in the session with a new, randomly generated one.
     * Strengthens protection by ensuring new tokens are used in subsequent requests.
     *
     * @return void
     */
    public function rotateCsrfToken(): void;
}