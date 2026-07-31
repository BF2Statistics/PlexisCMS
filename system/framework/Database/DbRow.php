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
namespace System\Database;

use ArrayAccess;
use Countable;
use System\IndexOutOfRangeException;

/**
 * Represents a single row of data, retrieved from a database query.
 * Provides functionality to access individual columns using specified keys.
 */
class DbRow implements ArrayAccess, Countable
{
    private array $data;

    /**
     * Constructor method.
     *
     * @param array $data An associative array to initialize the object with.
     */
   public function __construct(array $data)
   {
       $this->data = $data;
   }

    /**
     * Retrieves the value associated with the specified key or index from the data array.
     *
     * @param string|int $key The key or index whose value should be retrieved from the data array.
     *
     * @return mixed The value associated with the specified key or index.
     *
     * @throws IndexOutOfRangeException If the specified key or index does not exist in the data array.
     *
     */
    public function get(string|int $key): mixed
    {
       if (!array_key_exists($key, $this->data))
       {
           if (is_int($key))
           {
               throw new IndexOutOfRangeException("Index $key does not exist in the row");
           }

           throw new IndexOutOfRangeException("Column '$key' does not exist in the row");
       }

       return $this->data[$key];
   }

    /**
     * Retrieves the value for the given key, or returns a default if the key doesn't exist.
     *
     * @param string|int $key The column name or index.
     * @param mixed $default The default value to return if the key doesn't exist.
     * @return mixed
     */
    public function getOrDefault(string|int $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * @inheritDoc
     * @throws IndexOutOfRangeException
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * @inheritDoc
     * @throws \BadMethodCallException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException("DbRow is read-only");
    }

    /**
     * @inheritDoc
     * @throws \BadMethodCallException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException("DbRow is read-only");
    }

    /**
     * @inheritDoc
     *
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Checks whether a column exists in the row.
     *
     * @param string|int $key The column name or index.
     * @return bool True if the column exists, false otherwise.
     */
    public function has(string|int $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Returns the row data as a plain associative array.
     *
     * @return array The row data.
     */
    public function toArray(): array
    {
        return $this->data;
    }
}