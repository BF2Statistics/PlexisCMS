<?php declare(strict_types=1);
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
 * Immutable value object describing the result of routing a request.
 *
 * A {@see RoutingDirective} bundles:
 * - the resolved destination ({@see RouteTarget}: module, controller class, method, and route metadata), and
 * - any route parameters extracted during matching (e.g. URI placeholders).
 *
 * This object is typically produced by the router/dispatcher layer and then consumed by the controller invoker.
 */
readonly class RoutingDirective
{
    /**
     * @param RouteTarget          $target     Resolved route target (module/controller/method) and associated route metadata.
     * @param array<string,mixed>  $parameters Route parameters extracted from the request/URI.
     *                                         Typically an associative array keyed by parameter name.
     */
    public function __construct(
        public RouteTarget $target,
        public array       $parameters
    ) { }

    /**
     * Creates a routing directive from raw route components.
     *
     * Convenience factory to assemble a {@see RouteTarget} and wrap it together with extracted parameters.
     *
     * @param Route               $route      The route definition that was matched/selected.
     * @param string              $module     The module name/directory that owns the route/controller.
     * @param class-string        $controller Fully-qualified controller class name that will handle the route.
     * @param non-empty-string    $method     Controller method/action name to invoke.
     * @param array<string,mixed> $params     Route parameters to include in the directive.
     *
     * @return self
     */
    public static function From(Route $route, string $module, string $controller, string $method, array $params = []): RoutingDirective
    {
        return new RoutingDirective(new RouteTarget($module, $controller, $method, $route, []), $params);
    }
}