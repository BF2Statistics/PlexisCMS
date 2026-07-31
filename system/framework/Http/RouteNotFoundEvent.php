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

use System\Events\Event;
use System\Events\StoppableEvent;
use System\ModuleProvider;
use System\Routing\RoutingDirective;

/**
 * Event dispatched when the router/pipeline fails to resolve a request and a 404 would be produced.
 *
 * Listeners can handle the 404 in one of two ways:
 *
 * 1) **Immediate response**: call {@see self::setResponse()} to provide a {@see Response} and stop further
 *    event propagation (short-circuiting the pipeline).
 * 2) **Override routing**: call {@see self::override()} to re-route the request to a different
 *    {@see ModuleProvider} and {@see RoutingDirective} while allowing the pipeline to continue.
 *
 * The original request may be provided either as a {@see Request} instance or as a route name,
 * depending on where the event is raised.
 */
class RouteNotFoundEvent extends StoppableEvent
{
    /**
     * The unresolved request associated with the 404.
     *
     * Can be either a concrete {@see Request} instance (preferred), or a string representation
     * (for example, a URI/path) when a full Request object is not available.
     *
     * @var Request|string
     */
    protected(set) Request|string $request;

    /**
     * A response to return immediately (Mode 1).
     *
     * This is typically set by {@see self::setResponse()}.
     *
     * @var Response
     */
    protected(set) Response $response;

    /**
     * The module/provider to use if overriding the 404 (Mode 2).
     *
     * @var ModuleProvider
     */
    protected(set) ModuleProvider $moduleProvider;

    /**
     * The routing directive to apply if overriding the 404 (Mode 2).
     *
     * @var RoutingDirective
     */
    protected(set) RoutingDirective $directive;

    /**
     * Whether the 404 has been overridden with a new route (Mode 2).
     *
     * When true, {@see self::$moduleProvider} and {@see self::$directive} will have been set.
     *
     * @var bool
     */
    protected(set) bool $handled = false;

    /**
     * Create a new 404 event.
     * @param Request|string $request The request that could not be routed or a route name when a Request instance is not available.
     */
    public function __construct(Request|string $request)
    {
        $this->request = $request;
    }

    /**
     * Provide a response to return immediately and stop further handling (Mode 1).
     *
     * Calling this method sets {@see self::$response} and stops event propagation via
     * {@see Event::stopPropagation()}.
     *
     * @param Response $response The response to return for this 404.
     *
     * @return void
     */
    public function setResponse(Response $response): void
    {
        $this->response = $response;
        $this->stopPropagation();
    }

    /**
     * Override the 404 by assigning a different module/provider and routing directive (Mode 2).
     *
     * This marks the event as handled and allows the request to continue through the pipeline.
     * If {@see self::$request} is a {@see Request} instance, its routing directive will also be updated.
     *
     * @param ModuleProvider   $module    The module/provider that should handle the request.
     * @param RoutingDirective $directive The routing directive to apply to the request.
     *
     * @return void
     */
    public function override(ModuleProvider $module, RoutingDirective $directive): void
    {
        $this->handled = true;
        $this->moduleProvider = $module;
        $this->directive = $directive;

        if ($this->request instanceof Request)
        {
            $this->request->setRoutingDirective($directive);
        }
    }
}