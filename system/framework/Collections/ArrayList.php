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

/**
 * Represents a dynamic array-like structure that supports various operations such as addition, removal,
 * searching, and iteration over elements. The ArrayList can be configured to be read-only, preventing modifications.
 * Implements standard interfaces to allow iteration, array-like access, and counting of elements.
 */
class ArrayList implements \IteratorAggregate, \ArrayAccess, \Countable
{
    /**
     * Data Container.
     * @var mixed[]
     */
    private array $data = array();

    /**
     * Represents whether this list is read-only.
     * @var bool
     */
    protected bool $isReadOnly = false;

    /**
     * Constructor
     *
     * @param bool $readOnly
     */
    public function __construct(bool $readOnly = false)
    {
        $this->isReadOnly = $readOnly;
    }

    /**
     * Appends an item at the end of the list.
     *
     * @param mixed $item the new item to add
     *
     * @return int the zero based index at which the item is added
     *
     * @throws \Exception
     */
    public function add(mixed $item) : int
    {
        $index = $this->getCurrentIndex();
        $this->insertAt($index, $item);
        return $index; // $this->currentIndex is incremented from insertAt, so return old index
    }

    /**
     * Returns where the dictionary contains a value
     *
     * @param mixed $item The value to search for
     *
     * @return bool
     */
    public function contains(mixed $item) : bool
    {
        return $this->indexOf($item) >= 0;
    }

    /**
     * Removes all items from the dictionary
     *
     * @throws \Exception Thrown if the ListObject is Read Only
     * @return void
     */
    public function clear() : void
    {
        // Check for readonly
        if ($this->isReadOnly)
            throw new \Exception("Unable to remove items from ArrayList. The current ArrayList object is set to read-only.");

        $this->data = array();
    }

    /**
     * Finds the index of a given value in the list.
     *
     * @param mixed $item The value to search for
     * @param bool $useStrict Whether to use strict type comparison during the search
     *
     * @return int The index of the value if found, or -1 if not found
     */
    public function indexOf(mixed $item, bool $useStrict = true) : int
    {
        return (($index = array_search($item, $this->data, $useStrict)) !== false) ? $index : -1;
    }

    /**
     * Inserts a new item at the specified index location
     *
     * @param int $index The index to place the item at.
     * @param mixed $item
     *
     * @throws \OutOfBoundsException If the specified index was out of bounds
     * @throws \Exception Thrown if the ListObject is Read Only
     * @return void
     */
    public function insertAt(int $index, mixed $item) : void
    {
        // Check for readonly
        if ($this->isReadOnly) {
            throw new \Exception("Unable to insert an item into ArrayList. The current ArrayList object is set to read-only.");
            }

        // Validate index
        if ($index < 0 || $index > $this->getCurrentIndex()) {
            throw new \OutOfBoundsException(sprintf("The specified index was out of bounds: %d", $index));
        }

        // Are we adding directly to the end of the array?
        if ($index == $this->getCurrentIndex())
        {
            $this->data[] = $item;
        }
        else
        {
            // We are adding to a specific index within the ArrayList
            array_splice($this->data, $index, 0, array($item));
        }
    }

    /**
     * Returns the item at the specified index
     *
     * @param int $index The zero based index of the item being requested
     *
     * @throws \OutOfBoundsException If the specified index was out of bounds
     *
     * @return mixed Returns the item of the specified index
     */
    public function itemAt(int $index): mixed
    {
        if ($index >= 0 && $index < $this->getCurrentIndex())
            return $this->data[$index];
        else
            throw new \OutOfBoundsException(sprintf("The specified index was out of bounds: %d", $index));
    }

    /**
     * Removes an item value from the dictionary
     *
     * @param mixed $item The item value to search for
     *
     * @throws \Exception Thrown if the ListObject is Read Only
     *
     * @return int The zero based index of the item was removed from, or -1 if
     *             the item did not exist in the collection
     */
    public function remove(mixed $item): int
    {
        // Check that the item exists
        if (($index = $this->indexOf($item)) >= 0)
        {
            $this->removeAt($index);
            return $index;
        }

        return -1;
    }

    /**
     * Removes an item at a specified index
     *
     * @param int $index The zero based index of the item to remove
     *
     * @throws \OutOfBoundsException If the specified index was out of bounds
     * @throws \Exception Thrown if the ListObject is Read Only
     *
     * @return mixed Returns the value of the item that was removed
     */
    public function removeAt(int $index): mixed
    {
        // Check for readonly
        if ($this->isReadOnly) {
            throw new \Exception("Unable to remove item from ArrayList. The current ArrayList object is set to read-only.");
        }

        // Validate index
        if ($index < 0 || $index >= count($this->data)) {
            throw new \OutOfBoundsException(sprintf("The specified index was out of bounds: %d", $index));
        }

        if (($index + 1) === $this->getCurrentIndex())
        {
            return array_pop($this->data);
        }
        else
        {
            $item = $this->data[$index];
            array_splice($this->data, $index, 1);
            return $item;
        }
    }

    /**
     * Returns the list as an array
     * @return mixed[]
     */
    public function toArray() : array
    {
        return $this->data;
    }

    /**
     * Gets the current index based on the count of data.
     *
     * @return int
     */
    protected function getCurrentIndex(): int
    {
        return count($this->data);
    }


    // === Interface / Abstract Methods === //

    /**
     * Returns the number of items in the list
     * This method is required by the interface Countable.
     *
     * @return int
     */
    public function count() : int
    {
        return count($this->data);
    }

    /**
     * Returns whether there is an item at the specified offset.
     * This method is required by the interface ArrayAccess.
     *
     * @param mixed $offset The offset to check for
     *
     * @return bool
     */
    public function offsetExists(mixed $offset) : bool
    {
        if (!is_int($offset))
            return false;

        return ($offset >= 0 && $offset < $this->getCurrentIndex());
    }

    /**
     * Returns the item at the specified offset.
     * This method is required by the interface ArrayAccess.
     *
     * @param int $offset The item index to fetch
     *
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->itemAt($offset);
    }

    /**
     * Sets the item at the specified index.
     * This method is required by the interface ArrayAccess.
     *
     * @param mixed $offset
     * @param mixed $value The item's value
     *
     * @return void
     * @throws \Exception
     */
    public function offsetSet(mixed $offset, mixed $value) : void
    {
        if (!is_int($offset))
            throw new \Exception("The offset must be an integer.");

        // If no index is supplied, add a new item to the list
        if ($offset === $this->getCurrentIndex())
        {
            $this->insertAt($this->getCurrentIndex(), $value);
        }
        else
        {
            $this->removeAt($offset);
            $this->insertAt($offset, $value);
        }
    }

    /**
     * Removes the item at the specified index.
     * This method is required by the interface ArrayAccess.
     *
     * @param int $offset The item index to set
     *
     * @throws \Exception Thrown if the ListObject is Read Only
     *
     * @return void
     */
    public function offsetUnset($offset) : void
    {
        if (!is_int($offset))
            throw new \Exception("The offset must be an integer.");

        $this->removeAt($offset);
    }

    /**
     * Returns the ArrayIterator of this object
     * This method is required by the interface IteratorAggregate.
     *
     * @return \Traversable
     */
    public function getIterator() : \Traversable
    {
        return new \ArrayIterator($this->data);
    }
}