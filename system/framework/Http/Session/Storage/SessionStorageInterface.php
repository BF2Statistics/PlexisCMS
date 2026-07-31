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

use System\Cache\DriverInfo;

/**
 * Interface defining the contract for session storage mechanisms.
 *
 * Provides methods to initialize, retrieve, save, and delete session data.
 * Also includes functionality for managing expired session data and accessing
 * details about the underlying storage driver.
 */
interface SessionStorageInterface
{
    /**
     * Initializes storage for a given session ID. If the session ID already exists,
     * the TTL is updated.
     *
     * @param string $sessionId The unique identifier for the session.
     * @param int $ttl The time-to-live for the session data, in seconds.
     */
    public function initialize(string $sessionId, int $ttl): void;

    /**
     * Retrieves the session data as a single JSON-encoded string.
     *
     * @param string $sessionId
     * @return string|null The stored JSON string or null if session doesn't exist
     */
    public function load(string $sessionId): ?string;

    /**
     * Saves the session data with a specified time-to-live (TTL).
     *
     * @param string $sessionId The unique identifier for the session.
     * @param string $data The JSON-encoded session data to store.
     * @param int $ttl The time-to-live for the session data, in seconds.
     *
     * @return void
     */
    public function save(string $sessionId, string $data, int $ttl): void;

    /**
     * Deletes the entire session from storage.
     *
     * @param string $sessionId
     */
    public function destroy(string $sessionId): void;

    /**
     * Performs garbage collection to clean up expired session data that has
     * exceeded the specified time-to-live threshold.
     *
     * @param int $timeToLive The maximum allowed age of a session in seconds
     *
     * @return void
     */
    public function purgeStaleSessions(int $timeToLive): void;

    /**
     * Retrieves information about the driver currently in use.
     *
     * @return DriverInfo The information related to the driver.
     */
    public static function GetDriverInfo(): DriverInfo;
}