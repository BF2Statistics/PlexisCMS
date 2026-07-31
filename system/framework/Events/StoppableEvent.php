<?php
/**
 * Plexis CMS, the Battlefield 2 private statistics frontend.
 *
 * Originally created for use by the community of BF2Statistics.com
 * before it shut down in April 2023.
 *
 * PHP Version 8.4.2 or newer is required.
 *
 * @author:       Steven Wilson
 * @copyright:    Copyright 2026, Steven Wilson, All rights reserved.
 * @license:      GNU GPL v3
 */

namespace System\Events;

use System\Events\Event;
use System\Events\StoppableEventInterface;

/**
 * Base class for events that can have their propagation stopped.
 *
 * Implements StoppableEventInterface.
 * Use this as a base class when listeners should be able to
 * prevent other listeners from being called.
 */
class StoppableEvent extends Event implements StoppableEventInterface
{
    /**
     * @var bool
     */
    private bool $stopped = false;

    /**
     * Cancels the current operation or process by setting the canceled state to true.
     *
     * @return void
     */
    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    /**
     * Returns whether the current operation or process has been canceled.
     */
    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}