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

use JsonException;
use System\Collections\Dictionary;

/**
 * A class implementing the SessionDataInterface that provides functionality
 * to store, retrieve, modify, and encode session data.
 */
class PlainContainer implements SessionDataInterface
{
    private Dictionary $container;

    private $hasChanges = false;

    public function __construct()
    {
        $this->container = new Dictionary();
    }

    /**
     * Retrieves a value from the session by its key.
     *
     * @param string $key The key associated with the desired value.
     * @param mixed $default The default value to return if the key does not exist.
     *
     * @return mixed The value associated with the specified key, or the default value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->container->getValueOrDefault($key, $default);
    }

    /**
     * Sets a key-value pair in the session.
     *
     * @param string $key The key under which the value will be stored.
     * @param mixed $value The value to store.
     *
     * @throws \Exception If the value cannot be added to the container.
     */
    public function set(string $key, mixed $value): void
    {
        $this->hasChanges = true;
        $this->container->add($key, $value);
    }

    /**
     * Checks whether a given key exists in the session.
     *
     * @param string $key The key to check.
     *
     * @return bool True if the key exists; otherwise, false.
     */
    public function has(string $key): bool
    {
        return $this->container->containsKey($key);
    }

    /**
     * Removes a key-value pair from the session.
     *
     * @param string $key The key to remove.
     *
     * @throws \Exception If the key cannot be removed.
     */
    public function remove(string $key): void
    {
        $this->hasChanges = true;
        $this->container->remove($key);
    }

    /**
     * Clears all data from the session.
     *
     * @throws \Exception If the container cannot be cleared.
     */
    public function clear(): void
    {
        $this->hasChanges = true;
        $this->container->clear();
    }

    /**
     * Initializes the session with data from an encrypted string.
     *
     * @param string $data The encrypted string containing serialized session data.
     *
     * @throws \Exception If the data cannot be decrypted or loaded into the container.
     */
    public function initialize(string $data): void
    {
        $decoded = json_decode($data, true);
        if ($decoded !== false) {
            $this->container->mergeLeft($decoded);
        }
    }

    /**
     * Encodes the contents of the session container into an encrypted string.
     *
     * @return string The encrypted string representation of the container's data.
     *
     * @throws JsonException If the container data cannot be serialized to JSON.
     */
    public function encode(): string
    {
        return json_encode($this->container->toArray(), JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK);
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return $this->container->toArray();
    }

    /**
     * @inheritDoc
     */
    public function hasChanges(): bool
    {
        return $this->hasChanges;
    }
}