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
namespace System\Configuration;

/**
 * Abstract ConfigBase class to manage configuration variables.
 * This class implements several standard PHP interfaces, including IteratorAggregate,
 * ArrayAccess, Countable, and Serializable, to provide seamless interaction with stored variables.
 */
abstract class ConfigBase implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * A dictionary container for all config variables
     * @var array
     */
    protected array $variables;

    /**
     * The full file path to the config file
     * @var string
     */
    protected string $filePath;

    /**
     * Loads the files variables into the $variables array
     *
     * @param string $_filepath The full file path to the config file
     *
     * @throws \System\IO\FileNotFoundException Thrown if the config file does not exist
     */
    public abstract function __construct(string $_filepath);

    /**
     * Retrieves the value associated with the specified key.
     * If the key is a multi-dimensional array reference (e.g., "a.b.c"),
     * it will traverse the array to find the corresponding value.
     * Returns a default value if the key does not exist.
     *
     * @param string $key The key for the desired value. Can include dot notation for nested keys.
     * @param mixed $defaultReturn The default value to return if the key does not exist. Default is null.
     *
     * @return mixed The value associated with the specified key, or the default value if the key does not exist.
     */
    public function get(string $key, mixed $defaultReturn = null): mixed
    {
        // Check if this is a multi-dimensional array
        if (strpos($key, '.') !== false)
        {
            $args = explode('.', $key);
            $count = count($args);
            $buffer = &$this->variables;

            // Traverses a nested array using keys from the given $args array and retrieve the corresponding value.
            // If any of the keys in $args do not exist in the current array level, return the $defaultReturn value.
            for ($i = 0; $i < $count; $i++)
            {
                if (!isset($buffer[$args[$i]]))
                    return $defaultReturn;
                else if ($i == $count - 1)
                    return $buffer[$args[$i]];
                else
                    $buffer = $buffer[$args[$i]];
            }
        }

        // Just a simple 1 stack array
        else
        {
            // Check if variable exists in $array
            if (array_key_exists($key, $this->variables))
                return $this->variables[$key];
        }

        return $defaultReturn;
    }

    /**
     * Sets a value or a collection of values in the internal variables array.
     * Supports setting values either as single keys, multidimensional keys, or arrays of key-value pairs.
     *
     * @param string|array $key The key or array of keys and values to set. If a string is provided, it can represent
     *                          a single key or a multidimensional key separated by periods.
     * @param mixed $value The value to set for the specified key. Ignored if $key is an array.
     * @param bool $ifNotExists If true, it will only set the values for keys that do not already exist.
     *
     * @return bool Returns true if the value(s) were successfully set, false if the operation failed due to $ifNotExists logic.
     */
    public function set(string|array $key, mixed $value, bool $ifNotExists = false): bool
    {
        // Are we setting an array of values?
        if (is_array($key))
        {
            foreach ($key as $k => $v)
            {
                if (!$this->set($k, $v, $ifNotExists))
                    return false;
            }

            return true;
        }
        // Check if this is a multidimensional array
        else if (str_contains($key, '.'))
        {
            // Each dot will be a new element in the multidimensional array
            $args = explode('.', $key);
            $reference = &$this->variables;

            // Loop though each level (period or "element")
            foreach ($args as $segment)
            {
                if (!array_key_exists($segment, $reference))
                {
                    // Only create the key if not exists
                    if ($ifNotExists)
                        $reference[$segment] = [];
                    else
                        return false;
                }

                $reference = &$reference[$segment];
            }

            $reference = $value;
            return true;
        }
        else
        {
            if (!$ifNotExists && !array_key_exists($key, $this->variables))
                return false;

            $this->variables[$key] = $value;
            return true;
        }

        return false;
    }

    /**
     * Saves the current configuration values to the file.
     *
     * This method generates a configuration file based on the values
     * stored in the `$variables` array, backing up the existing file
     * beforehand. Numeric values, arrays, and strings are all handled
     * appropriately to ensure proper PHP syntax in the generated file.
     *
     * @return bool Returns true if the file was successfully written, otherwise false.
     *
     * @throws \System\IO\IOException thrown if there is an issue creating or modifying the configuration file.
     */
    public abstract function save(): bool;

    /**
     * Retrieves all variables.
     *
     * @return array The array of all variables.
     */
    public function fetchAll(): array
    {
        return $this->variables;
    }

    // === Interface / Abstract Methods === //

    /**
     * Returns the number of items in the list
     * This method is required by the interface Countable.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->variables);
    }

    /**
     * Checks if the specified offset exists.
     * This method is required by the interface ArrayAccess.
     *
     * @param mixed $offset The offset to check for existence
     * @return bool True if the offset exists, false otherwise
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->variables);
    }

    /**
     * Retrieves the value associated with the specified offset.
     * This method is required by the interface ArrayAccess.
     *
     * @param string|int $offset The offset for which the value should be retrieved
     * @return mixed The value associated with the given offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->variables[$offset];
    }

    /**
     * Sets the value at the specified offset.
     * This method is required by the interface ArrayAccess.
     *
     * @param mixed $offset The offset to assign the value to
     * @param mixed $value The value to set
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->variables[$offset] = $value;
    }

    /**
     * Unsets the value at the specified offset.
     *
     * @param mixed $offset The offset to unset.
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->variables[$offset]);
    }

    /**
     * Serializes the data, and returns it.
     * This method is required by the interface Serializable.
     *
     * @return string The serialized string
     */
    public function serialize(): string
    {
        return serialize($this->variables);
    }

    /**
     * Unserializes the data, and sets up the storage in this container
     * This method is required by the interface Serializable.
     *
     * @param string $data
     *
     * @return void
     */
    public function unserialize(string $data): void
    {
        $this->variables = unserialize($data);
    }

    /**
     * Retrieves an external iterator.
     *
     * @return \Traversable An instance of Traversable for iterating over the object's variables.
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->variables);
    }
}