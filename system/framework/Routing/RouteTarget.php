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
namespace System\Routing;

/**
 * Value object describing the resolved execution target for a matched route.
 *
 * A {@see RouteTarget} bundles everything the dispatcher/router needs to invoke a
 * controller action: where the module lives, which controller class and method
 * should be called, the originating {@see Route} attribute instance, and any
 * middleware to run for this target.
 *
 * This class is immutable (readonly) and intended to be created by the routing
 * layer after a route has been matched.
 */
readonly class RouteTarget
{
    /**
     * @param string $moduleName The name of the module that owns the controller.
     * @param string $controllerClassName The Fully-qualified controller class name (including namespace).
     * @param string $methodName Controller method/action name to invoke.
     * @param Route  $route Route metadata/definition associated with this target.
     * @param array  $middleware Middleware to apply for this route target (ordering is significant).
     */
    public function __construct(
        public string $moduleName,
        public string $controllerClassName,
        public string $methodName,
        public Route  $route,
        public array  $middleware
    ) { }
}