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
namespace System\Http\Session\Containers;

/**
 * Interface for managing session data.
 */
interface SessionDataInterface
{
    /**
     * Initializes the session data
     *
     * @param string $data
     */
    public function initialize(string $data): void;

    /**
     * Determines whether there are any changes.
     *
     * @return bool True if there are changes, false otherwise.
     */
    public function hasChanges(): bool;

    /**
     * Get a value from the session.
     *
     * @param string $key The key to retrieve.
     * @param mixed $default The default value to return if the key does not exist.
     *
     * @return mixed The value from the session, or the default value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Retrieves all items.
     *
     * @return array An array containing all items.
     */
    public function getAll(): array;

    /**
     * Set a value in the session.
     *
     * @param string $key The key to store the value under.
     * @param mixed $value The value to store.
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if a key exists in the session.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Remove a key from the session.
     *
     * @param string $key The key to remove.
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Clear all session data.
     *
     * @return void
     */
    public function clear(): void;

    /**
     * Encodes the session data and return the data as a string.
     *
     * @return string The encoded data as a string.
     */
    public function encode(): string;
}