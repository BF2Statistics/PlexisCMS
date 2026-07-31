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
 * Class TimeSpan
 *
 * A utility class that represents a span of time and provides methods
 * for time manipulation, conversion, and retrieval. The `TimeSpan` class
 * is designed to handle operations involving durations in terms of
 * seconds, minutes, hours, days, and weeks.
 *
 * ## Key Responsibilities:
 * - **Time Construction**: Creates time spans from various units (seconds, minutes, hours, days, weeks).
 * - **Time Manipulation**: Provides methods to add or subtract time spans.
 * - **Time Retrieval**: Offers functionality to retrieve the total or individual components of a time span.
 *
 * ## Features:
 * - Supports generating a `TimeSpan` from specific components or utility methods like `FromSeconds`, `FromMinutes`, etc.
 * - Provides error handling for invalid or non-numeric arguments.
 * - Ensures values are always stored as absolute positive durations and prevents negative time spans.
 * - Handles addition and subtraction of time spans, with exceptions for invalid operations.
 *
 * ## Usage:
 * The `TimeSpan` class can be used for any operations requiring precise handling
 * of time durations, such as scheduling, logging, or time-based calculations.
 *
 * Example 1: Creating a `TimeSpan` from hours, minutes, and seconds.
 * ```
 * $timeSpan = new TimeSpan(1, 30, 15); // 1 hour, 30 minutes, and 15 seconds
 * echo $timeSpan->getSeconds(); // Output: 5415
 * ```
 *
 * Example 2: Adding one `TimeSpan` to another:
 * ```
 * $timeSpan1 = new TimeSpan(1, 0, 0); // 1 hour
 * $timeSpan2 = TimeSpan::FromMinutes(30); // 30 minutes
 * $timeSpan1->add($timeSpan2);
 * echo $timeSpan1->getSeconds(); // Output: 5400 (1 hour, 30 minutes)
 * ```
 *
 * Example 3: Subtracting time spans:
 * ```
 * $timeSpan1 = new TimeSpan(1, 0, 0); // 1 hour
 * $timeSpan2 = TimeSpan::FromMinutes(30); // 30 minutes
 * $timeSpan1->subtract($timeSpan2);
 * echo $timeSpan1->getSeconds(); // Output: 1800 (30 minutes)
 * ```
 *
 * ## Features and Benefits:
 * - **Utility Methods**: Multiple static factory methods make it easy to create `TimeSpan` objects from various units.
 * - **Error Handling**: Attempts to ensure validity by throwing exceptions for incorrect inputs or invalid operations.
 * - **Immutability in Arithmetic**: Methods like `add` and `subtract` return the same object instance, modifying its state.
 *
 * ## Notes:
 * - For subtraction, ensure the first `TimeSpan` is longer than the second to avoid issues with negative values.
 * - This class uses absolute values internally, ensuring time spans are always stored as positive integers.
 *
 * @package System
 * @author Steven Wilson
 * @license GNU GPL v3
 */

class TimeSpan
{
    protected int $seconds = 0;

    /**
     * Constructor
     *
     * @param int $hours
     * @param int $mins
     * @param int $secs secs - an amount of seconds, absolute value is used
     */
    public function __construct(int $hours, int $mins, int $secs)
    {
        // Append seconds
        $this->seconds = (int)abs($secs);

        // Append hours
        if ($hours > 0)
            $this->seconds += (int)abs($hours * 3600);

        // Append minutes
        if ($mins > 0)
            $this->seconds += (int)abs($mins * 60);
    }

    /**
     * Add a TimeSpan
     *
     * @param TimeSpan $span
     *
     * @return TimeSpan
     * @throws ArgumentException
     */
    public function add(TimeSpan $span): static
    {
        $this->seconds += $span->seconds;
        return $this;
    }

    /**
     * Subtracts a TimeSpan from this current TimeSpan object
     *
     * @param TimeSpan $span
     *
     * @return TimeSpan
     *
     * @throws ArgumentException
     */
    public function subtract(TimeSpan $span): static
    {
        // Check for new negative value
        if ($span->seconds > $this->seconds)
        {
            throw new ArgumentException('Cannot subtract ' . $span->toString() . ' from ' . $this->toString());
        }

        $this->seconds -= $span->seconds;
        return $this;
    }

    /**
     * Gets a timespan from seconds
     *
     * @param int $seconds
     *
     * @return TimeSpan
     */
    public static function FromSeconds(int $seconds): TimeSpan
    {
        return new self(0, 0, $seconds);
    }

    /**
     * Gets a timespan from minutes
     *
     * @param int $minutes
     *
     * @return TimeSpan
     */
    public static function FromMinutes(int $minutes): TimeSpan
    {
        return new self(0, $minutes, 0);
    }

    /**
     * Gets a timespan from hours
     *
     * @param int $hours
     *
     * @return TimeSpan
     */
    public static function FromHours(int $hours): TimeSpan
    {
        return new self($hours, 0, 0);
    }

    /**
     * Gets a timespan from days
     *
     * @param int $days
     *
     * @return TimeSpan
     */
    public static function days(int $days): TimeSpan
    {
        return new self(0, 0, $days * 86400);
    }

    /**
     * Gets a timespan from weeks
     *
     * @param int $weeks
     *
     * @return TimeSpan
     * @throws ArgumentException
     */
    public static function FromWeeks(int $weeks): TimeSpan
    {
        return new self(0, 0, $weeks * 604800);
    }

    /**
     * Returns this span of time in seconds
     *
     * @return  int
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * Returns the amount of 'whole' seconds in this
     * span of time
     *
     * @return  int
     */
    public function getWholeSeconds(): int
    {
        return $this->seconds % 60;
    }

    /**
     * Return an amount of minutes less than or equal
     * to this span of time
     *
     * @return  int
     */
    public function getMinutes(): int
    {
        return (int)floor($this->seconds / 60);
    }

    /**
     * Returns a float value representing this span of time
     * in minutes
     *
     * @return  float
     */
    public function getMinutesFloat(): float
    {
        return $this->seconds / 60;
    }

    /**
     * Returns the amount of 'whole' minutes in this
     * span of time
     *
     * @return  int
     */
    public function getWholeMinutes(): int
    {
        return (int)floor(($this->seconds % 3600) / 60);
    }

    /**
     * Adds an amount of minutes to this span of time
     *
     * @param int $mins
     */
    public function addMinutes(int $mins): void
    {
        $this->seconds += (int)$mins * 60;
    }

    /**
     * Returns an amount of hours less than or equal
     * to this span of time
     *
     * @return  int
     */
    public function getHours(): int
    {
        return (int)floor($this->seconds / 3600);
    }

    /**
     * Returns a float value representing this span of time
     * in hours
     *
     * @return  float
     */
    public function getHoursFloat(): float
    {
        return $this->seconds / 3600;
    }

    /**
     * Returns the amount of 'whole' hours in this
     * span of time
     *
     * @return  int
     */
    public function getWholeHours(): int
    {
        return (int)floor(($this->seconds % 86400) / 3600);
    }

    /**
     * Adds an amount of Hours to this span of time
     *
     * @param int $hours
     */
    public function addHours(int $hours): void
    {
        $this->seconds += (int)$hours * 3600;
    }

    /**
     * Returns an amount of days less than or equal
     * to this span of time
     *
     * @return  int
     */
    public function getDays(): int
    {
        return (int)floor($this->seconds / 86400);
    }

    /**
     * Returns a float value representing this span of time
     * in days
     *
     * @return  float
     */
    public function getDaysFloat(): float
    {
        return $this->seconds / 86400;
    }

    /**
     * Returns the amount of 'whole' days in this
     * span of time
     *
     * @return  int
     */
    public function getWholeDays(): int
    {
        return $this->getDays();
    }

    /**
     * Adds an amount of Days to this span of time
     *
     * @param int $days
     */
    public function addDays($days): void
    {
        $this->seconds += (int)$days * 86400;
    }

    /**
     * Format timespan
     *
     * Format tokens are:
     * <pre>
     * %s   - seconds
     * %w   - 'whole' seconds
     * %m   - minutes
     * %M   - minutes (float)
     * %j   - 'whole' minutes
     * %h   - hours
     * %H   - hours (float)
     * %y   - 'whole' hours
     * %d   - days
     * %D   - days (float)
     * %e   - 'whole' days
     * </pre>
     *
     * @param string $format
     *
     * @return  string the formatted timespan
     */
    public function format(string $format): string
    {
        $return = '';
        $o = 0;
        $l = strlen($format);
        while (0 < ($p = strcspn($format, '%', $o)))
        {
            $return .= substr($format, $o, $p);
            if (($o += $p + 2) <= $l)
            {
                switch ($format[$o - 1])
                {
                    case 's':
                        $return .= $this->getSeconds();
                        break;
                    case 'w':
                        $return .= $this->getWholeSeconds();
                        break;
                    case 'm':
                        $return .= $this->getMinutes();
                        break;
                    case 'M':
                        $return .= sprintf('%.2f', $this->getMinutesFloat());
                        break;
                    case 'j':
                        $return .= $this->getWholeMinutes();
                        break;
                    case 'h':
                        $return .= $this->getHours();
                        break;
                    case 'H':
                        $return .= sprintf('%.2f', $this->getHoursFloat());
                        break;
                    case 'y':
                        $return .= $this->getWholeHours();
                        break;
                    case 'd':
                        $return .= $this->getDays();
                        break;
                    case 'D':
                        $return .= sprintf('%.2f', $this->getDaysFloat());
                        break;
                    case 'e':
                        $return .= $this->getWholeDays();
                        break;
                    case '%':
                        $return .= '%';
                        break;
                    default:
                        $o--;
                }
            }
        }

        return $return;
    }

    /**
     * Indicates whether the timespan to compare equals this timespan
     *
     * @param TimeSpan $cmp
     *
     * @return bool true if the two timespan objects are equal
     */
    public function equals(TimeSpan $cmp): bool
    {
        return $cmp->getSeconds() == $this->getSeconds();
    }

    /**
     * Creates a string representation
     *
     * @param string $format, defaults to '%ed, %yh, %jm, %ws'
     *
     * @return string
     */
    public function toString(string $format = '%ed, %yh, %jm, %ws'): string
    {
        return $this->format($format);
    }

    public function __toString()
    {
        return $this->toString();
    }
}