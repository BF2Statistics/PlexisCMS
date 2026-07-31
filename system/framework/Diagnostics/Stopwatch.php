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
namespace System\Diagnostics;

/**
 * Provides a set of methods that you can use to accurately measure elapsed time.
 */
class Stopwatch
{
    /**
     * The total elapsed time counter
     * @var float
     */
    protected float $buffer = 0;

    /**
     * The start time from the last pause or stop
     * @var float
     */
    protected float $start = 0;

    /**
     * Indicates whether the timer is ticking.
     * @var bool
     */
    protected bool $isRunning = false;

    /**
     * Starts, or resumes, measuring elapsed time for an interval.
     *
     * @return void
     */
    public function start(): void
    {
        if(!$this->isRunning)
        {
            $this->start = microtime(true);
            $this->isRunning = true;
        }
    }

    /**
     * Stops measuring elapsed time for an interval.
     *
     * @return void
     */
    public function stop(): void
    {
        if($this->isRunning)
        {
            $this->buffer += microtime(true) - $this->start;
            $this->isRunning = false;
        }
    }

    /**
     * Stops time interval measurement, resets the elapsed time to zero,
     * and starts measuring elapsed time.
     *
     * @return void
     */
    public function restart(): void
    {
        $this->buffer = 0;
        $this->start = microtime(true);
        $this->isRunning = true;
    }

    /**
     * Stops time interval measurement and resets the elapsed time to zero.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->isRunning = false;
        $this->buffer = 0;
        $this->start = 0;
    }

    /**
     * Gets the total elapsed time measured in microseconds
     *
     * @return float
     */
    public function elapsedTime(int $decimals = 3): float
    {
        if($this->isRunning)
            return round($this->buffer + (microtime(true) - $this->start), $decimals);
        else
            return $this->buffer;
    }

    /**
     * Gets a value indicating whether the Stopwatch timer is running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Creates and starts a new Stopwatch instance, optionally initializing it with a specified start time.
     *
     * @param float|null $timeStart The start time to initialize the Stopwatch with, as a Unix Timestamp.
     *                              If null, the stopwatch starts at the current time.
     *                              If provided, it must be less than the current time.
     *
     * @return Stopwatch Returns a new Stopwatch instance that has been started.
     *
     * @throws \System\ArgumentException If $timeStart is greater than the current time.
     */
    public static function StartNew(?float $timeStart = null): Stopwatch
    {
        $Sw = new Stopwatch();

        if ($timeStart !== null)
        {
            $now = microtime(true);
            if ($timeStart > $now)
            {
                throw new \System\ArgumentException("timeStart must be less than the current time.");
            }

            $Sw->buffer = microtime(true) - $timeStart;
        }

        $Sw->start();
        return $Sw;
    }
}