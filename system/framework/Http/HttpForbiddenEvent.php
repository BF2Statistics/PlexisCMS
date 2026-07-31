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

namespace System\Http;

use System\Events\StoppableEvent;

/**
 * Event raised when a request results in an HTTP 403 (Forbidden).
 *
 * This event carries the resolved {@see Request} and a human-readable message
 * describing why access was denied. A listener may optionally provide a
 * {@see Response} to return immediately (by calling {@see self::setResponse()}),
 * which also stops further event propagation.
 */
class HttpForbiddenEvent extends StoppableEvent
{
    /**
     * The resolved request associated with the 403.
     */
    protected(set) Request $request;

    /**
     * A human-readable message explaining why the request is forbidden.
     */
    protected(set) string $message;

    /**
     * Creates a new forbidden event instance.
     *
     * @param Request $request The resolved request that triggered the 403.
     * @param string  $message A human-readable explanation for the denial.
     */
    public function __construct(Request $request, string $message)
    {
        $this->request = $request;
        $this->message = $message;
    }
}