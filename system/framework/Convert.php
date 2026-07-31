<?php
declare(strict_types=1);
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
 * A utility class that provides static methods for various data conversion tasks.
 */
class Convert
{
    /**
     * Converts a given size in bytes to a human-readable file size with appropriate units.
     *
     * @param int|float $bytes The size in bytes to be converted.
     * @param int $decimals The number of decimal points to include in the result. Default is 2.
     *
     * @return string The formatted file size with appropriate unit.
     */
    public static function BytesToUnits(int|float $bytes, int $decimals = 2): string
    {
        $size = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $strBytes = strval($bytes);
        $factor = (int)floor((strlen($strBytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
    }
}