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

use System\Events\StoppableEvent;
use System\Http\Request;
use System\Http\Response;
use Throwable;

/**
 * Event dispatched when an unhandled exception or error occurs anywhere in the application.
 *
 * Listeners can provide a custom {@see Response} by calling {@see self::setResponse()},
 * which also stops further event propagation. If no listener provides a response,
 * the framework's built-in default error page will be rendered as a fallback.
 *
 * @event system.error
 */
class UnhandledExceptionEvent extends StoppableEvent
{
    /**
     * The unhandled throwable that triggered this event.
     */
    protected(set) Throwable $throwable;

    /**
     * The current request, or null if the request could not be resolved.
     */
    protected(set) ?Request $request;

    /**
     * The HTTP status code for the error response.
     */
    protected(set) int $statusCode;

    /**
     * A custom response provided by a listener.
     */
    protected(set) ?Response $response = null;

    /**
     * Creates a new unhandled exception event.
     *
     * @param Throwable    $throwable  The exception or error that was not caught.
     * @param Request|null $request    The current request, or null if unavailable.
     * @param int          $statusCode The HTTP status code (typically 500).
     */
    public function __construct(Throwable $throwable, ?Request $request, int $statusCode = 500)
    {
        $this->throwable = $throwable;
        $this->request = $request;
        $this->statusCode = $statusCode;
    }

    /**
     * Provide a custom response and stop further event propagation.
     *
     * @param Response $response The response to send for this error.
     *
     * @return void
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
        $this->stopPropagation();
    }

    /**
     * Whether a listener has provided a custom response.
     *
     * @return bool
     */
    public function hasResponse(): bool
    {
        return $this->response !== null;
    }
}