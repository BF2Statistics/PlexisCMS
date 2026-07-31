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
namespace System;

/**
 * Represents a version number with major, minor, and revision components.
 */
class Version
{
    /**
     * @var int The major version number
     */
    public $major = 0;

    /**
     * @var int The minor version number
     */
    public $minor = 0;

    /**
     * @var int The revision number
     */
    public $revision = 0;

    /**
     * @var int The integer representation of this version
     */
    protected $intVal = 0;

    /**
     * Version constructor.
     *
     * @param int $major The major component of the version number
     * @param int $minor The minor component of the version number
     * @param int $revision The revision component of the version number
     */
    public function __construct(int $major, int $minor = 0, int $revision = 0)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->revision = $revision;
    }

    /**
     * Converts the string representation of a version number to an equivalent Version object.
     *
     * @param string $version A string that contains a version number to convert.
     *
     * @return Version
     */
    public static function Parse(string $version): Version
    {
        // Ensure valid characters are passed
        if (!preg_match("/[0-9.]+/i", $version))
            throw new \InvalidArgumentException("Version string contains illegal characters");

        $ver_arr = explode(".", $version);
        $size = sizeof($ver_arr);
        if ($size >= 3)
        {
            $major = (int)$ver_arr[0];
            $minor = (int)$ver_arr[1];
            $rev = (int)$ver_arr[2];

            return new Version($major, $minor, $rev);
        }
        elseif ($size == 2)
        {
            $major = (int)$ver_arr[0];
            $minor = (int)$ver_arr[1];

            return new Version($major, $minor, 0);
        }
        else
            return new Version((int)$ver_arr[0]);
    }

    /**
     * Compares 2 versions and returns which is greater
     *
     * @param string|Version $version The Version to compare to
     *
     * @return int Returns 0 if the versions are equal, -1 if the $version
     *   variable is larger than this instance, or 1 if this instance is
     *   greater then $version
     */
    public function compare(Version|string $version): int
    {
        // Ensure we have a Version object before proceeding
        if (!($version instanceof Version))
            $version = Version::Parse($version);

        // Versions are equal
        if ($version->toInt() == $this->toInt())
            return 0;

        // This version instance is less than the comparison
        elseif ($version->toInt() > $this->toInt())
            return -1;

        // This version instance is larger than
        else
            return 1;
    }

    /**
     * Converts this Version object into a comparable integer.
     *
     * @return int
     */
    public function toInt(): int
    {
        if ($this->intVal == 0)
        {
            $this->intVal = $this->major * 10000;
            $this->intVal += $this->minor * 100;
            $this->intVal += $this->revision;
        }

        return $this->intVal;
    }

    public function toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->revision}";
    }

    public function __toString(): string
    {
        return $this->toString();

    }

    /**
     * Determines whether the first specified Version object equals
     * the second specified Version object.
     *
     * @param string|Version $v1 The first version.
     * @param string|Version $v2 The second version
     *
     * @return bool
     */
    public static function Equals(Version|string $v1, Version|string $v2): bool
    {
        // Ensure we have a Version object before proceeding
        if (!($v1 instanceof Version))
            $v1 = Version::Parse($v1);

        // Ensure we have a Version object before proceeding
        if (!($v2 instanceof Version))
            $v2 = Version::Parse($v2);

        return ($v1->toInt() == $v2->toInt());
    }

    /**
     * Determines whether the first specified Version object is greater
     * than the second specified Version object.
     *
     * @param string|Version $v1 The first version.
     * @param string|Version $v2 The second version
     *
     * @return bool
     */
    public static function GreaterThan(Version|string $v1, Version|string $v2): bool
    {
        // Ensure we have a Version object before proceeding
        if (!($v1 instanceof Version))
            $v1 = Version::Parse($v1);

        // Ensure we have a Version object before proceeding
        if (!($v2 instanceof Version))
            $v2 = Version::Parse($v2);

        return ($v1->toInt() > $v2->toInt());
    }

    /**
     * Determines whether the first specified Version object is greater
     * than or equal to the second specified Version object.
     *
     * @param string|Version $v1 The first version.
     * @param string|Version $v2 The second version
     *
     * @return bool
     */
    public static function GreaterThanOrEqual(Version|string $v1, Version|string $v2): bool
    {
        // Ensure we have a Version object before proceeding
        if (!($v1 instanceof Version))
            $v1 = Version::Parse($v1);

        // Ensure we have a Version object before proceeding
        if (!($v2 instanceof Version))
            $v2 = Version::Parse($v2);

        return ($v1->toInt() >= $v2->toInt());
    }

    /**
     * Determines whether the first specified Version object is less
     * than the second specified Version object.
     *
     * @param string|Version $v1 The first version.
     * @param string|Version $v2 The second version
     *
     * @return bool
     */
    public static function LessThan(Version|string $v1, Version|string $v2): bool
    {
        // Ensure we have a Version object before proceeding
        if (!($v1 instanceof Version))
            $v1 = Version::Parse($v1);

        // Ensure we have a Version object before proceeding
        if (!($v2 instanceof Version))
            $v2 = Version::Parse($v2);

        return ($v1->toInt() < $v2->toInt());
    }

    /**
     * Determines whether the first specified Version object is less
     * than or equal to the second specified Version object.
     *
     * @param string|Version $v1 The first version.
     * @param string|Version $v2 The second version
     *
     * @return bool
     */
    public static function LessThanOrEqual(Version|string $v1, Version|string $v2): bool
    {
        // Ensure we have a Version object before proceeding
        if (!($v1 instanceof Version))
            $v1 = Version::Parse($v1);

        // Ensure we have a Version object before proceeding
        if (!($v2 instanceof Version))
            $v2 = Version::Parse($v2);

        return ($v1->toInt() <= $v2->toInt());
    }
}