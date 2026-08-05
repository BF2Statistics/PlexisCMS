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

use Exception;
use ReflectionClass;
use ReflectionMethod;
use System\AbstractModule;
use System\Diagnostics\LogWriter;
use System\Events\EventManager;
use System\Http\Middleware;
use System\Http\Request;
use System\Http\RouteMatchedEvent;
use System\IO\Directory;
use System\IO\DirectoryNotFoundException;
use System\IO\File;
use System\IO\IOException;
use System\ModuleProvider;
use System\ModuleNotFoundException;
use System\ObjectDisposedException;
use System\Security\SecurityException;

/**
 * Class Router
 *
 * The `Router` class is responsible for managing the routing of HTTP requests in Plexis CMS.
 * It determines the appropriate module, controller, and action to handle each request by analyzing the requested URL.
 * Routes are defined either in the `routes.php` configuration file or dynamically through the `Route` attribute in controllers.
 *
 * ## Key Responsibilities:
 * - **Dynamic Routing**: Matches URLs to their corresponding routes, leveraging both configuration files and attributes.
 * - **Route Management**: Manages added routes, processes routing logic, and ensures efficient lookups.
 * - **Request Processing**: Extracts and analyzes URL paths to determine the appropriate controller, action, and parameters.
 * - **Fallbacks**: Ensures well-defined behavior when no matching route is found by throwing structured exceptions.
 *
 * ## Features:
 * - Supports routing defined in `routes.php` or additional routing logic using attributes.
 * - Allows for custom route parameters and regex validation.
 * - AJAX-specific route handling with metadata support via the `Route` attribute.
 * - Integration with `Request` objects to provide URL details.
 * - Automatically initializes and loads global routes at runtime.
 * - Comprehensive debugging support with route logging.
 *
 * ## How Routes Are Loaded:
 * 1. **From `routes.php` Configuration File**:
 *    - If the configuration file exists and contains route definitions, these are loaded automatically during initialization.
 * 2. **From `Route` Attribute**:
 *    - When the `routes.php` file is empty, the `Route` attribute dynamically defines routing information by annotating controller methods.
 *    - The `Route` attribute supports additional metadata, such as HTTP methods, AJAX-specific flags, and parameter validation.
 *
 * ## Features and Benefits:
 * - **Flexible Route Sources**: Routes can be predefined in configuration files or dynamically discovered through attributes.
 * - **Dynamic Parameter Support**: Routes can include parameters with optional regex-based validation defined in the `Route` attribute.
 * - **Comprehensive Debugging**: Logs all routing operations for easier troubleshooting of routing mismatches or errors.
 * - **Optimized Processing**: Ensures that routes are evaluated and matched efficiently to handle high-traffic requests.
 *
 * ## Advanced Notes:
 * - **The `Route` Attribute**:
 *   - Can define paths, HTTP methods, and regex-backed parameters with a straightforward annotation format.
 *   - Example:
 *     ```
 *     #[Route(path: "/user/{id<\d+>}", name: "user_details", methods: ["GET"])]
 *     public function getUserDetails(Request $request) {
 *         // ...
 *     }
 *     ```
 *   - In this case, the route matches `/user/{id}` where `{id}` must be a numeric value (validated using regex).
 *
 * - **404 Handling**:
 *   - If the requested route does not match any definition, the `Forge` method will throw a `RouteNotFoundException` for consistent error handling.
 *
 * ## Known Exceptions:
 * - **RouteNotFoundException**: Thrown when a route cannot be matched to a request or its associated module cannot be resolved.
 * - **\Exception**: Thrown during initialization errors, such as logging setup issues or route collection failures.
 *
 * @package System\Routing
 * @subpackage Routing
 * @license GNU GPL v3
 * @author
 */
class Router implements RouterInterface
{
    /**
     * @var RouterInterface|null
     */
    protected static RouterInterface|null $instance = null;

    /**
     * Have we routed the url yet?
     * @var bool
     */
    protected static bool $routed = false;

    /**
     * The route stack of all defined routes
     * @var RouteCollection
     */
    protected RouteCollection $routes;

    /**
     * Holds the plexis Logger object
     * @var LogWriter
     */
    protected LogWriter $logWriter;

    /**
     * List of bootstrap classes or components to be initialized.
     *
     * @var array
     */
    protected array $bootstrappers = [];

    /**
     * Sets the singleton instance of the router.
     *
     * @param RouterInterface $router The router instance to set as the singleton.
     *
     * @return void
     */
    public static function SetInstance(RouterInterface $router): void
    {
        self::$instance = $router;
    }

    /**
     * Returns the singleton instance of the router.
     * If an instance does not already exist, a new router instance is created.
     *
     * @return RouterInterface The singleton instance of the router implementing the RouterInterface.
     */
    public static function Instance(): RouterInterface
    {
        return self::$instance ?? new Router();
    }

    /**
     * This method analyzes the current URL request, and loads the
     * module in which claims the URL route. This method is called
     * automatically, and will not do anything if called again.
     *
     * @return void
     *
     * @throws Exception
     */
    public function __construct()
    {
        // Load debug log
        $this->logWriter = LogWriter::Instance("debug");

        // Load our route collection
        $this->routes = new RouteCollection();

        // Load the routes
        $this->loadRoutes();

        // Set static instance
        self::$instance = $this;
    }

    /**
     * @inheritDoc
     */
    public function resolve(Request $request, ?RoutingDirective &$directive): ModuleProvider
    {
        // Debug logging
        $route = $request->getPath();
        $this->logWriter->logDebug("[Router] Forging route \"{$route}\"");

        // Try to find a module route for the request
        $directive = $this->routes->match($request);
        if ($directive === false)
        {
            // Allow other modules to handle the route not found event (Recovery)
            $event = new RouteNotFoundEvent($request);
            EventManager::Dispatch('route.notFound', $event);

            // If the event is handled, return the provider and stop processing
            if ($event->isPropagationStopped() && $event->handled)
            {
                // Debug logging
                $directive = $event->routingDirective;
                $this->logWriter->logDebug("[Router] Global route for \"{$route}\" not found, but handled via an event listener. Loading module \"{$directive->target->moduleName}\"...");

                // return the provider
                $request->setRoutingDirective($directive);
                return $event->moduleProvider;
            }

            throw new RouteNotFoundException("Unable to find a route for \"{$route}\"");
        }

        // Debug logging
        $this->logWriter->logDebug("[Router] Global route for \"{$route}\" found. Loading module \"{$directive->target->moduleName}\"...");
        $request->setRoutingDirective($directive);

        // Check for routes
        try
        {
            // Is the module installed?
            $provider = ModuleProvider::Load($directive->target->moduleName);

            // Fire event listener so that other modules can handle the route match event
            $event = new RouteMatchedEvent(request: $request, provider: $provider);
            EventManager::Dispatch('route.matched', $event);

            // Allow listeners to stop and override the directive
            if ($event->isPropagationStopped() && $event->handled)
            {
                $provider = $event->moduleProvider;
                $directive = $event->request->getRoutingDirective();
                $this->logWriter->logDebug("[Router] Route overridden for '{$route}' using an Event listener. New route: ". $directive->target->route->getPath());
            }

            // Debug logging
            $params = (!empty($directive->parameters)) ? " with Params: " . implode(', ', $directive->parameters) : '';
            $this->logWriter->logDebug("[Router] Found route for '{$route}' using controller '{$directive->target->controllerClassName}' " . $params);
            return $provider;
        }
        catch (ModuleNotFoundException $e)
        {
            // Debug logging
            $module = $directive->target->moduleName;
            $this->logWriter->logWarning("[Router] Unable to locate module \"{$module}\": ". $e->getMessage());
            throw new RouteNotFoundException("[Router] Unable to locate module \"{$module}\"", 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function resolveByName(string $routeName, ?RoutingDirective &$directive, array $params = []): ModuleProvider
    {
        // Instant Lookup (No looping!)
        $directive = null;
        $target = $this->routes->getRouteByName($routeName);

        if (!$target)
        {
            // Allow other modules to handle the route not found event (Recovery)
            $event = new RouteNotFoundEvent($routeName);
            EventManager::Dispatch('route.notFound', $event);

            // If the event is handled, return the provider and stop processing
            if ($event->isPropagationStopped() && $event->handled)
            {
                // Debug logging
                $directive = $event->routingDirective;
                $this->logWriter->logDebug("[Router] Global route for \"{$routeName}\" not found, but handled via an event listener. Loading module \"{$directive->target->moduleName}\"...");

                // return the provider
                return $event->moduleProvider;
            }

            throw new RouteNotFoundException("Widget route '{$routeName}' not found.");
        }

        // Define routing directive
        $directive = new RoutingDirective($target, $params);

        // Check for routes
        try
        {
            // Is the module installed?
            $provider = ModuleProvider::Load($directive->target->moduleName);

            // Fire event listener so that other modules can handle the route match event
            $event = new RouteMatchedEvent($routeName, $provider);
            EventManager::Dispatch('route.matched', $event);

            // Allow listeners to stop and override the directive
            if ($event->isPropagationStopped() && $event->handled)
            {
                $provider = $event->moduleProvider;
                $directive = $event->directive;
                $this->logWriter->logDebug("[Router] Route overridden for '{$routeName}' using an Event listener. New route: ". $directive->target->route->getName());
            }

            // Debug logging
            $usingParams = (!empty($params)) ? " with Params: " . implode(', ', $params) : '';
            $this->logWriter->logDebug("[Router] Found named route '{$routeName}' using controller '{$directive->target->controllerClassName}' " . $usingParams);
            return $provider;
        }
        catch (ModuleNotFoundException $e)
        {
            // Debug logging
            $module = $directive->target->moduleName;
            $this->logWriter->logWarning("[Router] Unable to locate module \"{$module}\": ". $e->getMessage());
            throw new RouteNotFoundException("[Router] Unable to locate module \"{$module}\"", 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function generate(string $routeName, array $params = []) : string
    {
        // Attempt to fetch the route by name
        $target = $this->routes->getRouteByName($routeName);
        if ($target === false) {
            throw new RouteNotFoundException("Route '{$routeName}' not found.");
        }

        // Regex matches: {param} OR {param<regex>}
        // Group 1: Param Name
        // Group 2: Optional Regex Pattern
        return preg_replace_callback(
            '/{([\w\-%]+)(?:<([^>]+)>)?}/',
            function ($matches) use ($params) {
                $key = $matches[1];
                $pattern = $matches[2] ?? '[\w\-]+'; // Default regex if none defined

                // 1. Check if param exists
                if (!isset($params[$key])) {
                    throw new \InvalidArgumentException("Missing required parameter '{$key}'");
                }

                $value = (string)$params[$key];

                // 2. Validate against the route's regex requirements. This ensures we don't generate a URI that would 404 later.
                if (!preg_match('#^' . $pattern . '$#', $value)) {
                    throw new \InvalidArgumentException("Parameter '{$key}' value '{$value}' does not match requirement '{$pattern}'");
                }

                return $value;
            },
            $target->route->getPath()
        );
    }

    /**
     * @inheritDoc
     */
    public function addRoutes(RouteCollection $routes) : void
    {
        // Add routes to the collection
        $this->routes->merge($routes);
    }

    /**
     * @inheritDoc
     */
    public function removeRoute(string $key) : void
    {
        $this->routes->removeRouteByName($key);
    }

    /**
     * @inheritDoc
     */
    public function fetchRoutes() : RouteCollection
    {
        return $this->routes;
    }

    /**
     * @inheritDoc
     */
    public function saveManifest(): void
    {
        // Get our new list of routes
        $routes = $this->routes->getRoutes();
        if (empty($routes))
            throw new Exception("Unable to save manifest files. No routes found.");

        // ========== SAVE ROUTES CACHE FILE ==========
        $this->saveRoutesCache($routes);

        // ========== SAVE EVENTS CACHE FILE ==========
        $this->saveEventsCache();
    }

    /**
     * Saves the routes to a separate cache file for independent loading by the Router.
     *
     * @param array $routes The routes array to save.
     * @return void
     * @throws IOException
     * @throws ObjectDisposedException
     */
    protected function saveRoutesCache(array $routes): void
    {
        $routesExport = "\n";
        foreach ($routes as $routeName => $routeInfo)
        {
            $controller = $routeInfo->controllerClassName . "::" . $routeInfo->methodName;
            $routesExport .= "\t'" . $routeName . "' => [\n";
            $routesExport .= "\t\t'path' => '" . $routeInfo->route->getPath() . "',\n";
            $routesExport .= "\t\t'methods' => ['" . implode("', '", $routeInfo->route->getMethods()) . "'],\n";
            $routesExport .= "\t\t'isAjax' => " . (($routeInfo->route->isAjax()) ? 'true' : 'false') . ",\n";
            $routesExport .= "\t\t'isInternal' => " . (($routeInfo->route->isInternal()) ? 'true' : 'false') . ",\n";
            $routesExport .= "\t\t'controller' => '" . $controller . "',\n";
            $routesExport .= "\t\t'middleware' => [\n";
            foreach ($routeInfo->middleware as $middleware => $params)
            {
                $routesExport .= "\t\t\t'" . $middleware . "' => [" . $this->getParamsArrayString($params) . "],\n";
            }
            $routesExport .= "\t\t],\n";
            $routesExport .= "\t],\n";
        }
        $routesExport = rtrim($routesExport, ",\n") . "\n";

        // Build file content
        $string = "<?php" . PHP_EOL;
        $string .= "/**" . PHP_EOL;
        $string .= " * Routes Cache (Auto-Generated)" . PHP_EOL;
        $string .= " * " . PHP_EOL;
        $string .= " * This file contains route definitions only." . PHP_EOL;
        $string .= " * It is auto-generated when routes are reloaded. DO NOT EDIT THIS FILE." . PHP_EOL;
        $string .= " * " . PHP_EOL;
        $string .= " * To override routes, create/edit system/config/routes.php" . PHP_EOL;
        $string .= " * " . PHP_EOL;
        $string .= " * Generated: " . date('Y-m-d H:i:s') . PHP_EOL;
        $string .= " */" . PHP_EOL;
        $string .= "return [" . $routesExport . "];" . PHP_EOL;

        // Write the routes cache file
        $file = SYSTEM_DIR . DS . 'cache' . DS . 'routes.cache.php';
        File::WriteAllText($file, $string);

        // Tell OPCache to purge the file
        if (function_exists('opcache_invalidate'))
        {
            opcache_invalidate($file, true);
        }
    }

    /**
     * Saves the event bootstrappers to a separate cache file for independent loading by System.
     *
     * @return void
     * @throws IOException
     * @throws ObjectDisposedException
     */
    protected function saveEventsCache(): void
    {
        $eventsExport = "\n";
        foreach ($this->bootstrappers as $eventName => $handlers)
        {
            $eventsExport .= "\t'" . $eventName . "' => [\n";
            foreach ($handlers as $item)
            {
                $eventsExport .= "\t\t[\n";
                $eventsExport .= "\t\t\t'class' => '" . $item[0] . "',\n";
                $eventsExport .= "\t\t\t'method' => '" . $item[1] . "',\n";
                $eventsExport .= "\t\t\t'priority' => " . intval($item[2]) . ",\n";
                $eventsExport .= "\t\t],\n";
            }
            $eventsExport .= "\t],\n";
        }
        $eventsExport = rtrim($eventsExport, ",\n") . "\n";

        // Build file content
        $string = "<?php" . PHP_EOL;
        $string .= "/**" . PHP_EOL;
        $string .= " * Events Cache (Auto-Generated)" . PHP_EOL;
        $string .= " * " . PHP_EOL;
        $string .= " * This file contains event listener registrations only." . PHP_EOL;
        $string .= " * It is auto-generated when routes are reloaded. DO NOT EDIT THIS FILE." . PHP_EOL;
        $string .= " * " . PHP_EOL;
        $string .= " * To override events, create/edit system/config/events.php" . PHP_EOL;
        $string .= " * " . PHP_EOL;
        $string .= " * Generated: " . date('Y-m-d H:i:s') . PHP_EOL;
        $string .= " */" . PHP_EOL;
        $string .= "return [" . $eventsExport . "];" . PHP_EOL;

        // Write the events cache file
        $file = SYSTEM_DIR . DS . 'cache' . DS . 'events.cache.php';
        File::WriteAllText($file, $string);

        // Tell OPCache to purge the file
        if (function_exists('opcache_invalidate'))
        {
            opcache_invalidate($file, true);
        }
    }

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
     *
     * @throws IOException
     * @throws DirectoryNotFoundException
     * @throws SecurityException
     * @throws Exception
     */
    public function reloadRoutes() : void
    {
        // Reset
        $this->bootstrappers = [];

        // Load module directories, skipping those that start with an underscore
        $modDirs = Directory::GetDirectories(APP_DIR . DS . 'modules', '^(?!_).+');

        // foreach module, load all controller classes using reflection
        foreach ($modDirs as $modDir)
        {
            // Extract the module name from the directory name
            $moduleName = basename($modDir);

            $event = new RouteEvent($moduleName);
            EventManager::Dispatch('router.reloadModuleRoutes.before', $event);

            // Stop if the event was canceled
            if ($event->isPropagationStopped()) {
                continue;
            }

            // Load the Module class
            $className = "\\Modules\\{$moduleName}\\Module";
            if (!class_exists($className))
                continue;

            // Fetch the names of all controllers from the module.xml meta file
            /** @var AbstractModule $className */
            $controllerNames = $className::GetRouteControllers();
            if (empty($controllerNames))
                continue;

            // Load controllers
            foreach ($controllerNames as $controllerName)
            {
                if (!class_exists($controllerName))
                {
                    // Log the error and continue
                    \System::Log()->logDebug("Unable to reload routes for module \"{$moduleName}\". Class \"{$controllerName}\" does not exist.");
                    continue;
                }

                // Load the controller class
                $reflectionController = new \ReflectionClass($controllerName);

                // Initialize middleware stack
                $middlewares = $this->getMiddlewareMetadata($reflectionController);

                // Load action methods
                foreach ($reflectionController->getMethods() as $reflectionMethod)
                {
                    // Grab middleware
                    $middleware = array_merge($middlewares, $this->getMiddlewareMetadata($reflectionMethod));

                    // Add route
                    $routeAttributes = $reflectionMethod->getAttributes(Route::class);
                    foreach ($routeAttributes as $routeAttribute)
                    {
                        $route = $routeAttribute->newInstance();
                        $this->routes->addRoute(new RouteTarget($moduleName, $controllerName, $reflectionMethod->getName(), $route, $middleware));
                    }
                }
            }

            // Load listeners
            $listeners = $className::GetSubscribedEvents();
            foreach ($listeners as $eventName => $listener)
            {
                $this->bootstrappers[$eventName][] = $listener;
            }
        }

        // Save manifest (routes + bootstrappers) to the cache/manifest.php file
        $this->saveManifest();

        // Fire event
        EventManager::Dispatch('router.reloadRoutes.after', new RouterEvent());
    }

    /**
     * Loads the application routes from the routes cache file and adds them to the routing system.
     * First loads from cache, then merges any overrides from config/manifest.php.
     * If the cache file is missing or invalid, it attempts to regenerate the file.
     *
     * @return void This method does not return a value but throws an exception if the routes
     *   configuration file cannot be successfully loaded or processed.
     *
     * @throws Exception
     */
    protected function loadRoutes(): void
    {
        // Check for cached routes file
        $cacheFile = SYSTEM_DIR . DS . 'cache' . DS . 'routes.cache.php';
        $configFile = SYSTEM_DIR . DS . 'config' . DS . 'routes.php';  // Changed from manifest.php

        // If the cache routes file is missing, create a new one
        if (!file_exists($cacheFile))
        {
            ReloadRoutes:
            {
                $this->logWriter->logNotice("[Router] Routes cache file is missing... Generating new cache files");
                $this->reloadRoutes();
                return;
            }
        }

        // Import the cached routes file
        $routes = include $cacheFile;
        if (!is_array($routes))
        {
            $this->logWriter->logError("[Router] Incorrect return format for routes.cache.php, array expected... Regenerating cache files");
            goto ReloadRoutes;
        }

        // Is the route array empty?
        if (empty($routes))
        {
            $this->logWriter->logNotice("[Router] Routes array is empty in cache... Regenerating cache files");
            goto ReloadRoutes;
        }

        // Check for manual overrides in config/routes.php
        if (file_exists($configFile))
        {
            $overrides = include $configFile;
            if (is_array($overrides))
            {
                $this->logWriter->logDebug("[Router] Loading route overrides from config/routes.php");
                $routes = array_merge($routes, $overrides);
            }
        }

        // Add each route
        foreach ($routes as $routeName => $info)
        {
            // Improper format
            if (!is_array($info))
            {
                $this->logWriter->logWarning("[Router] Incorrect format for the route \"{$routeName}\"... Skipping route");
                continue;
            }

            // Extract data from the array
            list($controller, $action) = explode('::', $info['controller'], 2);
            $path = $info['path'];
            $methods = (is_array($info['methods']) ? $info['methods'] : array('GET'));

            // Get module name from the namespace
            $parts = explode('\\', $controller);
            if (count($parts) < 3)
            {
                $this->logWriter->logWarning("[Router] Unable to determine module name from controller \"{$controller}\"... Skipping route");
                continue;
            }

            // Create the route info
            $routeAttr = new Route($path, $routeName, $methods, $info['isAjax'], $info['isInternal'] ?? false);
            $route = new RouteTarget($parts[1], $controller, $action, $routeAttr, $info['middleware'] ?? array());
            $this->routes->addRoute($route);
        }
    }

    /**
     * Handles and processes middleware attributes for a given class or method.
     *
     * This method retrieves the attributes of type Middleware applied to the provided class
     * or method, instantiates them, and collects their relevant data, such as the middleware
     * class and parameters. The resulting middleware stack is then returned as an array.
     *
     * @param ReflectionClass|ReflectionMethod $item The reflection instance of the class or method being inspected.
     *
     * @return array An array of middleware metadata containing the middleware class and parameters.
     */
    protected function getMiddlewareMetadata(ReflectionClass|ReflectionMethod $item): array
    {
        // Initialize middleware stack
        $middlewares = [];

        // Construct our controller
        foreach ($item->getAttributes(Middleware::class) as $attribute)
        {
            // Create an instance of the Middleware attribute
            /** @var Middleware $middleware */
            $middleware = $attribute->newInstance();

            // Access the middleware data
            $middlewareClass = $middleware->middlewareClass;
            $parameters = $middleware->parameters;
            $middlewares[$middlewareClass] = $parameters;
        }

        return $middlewares;
    }

    /**
     * Converts an associative array of parameters into a formatted string representation.
     *
     * This method iterates over an array of parameter key-value pairs and constructs
     * a string where each key-value pair is formatted. Numeric values remain as-is,
     * while non-numeric values are wrapped in single quotes.
     *
     * @param array $params An associative array of parameters, with keys as strings
     *                      and values as either numeric or string types.
     *
     * @return string A formatted string representation of the parameters array,
     *                with each entry formatted as 'key' => value or 'key' => 'value'.
     */
    protected function getParamsArrayString(array $params): string
    {
        // Transform each value into a properly formatted string
        $formattedParams = array_map(function ($value) {
            return is_numeric($value) ? $value : "'" . addslashes((string)$value) . "'";
        }, $params);

        // Join the formatted values with commas and return as a string
        return implode(', ', $formattedParams);
    }
}