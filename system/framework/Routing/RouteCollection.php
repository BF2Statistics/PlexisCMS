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

use System\Http\Request;

/**
 * Represents a collection of defined routes, offering functionality to manage, match, and manipulate
 * these routes. This class forms the core of the routing system and handles operations such as adding,
 * merging, or removing routes as well as checking for a matching route based on a web request.
 */
class RouteCollection
{
    /**
     * Master list (name => RouteInfo)
     * @var RouteTarget[]
     */
    protected array $routes = [];

    /**
     * Static fast-path (key => RouteInfo)
     * Key format: METHOD:AJAXFLAG:/path
     * @var array<string, RouteTarget>
     */
    protected array $staticRoutes = [];

    /**
     * Dynamic routes (name => RouteInfo)
     * @var array<string, RouteTarget>
     */
    protected array $dynamicRoutes = [];

    /**
     * Compiled route cache (name => compiled array)
     * @var array<string, array>
     */
    protected array $compiledRoutes = [];

    /**
     * Dynamic routes grouped by segment count
     * @var array<int, RouteTarget[]>
     */
    protected array $routesBySegmentCount = [];

    /**
     * Adds a route to the collection of routes.
     *
     * @param RouteTarget $route The route information to be added.
     * @return void
     */
    public function addRoute(RouteTarget $route): void
    {
        $name = $route->route->getName();
        $path = $route->route->getPath();

        $this->routes[$name] = $route;

        // Compile once (per request lifecycle)
        $compiled = $this->compileRoute($route);
        $this->compiledRoutes[$name] = $compiled;

        $hasParams = $compiled['hasParams'];

        if (!$hasParams) {
            // Static route fast-path: store per method and ajax-flag
            foreach ($compiled['methods'] as $method) {
                $key = $this->makeStaticKey($method, (bool)$compiled['isAjax'], $path);
                $this->staticRoutes[$key] = $route;
            }
            return;
        }

        // Dynamic route
        $this->dynamicRoutes[$name] = $route;

        $segmentCount = (int)$compiled['segmentCount'];
        $this->routesBySegmentCount[$segmentCount] ??= [];
        $this->routesBySegmentCount[$segmentCount][] = $route;
    }

    /**
     * Matches the given web request against available routes and returns the routing result if a match is found.
     *
     * @param Request $request The web request containing the URI to be matched against defined routes.
     *
     * @return RoutingDirective|false Returns the routing result if a match is found; otherwise, returns false.
     */
    public function match(Request $request): RoutingDirective|false
    {
        $path = $request->getPath();
        $method = $request->method()->value;
        $ajax = $request->isAjax();

        // 1) Static fast-path (O(1))
        $key = $this->makeStaticKey($method, $ajax, $path);
        if (isset($this->staticRoutes[$key])) {
            return new RoutingDirective($this->staticRoutes[$key], []);
        }

        // 2) Dynamic fallback
        return $this->matchDynamicRoutes($request);
    }

    /**
     * Matches a request to a given route based on various criteria such as request path, HTTP method,
     * expected output format, and defined route parameters.
     *
     * @param array $requestArray The segments of the request URI as an array
     * @param RouteTarget $info The route's definition and configuration details
     * @param RoutingDirective|false &$data A reference to store the matching result, if successful
     *
     * @return bool True if the request matches the route; otherwise, false
     */
    private function matchRequest(array $requestArray, Request $request, RouteTarget $info, RoutingDirective|false &$data): bool
    {
        $name = $info->route->getName();
        $compiled = $this->compiledRoutes[$name] ?? $this->compileRoute($info);
        $this->compiledRoutes[$name] = $compiled;

        // Quick rejects
        if ($compiled['segmentCount'] !== count($requestArray)) {
            return false;
        }

        if (!in_array($request->method()->value, $compiled['methods'], true)) {
            return false;
        }

        if ($request->isAjax() !== $compiled['isAjax']) {
            return false;
        }

        $params = [];
        $segments = $compiled['segments'];

        foreach ($segments as $index => $urlPart)
        {
            if (!isset($requestArray[$index])) {
                return false;
            }

            // Param segment
            if (isset($compiled['paramMetaByIndex'][$index]))
            {
                $meta = $compiled['paramMetaByIndex'][$index];
                if (!preg_match($meta['regex'], $requestArray[$index]))
                {
                    return false;
                }

                $params[$meta['name']] = $requestArray[$index];
                continue;
            }

            // Static segment
            if ($urlPart !== $requestArray[$index]) {
                return false;
            }
        }

        $data = new RoutingDirective($info, $params);
        return true;
    }

    /**
     * Merges another RouteCollections route with this collection.
     *
     * @param RouteCollection $Routes The route collection to merge with
     *
     * @return void
     */
    public function merge(RouteCollection $Routes) : void
    {
        $r = $Routes->getRoutes();
        foreach ($r as $match => $route)
            $this->addRoute($route);
    }

    /**
     * Retrieves route information for a specified route name.
     *
     * @param string $routeName The name of the route to retrieve information for.
     *
     * @return RouteTarget|false Returns the route information if the route name exists; otherwise, returns false.
     */
    public function getRouteByName(string $routeName) : RouteTarget|false
    {
        return $this->routes[$routeName] ?? false;
    }

    /**
     * Removes the specified route from the list of routes.
     *
     * @param string $routeName The regular expression to remove
     *
     * @return void
     */
    public function removeRouteByName(string $routeName) : void
    {
        $routeInfo = $this->routes[$routeName] ?? null;
        if ($routeInfo === null) {
            return;
        }

        unset($this->routes[$routeName], $this->dynamicRoutes[$routeName], $this->compiledRoutes[$routeName]);

        // Remove from static index (if static)
        $path = $routeInfo->route->getPath();
        foreach ($routeInfo->route->getMethods() as $method)
        {
            $keyAjax0 = $this->makeStaticKey($method, false, $path);
            $keyAjax1 = $this->makeStaticKey($method, true, $path);
            unset($this->staticRoutes[$keyAjax0], $this->staticRoutes[$keyAjax1]);
        }

        // Rebuild dynamic segment-count buckets (simple + safe)
        $this->routesBySegmentCount = [];
        foreach ($this->dynamicRoutes as $dynInfo)
        {
            $n = $dynInfo->route->getName();
            $compiled = $this->compiledRoutes[$n] ?? $this->compileRoute($dynInfo);
            $this->compiledRoutes[$n] = $compiled;

            $sc = (int)$compiled['segmentCount'];
            $this->routesBySegmentCount[$sc] ??= [];
            $this->routesBySegmentCount[$sc][] = $dynInfo;
        }
    }

    /**
     * Returns a list of all defined routes in this stack.
     *
     * @return RouteTarget[]
     */
    public function getRoutes() : array
    {
        return $this->routes;
    }

    /**
     * Matches a web request against dynamic routes based on its URI structure and parameters.
     *
     * @param Request $request The web request containing the URI to be matched against dynamic route patterns.
     *
     * @return RoutingDirective|false Returns a RoutingDirective object if a matching dynamic route is found; otherwise, returns false.
     */
    protected function matchDynamicRoutes(Request $request): RoutingDirective|false
    {
        // Split request path once
        $requestArray = explode('/', $request->getPath());
        $requestArray = array_values(array_filter($requestArray, 'strlen'));
        $segmentCount = count($requestArray);

        $candidates = $this->routesBySegmentCount[$segmentCount] ?? [];
        if (empty($candidates)) {
            return false;
        }

        $data = false;
        foreach ($candidates as $routeInfo)
        {
            if ($this->matchRequest($requestArray, $request, $routeInfo, $data))
            {
                return $data;
            }
        }

        return false;
    }

    /**
     * Compiles the given route into a structured array containing metadata for matching and processing.
     *
     * @param RouteTarget $route The route information object, which includes the route path and related metadata.
     *
     * @return array Returns an associative array with the route's compiled structure, including segments, methods, and parameter metadata.
     */
    protected function compileRoute(RouteTarget $route): array
    {
        $path = $route->route->getPath();

        $segments = explode('/', $path);
        $segments = array_values(array_filter($segments, 'strlen'));

        $paramMetaByIndex = [];

        foreach ($segments as $index => $segment)
        {
            if (!str_starts_with($segment, '{')) {
                continue;
            }

            // Your existing matching format supports: {name} and {name<regex>}
            $routeParameter = explode(' ', preg_replace('/{([\w\-%]+)(<(.+)>)?}/', '$1 $3', $segment));
            $paramName = $routeParameter[0];
            $paramRegExp = (empty($routeParameter[1]) ? Route::DEFAULT_REGEX : $routeParameter[1]);

            // Pre-wrap into a ready-to-use regex
            $paramMetaByIndex[$index] = [
                'name' => $paramName,
                'regex' => '/^' . $paramRegExp . '$/',
            ];
        }

        return [
            'segments' => $segments,
            'segmentCount' => count($segments),
            'methods' => $route->route->getMethods(),
            'isAjax' => $route->route->isAjax(),
            'hasParams' => !empty($paramMetaByIndex),
            'paramMetaByIndex' => $paramMetaByIndex,
        ];
    }

    /**
     * Generates a static key by combining the HTTP method, a flag indicating if the request is AJAX,
     * and the provided path.
     *
     * @param string $method The HTTP method (e.g., GET, POST).
     * @param bool $isAjax Indicates whether the request is made via AJAX.
     * @param string $path The path or endpoint associated with the request.
     * @return string A concatenated string representing the static key.
     */
    protected function makeStaticKey(string $method, bool $isAjax, string $path): string
    {
        return $method . ':' . ($isAjax ? '1' : '0') . ':' . $path;
    }
}