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
namespace System\Presentation;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use System\Collections\ArrayHelper;

/**
 * A class for managing hierarchical contexts with support for accessing and modifying
 * data in both the current context and parent contexts.
 *
 * This class implements ArrayAccess and IteratorAggregate interfaces to allow accessing
 * stored data using array-like syntax and to enable iteration over the stored data.
 *
 * @package System\Presentation
 */
class ViewContextProvider implements ArrayAccess, IteratorAggregate
{
    /**
     * The local data storage for the current context.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * The parent context manager instance, if any.
     *
     * @var ViewContextProvider|null
     */
    private ?ViewContextProvider $parent = null;

    /**
     * ContextManager constructor.
     *
     * @param ViewContextProvider|null $parent Optional parent context.
     */
    public function __construct(?ViewContextProvider $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Sets a value in the current context.
     *
     * @param string $key The unique identifier for the data.
     * @param mixed $value The value to store.
     *
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Merges the provided array into the existing data using a recursive distinct merge strategy.
     *
     * @param array $data The array to be merged into the existing data.
     *
     * @return void
     */
    public function merge(array $data): void
    {
        $this->data = ArrayHelper::MergeRecursiveDistinct($this->data, $data);
    }

    /**
     * Retrieves a value from the current context or its parents.
     *
     * Checks the local context first. If the key is not found, it traverses
     * up the parent hierarchy.
     *
     * @param string $key The key to look for.
     *
     * @return mixed Returns the value if found, or null otherwise.
     */
    public function get(string $key): mixed
    {
        // Check local context first (child takes precedence)
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        // Fall back to parent context if not found
        if ($this->parent !== null) {
            return $this->parent->get($key);
        }

        return null;
    }

    /**
     * Checks if a key exists in the current context or any of its parents.
     *
     * @param string $key The key to check.
     *
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data)
            || ($this->parent !== null && $this->parent->has($key));
    }

    /**
     * ArrayAccess implementation: checks if an offset exists.
     *
     * @param mixed $offset The offset to check.
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * ArrayAccess implementation: retrieves the value at a specific offset.
     *
     * @param mixed $offset The offset to retrieve.
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess implementation: sets a value at a specific offset.
     *
     * @param mixed $offset The offset to assign the value to.
     * @param mixed $value The value to set.
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * ArrayAccess implementation: removes an offset from the local context.
     *
     * @param mixed $offset The offset to unset.
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Converts the hierarchical context into a single flattened array.
     *
     * Merges parent contexts first, then the current child context,
     * ensuring child values overwrite parent values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        // Merge parent context first, then child (child overwrites parent)
        if ($this->parent !== null) {
            return ArrayHelper::MergeRecursiveDistinct($this->parent->toArray(), $this->data);
        }
        return $this->data;
    }

    /**
     * IteratorAggregate implementation: returns an iterator for the context data.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * Creates a new child context manager that points to the current instance as its parent.
     *
     * Useful for maintaining scope in nested operations like loops.
     *
     * @return ViewContextProvider
     */
    public function createChild(): ViewContextProvider
    {
        return new ViewContextProvider($this);
    }

    /**
     * Sets or updates the parent context manager.
     *
     * @param ViewContextProvider|null $parent The parent context manager or null.
     *
     * @return void
     */
    public function setParent(?ViewContextProvider $parent): void
    {
        $this->parent = $parent;
    }
}