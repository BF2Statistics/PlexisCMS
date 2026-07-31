<?php
declare(strict_types=1);
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

use Exception;
use InvalidArgumentException;
use System\Http\Request;
use System\IO\DirectoryNotFoundException;
use System\IO\IOException;
use System\ModuleProvider;
use System\Security\SecurityException;

/**
 * Interface RouterInterface
 *
 *  This interface defines the contract for a router system, responsible for
 *  managing the routing of HTTP requests, generating URLs for routes, and
 *  providing additional route management capabilities.
 */
interface RouterInterface
{
    /**
     * Resolves the provided HTTP request to a module and retrieves routing information.
     *
     * This method analyzes a request and determines the appropriate module to handle it.
     * It also fills the reference parameter `$result` with additional routing data,
     * such as the controller, action, and parameters required for invoking the module's functionality.
     *
     * @param Request $request The HTTP request to be routed.
     * @param ?RoutingDirective $directive [Reference Variable] A reference variable that will
     *     be populated with the routing information, such as the controller, action, and parameters.
     *     This will be `null` if the module cannot be routed.
     *
     * @return ModuleProvider Returns the resolved module instance if routing is successful.
     *
     * @event System\Routing\RouteNotFoundEvent route.notFound Fired when the request cannot be routed to a valid endpoint.
     * @event System\Routing\RouteMatchedEvent route.matched Fired after the request has been routed to a valid endpoint.
     *
     * @throws RouteNotFoundException If the route cannot be resolved or results in a 404 Not Found error.
     * @throws Exception If the module cannot be loaded or another issue arises during resolution.
     */
    public function resolve(Request $request, ?RoutingDirective &$directive): ModuleProvider;

    /**
     * Resolves a route by name and returns the controller and action to be invoked.
     *
     * Fast O(1) lookup for internal widgets using Route Name
     *
     * @param string $routeName The name of the route to resolve.
     * @param array $params Optional parameters to be used in the route, replacing named placeholders in the route definition.
     *
     * @return ModuleProvider Returns the resolved module instance if routing is successful.
     *
     * @event RouteNotFoundEvent route.notFound Fired when the request cannot be routed to a valid endpoint.
     * @event RouteMatchedEvent route.matched Fired after the request has been routed to a valid endpoint.
     *
     * @throws RouteNotFoundException
     */
    public function resolveByName(string $routeName, ?RoutingDirective &$directive, array $params = []): ModuleProvider;

    /**
     * Generates a URI for a specific route based on the provided route name and parameters.
     *
     * This method creates a URI string by substituting the specified route name and
     * parameters into the corresponding route definition.
     *
     * @param string $routeName The name of the route for which the URI should be generated.
     * @param array $params Optional parameters to be used in the route, replacing named placeholders in the route definition.
     *
     * @return string Returns the generated URI string.
     *
     * @throws InvalidArgumentException If the provided parameters are invalid or incomplete for the route.
     * @throws RouteNotFoundException If the specified route name does not match any existing route definition.
     */
    public function generate(string $routeName, array $params = []): string;

    /**
     * Adds a list of new route definitions to the routing system.
     *
     * This method allows the addition of multiple new routes that can be used
     * for future route matching and resolution.
     *
     * @param RouteCollection $routes A collection of routes to be added.
     *
     * @return void
     */
    public function addRoutes(RouteCollection $routes): void;

    /**
     * Removes a specified route rule from the routing system by its key.
     *
     * This method allows for the deletion of a specific route definition,
     * identified by its unique key.
     *
     * @param string $key The key of the route to be removed, as defined in the routing configuration.
     *
     * @return void
     */
    public function removeRoute(string $key): void;

    /**
     * Retrieves the collection of all currently defined routes in the routing system.
     *
     * This method provides access to the internal representation of the route definitions,
     * allowing for inspection or manipulation.
     *
     * @return RouteCollection Returns the collection of all defined routes.
     */
    public function fetchRoutes(): RouteCollection;

    /**
     * Reloads all module routes by scanning module directories, identifying controller
     * classes and their methods, and extracting route attributes to populate the routing table.
     *
     * This process ensures that the routing information reflects the current state of the modules,
     * including any newly added routes or controllers.
     *
     * @return void This method does not return any value.
     *
     * @event RouterEvent router.reloadRoutes.after Called after all modules have been reloaded.
     * @event RouteEvent router.reloadModuleRoutes.before Called for each module being reloaded.
     *  Allows for module routes to be excluded from the reload process, thus disabling them.
     *
     * @throws IOException
     * @throws DirectoryNotFoundException
     * @throws SecurityException
     * @throws Exception
     */
    public function reloadRoutes() : void;

    /**
     * Saves the current routes and event bootstrappers configuration into a single PHP manifest file.
     * This method retrieves all the registered routes and event listeners, formats them into a readable
     * PHP array structure, and writes them into the cache/routes.cache.php and cache/events.cache.php files.
     *
     * @return void This method does not return a value.
     *
     * @throws IOException If there is an error writing to the specified cache files.
     * @throws Exception If there are no routes in the internal route collection
     */
    public function saveManifest(): void;
}
