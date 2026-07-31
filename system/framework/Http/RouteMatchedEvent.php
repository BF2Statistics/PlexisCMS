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

namespace System\Http;

use System\Events\Event;
use System\Events\StoppableEvent;
use System\ModuleProvider;
use System\Routing\RoutingDirective;

/**
 * Event emitted after a request has been matched to a module/route.
 *
 * Listeners may inspect the matched module and request, or override the match by
 * providing a different module and routing directive via {@see override()}.
 */
class RouteMatchedEvent extends StoppableEvent
{
    /**
     * The current request being routed.
     */
    protected(set) Request|string $request;

    /**
     * The module/provider selected by the router for the current request.
     */
    protected(set) ModuleProvider $moduleProvider;

    /**
     * The directive to be applied.
     */
    protected(set) RoutingDirective $directive;

    /**
     * Whether the match has been overridden/handled by a listener.
     *
     * This is set to {@see true} when {@see override()} is called.
     */
    protected(set) bool $handled = false;

    /**
     * Create a new route-matched event.
     *
     * @param Request|string $request  The request that was routed or route name.
     * @param ModuleProvider $provider The initially matched module/provider.
     */
    public function __construct(Request|string $request, ModuleProvider $provider)
    {
        $this->request = $request;
        $this->moduleProvider = $provider;
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
        $this->directive = $directive;

        if ($this->request instanceof Request)
        {
            $this->request->setRoutingDirective($directive);
        }
    }
}