<?php declare(strict_types=1);
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

namespace System\Routing;

use System\Events\Event;
use System\Events\StoppableEvent;
use System\Http\Request;
use System\ModuleProvider;

/**
 * Event dispatched when the router fails to resolve the current request to a route.
 *
 * Listeners may “handle” the not-found case by providing both a {@see RoutingDirective}
 * (how the request should be routed/treated) and a {@see ModuleProvider} (who will handle it).
 *
 * The event is considered handled when {@see self::$routingDirective} and {@see self::$moduleProvider}
 * are both non-null (see {@see self::$handled}).
 */
class RouteNotFoundEvent extends StoppableEvent
{
    /**
     * The request or route name that could not be matched to a route.
     *
     * @var Request|string
     */
    protected(set) Request|string $request;

    /**
     * Optional routing directive supplied by an event listener to influence how the request
     * should be resolved (e.g. redirect, rewrite, fallback behavior, etc.).
     *
     * When set together with {@see self::$moduleProvider}, the event is considered handled.
     *
     * @var RoutingDirective|null
     */
    public RoutingDirective|null $routingDirective = null;

    /**
     * Optional module provider selected by an event listener to handle the request.
     *
     * When set together with {@see self::$routingDirective}, the event is considered handled.
     *
     * @var ModuleProvider|null
     */
    public ModuleProvider|null $moduleProvider = null;

    /**
     * Indicates whether the event has been handled by listeners.
     *
     * The event is handled when both a routing directive and a module provider were provided.
     *
     * @var bool
     */
    protected(set) bool $handled = false;

    /**
     * @param Request|string $request The request or route name that could not be matched to a route.
     */
    public function __construct(Request|string $request)
    {
        $this->request = $request;
    }

    /**
     * Override the matched module/provider and routing directive for this request.
     *
     * Calling this marks the event as handled and updates the request with the
     * provided routing directive.
     *
     * @param ModuleProvider    $module    The module/provider that should handle the request.
     * @param RoutingDirective  $directive The routing directive to apply to the request.
     */
    public function override(ModuleProvider $module, RoutingDirective $directive): void
    {
        $this->handled = true;
        $this->moduleProvider = $module;
        $this->routingDirective = $directive;

        if ($this->request instanceof Request)
        {
            $this->request->setRoutingDirective($directive);
        }
    }
}