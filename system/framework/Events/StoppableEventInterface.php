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

namespace System\Events;

/**
 * Interface for stoppable events.
 *
 * Meets PSR-14 standards
 *
 * Events implementing this interface indicate that listeners
 * can stop propagation.
 */
interface StoppableEventInterface
{
    /**
     * Stops the propagation of the event to further listeners.
     *
     * @return void
     */
    public function stopPropagation(): void;

    /**
     * Returns whether the current operation or process has been canceled.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool;
}