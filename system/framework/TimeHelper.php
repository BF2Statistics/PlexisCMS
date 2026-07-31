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
 * Class TimeHelper
 *
 * A utility class for handling and formatting time-related operations.
 * The `TimeHelper` class provides methods for converting seconds into a human-readable
 * hour-minute-second format (`h:m:s`) and for formatting time differences between
 * two timestamps in a human-readable way.
 *
 * ## Key Responsibilities:
 * - **Time Conversions**: Converts seconds into `h:m:s` format.
 * - **Time Differences**: Generates a human-readable string for the difference
 *   between two timestamps.
 *
 * ## Features:
 * - Customizable time formatting for output, such as limiting the number of
 *   time difference components displayed.
 * - Supports both integer and floating-point values for conversion in seconds.
 * - Handles edge cases, such as zero timestamps, gracefully.
 *
 * ## Usage:
 * The `TimeHelper` class can be used when working with time values that need to be
 * formatted for user-friendly output. It is especially useful in applications that
 * deal with durations or time intervals.
 *
 * Example 1: Convert seconds to `h:m:s` format.
 * ```
 * echo TimeHelper::SecondsToHms(3661); // Output: "01:01:01"
 * ```
 *
 * Example 2: Format time difference between two timestamps.
 * ```
 * $future = time();
 * $past = strtotime('-1 year -2 months -5 days -3 hours -10 minutes');
 * echo TimeHelper::FormatDifference($past, $future, 3);
 * // Output: "1 year, 2 months, 5 days ago"
 * ```
 *
 * ## Key Methods:
 * ### static SecondsToHms(int|float $seconds): string
 * Converts a number of seconds into the `h:m:s` time format.
 * - **Parameters**:
 *   - `$seconds`: An integer or floating-point value representing the duration in seconds.
 * - **Returns**: A formatted string in the `hh:mm:ss` format
 *   with zero-padded fields for hours, minutes, and seconds.
 * - **Example**:
 *   ```php
 *   echo TimeHelper::SecondsToHms(3661); // Output: "01:01:01"
 *   ```

 * ### static FormatDifference(int $time1, int $time2, int $length = 2): string
 * Formats the difference between two timestamps into human-readable text.
 * The difference is divided into parts such as years, months, days, hours, minutes,
 * and seconds, and the number of parts displayed is customizable.
 * - **Parameters**:
 *   - `$time1`: The earlier timestamp (in seconds since the Unix Epoch).
 *   - `$time2`: The later timestamp (in seconds since the Unix Epoch).
 *   - `$length`: The maximum number of components to include in the output (default is `2`).
 * - **Returns**: A human-readable string representing the time difference, such as `1 year, 2 months ago`.
 * - **Edge Case**:
 *   - Returns `'Never'` if `$time1` is `0`.
 * - **Example**:
 *   ```php
 *   $future = time();
 *   $past = strtotime('-1 year -15 days');
 *   echo TimeHelper::FormatDifference($past, $future, 2);
 *   // Output: "1 year, 15 days ago"
 *   ```

 * ## Edge Cases and Notes:
 * ### SecondsToHms:
 * - Handles floating-point values by rounding seconds to the nearest integer.
 * - Ensures zero-padded output for consistency (e.g., `00:01:05` instead of `0:1:5`).
 *
 * ### FormatDifference:
 * - Limits the number of components in the response using the `$length` parameter,
 *   e.g., "1 year, 2 months" instead of "1 year, 2 months, 3 days".
 * - Properly handles zero (`$time1 = 0`) by returning `'Never'`.
 *
 * @package System
 * @author Steven Wilson
 * @license GNU GPL v3
 * @copyright Copyright 2025
 */

class TimeHelper
{
    /**
     * Converts a time from seconds to a string format of h:m:s
     *
     * @param int|float $seconds
     *
     * @return string
     */
    public static function SecondsToHms(int|float $seconds): string
    {
        $h = floor($seconds / 3600);
        $reste_secondes = $seconds - $h * 3600;

        $m = floor($reste_secondes / 60);
        $reste_secondes = $reste_secondes - $m * 60;

        $s = round($reste_secondes, 3);
        $s = number_format($s, 0, '.', '');

        $h = str_pad($h, 2, '0', STR_PAD_LEFT);
        $m = str_pad($m, 2, '0', STR_PAD_LEFT);
        $s = str_pad($s, 2, '0', STR_PAD_LEFT);

        $temps = $h . ":" . $m . ":" . $s;

        return $temps;
    }

    /**
     * Formats a time difference (timestamp) into a human-readable format
     *
     * @param int $time1 The earliest time
     * @param int $time2 The latter time
     * @param int $length The number of interval parts to display
     *
     * @return string
     */
    public static function FormatDifference(int $time1, int $time2, int $length = 2): string
    {
        if ($time1 == 0)
            return 'Never';

        // Define variables
        $now = new \DateTime("@". $time2);
        $last = new \DateTime("@". $time1);
        $interval = $now->diff($last);
        $parts = [];

        // Append year difference
        if ($interval->y > 0)
        {
            $parts[] = $interval->y . (($interval->y > 1) ? ' years' : ' year');
        }

        // Append month difference
        if ($interval->m > 0)
        {
            $parts[] = $interval->m . (($interval->m > 1) ? ' months' : ' month');
        }

        // Append day difference
        if ($interval->d > 0)
        {
            $parts[] = $interval->d . (($interval->d > 1) ? ' days' : ' day');
        }

        // Append hour difference
        if ($interval->h > 0)
        {
            $parts[] = $interval->h . (($interval->h > 1) ? ' hours' : ' hour');
        }

        // Append minute difference
        if ($interval->i > 0)
        {
            $parts[] = $interval->i . (($interval->i > 1) ? ' minutes' : ' minute');
        }

        // Append second difference
        if ($interval->s > 0)
        {
            $parts[] = $interval->s . (($interval->s > 1) ? ' seconds' : ' second');
        }

        return implode(', ', array_slice($parts, 0, $length, true)) . " ago";
    }
}