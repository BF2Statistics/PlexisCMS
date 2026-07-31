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
namespace System\Collections;
use Exception;
use Traversable;

/**
 * Represents a collection of keys and values.
 *
 * What sets the Dictionary apart over an array is that the Dictionary will throw
 * exceptions instead of outputting PHP errors, allowing the developer more control
 * over erroneous operations.
 *
 * The Dictionary class also supports case-insensitive key lookups and read-only
 * enforcement. Read-Only enforcement can only enforce that no items are added or removed
 * from this collection, not the changing of values!
 *
 * You can access and add items to the collection using the "add", "itemAt", and "remove"
 * methods, or you can use this object like an array:
 * <ul>
 *    <li> $dictionary[$key] = $value </li>
 *    <li> unset($dictionary[$key]) </li>
 *    <li> if(isset($dictionary[$key])) </li>
 *    <li> $numItems = count($dictionary) </li>
 *    <li> foreach($dictionary as $item) </li>
 * </ul>
 */
class Dictionary implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * @var mixed[] Data Container.
     */
    private array $data = array();

    /**
     * @var int The index count of the data container
     */
    protected int $size = 0;

    /**
     * @var bool Indicates whether this dictionary is read-only.
     */
    protected bool $isReadOnly = false;

    /**
     * @var bool Indicates whether the comparison of keys is case sensitive.
     */
    protected bool $caseSensitive = true;

    /**
     * Constructor
     *
     * @param bool $readOnly Indicates whether this Dictionary collection will be readonly.
     * @param array $items Default items to add to this Dictionary
     * @param bool $caseSensitive Indicates whether the comparison of keys is case sensitive.
     */
    public function __construct(bool $readOnly = false, array $items = [], bool $caseSensitive = true)
    {
        // Set case sensitivity
        $this->caseSensitive = $caseSensitive;

        // Add initialization data is set
        if (!empty($items))
        {
            // Set internal data container
            $this->data = ($caseSensitive) ? $items : array_change_key_case($items, CASE_LOWER);

            // Set internal size counter
            $this->size = count($items);
        }

        // Set readonly last, after items are added to the collection
        $this->isReadOnly = $readOnly;
    }

    /**
     * Adds a new item to the dictionary with the specified key and value, or sets the value of an existing item.
     * Throws an exception if the dictionary is set to read-only.
     *
     * @param mixed $key The key of the item to be added
     * @param mixed $value The value of the item to be added
     *
     * @return void
     *
     * @throws Exception If the dictionary is read-only
     */
    public function add(mixed $key, mixed $value): void
    {
        if ($this->isReadOnly)
            throw new Exception("Unable to add item to Dictionary. The current Dictionary object is set to read-only.");

        // Add item
        if (!empty($key) || $key === 0)
        {
            // Lowercase key if we are in case-insensitive mode
            if (!$this->caseSensitive && !is_numeric($key))
                $key = strtolower($key);

            $this->data[$key] = $value;
        }
        else
            $this->data[] = $value;

        ++$this->size;
    }

    /**
     * Merges another dictionary or array into the current dictionary.
     * For conflicting keys, the current dictionary's keys and values are retained.
     *
     * @param array|Dictionary $other The other dictionary or array to merge.
     *
     * @throws Exception if the dictionary is read-only
     * @return void
     */
    public function mergeLeft(array|Dictionary $other): void
    {
        if ($this->isReadOnly) {
            throw new Exception("Unable to merge dictionaries. The current Dictionary object is set to read-only.");
        }

        // Get the data from the other dictionary or array
        $otherData = ($other instanceof Dictionary) ? $other->toArray() : $other;

        // Handle case sensitivity
        if (!$this->caseSensitive) {
            $otherData = array_change_key_case($otherData, CASE_LOWER);
        }

        // Merge data, overwrite only with non-existing keys
        $this->data = $otherData + $this->data; // Retain current dictionary values on key conflict

        // Update size
        $this->size = count($this->data);
    }

    /**
     * Merges another dictionary or array into the current dictionary.
     * For conflicting keys, the new dictionary's keys and values overwrite existing ones.
     *
     * @param array|Dictionary $other The other dictionary or array to merge.
     *
     * @throws Exception if the dictionary is read-only
     * @return void
     */
    public function mergeRight(array|Dictionary $other): void
    {
        if ($this->isReadOnly) {
            throw new Exception("Unable to merge dictionaries. The current Dictionary object is set to read-only.");
        }

        // Get the data from the other dictionary or array
        $otherData = ($other instanceof Dictionary) ? $other->toArray() : $other;

        // Handle case sensitivity
        if (!$this->caseSensitive) {
            $otherData = array_change_key_case($otherData, CASE_LOWER);
        }

        // Merge data, overwrite with new dictionary values on key conflict
        $this->data = array_merge($this->data, $otherData);

        // Update size
        $this->size = count($this->data);
    }

    /**
     * Determines whether the dictionary contains the specified key
     *
     * @param mixed $key The item key
     *
     * @return bool
     */
    public function containsKey(mixed $key): bool
    {
        // Lowercase key if we are in case-insensitive mode
        if (!$this->caseSensitive && !is_numeric($key))
            $key = strtolower($key);

        return array_key_exists($key, $this->data);
    }

    /**
     * Determines whether the dictionary contains the specified key.
     *
     * DOES NOT DO CASE IN-SENSITIVE CHECKS!
     *
     * @param mixed $key The item key
     *
     * @return bool
     */
    private function _containsKey(mixed $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Determines whether the dictionary contains a value
     *
     * @param mixed $item The value to search for
     *
     * @return bool
     */
    public function containsValue(mixed $item): bool
    {
        return (($index = array_search($item, $this->data, true)) !== false);
    }

    /**
     * Returns All the dictionary keys
     *
     * @return string[]
     */
    public function getKeys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Returns All the dictionary values
     *
     * @return mixed[]
     */
    public function getValues(): array
    {
        return array_values($this->data);
    }

    /**
     * Removes all items from the dictionary
     *
     * @throws Exception
     * @return void
     */
    public function clear(): void
    {
        if ($this->isReadOnly)
            throw new Exception("Unable to clear Dictionary items. The current Dictionary object is set to read-only.");

        $this->data = array();
        $this->size = 0;
    }

    /**
     * Gets the value associated with the specified key
     *
     * @param string $key The item's key
     *
     * @return mixed Returns the item of the specified index
     * @throws Exception if the $key is not present in the dictionary
     */
    public function itemAt($key): mixed
    {
        // Lowercase key if we are in case-insensitive mode
        if (!$this->caseSensitive && !is_numeric($key))
            $key = strtolower($key);

        // Check if key exists in the collection
        if (!$this->_containsKey($key))
            throw new Exception("The given key was not present in the dictionary: {$key}");

        return $this->data[$key];
    }

    /**
     * Gets the value associated with the specified key.
     *
     * @param mixed $key The key of the value to get.
     * @param mixed $value Contains the value associated with the specified key if the key is found; otherwise null
     *
     * @return bool true if the Dictionary contains an element with the specified key; otherwise, false.
     */
    public function tryGetValue($key, &$value): bool
    {
        // Lowercase key if we are in case-insensitive mode
        if (!$this->caseSensitive && !is_numeric($key))
            $key = strtolower($key);

        // Check if key exists in the collection
        if (!$this->_containsKey($key))
        {
            $value = null;
            return false;
        }

        $value = $this->data[$key];
        return true;
    }

    /**
     * Gets the value associated with the specified key, or returns the specified
     * default value if the Dictionary does not contain the specified key.
     *
     * @param mixed $key The key of the value to get.
     * @param mixed $default The default value to return in the specified key does not exist.
     *
     * @return mixed
     */
    public function getValueOrDefault($key, $default): mixed
    {
        // Lowercase key if we are in case-insensitive mode
        if (!$this->caseSensitive && !is_numeric($key))
            $key = strtolower($key);

        // Check if key exists in the collection
        return (!$this->_containsKey($key)) ? $default : $this->data[$key];
    }

    /**
     * Removes the value with the specified key from the Dictionary
     *
     * @param $key
     *
     * @throws Exception
     * @internal param mixed $item The item value to search for
     * @return bool true if the item was removed, otherwise false
     */
    public function remove($key): bool
    {
        if ($this->isReadOnly)
            throw new Exception("Unable to remove item to Dictionary. The current Dictionary object is set to read-only.");

        // Lowercase key if we are in case-insensitive mode
        if (!$this->caseSensitive && !is_numeric($key))
            $key = strtolower($key);

        // Check if key exists in the collection
        if ($this->_containsKey($key))
        {
            unset($this->data[$key]);
            --$this->size;
            return true;
        }

        return false;
    }

    /**
     * Returns the list as an array
     * @return mixed[]
     */
    public function toArray(): array
    {
        return $this->data;
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
        return $this->size;
    }

    /**
     * Returns whether the specified item key exists in the container
     * This method is required by the interface ArrayAccess.
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        // Lowercase key if we are in case-insensitive mode
        if (!$this->caseSensitive && !is_numeric($offset))
            $offset = strtolower($offset);

        // Check if key exists in the collection
        return $this->_containsKey($offset);
    }

    /**
     * Returns the item value of the specified key.
     * This method is required by the interface ArrayAccess.
     *
     * @param string $key The item key
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->itemAt($offset);
    }

    /**
     * Sets the item with the specified key.
     * This method is required by the interface ArrayAccess.
     *
     * @param mixed $offset
     * @param mixed $value The item value
     *
     * @return void
     *
     * @throws Exception
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->add($offset, $value);
    }

    /**
     * Removes the item with the specified key.
     * This method is required by the interface ArrayAccess.
     *
     * @param mixed $offset The item key
     *
     * @return void
     *
     * @throws Exception
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    /**
     * Serializes the data, and returns it.
     * This method is required by the interface Serializable.
     *
     * @return string The serialized string
     */
    public function serialize(): string
    {
        return serialize($this->data);
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
        $this->data = unserialize($data);
    }

    /**
     * Returns the ArrayIterator of this object
     * This method is required by the interface IteratorAggregate.
     *
     * @return Traversable The serialized string
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->data);
    }
}