<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.2 or newer required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2025, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */
namespace Application\Battlefield2;

use System\ArgumentOutOfRangeException;

/**
 * Class ObjectStat
 *
 * @package System\BF2
 */
class ObjectStat implements \ArrayAccess
{
    /**
     * @var int The object id
     */
    public int $id = 0;

    /**
     * @var int The time in seconds played with this object
     */
    public int $time = 0;

    /**
     * @var int The total score earned with this object
     */
    public int $score = 0;

    /**
     * @var int The number of kills with this object
     */
    public int $kills = 0;

    /**
     * @var int The number of deaths with this object
     */
    public int $deaths = 0;

    /**
     * @var int The number of shots fired with this object
     */
    public int $fired = 0;

    /**
     * @var int The number of hits with this object
     */
    public int $hits = 0;

    /**
     * @var int The number of road kills with this object
     */
    public int $roadKills = 0;

    /**
     * @var int The number of times this object was deployed
     */
    public int $deployed = 0;

    /**
     * Whether a offset exists
     *
     * @param mixed $offset An offset to check for.
     *
     * @return boolean true on success or false on failure.
     *
     * The return value will be casted to boolean if non-boolean was returned.
     *
     */
    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    /**
     * Offset to retrieve
     *
     * @param string $offset The offset to retrieve.
     *
     * @return mixed Can return all value types.
     *
     * @throws ArgumentOutOfRangeException
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->offsetExists($offset))
        {
            throw new \System\ArgumentOutOfRangeException("The given offset was not present in the object: {$offset}");
        }

        return $this->{$offset};
    }

    /**
     * Offset to set
     *
     * @param string $offset The offset to assign the value to.
     *
     * @param mixed $value The value to set.
     *
     * @return void
     *
     * @throws ArgumentOutOfRangeException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!$this->offsetExists($offset))
        {
            throw new \System\ArgumentOutOfRangeException("The given offset was not present in the object: {$offset}");
        }

        $this->{$offset} = $value;
    }

    /**
     * Method un-used
     *
     * @deprecated This method does not do anything
     *
     * @param string $offset The offset to unset.
     */
    public function offsetUnset(mixed $offset): void
    {

    }
}