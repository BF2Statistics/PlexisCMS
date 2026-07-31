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

use ReflectionException;
use RuntimeException;
use System\Events\EventManager;
use System\Routing\RouteNotFoundException;
use System\Routing\Router;
use System\Routing\RouteResolveEvent;
use System\Routing\RouterInterface;
use System\Routing\RoutingDirective;

/**
 * The Dispatcher class handles the sequential processing of a `Request` through a series of middleware pipes,
 * eventually passing the request to a final destination callback.
 *
 * This class manages middleware execution and ensures that a request flows through the pipeline
 * in the intended order. It supports adding middleware dynamically, executing them in reverse
 * order (last-in, first-out), and clearing the pipeline.
 *
 * Usage:
 * 1. Use the `process` method to set the request object being passed through the pipeline.
 * 2. Add middleware processes using the `through` method.
 * 3. Use the `using` method to define the RouterInterface to resolve the `Request`
 * 4. Execute the pipeline with the `execute` method.
 * 5. Optionally clear all middleware from the pipeline using the `clear` method.
 *
 * Example:
 * ```
 * $dispatcher = new Dispatcher();
 * $response = $dispatcher
 *     ->process($request)
 *     ->using($router)
 *     ->through($middleware1, $middleware2)
 *     ->execute();
 * ```
 *
 * @package System\Http
 */
class Dispatcher
{

    /**
     * The `Request` being passed through the pipeline.
     *
     * @var ?Request
     */
    protected ?Request $request = null;

    /**
     * The name of the current route if the {@see $request} is not set.
     *
     * @var ?string
     */
    protected ?string $routeName = null;

    /**
     * The parameters for the current route if the {@see $request} is not set.
     */
    protected array $routeParams = [];

    /**
     * The series of middleware pipes to process the request.
     *
     * Each middleware in the pipeline implements the `MiddlewareInterface` interface, allowing
     * consistent handling and passing of the `Request` through the chain of middleware.
     *
     * @var array<MiddlewareInterface>
     */
    protected array $middleware = [];

    /**
     * The router instance used to resolve the request to a specific action.
     *
     * The router determines the route and resolves it to a module or action. It implements
     * the `RouterInterface` interface.
     *
     * @var ?RouterInterface
     */
    protected ?RouterInterface $router = null;

    /**
     * Sets the router instance to be used.
     *
     * This sets the router, which is responsible for resolving routes and determining where
     * the request should be routed.
     *
     * @param RouterInterface $router The router instance provided.
     *
     * @return $this Allows method chaining.
     */
    public function using(RouterInterface $router): static
    {
        $this->router = $router;
        return $this;
    }

    /**
     * Sets the request object that will flow through the pipeline.
     *
     * This establishes the `Request` to be processed by the pipeline of middleware
     * and eventually routed by the router.
     *
     * @param Request $request The object being sent through the pipeline.
     *
     * @return $this Allows method chaining.
     */
    public function process(Request $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Assigns a route name with its associated parameters for a call.
     *
     * This sets the route name and parameters to be used for resolving and processing
     * a specific route within the application. Use this method when the request is not
     * available, such as when the pipeline is being executed outside of a web request.
     *
     * @param string $routeName The name of the route to be called.
     * @param array $params Optional route parameters to pass along with the route.
     *
     * @return $this Allows method chaining.
     */
    public function call(string $routeName, array $params = []): static
    {
        $this->routeName = $routeName;
        $this->routeParams = $params;

        return $this;
    }

    /**
     * Specifies the series of middleware through which the request should pass.
     *
     * Middleware added to the pipeline will process the request in the order they are provided,
     * and then pass control to the next middleware or endpoint. Each middleware must implement
     * the `MiddlewareInterface` interface.
     *
     * @param MiddlewareInterface ...$pipes Middleware to be added to the pipeline.
     *
     * @return $this Allows method chaining.
     */
    public function through(MiddlewareInterface ...$pipes): static
    {
        $this->middleware = array_merge($this->middleware, $pipes);

        return $this;
    }

    /**
     * Executes the pipeline by processing the request through each middleware.
     *
     * The middleware in the pipeline are executed in reverse order (last-in, first-out).
     * Each middleware processes the request and passes it to the next middleware or final
     * destination. The router resolves the request to its respective action or handler.
     *
     * @return Response Returns the result of the final handler or the pipeline's processing.
     *
     * @event System\Http\RouteNotFoundEvent dispatch.route.notFound Called when the request cannot be routed to a valid endpoint.
     * @event RouteMatchedEvent dispatch.route.matched Called after the request has been routed to a valid endpoint.
     *  This will fire even if the route has been overridden by a listener of dispatch.route.notFound
     * @event HttpForbiddenEvent dispatch.forbidden Called when a request is denied access due to insufficient permissions.
     *
     * @throws RuntimeException If the router or request is not set.
     * @throws RouteNotFoundException If the request cannot be routed to a valid endpoint.
     * @throws ReflectionException If there is an error invoking the action via reflection.
     * @throws \Exception
     */
    public function execute(): Response
    {
        if (empty($this->router))
            throw new RuntimeException('Router not set');

        // Route request
        /** @var RoutingDirective $directive */
        $directive = null;
        try
        {
            if (!empty($this->request))
            {
                $module = $this->router->resolve($this->request, $directive);
                if ($directive->target->route->isInternal() && !$this->request->isInternal()) {
                    throw new RouteNotFoundException("Route '{$directive->target->route->getName()}' is internal and can only be accessed from within the application.");
                }
            }
            else if (!empty($this->routeName))
            {
                // Resolve route by name
                $module = $this->router->resolveByName($this->routeName, $directive, $this->routeParams);

                // We need to create a Request object for the route to work
                $route = $this->router->generate($this->routeName, $this->routeParams);
                $this->request = new Request($route);
                $this->request->setRoutingDirective($directive);
            }
            else
            {
                throw new RuntimeException('Request or routeName not set');
            }
        }
        catch (RouteNotFoundException $e)
        {
            // Allow other modules to handle the route not found event (Recovery)
            $event = new RouteNotFoundEvent($this->request ?? $this->routeName);
            EventManager::Dispatch('dispatch.route.notFound', $event);

            // If handled with override, continue pipeline with new module/directive
            if ($event->isPropagationStopped() && $event->handled)
            {
                $module = $event->moduleProvider;
                $directive = $event->request instanceof Request
                    ? $event->request->getRoutingDirective()
                    : $event->directive;
                \System::Log()->logDebug("[Dispatcher] 404 event has redirected the route to '{$directive->target->route->getPath()}'.");
            }
            // Mode 1: If response was set, return it immediately
            else if ($event->isPropagationStopped() && !empty($event->response))
            {
                \System::Log()->logDebug("[Dispatcher] 404 event has provided a custom response.");
                return $event->response;
            }
            else
            {
                throw $e;
            }
        }

        // Create middleware pipeline
        $executionChain = $this->middleware;

        // Fire success event
        $event = new RouteMatchedEvent($this->request, $module);
        EventManager::Dispatch('dispatch.route.matched', $event);

        // Allow listeners to stop and override the directive
        if ($event->isPropagationStopped() && $event->handled)
        {
            $module = $event->moduleProvider;
            $directive = $event->request->getRoutingDirective();
            \System::Log()->logDebug("[Dispatcher] Route matched event has redirected the route to '{$directive->target->route->getPath()}'.");
        }

        // Add controller and action middleware
        if (!empty($directive->target->middleware))
        {
            foreach ($directive->target->middleware as $name => $params)
            {
                $executionChain[] = (empty($params)) ? new $name() : new $name(...$params);
            }
        }

        try
        {
            // Wrap middleware to work "down and up" the pipeline
            if (!empty($executionChain))
            {
                $pipeline = array_reduce(
                    array_reverse($executionChain), // Start with the last middleware
                    fn($next, $middleware) => fn($request) => $middleware->process($request, $next), // Wrap the "next callable"
                    function (Request $request) use ($module, $directive) {
                        return $module->invokeAction($request, $directive);
                    }
                );

                // Start the pipeline processing
                $response = $pipeline($this->request);
            }
            else
            {
                // No middleware
                $response = $module->invokeAction($this->request, $directive);
            }
        }
        catch (HttpForbiddenException $ex)
        {
            $event = new HttpForbiddenEvent($this->request, $ex->getMessage());
            $response = EventManager::Dispatch('dispatch.forbidden', $event);

            // Allow listeners to stop and override the response
            if ($event->isPropagationStopped() && $response instanceof Response) {
                return $response;
            }

            throw $ex;
        }

        // Ensure the final output is a Response object
        if (!$response instanceof Response) {
            throw new RuntimeException('Pipeline did not return an actual Response object');
        }

        return $response;
    }

    /**
     * Clears all middleware from the pipeline.
     *
     * This method removes all middleware pipes, resetting the pipeline to an empty state.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->middleware = [];
    }
}